<?php
namespace app\controller;

use support\Request;
use support\Db;

class WorkerPortalController
{
    private function getWorkerId(Request $request) 
    {
        $user = $request->user;
        if ($user) return is_array($user) ? ($user['id'] ?? 0) : ($user->id ?? 0);
        $sessionUser = $request->session()->get('worker');
        return is_array($sessionUser) ? ($sessionUser['id'] ?? 0) : 0;
    }

    public function reportIssue(Request $request)
    {
        $workerId = $this->getWorkerId($request);
        if (!$workerId) return json(['code' => 401, 'msg' => '安全校验失败，人员未授权']);

        $worker = Db::table('admins')->where('id', $workerId)->first();
        if (!$worker) return json(['code' => 404, 'msg' => '档案异常']);

        // 构造初始的生命周期轨迹
        $processLog = [
            [
                'title' => '隐患发现与上报',
                'operator' => $worker->real_name . ' (外勤)',
                'desc' => $request->post('description', '无补充说明'),
                'image' => $request->post('image_url', ''),
                'time' => date('Y-m-d H:i:s')
            ]
        ];

        Db::table('work_orders')->insert([
            'tenant_id' => $worker->tenant_id ?? 1,
            'title' => $request->post('title', '外勤巡查上报'),
            'description' => $request->post('description', ''),
            'reporter_name' => $worker->real_name . ' (巡查上报)', 
            'report_image_url' => $request->post('image_url', ''),   
            'priority' => intval($request->post('priority', 1)),
            'status' => 1, 
            'process_log' => json_encode($processLog, JSON_UNESCAPED_UNICODE), // 写入初始轨迹
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '现场隐患已成功上报至调度中心']);
    }

    public function getInventory(Request $request)
    {
        $workerId = $this->getWorkerId($request);
        $worker = Db::table('admins')->where('id', $workerId)->first();
        if (!$worker) return json(['code' => 401, 'msg' => '未授权']);

        $list = Db::table('inventory_records')
            ->leftJoin('inventory_items', 'inventory_records.item_id', '=', 'inventory_items.id')
            ->where('inventory_records.related_person', 'like', '%' . $worker->real_name . '%')
            ->select('inventory_records.*', 'inventory_items.name as item_name', 'inventory_items.unit')
            ->orderBy('inventory_records.id', 'desc')->get();
        return json(['code' => 200, 'data' => $list]);
    }

    public function getNotifications(Request $request)
    {
        $workerId = $this->getWorkerId($request);
        if (!$workerId) return json(['code' => 401, 'msg' => '未授权']);
        $list = Db::table('worker_notifications')->where('worker_id', $workerId)->orderBy('id', 'desc')->get();
        return json(['code' => 200, 'data' => $list]);
    }

    public function readNotification(Request $request)
    {
        $id = $request->post('id');
        Db::table('worker_notifications')->where('id', $id)->update(['is_read' => 1]);
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function updatePassword(Request $request)
    {
        $workerId = $this->getWorkerId($request);
        if (!$workerId) return json(['code' => 401, 'msg' => '未授权']);
        Db::table('admins')->where('id', $workerId)->update(['password' => md5($request->post('new_password'))]);
        return json(['code' => 200, 'msg' => '密码已更新，请重新登录']);
    }

    public function getTasks(Request $request) { return json(['code' => 200, 'data' => []]); }
    public function completeTask(Request $request) { return json(['code' => 200, 'msg' => '该接口已重构']); }

    public function getPatrolPoints(Request $request)
    {
        $points = Db::table('patrol_points')->select('id', 'tenant_id', 'point_name as name', 'location', 'created_at')->get();
        return json(['code' => 200, 'data' => $points]);
    }

    public function submitPatrol(Request $request)
    {
        $workerId = $this->getWorkerId($request);
        if (!$workerId) return json(['code' => 401, 'msg' => '未授权']);

        $worker = Db::table('admins')->where('id', $workerId)->first();

        Db::table('patrol_records')->insert([
            'worker_id' => $workerId,
            'point_id' => $request->post('point_id'),
            'image_url' => $request->post('image_url', ''),
            'remark' => $request->post('remark', ''),
            'status' => $request->post('status', 1), 
            // 【核心修复】：移除了导致崩溃的 clone 关键字
            'is_normal' => $request->post('status', 1) == 1 ? 1 : 0, 
            'operator_name' => $worker->real_name ?? '未知外勤', 
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => '防区巡更打卡成功']);
    }

    public function getPatrolRecords(Request $request)
    {
        $workerId = $this->getWorkerId($request);
        if (!$workerId) return json(['code' => 401, 'msg' => '未授权']);
        $records = Db::table('patrol_records as r')
            ->leftJoin('patrol_points as p', 'r.point_id', '=', 'p.id')
            ->where('r.worker_id', $workerId)
            ->select('r.*', 'p.point_name', 'p.location')
            ->orderBy('r.id', 'desc')->get();
        return json(['code' => 200, 'data' => $records]);
    }
}