<?php
namespace app\controller;

use support\Request;
use support\Db;

class EnterpriseController
{
    /**
     * 企业户籍大盘 (重构：引入分页防内存溢出)
     */
    public function list(Request $request)
    {
        $page = (int)$request->get('page', 1);
        $pageSize = (int)$request->get('limit', 15);
        $tenantId = $request->tenantId ?? 1;

        $query = Db::table('enterprises')
            ->where('enterprises.tenant_id', $tenantId)
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
            ->groupBy(
                'enterprises.id', 
                'enterprises.name', 
                'enterprises.industry', 
                'enterprises.contact_person', 
                'enterprises.phone', 
                'enterprises.created_at'
            );

        // 分页计算引擎
        $paginator = $query->orderBy('enterprises.id', 'desc')
                           ->paginate($pageSize, ['*'], 'page', $page);

        return json([
            'code' => 200, 
            'msg' => 'success', 
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage()
            ]
        ]);
    }

    public function add(Request $request)
    {
        $phone = $request->post('phone');
        $spaceId = $request->post('space_id'); 
        $dateRange = $request->post('dateRange', []); 
        $tenantId = $request->tenantId ?? 1;
        
        $exists = Db::table('enterprises')->where('phone', $phone)->where('tenant_id', $tenantId)->first();
        if ($exists) {
            return json(['code' => 400, 'msg' => '该联系人手机号已被园区内其他企业绑定']);
        }

        Db::beginTransaction();
        try {
            $enterpriseId = Db::table('enterprises')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => $request->post('name'),
                'contact_person' => $request->post('contact_person'),
                'phone' => $phone,
                'password' => md5('123456'), 
                'industry' => $request->post('industry', ''),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // 快捷建档：同步生单入驻
            if ($spaceId && !empty($dateRange) && is_array($dateRange) && count($dateRange) >= 2) {
                $space = Db::table('spaces')->where('id', $spaceId)->lockForUpdate()->first();
                
                if ($space && $space->status == 0) {
                    Db::table('spaces')->where('id', $spaceId)->update([
                        'status' => 1,
                        'enterprise_name' => $request->post('name'),
                        'water_meter' => $request->post('water_meter', 0),
                        'electric_meter' => $request->post('electric_meter', 0)
                    ]);
                    
                    Db::table('contracts')->insert([
                        'tenant_id' => $tenantId,
                        'contract_no' => 'HT' . date('YmdHi') . rand(10, 99),
                        'enterprise_id' => $enterpriseId,
                        'space_id' => $spaceId,
                        'start_date' => $dateRange[0],
                        'end_date' => $dateRange[1],
                        'monthly_rent' => $request->post('monthly_rent', 0),
                        'property_fee' => $request->post('property_fee', 0),
                        'deposit' => $request->post('deposit', 0),
                        'water_meter' => $request->post('water_meter', 0),
                        'electric_meter' => $request->post('electric_meter', 0),
                        'vehicle_info' => '',
                        'status' => 1,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            Db::commit();
            return json(['code' => 200, 'msg' => '企业户籍建档成功']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '建档拦截，底层原因: ' . $e->getMessage()]);
        }
    }

    public function resetPwd(Request $request)
    {
        $id = $request->post('id');
        $tenantId = $request->tenantId ?? 1;
        Db::table('enterprises')->where('id', $id)->where('tenant_id', $tenantId)->update(['password' => md5('123456')]);
        return json(['code' => 200, 'msg' => '重置成功，该企业租户端登录密码已恢复为 123456']);
    }
}