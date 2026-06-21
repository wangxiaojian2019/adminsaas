<?php
namespace app\controller;

use support\Request;
use support\Db;

class WorkerPortalController
{
    public function getTasks(Request $request)
    {
        $workerId = $request->user->id ?? 0;
        $list = Db::table('work_orders')
            ->where('handler_id', $workerId)
            ->orderByRaw("CASE WHEN status = 2 THEN 0 WHEN status = 3 THEN 1 ELSE 2 END")
            ->orderBy('id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function completeTask(Request $request)
    {
        $workerId = $request->user->id ?? 0;
        $id = $request->post('id');
        $replyRemarks = $request->post('reply_remarks', '');
        $imageUrl = $request->post('image_url', '');

        $order = Db::table('work_orders')->where('id', $id)->where('handler_id', $workerId)->first();
        if (!$order) return json(['code' => 400, 'msg' => '未找到指派工单']);

        $descAppend = $order->description;
        if (!empty($replyRemarks)) $descAppend .= "\n\n【外勤反馈纪要】: " . $replyRemarks;
        if (!empty($imageUrl)) $descAppend .= "\n【完工现场照片证物】: " . $imageUrl;

        Db::table('work_orders')->where('id', $id)->update(['status' => 3, 'description' => $descAppend]);
        return json(['code' => 200, 'msg' => '完工单据已上报']);
    }

    public function getPatrolPoints(Request $request)
    {
        $list = Db::table('patrol_points')->orderBy('id', 'asc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function submitPatrol(Request $request)
    {
        $workerName = $request->user->real_name ?? $request->user->username ?? '外勤人员';
        $pointId = $request->post('point_id');
        $remarks = $request->post('remarks', '');
        $imageUrl = $request->post('image_url', '');

        $point = Db::table('patrol_points')->where('id', $pointId)->first();
        if (!$point) return json(['code' => 400, 'msg' => '节点不存在']);

        if (!empty($imageUrl)) $remarks .= " [实地打卡现场证物: " . $imageUrl . "]";
        Db::table('patrol_records')->insert([
            'point_id' => $pointId, 
            'operator_name' => $workerName, 
            'is_normal' => $request->post('status', 1),
            'remark' => $remarks, 
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => '巡更打卡数据存证成功']);
    }

    public function updatePassword(Request $request)
    {
        $adminId = $request->user->id ?? 0;
        $admin = Db::table('admins')->where('id', $adminId)->first();
        if (!$admin || md5($request->post('old_password')) !== $admin->password) return json(['code' => 400, 'msg' => '原密码不正确']);

        Db::table('admins')->where('id', $adminId)->update(['password' => md5($request->post('new_password'))]);
        return json(['code' => 200, 'msg' => '密码重置成功']);
    }

    public function getInventory(Request $request)
    {
        $workerId = $request->user->id ?? 0;
        $worker = Db::table('admins')->where('id', $workerId)->first();
        if (!$worker) return json(['code' => 200, 'msg' => 'success', 'data' => []]);

        $realName = $worker->real_name ?? '';
        $username = $worker->username ?? '';
        $name     = $worker->name ?? '';

        $query = Db::table('inventory_records')->join('inventory_items', 'inventory_records.item_id', '=', 'inventory_items.id');

        $query->where(function($q) use ($realName, $username, $name) {
            $hasCondition = false;
            if (!empty($realName)) { $q->orWhere('inventory_records.related_person', 'like', "%{$realName}%"); $hasCondition = true; }
            if (!empty($username)) { $q->orWhere('inventory_records.related_person', 'like', "%{$username}%"); $hasCondition = true; }
            if (!empty($name))     { $q->orWhere('inventory_records.related_person', 'like', "%{$name}%"); $hasCondition = true; }
            if (!$hasCondition)    { $q->where('inventory_records.id', '<', 0); }
        });

        $list = $query->select('inventory_records.*', 'inventory_items.name as item_name', 'inventory_items.unit', 'inventory_items.category')
            ->orderBy('inventory_records.id', 'desc')->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    // 核心新增：提取员工的消息列表
    public function getNotifications(Request $request)
    {
        $workerId = $request->user->id ?? 0;
        $list = Db::table('worker_notifications')->where('worker_id', $workerId)->orderBy('id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    // 核心新增：核销员工的未读消息
    public function readNotification(Request $request)
    {
        $workerId = $request->user->id ?? 0;
        $id = $request->post('id');
        Db::table('worker_notifications')->where('id', $id)->where('worker_id', $workerId)->update(['is_read' => 1]);
        return json(['code' => 200, 'msg' => 'success']);
    }
}