<?php
namespace app\controller;

use support\Request;
use support\Db;

class EnterpriseController
{
    public function list(Request $request)
    {
        // 核心修改：联表聚合，提取履约中合同的财务数据与到期时间
        $list = Db::table('enterprises')
            ->leftJoin('contracts', function($join) {
                $join->on('enterprises.id', '=', 'contracts.enterprise_id')
                     ->where('contracts.status', '=', 1);
            })
            ->select(
                'enterprises.id', 
                'enterprises.name', 
                'enterprises.industry', 
                'enterprises.contact_person', 
                'enterprises.phone', 
                'enterprises.created_at',
                Db::raw('MAX(contracts.end_date) as end_date'),
                Db::raw('SUM(contracts.monthly_rent) as monthly_rent'),
                Db::raw('SUM(contracts.property_fee) as property_fee'),
                Db::raw('SUM(contracts.deposit) as deposit')
            )
            ->groupBy('enterprises.id', 'enterprises.name', 'enterprises.industry', 'enterprises.contact_person', 'enterprises.phone', 'enterprises.created_at')
            ->orderBy('enterprises.id', 'desc')
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function add(Request $request)
    {
        $phone = $request->post('phone');
        $spaceId = $request->post('space_id'); 
        $dateRange = $request->post('dateRange', []); 
        
        $exists = Db::table('enterprises')->where('phone', $phone)->first();
        if ($exists) {
            return json(['code' => 400, 'msg' => '该联系人手机号已被其他企业绑定']);
        }

        Db::beginTransaction();
        try {
            $enterpriseId = Db::table('enterprises')->insertGetId([
                'name' => $request->post('name'),
                'contact_person' => $request->post('contact_person'),
                'phone' => $phone,
                'password' => md5('123456'), 
                'industry' => $request->post('industry', ''),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            if ($spaceId && !empty($dateRange) && is_array($dateRange) && count($dateRange) >= 2) {
                $space = Db::table('spaces')->where('id', $spaceId)->lockForUpdate()->first();
                
                if ($space && $space->status == 0) {
                    Db::table('spaces')->where('id', $spaceId)->update([
                        'status' => 1,
                        'enterprise_name' => $request->post('name')
                    ]);
                    
                    Db::table('contracts')->insert([
                        'contract_no' => 'HT' . date('YmdHi') . rand(10, 99),
                        'enterprise_id' => $enterpriseId,
                        'space_id' => $spaceId,
                        'start_date' => $dateRange[0],
                        'end_date' => $dateRange[1],
                        'monthly_rent' => $request->post('monthly_rent', 0),
                        'property_fee' => $request->post('property_fee', 0),
                        'deposit' => $request->post('deposit', 0),
                        'vehicle_info' => '',
                        'status' => 1,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            Db::commit();
            return json(['code' => 200, 'msg' => 'success']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '建档拦截，底层原因: ' . $e->getMessage()]);
        }
    }

    public function resetPwd(Request $request)
    {
        $id = $request->post('id');
        Db::table('enterprises')->where('id', $id)->update(['password' => md5('123456')]);
        return json(['code' => 200, 'msg' => '重置成功，该企业租户端登录密码已恢复为 123456']);
    }
}