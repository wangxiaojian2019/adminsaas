<?php
namespace app\controller;

use support\Request;
use support\Db;

class WorkOrderController
{
    public function list(Request $request)
    {
        $list = Db::table('work_orders')
            ->leftJoin('admins', 'work_orders.worker_id', '=', 'admins.id')
            ->select('work_orders.*', 'admins.real_name as worker_name')
            ->orderBy('work_orders.priority', 'desc')
            ->orderBy('work_orders.id', 'desc')
            ->get();

        $now = time();
        // SLA 引擎：动态计算超时违规标识
        foreach ($list as &$item) {
            $item->is_timeout = false;
            // 规则：P0 紧急工单，创建后 15 分钟未接单即判定为 SLA 违规
            if ($item->priority == 1 && in_array($item->status, [1, 2]) && !$item->accepted_at) {
                if (($now - strtotime($item->created_at)) > 15 * 60) {
                    $item->is_timeout = true;
                }
            }
        }

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function add(Request $request)
    {
        $title = $request->post('title');
        $content = $request->post('content');
        $priority = intval($request->post('priority', 0));

        if (!$title) {
            return json(['code' => 400, 'msg' => '工单摘要不可为空']);
        }

        Db::table('work_orders')->insert([
            'title' => $title,
            'content' => $content,
            'priority' => $priority,
            'status' => 1, 
            'worker_id' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => '工单下发成功，已进入派单池']);
    }

    public function action(Request $request)
    {
        $id = $request->post('id');
        $action = $request->post('action'); 
        $admin = $request->user;

        $order = Db::table('work_orders')->where('id', $id)->first();
        if (!$order) {
            return json(['code' => 404, 'msg' => '工单不存在']);
        }

        $updateData = [];
        $now = date('Y-m-d H:i:s');

        // 状态机与 SLA 时间戳记录
        if ($action === 'accept') {
            $updateData['accepted_at'] = $now;
            $updateData['status'] = 3; 
            // 若为主动抢单，则绑定当前操作人
            $updateData['worker_id'] = $order->worker_id ?: ($admin->id ?? 0);
        } elseif ($action === 'arrive') {
            $updateData['arrived_at'] = $now;
        } elseif ($action === 'resolve') {
            $updateData['resolved_at'] = $now;
            $updateData['status'] = 4; 
            $updateData['result_remark'] = $request->post('result_remark', '');
        } else {
            return json(['code' => 400, 'msg' => '非法状态流转']);
        }

        Db::beginTransaction();
        try {
            Db::table('work_orders')->where('id', $id)->update($updateData);

            // SLA 告警：如果是 P0 工单且发生接单超时，记录系统级审计日志
            if ($action === 'accept' && $order->priority == 1) {
                if (($now - strtotime($order->created_at)) > 15 * 60) {
                    Db::table('notifications')->insert([
                        'enterprise_id' => 0,
                        'title' => '【SLA违规告警】紧急工单响应超时',
                        'content' => "工单 [{$order->title}] 已超出 15 分钟响应时效，当前已由相关人员接单，请后勤主管介入复盘效能。",
                        'is_read' => 0,
                        'created_at' => $now
                    ]);
                }
            }

            Db::commit();
            return json(['code' => 200, 'msg' => 'SLA 节点打卡成功']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '工单引擎处理失败']);
        }
    }
}