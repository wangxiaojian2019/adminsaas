<?php
namespace app\controller;

use support\Request;
use support\Db;

class WorkerPortalController
{
    public function getTasks(Request $request)
    {
        $worker = $request->session()->get('worker');
        $workerId = $worker['id'];
        $roleType = $worker['role_type'] ?? '综合'; // 维修, 保洁, 巡逻, 综合

        $query = Db::table('work_orders')
            ->leftJoin('spaces', 'work_orders.space_id', '=', 'spaces.id')
            ->select('work_orders.*', 'spaces.building_name', 'spaces.room_number');

        // 【千人千面数据下发控制】
        if ($roleType == '维修') {
            $query->where('work_orders.type', '维修');
        } elseif ($roleType == '保洁') {
            $query->where('work_orders.type', '保洁');
        } else {
            // 巡逻人员默认只看被显式派发给自己的工单
            $query->where('work_orders.assignee_id', $workerId);
        }

        $list = $query->orderBy('work_orders.id', 'desc')->get();
        return json(['code' => 200, 'data' => $list]);
    }

    public function completeTask(Request $request)
    {
        $id = $request->post('id');
        $images = $request->post('images', '');
        $remark = $request->post('remark', '');
        
        Db::table('work_orders')->where('id', $id)->update([
            'status' => 2, 
            'result_images' => $images,
            'result_remark' => $remark,
            'completed_at' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => '任务已提报完成']);
    }

    public function getPatrolPoints(Request $request)
    {
        $points = Db::table('patrol_points')->get();
        return json(['code' => 200, 'data' => $points]);
    }

    public function submitPatrol(Request $request)
    {
        $worker = $request->session()->get('worker');
        Db::table('patrol_records')->insert([
            'worker_id' => $worker['id'],
            'point_id' => $request->post('point_id'),
            'image_url' => $request->post('image_url', ''),
            'remark' => $request->post('remark', ''),
            'status' => $request->post('status', 1), 
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => '打卡成功']);
    }

    public function getInventory(Request $request)
    {
        $list = Db::table('inventory')->get();
        return json(['code' => 200, 'data' => $list]);
    }

    public function getNotifications(Request $request)
    {
        $worker = $request->session()->get('worker');
        $list = Db::table('notifications')
            ->where('worker_id', $worker['id'])
            ->orderBy('id', 'desc')
            ->get();
        return json(['code' => 200, 'data' => $list]);
    }

    public function readNotification(Request $request)
    {
        $id = $request->post('id');
        Db::table('notifications')->where('id', $id)->update(['is_read' => 1]);
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function updatePassword(Request $request)
    {
        $worker = $request->session()->get('worker');
        $newPass = $request->post('new_password');
        Db::table('service_staff')->where('id', $worker['id'])->update([
            'password' => md5($newPass)
        ]);
        return json(['code' => 200, 'msg' => '密码已更新，请重新登录']);
    }
}