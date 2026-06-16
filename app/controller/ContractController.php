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
        $spaceId = $request->post('space_id');
        $enterpriseId = $request->post('enterprise_id');
        
        $space = Db::table('spaces')->where('id', $spaceId)->lockForUpdate()->first();
        if ($space->status != 0) {
            return json(['code' => 400, 'msg' => '业务拦截：该物理空间已被占用，无法重复签约']);
        }

        $startDate = $request->post('start_date');
        $deposit = $request->post('deposit', 0);

        $data = [
            'contract_no' => 'HT' . date('YmdHis') . rand(1000, 9999),
            'enterprise_id' => $enterpriseId,
            'space_id' => $spaceId,
            'start_date' => $startDate,
            'end_date' => $request->post('end_date'),
            'monthly_rent' => $request->post('monthly_rent', 0),
            'property_fee' => $request->post('property_fee', 0),
            'payment_cycle' => $request->post('payment_cycle', 3), 
            'next_bill_date' => $startDate,
            'vehicle_info' => $request->post('vehicle_info', ''),
            'deposit' => $deposit,
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        Db::beginTransaction();
        try {
            Db::table('contracts')->insert($data);
            
            $entName = Db::table('enterprises')->where('id', $enterpriseId)->value('name');
            Db::table('spaces')->where('id', $spaceId)->update([
                'status' => 1, 
                'enterprise_name' => $entName
            ]);

            if ($deposit > 0) {
                Db::table('receivables')->insert([
                    'enterprise_id' => $enterpriseId,
                    'space_id' => $spaceId,
                    'bill_type' => 6, 
                    'amount' => $deposit,
                    'due_date' => date('Y-m-d'), 
                    'is_paid' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            Db::commit();
            return json(['code' => 200, 'msg' => 'success']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '签约流转失败，已回滚数据']);
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
            
            Db::table('contracts')->where('id', $id)->update(['status' => 0]);
            
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

    // 核心新增：撤销退租防误触引擎
    public function revokeTerminate(Request $request)
    {
        $contractId = $request->post('contract_id');
        $contract = Db::table('contracts')->where('id', $contractId)->first();
        if (!$contract) return json(['code' => 400, 'msg' => '合同不存在']);

        // 拦截逻辑：查验财务核销状态
        $checkout = Db::table('checkouts')->where('contract_id', $contractId)->orderBy('id', 'desc')->first();
        if ($checkout && $checkout->status == 1) {
            return json(['code' => 400, 'msg' => '阻断：财务已完成该单据的打款结清，退租流程不可逆！']);
        }

        Db::beginTransaction();
        try {
            // 1. 恢复底层空间的占用状态
            $entName = Db::table('enterprises')->where('id', $contract->enterprise_id)->value('name');
            Db::table('spaces')->where('id', $contract->space_id)->update([
                'status' => 1,
                'enterprise_name' => $entName
            ]);

            // 2. 恢复合同主表的履约状态
            Db::table('contracts')->where('id', $contractId)->update(['status' => 1]);

            // 3. 物理销毁作废的退租清单
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
}