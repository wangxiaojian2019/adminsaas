<?php
namespace app\controller;

use support\Request;
use support\Db;

class PatrolController
{
    public function pointList(Request $request) {
        $list = Db::table('patrol_points')->orderBy('id', 'desc')->get();
        $result = [];

        foreach ($list as $item) {
            $point = is_array($item) ? $item : (array)$item;
            
            $timeSlots = json_decode($point['time_slots'] ?? '[]', true) ?: [];
            $point['time_slots'] = $timeSlots;
            
            $point['current_status'] = 'pending'; 
            $point['checked_by'] = '';
            
            $freq = $point['frequency'] ?? 'daily';
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
                $existsRecord = Db::table('patrol_records')
                    ->where('point_id', $point['id'])
                    ->whereBetween('created_at', [$startTime, $endTime])
                    ->first();
                    
                if ($existsRecord) {
                    $point['current_status'] = 'already_checked';
                    $point['checked_by'] = is_array($existsRecord) 
                        ? (!empty($existsRecord['worker_name']) ? $existsRecord['worker_name'] : ($existsRecord['operator_name'] ?? '同事')) 
                        : (!empty($existsRecord->worker_name) ? $existsRecord->worker_name : ($existsRecord->operator_name ?? '同事'));
                }
            }

            $history = Db::table('patrol_records')
                ->where('point_id', $point['id'])
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get();
                
            // 【核心修复5】抹平嵌套历史里的残缺新旧字段，找回刚刚测试产生的老数据！
            $formattedHistory = [];
            foreach ($history as $h) {
                $hArr = is_array($h) ? $h : (array)$h;
                $hArr['worker_name'] = !empty($hArr['worker_name']) ? $hArr['worker_name'] : ($hArr['operator_name'] ?? '未知外勤');
                $hArr['remarks'] = !empty($hArr['remarks']) ? $hArr['remarks'] : ($hArr['remark'] ?? '');
                $formattedHistory[] = $hArr;
            }
            $point['historyRecords'] = $formattedHistory;

            $result[] = $point;
        }

        return json(['code' => 200, 'msg' => 'success', 'data' => $result]);
    }
    
    // [新增点位]
    public function pointAdd(Request $request) {
        try {
            $locationName = $request->post('location');
            $taskType = $request->post('task_type', 'security');
            $frequency = $request->post('frequency', 'daily');
            $timeSlots = $request->post('time_slots', []);

            Db::table('patrol_points')->insert([
                'location' => $locationName,
                'point_name' => $locationName,
                'task_type' => $taskType,
                'frequency' => $frequency,
                'time_slots' => json_encode($timeSlots, JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            return json(['code' => 200, 'msg' => 'success']);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => '点位设立失败，底层原因: ' . $e->getMessage()]);
        }
    }

    // [二次编辑/更新点位]
    public function pointUpdate(Request $request) {
        try {
            $id = $request->post('id');
            if (!$id) return json(['code' => 400, 'msg' => '缺少配置参数ID']);

            $locationName = $request->post('location');
            $taskType = $request->post('task_type', 'security');
            $frequency = $request->post('frequency', 'daily');
            $timeSlots = $request->post('time_slots', []);

            Db::table('patrol_points')->where('id', $id)->update([
                'location' => $locationName,
                'point_name' => $locationName,
                'task_type' => $taskType,
                'frequency' => $frequency,
                'time_slots' => json_encode($timeSlots, JSON_UNESCAPED_UNICODE)
            ]);
            return json(['code' => 200, 'msg' => '点位配置修改成功']);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => '点位修改失败: ' . $e->getMessage()]);
        }
    }

    // [删除点位]
    public function pointDelete(Request $request) {
        try {
            $id = $request->post('id');
            if (!$id) return json(['code' => 400, 'msg' => '缺少需要删除的记录ID']);
            
            Db::table('patrol_points')->where('id', $id)->delete();
            return json(['code' => 200, 'msg' => '点位已成功删除']);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => '点位删除失败: ' . $e->getMessage()]);
        }
    }

    public function checkin(Request $request) {
        $user = $request->user;
        $status = $request->post('status', 1);
        $location = $request->post('location');
        $remarks = $request->post('remarks', '');
        
        $pointId = $request->post('point_id', 0);

        if (!$pointId && !empty($location)) {
            $pointInfo = Db::table('patrol_points')->where('location', $location)->first();
            if ($pointInfo) {
                $pointId = is_array($pointInfo) ? $pointInfo['id'] : $pointInfo->id;
            }
        }

        if ($pointId > 0) {
            $pointInfo = Db::table('patrol_points')->where('id', $pointId)->first();
            if ($pointInfo) {
                $pointArr = is_array($pointInfo) ? $pointInfo : (array)$pointInfo;
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
                        $worker = is_array($exists) ? ($exists['worker_name'] ?? '') : ($exists->worker_name ?? '');
                        return json(['code' => 400, 'msg' => "当前防区在本周期({$freq})内已有人员({$worker})完成巡查，无需重复打卡"]);
                    }
                }
            }
        }

        Db::beginTransaction();
        try {
            Db::table('patrol_records')->insert([
                'point_id' => $pointId,
                'location' => $location,
                'worker_name' => $user->real_name ?? '现场巡检员',
                'status' => $status,
                'remarks' => $remarks,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($status == 0 || $status == 2) {
                $processLog = [
                    [
                        'title' => '安全网格异常告警',
                        'operator' => $user->real_name ?? '系统自动监控',
                        'desc' => '防区巡检发现严重隐患: ' . $remarks,
                        'image' => '', 
                        'time' => date('Y-m-d H:i:s')
                    ]
                ];

                Db::table('work_orders')->insert([
                    'tenant_id' => $user->tenant_id ?? 1,
                    'title' => '巡检网格异常警报: ' . $location,
                    'description' => '打卡发现隐患: ' . $remarks,
                    'reporter_name' => $user->real_name ?? '系统监控',
                    'status' => 1,
                    'process_log' => json_encode($processLog, JSON_UNESCAPED_UNICODE),
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
        $list = Db::table('patrol_records as r')
            ->leftJoin('patrol_points as p', 'r.point_id', '=', 'p.id')
            ->select('r.*', 'p.location as p_location', 'p.point_name')
            ->orderBy('r.id', 'desc')
            ->get();
            
        $result = [];
        foreach ($list as $item) {
            $isArr = is_array($item);
            $workerName = $isArr ? ($item['worker_name'] ?? $item['operator_name'] ?? '未知外勤') : ($item->worker_name ?? $item->operator_name ?? '未知外勤');
            $loc = $isArr ? ($item['location'] ?? $item['p_location'] ?? $item['point_name'] ?? '未知防区') : ($item->location ?? $item->p_location ?? $item->point_name ?? '未知防区');
            $rem = $isArr ? ($item['remarks'] ?? $item['remark'] ?? '') : ($item->remarks ?? $item->remark ?? '');
            $stat = $isArr ? ($item['status'] ?? 1) : ($item->status ?? 1);
            if ($stat == 0) $stat = 2; 

            if ($isArr) {
                $item['worker_name'] = $workerName ?: '未知外勤';
                $item['location'] = $loc ?: '未知防区';
                $item['remarks'] = $rem;
                $item['status'] = $stat;
            } else {
                $item->worker_name = $workerName ?: '未知外勤';
                $item->location = $loc ?: '未知防区';
                $item->remarks = $rem;
                $item->status = $stat;
            }
            $result[] = $item;
        }

        return json(['code' => 200, 'msg' => 'success', 'data' => $result]);
    }
}