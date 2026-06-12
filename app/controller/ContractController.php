<?php
namespace app\controller;

use support\Request;
use support\Db;

class ContractController
{
    public function list(Request $request)
    {
        // 连表查询，获取完整的企业名和空间房号
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
        
        // 并发拦截：校验空间是否已经被占用
        $space = Db::table('spaces')->where('id', $spaceId)->lockForUpdate()->first();
        if ($space->status != 0) {
            return json(['code' => 400, 'msg' => '业务拦截：该物理空间已被占用，无法重复签约']);
        }

        $data = [
            'contract_no' => 'HT' . date('YmdHis') . rand(1000, 9999),
            'enterprise_id' => $enterpriseId,
            'space_id' => $spaceId,
            'start_date' => $request->post('start_date'),
            'end_date' => $request->post('end_date'),
            'monthly_rent' => $request->post('monthly_rent', 0),
            'property_fee' => $request->post('property_fee', 0),
            'vehicle_info' => $request->post('vehicle_info', ''),
            'deposit' => $request->post('deposit', 0),
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // 开启事务处理
        Db::beginTransaction();
        try {
            // 1. 插入合同记录
            Db::table('contracts')->insert($data);
            
            // 2. 锁定物理空间，并挂载企业名称以便于热力图展示
            $entName = Db::table('enterprises')->where('id', $enterpriseId)->value('name');
            Db::table('spaces')->where('id', $spaceId)->update([
                'status' => 1, 
                'enterprise_name' => $entName
            ]);
            
            Db::commit();
            return json(['code' => 200, 'msg' => 'success']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '签约流转失败，已回滚数据']);
        }
    }

    /**
     * 核心重构：退租清算处理引擎
     */
    public function terminate(Request $request)
    {
        $id = $request->post('id');
        $contract = Db::table('contracts')->where('id', $id)->first();
        if (!$contract) return json(['code' => 400, 'msg' => '合同档宗不存在']);

        Db::beginTransaction();
        try {
            // 1. 释放空间资产 (状态归0，清空企业归属)
            Db::table('spaces')->where('id', $contract->space_id)->update([
                'status' => 0, 
                'enterprise_name' => null
            ]);
            
            // 2. 将原合同状态置为 0 (已失效/已退租)
            Db::table('contracts')->where('id', $id)->update(['status' => 0]);
            
            // 3. 生成并入库退租清算账单，推送到财务池
            Db::table('checkouts')->insert([
                'contract_id' => $contract->id,
                'enterprise_id' => $contract->enterprise_id,
                'refund_deposit' => $request->post('refund_deposit', $contract->deposit),
                'deduct_rent' => $request->post('deduct_rent', 0),
                'deduct_damage' => $request->post('deduct_damage', 0),
                'actual_refund' => $request->post('actual_refund', 0),
                'status' => 0, // 流转到财务等待打款
                'created_at' => date('Y-m-d H:i:s')
            ]);

            Db::commit();
            return json(['code' => 200, 'msg' => 'success']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '退租清算失败']);
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
        // 模拟生成PDF的物理路径
        $url = '/uploads/elec_ht_' . $contractId . '.pdf';
        
        Db::table('contract_docs')->updateOrInsert(
            ['contract_id' => $contractId],
            ['elec_contract_url' => $url, 'updated_at' => date('Y-m-d H:i:s')]
        );
        return json(['code' => 200, 'msg' => 'success']);
    }
}