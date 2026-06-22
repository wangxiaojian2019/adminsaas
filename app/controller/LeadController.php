<?php
namespace app\controller;

use support\Request;
use support\Db;

class LeadController
{
    public function list(Request $request)
    {
        $admin = $request->user;
        $adminId = $admin->id ?? 1;
        $roleId = $admin->role_id ?? 1;

        // 【自动掉落引擎 & 防囤积标记】：15天未跟进自动掉入公海，并打上前任负责人与掉落时间戳烙印
        $fifteenDaysAgo = date('Y-m-d H:i:s', strtotime('-15 days'));
        $nowStr = date('Y-m-d H:i:s');
        
        Db::update(
            "UPDATE leads SET last_owner_id = admin_id, drop_time = ?, admin_id = 0 WHERE admin_id > 0 AND status < 3 AND last_follow_time < ?", 
            [$nowStr, $fifteenDaysAgo]
        );

        // 获取当前登录角色的数据权限范围
        $role = Db::table('roles')->where('id', $roleId)->first();
        $dataScope = $role ? $role->data_scope : 1; // 1:本人, 2:本部门(直属下级), 3:全局

        $query = Db::table('leads')
            ->leftJoin('admins', 'leads.admin_id', '=', 'admins.id')
            ->select('leads.*', 'admins.real_name as responsible_person');

        // 【数据隔离引擎】：非超管且非全局权限时，严格隔离数据
        if ($roleId != 1 && $dataScope != 3) {
            if ($dataScope == 1) {
                // 只能看到自己名下 + 公海的线索
                $query->where(function ($q) use ($adminId) {
                    $q->where('leads.admin_id', $adminId)
                      ->orWhere('leads.admin_id', 0);
                });
            } elseif ($dataScope == 2) {
                // 能看到自己 + 直属下级 + 公海的线索
                $subAdmins = Db::table('admins')->where('parent_id', $adminId)->get();
                $subIds = [$adminId];
                foreach ($subAdmins as $sub) {
                    $subIds[] = $sub->id;
                }
                $query->where(function ($q) use ($subIds) {
                    $q->whereIn('leads.admin_id', $subIds)
                      ->orWhere('leads.admin_id', 0);
                });
            }
        }

        $list = $query->orderBy('leads.id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function add(Request $request)
    {
        $admin = $request->user;
        
        Db::table('leads')->insert([
            'customer_name' => $request->post('customer_name'),
            'contact_person' => $request->post('contact_person'),
            'phone' => $request->post('phone'),
            'demand_area' => $request->post('demand_area', ''),
            'source' => $request->post('source', ''),
            'status' => 1, 
            'admin_id' => $admin->id ?? 0, 
            'last_owner_id' => 0,
            'last_follow_time' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => '线索录入成功，已锁定在您的名下']);
    }

    public function followList(Request $request)
    {
        $leadId = $request->get('lead_id');
        $list = Db::table('lead_follow_ups')->where('lead_id', $leadId)->orderBy('id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function followAdd(Request $request)
    {
        $leadId = $request->post('lead_id');
        $status = $request->post('status');
        $now = date('Y-m-d H:i:s');
        $admin = $request->user;

        Db::beginTransaction();
        try {
            $lead = Db::table('leads')->where('id', $leadId)->lockForUpdate()->first();
            
            $updateData = [
                'status' => $status,
                'last_follow_time' => $now
            ];
            
            $isClaimed = false;
            
            // 【SOP 拦截核心】：如果当前处理的是公海线索
            if ($lead->admin_id == 0) {
                // 防囤积校验：如果是原负责人，且距离掉落不足7天，实施物理拦截
                if ($lead->last_owner_id == $admin->id && $lead->drop_time) {
                    $dropTimestamp = strtotime($lead->drop_time);
                    $sevenDaysLater = $dropTimestamp + (7 * 24 * 3600);
                    if (time() < $sevenDaysLater) {
                        Db::rollBack();
                        return json([
                            'code' => 403, 
                            'msg' => 'SOP防囤积拦截：由于您逾期未跟进导致线索掉落，已触发7天冷却惩罚。需至 '.date('Y-m-d H:i', $sevenDaysLater).' 方可重新捞取，当前仅允许其他同事介入。'
                        ]);
                    }
                }

                $updateData['admin_id'] = $admin->id;
                // 新人接手或惩罚期满后认领，抹除原有的掉落印记
                $updateData['last_owner_id'] = 0;
                $updateData['drop_time'] = null;
                $isClaimed = true;
            }

            // 1. 写跟进记录
            Db::table('lead_follow_ups')->insert([
                'lead_id' => $leadId,
                'operator_name' => $admin->real_name ?? ($admin->username ?? '业务员'),
                'content' => $request->post('content'),
                'created_at' => $now
            ]);

            // 2. 更新线索主表
            Db::table('leads')->where('id', $leadId)->update($updateData);

            Db::commit();
            
            $msg = $isClaimed ? '捞取成功！该公海线索已自动锁定到您的私海。' : '跟进纪要保存成功，私海保护期已重置15天。';
            return json(['code' => 200, 'msg' => $msg]);
            
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '业务引擎流转失败']);
        }
    }
}