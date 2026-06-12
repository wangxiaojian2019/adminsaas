<?php
namespace app\controller;

use support\Request;
use support\Db;

class PatrolController
{
    public function pointList(Request $request) {
        $list = Db::table('patrol_points')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }
    
    public function pointAdd(Request $request) {
        try {
            $locationName = $request->post('location');
            Db::table('patrol_points')->insert([
                'location' => $locationName,
                'point_name' => $locationName, // 核心修复：同步补齐数据库必需的 point_name 字段
                'created_at' => date('Y-m-d H:i:s')
            ]);
            return json(['code' => 200, 'msg' => 'success']);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => '点位设立失败，底层原因: ' . $e->getMessage()]);
        }
    }

    public function checkin(Request $request) {
        $user = $request->user;
        $status = $request->post('status', 1);
        $location = $request->post('location');
        $remarks = $request->post('remarks', '');

        Db::beginTransaction();
        try {
            // 1. 记录打卡流水
            Db::table('patrol_records')->insert([
                'point_id' => $request->post('point_id', 0),
                'location' => $location,
                'worker_name' => $user->real_name ?? '现场巡检员',
                'status' => $status,
                'remarks' => $remarks,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // 2. 若工况异常，自动生成告警工单并推送至调度中心
            if ($status == 0) {
                 Db::table('work_orders')->insert([
                    'title' => '安全网格异常警报: ' . $location,
                    'description' => '巡检发现隐患: ' . $remarks,
                    'reporter_name' => $user->real_name ?? '系统监控',
                    'status' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                 ]);
            }
            Db::commit();
            return json(['code' => 200, 'msg' => 'success']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '打卡数据归档失败: ' . $e->getMessage()]);
        }
    }

    public function records(Request $request) {
        $list = Db::table('patrol_records')->orderBy('id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }
}