<?php
namespace app\controller;

use support\Request;
use support\Db;

class WorkerPortalController
{
    public function getTasks(Request $request)
    {
        $workerId = $request->user->id;
        
        $list = Db::table('work_orders')
            ->where('handler_id', $workerId)
            ->orderByRaw("CASE WHEN status = 2 THEN 0 WHEN status = 3 THEN 1 ELSE 2 END")
            ->orderBy('id', 'desc')
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function completeTask(Request $request)
    {
        $workerId = $request->user->id;
        $id = $request->post('id');
        $replyRemarks = $request->post('reply_remarks', '');
        $imageUrl = $request->post('image_url', '');

        $order = Db::table('work_orders')->where('id', $id)->where('handler_id', $workerId)->first();
        if (!$order) {
            return json(['code' => 400, 'msg' => '阻断：未找到对应指派工单任务']);
        }

        $descAppend = $order->description;
        if (!empty($replyRemarks)) {
            $descAppend .= "\n\n【外勤反馈纪要】: " . $replyRemarks;
        }
        if (!empty($imageUrl)) {
            $descAppend .= "\n【完工现场照片证物】: " . $imageUrl;
        }

        Db::table('work_orders')->where('id', $id)->update([
            'status' => 3, 
            'description' => $descAppend
        ]);

        return json(['code' => 200, 'msg' => '完工单据已上报，已通知中控主管验收结案']);
    }

    public function getPatrolPoints(Request $request)
    {
        $list = Db::table('patrol_points')->orderBy('id', 'asc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function submitPatrol(Request $request)
    {
        $workerName = $request->user->real_name ?: $request->user->username;
        $pointId = $request->post('point_id');
        $status = $request->post('status', 1); 
        $remarks = $request->post('remarks', '');
        $imageUrl = $request->post('image_url', '');

        $point = Db::table('patrol_points')->where('id', $pointId)->first();
        if (!$point) {
            return json(['code' => 400, 'msg' => '目标巡检防区节点不存在']);
        }

        if (!empty($imageUrl)) {
            $remarks .= " [实地打卡现场证物: " . $imageUrl . "]";
        }

        Db::table('patrol_records')->insert([
            'point_id' => $pointId,
            'operator_name' => $workerName,
            'is_normal' => $status,
            'remark' => $remarks,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '防区巡更打卡数据存证成功']);
    }

    // 核心新增：外勤外勤作业员独立改密引擎
    public function updatePassword(Request $request)
    {
        $adminId = $request->user->id;
        $oldPwd = $request->post('old_password');
        $newPwd = $request->post('new_password');

        if (empty($oldPwd) || empty($newPwd)) {
            return json(['code' => 400, 'msg' => '核心字段不可留空']);
        }

        $admin = Db::table('admins')->where('id', $adminId)->first();
        if (!$admin) {
            return json(['code' => 404, 'msg' => '外勤人员档案不存在']);
        }

        // 强对比底层账号原密码指纹
        if (md5($oldPwd) !== $admin->password) {
            return json(['code' => 400, 'msg' => '原密码不正确，安全验证未通过']);
        }

        // 覆盖新密码散列
        Db::table('admins')->where('id', $adminId)->update([
            'password' => md5($newPwd)
        ]);

        return json(['code' => 200, 'msg' => '作业安全密码重置成功，请重新登录终端']);
    }
}