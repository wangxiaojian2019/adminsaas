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
            'process_log' => json_encode($processLog, JSON_UNESCAPED_UNICODE),
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
        // 提取所有网格配置点位
        $points = Db::table('patrol_points')->select('id', 'tenant_id', 'point_name as name', 'location', 'created_at', 'task_type', 'frequency', 'time_slots')->get();
        
        $result = [];
        $nowTime = time();
        $todayDate = date('Y-m-d', $nowTime);
        $h_i = date('H:i', $nowTime);

        foreach ($points as $item) {
            $point = is_array($item) ? $item : (array)$item;
            $timeSlots = json_decode($point['time_slots'] ?? '[]', true) ?: [];
            $freq = $point['frequency'] ?? 'daily';
            
            // 默认属性
            $point['deadline_str'] = '';
            $point['is_actionable'] = true; 
            
            if ($freq === 'monthly') {
                $startTime = date('Y-m-01 00:00:00', $nowTime);
                $endTime = date('Y-m-t 23:59:59', $nowTime);
                $exists = Db::table('patrol_records')->where('point_id', $point['id'])->whereBetween('created_at', [$startTime, $endTime])->first();
                if (!$exists) {
                    $point['deadline_str'] = date('n月t日 23:59', $nowTime) . ' 截止';
                    $result[] = $point;
                }
            } elseif ($freq === 'weekly') {
                $startTime = date('Y-m-d 00:00:00', strtotime('monday this week', $nowTime));
                $endTime = date('Y-m-d 23:59:59', strtotime('sunday this week', $nowTime));
                $exists = Db::table('patrol_records')->where('point_id', $point['id'])->whereBetween('created_at', [$startTime, $endTime])->first();
                if (!$exists) {
                    $point['deadline_str'] = '本周日 23:59 截止';
                    $result[] = $point;
                }
            } else { 
                // 每日巡检 (支持多时段)
                if (!empty($timeSlots)) {
                    $activeOrNextSlot = null;
                    $isCurrent = false;
                    foreach ($timeSlots as $slot) {
                        if (!isset($slot['start']) || !isset($slot['end'])) continue;
                        
                        $startTime = $todayDate . ' ' . $slot['start'] . ':00';
                        $endTime = $todayDate . ' ' . $slot['end'] . ':59';
                        
                        $exists = Db::table('patrol_records')->where('point_id', $point['id'])->whereBetween('created_at', [$startTime, $endTime])->first();
                        
                        if (!$exists) {
                            // 只要当前时间还未超过该时段的结束时间，就锁定此待办时段
                            if ($h_i <= $slot['end']) {
                                $activeOrNextSlot = $slot;
                                if ($h_i >= $slot['start']) {
                                    $isCurrent = true; // 当前正是打卡时间内
                                }
                                break; 
                            }
                        }
                    }
                    
                    if ($activeOrNextSlot) {
                        if ($isCurrent) {
                            // 当前在时段内，显示截止时间红字
                            $point['deadline_str'] = '今日 ' . $activeOrNextSlot['end'] . ' 前必达';
                            $point['is_actionable'] = true;
                        } else {
                            // 时段还未开始，显示即将开始时间灰字，锁定打卡按钮
                            $point['deadline_str'] = '今日 ' . $activeOrNextSlot['start'] . ' 开启打卡';
                            $point['is_actionable'] = false; 
                        }
                        $result[] = $point;
                    }
                } else {
                    $startTime = $todayDate . ' 00:00:00';
                    $endTime = $todayDate . ' 23:59:59';
                    $exists = Db::table('patrol_records')->where('point_id', $point['id'])->whereBetween('created_at', [$startTime, $endTime])->first();
                    if (!$exists) {
                        $point['deadline_str'] = '今日 23:59 前必达';
                        $point['is_actionable'] = true;
                        $result[] = $point;
                    }
                }
            }
        }

        // 高级排序：当前可打卡的排在最前面，并按时间先后倒排
        usort($result, function($a, $b) {
            if ($a['is_actionable'] == $b['is_actionable']) {
                return strcmp($a['deadline_str'], $b['deadline_str']);
            }
            return $a['is_actionable'] ? -1 : 1;
        });

        return json(['code' => 200, 'data' => $result]);
    }

    public function submitPatrol(Request $request)
    {
        $workerId = $this->getWorkerId($request);
        if (!$workerId) return json(['code' => 401, 'msg' => '未授权']);

        $worker = Db::table('admins')->where('id', $workerId)->first();
        $pointId = $request->post('point_id', 0);
        
        $point = Db::table('patrol_points')->where('id', $pointId)->first();
        $location = $point ? (is_array($point) ? $point['location'] : $point->location) : '未知防区';

        // 二次拦截防止网络卡顿导致的重复提交或越权时段提交
        if ($pointId > 0 && $point) {
            $pointArr = is_array($point) ? $point : (array)$point;
            $timeSlots = json_decode($pointArr['time_slots'] ?? '[]', true) ?: [];
            $freq = $pointArr['frequency'] ?? 'daily';
            
            $startTime = null;
            $endTime = null;
            $nowTime = time();
            
            if ($freq === 'monthly') {
                $startTime = date('Y-m-01 00:00:00', $nowTime);
                $endTime = date('Y-m-t 23:59:59', $nowTime);
            } elseif ($freq === 'weekly') {
                $startTime = date('Y-m-d 00:00:00', strtotime('monday this week', $nowTime));
                $endTime = date('Y-m-d 23:59:59', strtotime('sunday this week', $nowTime));
            } else {
                $h_i = date('H:i', $nowTime);
                $todayDate = date('Y-m-d', $nowTime);
                if (!empty($timeSlots)) {
                    foreach ($timeSlots as $slot) {
                        if (isset($slot['start']) && isset($slot['end']) && $h_i >= $slot['start'] && $h_i <= $slot['end']) {
                            $startTime = $todayDate . ' ' . $slot['start'] . ':00';
                            $endTime = $todayDate . ' ' . $slot['end'] . ':59';
                            break;
                        }
                    }
                } else {
                    $startTime = $todayDate . ' 00:00:00';
                    $endTime = $todayDate . ' 23:59:59';
                }
            }

            if ($startTime && $endTime) {
                $exists = Db::table('patrol_records')
                    ->where('point_id', $pointId)
                    ->whereBetween('created_at', [$startTime, $endTime])
                    ->first();
                    
                if ($exists) {
                    return json(['code' => 400, 'msg' => "打卡失败，该区域时段内已完成巡查，请勿重复操作"]);
                }
            } else {
                 return json(['code' => 400, 'msg' => "当前不在允许巡逻打卡的时间范围内"]);
            }
        }

        Db::beginTransaction();
        try {
            Db::table('patrol_records')->insert([
                'worker_id' => $workerId,
                'point_id' => $pointId,
                'location' => $location, 
                'worker_name' => $worker->real_name ?? '未知外勤',
                'operator_name' => $worker->real_name ?? '未知外勤', 
                'image_url' => $request->post('image_url', ''),
                'remark' => $request->post('remark', ''),
                'remarks' => $request->post('remark', ''), 
                'status' => $request->post('status', 1), 
                'is_normal' => $request->post('status', 1) == 1 ? 1 : 0, 
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($request->post('status', 1) == 2) {
                $remarks = $request->post('remark', '未填写原因');
                $processLog = [
                    [
                        'title' => '安全网格异常告警',
                        'operator' => $worker->real_name ?? '巡查系统',
                        'desc' => '防区现场实勘发现隐患: ' . $remarks,
                        'image' => $request->post('image_url', ''), 
                        'time' => date('Y-m-d H:i:s')
                    ]
                ];

                Db::table('work_orders')->insert([
                    'tenant_id' => $worker->tenant_id ?? 1,
                    'title' => '巡检网格异常警报: ' . $location,
                    'description' => '实勘发现隐患: ' . $remarks,
                    'reporter_name' => $worker->real_name ?? '前线监控',
                    'report_image_url' => $request->post('image_url', ''),
                    'status' => 1,
                    'process_log' => json_encode($processLog, JSON_UNESCAPED_UNICODE),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            Db::commit();
            return json(['code' => 200, 'msg' => '防区现场打卡成功']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '打卡失败：' . $e->getMessage()]);
        }
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