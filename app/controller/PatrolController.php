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
                'point_name' => $locationName, // 同步补齐数据库必需的 point_name 字段
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
            // 1. 记录 PC 端的手动打卡流水
            Db::table('patrol_records')->insert([
                'point_id' => $request->post('point_id', 0),
                'location' => $location,
                'worker_name' => $user->real_name ?? '现场巡检员',
                'status' => $status,
                'remarks' => $remarks,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // 2. 若工况异常，自动生成告警工单并推送至调度中心
            if ($status == 0 || $status == 2) {
                // 【核心修复】：自动生成的告警工单也必须拥有初创的 process_log 生命周期！
                $processLog = [
                    [
                        'title' => '安全网格异常告警',
                        'operator' => $user->real_name ?? '系统自动监控',
                        'desc' => '防区巡检发现严重隐患: ' . $remarks,
                        'image' => '', // PC端打卡暂无传图
                        'time' => date('Y-m-d H:i:s')
                    ]
                ];

                Db::table('work_orders')->insert([
                    'tenant_id' => $user->tenant_id ?? 1,
                    'title' => '安全网格异常警报: ' . $location,
                    'description' => '巡检发现隐患: ' . $remarks,
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
        // 联表查询 patrol_points，获取精确的物理位置
        $list = Db::table('patrol_records as r')
            ->leftJoin('patrol_points as p', 'r.point_id', '=', 'p.id')
            ->select('r.*', 'p.location as p_location', 'p.point_name')
            ->orderBy('r.id', 'desc')
            ->get();
            
        $result = [];
        
        // 【核心修复】：彻底消除 PHP Collection 迭代器 &$item 的引用报错！
        // 采用安全的变量重组，并且同时兼容 Webman ORM 返回数组或对象的情况。
        foreach ($list as $item) {
            $isArr = is_array($item);
            
            // 兼容人员：H5 存的是 operator_name，PC 旧版是 worker_name
            $workerName = $isArr 
                ? ($item['worker_name'] ?? $item['operator_name'] ?? '未知外勤') 
                : ($item->worker_name ?? $item->operator_name ?? '未知外勤');
                
            // 兼容位置：H5 靠 point_id 联表查，PC 旧版直写 location
            $loc = $isArr 
                ? ($item['location'] ?? $item['p_location'] ?? $item['point_name'] ?? '未知防区') 
                : ($item->location ?? $item->p_location ?? $item->point_name ?? '未知防区');
                
            // 兼容备注：H5 叫 remark，PC 旧版叫 remarks
            $rem = $isArr 
                ? ($item['remarks'] ?? $item['remark'] ?? '') 
                : ($item->remarks ?? $item->remark ?? '');
                
            // 抹平新旧异常状态码 (统一给前端输出 1: 正常, 2: 异常)
            $stat = $isArr ? ($item['status'] ?? 1) : ($item->status ?? 1);
            if ($stat == 0) $stat = 2; 

            // 安全赋值，规避强引用
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