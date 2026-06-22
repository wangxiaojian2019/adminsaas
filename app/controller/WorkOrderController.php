<?php
namespace app\controller;

use support\Request;
use support\Db;

class WorkOrderController
{
    public function list(Request $request)
    {
        $list = Db::table('work_orders')
            ->leftJoin('admins', 'work_orders.handler_id', '=', 'admins.id')
            ->select('work_orders.*', 'admins.real_name as handler_name')
            ->orderBy('work_orders.priority', 'desc')
            ->orderBy('work_orders.id', 'desc')
            ->get();

        $now = time();
        
        foreach ($list as $item) {
            $item->is_timeout = false;
            $item->response_time_seconds = 0; 
            $item->resolve_time_seconds = 0;  

            // 提取创建时间，若数据异常则回退到当前时间避免 strtotime 返回 false
            $createdAtTime = $item->created_at ? (int)strtotime($item->created_at) : $now;

            if ($item->accepted_at) {
                $item->response_time_seconds = (int)strtotime($item->accepted_at) - $createdAtTime;
            }

            if ($item->resolved_at && $item->accepted_at) {
                $item->resolve_time_seconds = (int)strtotime($item->resolved_at) - (int)strtotime($item->accepted_at);
            }

            // SLA 引擎：动态计算超时违规标识
            if ($item->priority == 1) {
                if (!$item->accepted_at && ($now - $createdAtTime) > 15 * 60) {
                    $item->is_timeout = true;
                } elseif ($item->accepted_at && $item->response_time_seconds > 15 * 60) {
                    $item->is_timeout = true;
                }
            }
        }

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function add(Request $request)
    {
        $title = $request->post('title');
        $content = $request->post('content', '');
        $description = $request->post('description', '');
        $reporter_name = $request->post('reporter_name', '系统内部指派'); 
        $priority = intval($request->post('priority', 0));

        if (!$title) {
            return json(['code' => 400, 'msg' => '工单摘要不可为空']);
        }

        Db::table('work_orders')->insert([
            'title' => $title,
            'content' => $content,
            'description' => $description,
            'reporter_name' => $reporter_name,
            'priority' => $priority,
            'status' => 1, 
            'handler_id' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return json(['code' => 200, 'msg' => '工单下发成功，已进入派单池']);
    }

    public function action(Request $request)
    {
        $id = $request->post('id');
        $action = $request->post('action'); 
        
        $targetHandlerId = intval($request->post('handler_id', 0));
        if ($targetHandlerId === 0) {
            $admin = $request->user;
            $targetHandlerId = $admin ? (is_array($admin) ? ($admin['id'] ?? 0) : ($admin->id ?? 0)) : 0;
        }

        $order = Db::table('work_orders')->where('id', $id)->first();
        if (!$order) {
            return json(['code' => 404, 'msg' => '工单不存在']);
        }

        $updateData = [];
        // 分离字符串时间（入库用）与整型时间戳（计算用）
        $nowStr = date('Y-m-d H:i:s');
        $nowTime = time();
        $isNewAssign = false;

        if ($action === 'accept') {
            if ($order->status != 1) {
                return json(['code' => 400, 'msg' => '当前状态不符合接单或派单条件']);
            }
            $updateData['accepted_at'] = $nowStr;
            $updateData['status'] = 2; 
            $updateData['handler_id'] = $targetHandlerId; 
            $isNewAssign = true;
            
        } elseif ($action === 'arrive') {
            if ($order->status != 2) {
                return json(['code' => 400, 'msg' => '未接单或已结单，无法进行到场打卡']);
            }
            $updateData['arrived_at'] = $nowStr;
            
        } elseif ($action === 'resolve') {
            if ($order->status != 2) {
                return json(['code' => 400, 'msg' => '前置状态异常，不满足结单条件']);
            }
            $updateData['resolved_at'] = $nowStr;
            $updateData['status'] = 4; 
            
            $resultRemark = $request->post('result_remark');
            if ($resultRemark) {
                $updateData['description'] = $order->description . "\n[结单备注]: " . $resultRemark;
            }
        } else {
            return json(['code' => 400, 'msg' => '非法状态流转参数']);
        }

        Db::beginTransaction();
        try {
            Db::table('work_orders')->where('id', $id)->update($updateData);

            if ($isNewAssign && $targetHandlerId > 0) {
                Db::table('worker_notifications')->insert([
                    'worker_id' => $targetHandlerId,
                    'title' => '调度中心新任务派发',
                    'content' => "任务大厅有新下发的工单 [{$order->title}] 已指定由您负责，请前往现场处置并在 H5 完工打卡。",
                    'is_read' => 0,
                    'created_at' => $nowStr
                ]);
            }

            // 使用安全转换的整型时间进行数学相减，规避 A non-numeric value encountered
            $createdAtTime = $order->created_at ? (int)strtotime($order->created_at) : $nowTime;
            if ($action === 'accept' && $order->priority == 1) {
                if (($nowTime - $createdAtTime) > 15 * 60) {
                    Db::table('notifications')->insert([
                        'enterprise_id' => 0, 
                        'title' => '【SLA违规告警】紧急工单响应超时',
                        'content' => "工单 [{$order->title}] 已超出 15 分钟响应时效，目前已由外勤人员接单，请后勤主管介入复盘效能。",
                        'is_read' => 0,
                        'created_at' => $nowStr
                    ]);
                }
            }

            Db::commit();
            return json(['code' => 200, 'msg' => '任务状态流转及通知派发成功']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '工单引擎处理失败: ' . $e->getMessage()]);
        }
    }
}