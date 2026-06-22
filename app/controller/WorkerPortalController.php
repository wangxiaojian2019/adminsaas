<?php
namespace app\controller;

use support\Request;
use support\Db;

class WorkerPortalController
{
    /**
     * 内部公共方法：从各种鉴权上下文中安全提取 worker_id
     */
    private function getWorkerId(Request $request) 
    {
        $user = $request->user;
        if ($user) {
            return is_array($user) ? ($user['id'] ?? 0) : ($user->id ?? 0);
        }
        $sessionUser = $request->session()->get('worker');
        return is_array($sessionUser) ? ($sessionUser['id'] ?? 0) : 0;
    }

    // --- 本次 SLA 重构优化的核心物资与消息接口 ---

    public function getInventory(Request $request)
    {
        $workerId = $this->getWorkerId($request);
        
        // 核心修复：拿着 ID 去底层 admins 表查询完整的员工实体，确保包含 real_name 字段
        $worker = Db::table('admins')->where('id', $workerId)->first();
        if (!$worker) {
            return json(['code' => 401, 'msg' => '未授权或人员档案不存在']);
        }

        // 使用查出来的真实姓名关联模糊匹配出借台账
        $list = Db::table('inventory_records')
            ->leftJoin('inventory_items', 'inventory_records.item_id', '=', 'inventory_items.id')
            ->where('inventory_records.related_person', 'like', '%' . $worker->real_name . '%')
            ->select('inventory_records.*', 'inventory_items.name as item_name', 'inventory_items.unit')
            ->orderBy('inventory_records.id', 'desc')
            ->get();
            
        return json(['code' => 200, 'data' => $list]);
    }

    public function getNotifications(Request $request)
    {
        $workerId = $this->getWorkerId($request);
        if (!$workerId) return json(['code' => 401, 'msg' => '未授权']);

        // 使用专属的外勤消息通道表
        $list = Db::table('worker_notifications')
            ->where('worker_id', $workerId)
            ->orderBy('id', 'desc')
            ->get();
            
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

        $newPass = $request->post('new_password');
        // 外勤的账号归属于底层 admins 人员架构表
        Db::table('admins')->where('id', $workerId)->update([
            'password' => md5($newPass)
        ]);
        
        return json(['code' => 200, 'msg' => '密码已更新，请重新登录']);
    }

    // --- 保留原有的其他业务接口 (巡更打卡等)，防止页面其余功能报错 ---

    public function getTasks(Request $request)
    {
        $workerId = $this->getWorkerId($request);
        // 此接口已在前端被废弃并替换为统一的 work_order/list，仅作防挂兼容保留
        return json(['code' => 200, 'data' => []]);
    }

    public function completeTask(Request $request)
    {
        // 此接口已在前端被废弃并替换为 work_order/action，仅作防挂兼容保留
        return json(['code' => 200, 'msg' => '该接口已重构，请使用 action 状态机引擎']);
    }

    public function getPatrolPoints(Request $request)
    {
        $points = Db::table('patrol_points')->get();
        return json(['code' => 200, 'data' => $points]);
    }

    public function submitPatrol(Request $request)
    {
        $workerId = $this->getWorkerId($request);
        if (!$workerId) return json(['code' => 401, 'msg' => '未授权']);

        Db::table('patrol_records')->insert([
            'worker_id' => $workerId,
            'point_id' => $request->post('point_id'),
            'image_url' => $request->post('image_url', ''),
            'remark' => $request->post('remark', ''),
            'status' => $request->post('status', 1), 
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => '防区巡更打卡成功']);
    }
}