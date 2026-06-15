<?php
namespace app\controller;

use support\Request;
use support\Db;

class LeadController
{
    public function list(Request $request)
    {
        $user = $request->user;
        $type = $request->get('type', 'private'); // 前端传参：public(公海) / private(私海)
        
        // 核心保留：利用子查询提取该线索在跟进表中的最新时间，并连表获取负责人姓名
        $query = Db::table('leads')
            ->select(
                'leads.*', 
                Db::raw('(SELECT MAX(created_at) FROM lead_follow_ups WHERE lead_id = leads.id) as last_follow_time'),
                'admins.real_name as owner_name'
            )
            ->leftJoin('admins', 'leads.admin_id', '=', 'admins.id');

        // 核心规则：15天的物理时间边界
        $deadline = date('Y-m-d H:i:s', strtotime('-15 days'));

        if ($type === 'public') {
            // 公海池：无负责人，或者最后心跳时间超过15天（触发强制掉落）
            $query->where(function ($q) use ($deadline) {
                $q->whereNull('leads.admin_id')
                  ->orWhere('leads.admin_id', 0)
                  ->orWhere('leads.last_track_time', '<', $deadline);
            });
        } else {
            // 私海池：有负责人，且心跳时间在15天内
            $query->where('leads.admin_id', '>', 0)
                  ->where('leads.last_track_time', '>=', $deadline);

            // RBAC 数据权限物理隔离
            if ($user->data_scope == 1) {
                // 单点隔离：仅看本人名下线索
                $query->where('leads.admin_id', $user->id);
            } elseif ($user->data_scope == 2) {
                // 树状穿透：可看本部门所有人名下线索
                $ids = $user->department_admin_ids ?? [$user->id];
                $query->whereIn('leads.admin_id', $ids);
            }
            // data_scope == 3 (全局透视) 则跳过 where 限制，看全盘
        }

        $list = $query->orderBy('leads.id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function add(Request $request)
    {
        $user = $request->user;
        
        $data = [
            'customer_name' => $request->post('customer_name'),
            'contact_person' => $request->post('contact_person', ''),
            'phone' => $request->post('phone', ''),
            'demand_area' => $request->post('demand_area', 0),
            'source' => $request->post('source', 1),
            'status' => 1,
            // 缝合新增：自动绑定录入者，并瞬间激活15天倒计时保护机制
            'admin_id' => $user->id,
            'last_track_time' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ];
        Db::table('leads')->insert($data);
        return json(['code' => 200, 'msg' => '录入成功，已锁定15天私海保护期']);
    }

    public function followList(Request $request)
    {
        // 100% 绝对保留你的原逻辑
        $leadId = $request->get('lead_id');
        $list = Db::table('lead_follow_ups')
            ->where('lead_id', $leadId)
            ->orderBy('id', 'desc')
            ->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function followAdd(Request $request)
    {
        $user = $request->user;
        $leadId = $request->post('lead_id');
        
        Db::beginTransaction();
        try {
            // 1. 100% 保留你原有的跟进表插入逻辑
            $data = [
                'lead_id' => $leadId,
                'operator_name' => $user->real_name ?? '系统',
                'content' => $request->post('content'),
                'intent_level' => $request->post('intent_level', '中'),
                'next_follow_time' => $request->post('next_follow_time'),
                'created_at' => date('Y-m-d H:i:s')
            ];
            Db::table('lead_follow_ups')->insert($data);
            
            // 2. 缝合新增：刷新主表物理心跳时间，防止掉落公海
            Db::table('leads')->where('id', $leadId)->update([
                'last_track_time' => date('Y-m-d H:i:s')
            ]);
            
            Db::commit();
            return json(['code' => 200, 'msg' => '跟进成功，15天防脱落保护期已重置']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '数据流转异常']);
        }
    }
}