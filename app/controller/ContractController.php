<?php
namespace app\controller;

use support\Request;
use support\Db;

class ContractController
{
    public function list(Request $request)
    {
        $list = Db::table('contracts')
            ->join('enterprises', 'contracts.enterprise_id', '=', 'enterprises.id')
            ->join('spaces', 'contracts.space_id', '=', 'spaces.id')
            ->select('contracts.*', 'enterprises.name as enterprise_name', 'spaces.building_name', 'spaces.room_number')
            ->orderBy('contracts.id', 'desc')
            ->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function add(Request $request)
    {
        $spaceIds = $request->post('space_ids'); 
        $enterpriseId = $request->post('enterprise_id');
        $metersData = $request->post('meters', []); 
        
        if (empty($spaceIds) || !is_array($spaceIds)) {
            return json(['code' => 400, 'msg' => '业务拦截：请至少分配一个物理空间']);
        }

        $spaces = Db::table('spaces')->whereIn('id', $spaceIds)->lockForUpdate()->get();
        $totalArea = 0;
        foreach ($spaces as $sp) {
            if ($sp->status != 0) {
                return json(['code' => 400, 'msg' => "业务拦截：空间 {$sp->room_number} 已被占用，无法重复签约"]);
            }
            $totalArea += floatval($sp->area);
        }
        if ($totalArea <= 0) $totalArea = count($spaces);

        $startDate = $request->post('start_date');
        $totalMonthlyRent = $request->post('monthly_rent', 0);
        $totalPropertyFee = $request->post('property_fee', 0);
        $totalDeposit = $request->post('deposit', 0);
        $scannedFileUrl = $request->post('scanned_file_url', '');
        
        $baseContractNo = 'HT' . date('YmdHis') . rand(100, 999);
        $entName = Db::table('enterprises')->where('id', $enterpriseId)->value('name');

        $allocatedRent = 0;
        $allocatedProp = 0;
        $allocatedDep = 0;

        Db::beginTransaction();
        try {
            foreach ($spaces as $index => $sp) {
                $isLast = ($index === count($spaces) - 1);
                $ratio = floatval($sp->area) / $totalArea;
                if ($totalArea == count($spaces)) $ratio = 1 / count($spaces);

                $rent = $isLast ? ($totalMonthlyRent - $allocatedRent) : round($totalMonthlyRent * $ratio, 2);
                $prop = $isLast ? ($totalPropertyFee - $allocatedProp) : round($totalPropertyFee * $ratio, 2);
                $dep = $isLast ? ($totalDeposit - $allocatedDep) : round($totalDeposit * $ratio, 2);

                $allocatedRent += $rent;
                $allocatedProp += $prop;
                $allocatedDep += $dep;

                // 核心修改：将水电底数的提取提前，以便同步落入主表
                $water = 0; $elec = 0;
                if (is_array($metersData)) {
                    foreach ($metersData as $md) {
                        if (isset($md['id']) && $md['id'] == $sp->id) {
                            $water = floatval($md['init_water'] ?? 0);
                            $elec = floatval($md['init_elec'] ?? 0);
                        }
                    }
                }

                Db::table('contracts')->insert([
                    'contract_no' => count($spaces) > 1 ? $baseContractNo . '-' . ($index + 1) : $baseContractNo,
                    'enterprise_id' => $enterpriseId,
                    'space_id' => $sp->id,
                    'start_date' => $startDate,
                    'billing_start_date' => $startDate,
                    'end_date' => $request->post('end_date'),
                    'monthly_rent' => $rent,
                    'property_fee' => $prop,
                    'payment_cycle' => $request->post('payment_cycle', 3), 
                    'next_bill_date' => $startDate,
                    'vehicle_info' => $request->post('vehicle_info', ''),
                    'deposit' => $dep,
                    'water_meter' => $water,  // <--- 新增同步到合同表
                    'electric_meter' => $elec, // <--- 新增同步到合同表
                    'scanned_file_url' => $scannedFileUrl,
                    'status' => 1,
                    'parent_id' => 0,
                    'alteration_type' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                Db::table('spaces')->where('id', $sp->id)->update([
                    'status' => 1, 
                    'enterprise_name' => $entName,
                    'water_meter' => $water,  // <--- 新增同步到空间表
                    'electric_meter' => $elec // <--- 新增同步到空间表
                ]);

                if ($dep > 0) {
                    Db::table('receivables')->insert([
                        'enterprise_id' => $enterpriseId,
                        'space_id' => $sp->id,
                        'bill_type' => 6, 
                        'amount' => $dep,
                        'due_date' => date('Y-m-d'), 
                        'is_paid' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }

                // 保留原有的独立抄表本逻辑，用于后续物业计费引擎
                Db::table('meters')->insert([
                    'space_id' => $sp->id,
                    'meter_type' => 1, 
                    'current_reading' => $water,
                    'record_month' => date('Y-m', strtotime($startDate)),
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                Db::table('meters')->insert([
                    'space_id' => $sp->id,
                    'meter_type' => 2, 
                    'current_reading' => $elec,
                    'record_month' => date('Y-m', strtotime($startDate)),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            Db::commit();
            return json(['code' => 200, 'msg' => '批量签约生单成功，租金、押金及期初水电已按资产比例切割落表']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '流转失败：' . $e->getMessage()]);
        }
    }

    public function terminate(Request $request)
    {
        $id = $request->post('id');
        $contract = Db::table('contracts')->where('id', $id)->first();
        if (!$contract) return json(['code' => 400, 'msg' => '合同档宗不存在']);

        Db::beginTransaction();
        try {
            Db::table('spaces')->where('id', $contract->space_id)->update([
                'status' => 0, 
                'enterprise_name' => null
            ]);
            
            Db::table('contracts')->where('id', $id)->update(['status' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
            
            Db::table('checkouts')->insert([
                'contract_id' => $contract->id,
                'enterprise_id' => $contract->enterprise_id,
                'refund_deposit' => $request->post('refund_deposit', $contract->deposit),
                'deduct_rent' => $request->post('deduct_rent', 0),
                'deduct_damage' => $request->post('deduct_damage', 0),
                'actual_refund' => $request->post('actual_refund', 0),
                'remark' => $request->post('remark', ''),
                'status' => 0, 
                'created_at' => date('Y-m-d H:i:s')
            ]);

            Db::commit();
            return json(['code' => 200, 'msg' => 'success']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '退租清算失败']);
        }
    }

    public function revokeTerminate(Request $request)
    {
        $contractId = $request->post('contract_id');
        $contract = Db::table('contracts')->where('id', $contractId)->first();
        if (!$contract) return json(['code' => 400, 'msg' => '合同不存在']);

        $checkout = Db::table('checkouts')->where('contract_id', $contractId)->orderBy('id', 'desc')->first();
        if ($checkout && $checkout->status == 1) {
            return json(['code' => 400, 'msg' => '阻断：财务已完成该单据的打款结清，退租流程不可逆！']);
        }

        Db::beginTransaction();
        try {
            $entName = Db::table('enterprises')->where('id', $contract->enterprise_id)->value('name');
            Db::table('spaces')->where('id', $contract->space_id)->update([
                'status' => 1,
                'enterprise_name' => $entName
            ]);

            Db::table('contracts')->where('id', $contractId)->update(['status' => 1, 'updated_at' => date('Y-m-d H:i:s')]);

            if ($checkout) {
                Db::table('checkouts')->where('id', $checkout->id)->delete();
            }

            Db::commit();
            return json(['code' => 200, 'msg' => '撤销成功，合同与物理空间已恢复至【履约中】状态']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '撤销恢复失败，底层引擎异常']);
        }
    }

    public function docs(Request $request)
    {
        $contractId = $request->get('contract_id');
        $docs = Db::table('contract_docs')->where('contract_id', $contractId)->first();
        return json(['code' => 200, 'msg' => 'success', 'data' => $docs]);
    }

    public function generateElec(Request $request)
    {
        $contractId = $request->post('contract_id');
        $url = '/uploads/elec_ht_' . $contractId . '.pdf';
        
        Db::table('contract_docs')->updateOrInsert(
            ['contract_id' => $contractId],
            ['elec_contract_url' => $url, 'updated_at' => date('Y-m-d H:i:s')]
        );
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function alterContract(Request $request)
    {
        $oldContractId = $request->post('old_contract_id');
        $alterationType = $request->post('alteration_type'); 
        $newSpaceId = $request->post('new_space_id');
        
        $physicalStartDate = $request->post('start_date');         
        $billingStartDate = $request->post('billing_start_date');   
        $endDate = $request->post('end_date');                     
        
        $monthlyRent = $request->post('monthly_rent');
        $propertyFee = $request->post('property_fee', 0);
        $scannedFileUrl = $request->post('scanned_file_url');       

        Db::beginTransaction();
        try {
            $oldContract = Db::table('contracts')->where('id', $oldContractId)->first();
            if (!$oldContract) {
                return json(['code' => 404, 'msg' => '未找到原合同底档']);
            }

            Db::table('contracts')->where('id', $oldContractId)->update([
                'status' => 0, 
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $targetSpaceId = $oldContract->space_id;
            if ($alterationType == 3) {
                if (!$newSpaceId) return json(['code' => 400, 'msg' => '园区内搬迁必须指定新物理空间']);
                Db::table('spaces')->where('id', $oldContract->space_id)->update(['status' => 0, 'enterprise_name' => null]);
                
                $entName = Db::table('enterprises')->where('id', $oldContract->enterprise_id)->value('name');
                Db::table('spaces')->where('id', $newSpaceId)->update(['status' => 1, 'enterprise_name' => $entName]);
                $targetSpaceId = $newSpaceId;
            }

            Db::table('contracts')->insert([
                'enterprise_id'      => $oldContract->enterprise_id,
                'space_id'           => $targetSpaceId,
                'parent_id'          => $oldContractId, 
                'alteration_type'    => $alterationType,
                'contract_no'        => $oldContract->contract_no . '-变更' . date('ymd'), 
                'start_date'         => $physicalStartDate,
                'billing_start_date' => $billingStartDate, 
                'next_bill_date'     => $billingStartDate, 
                'end_date'           => $endDate,
                'monthly_rent'       => $monthlyRent,
                'property_fee'       => $propertyFee,
                'payment_cycle'      => $oldContract->payment_cycle,
                'deposit'            => $oldContract->deposit, 
                'scanned_file_url'   => $scannedFileUrl,   
                'status'             => 1,                 
                'created_at'         => date('Y-m-d H:i:s'),
                'updated_at'         => date('Y-m-d H:i:s')
            ]);

            Db::commit();
            return json(['code' => 200, 'msg' => '业务流转成功，重叠期与新档案已生效']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '业务流转异常：' . $e->getMessage()]);
        }
    }

    public function history(Request $request)
    {
        $id = $request->get('id');
        $current = Db::table('contracts')->where('id', $id)->first();
        if (!$current) return json(['code' => 404, 'msg' => '数据不存在']);

        $baseNo = explode('-变更', $current->contract_no)[0];

        $history = Db::table('contracts')
            ->leftJoin('spaces', 'contracts.space_id', '=', 'spaces.id')
            ->where('contracts.contract_no', 'like', $baseNo . '%')
            ->select('contracts.*', 'spaces.building_name', 'spaces.room_number')
            ->orderBy('contracts.id', 'desc') 
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $history]);
    }
}