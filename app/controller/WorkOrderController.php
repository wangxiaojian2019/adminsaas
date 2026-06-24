<?php
namespace app\controller;

use support\Request;
use support\Db;

class WorkOrderController
{
    // 定义全局 SLA 熔断标准 (秒)
    const SLA_ACCEPT_LIMIT = 15 * 60; // 15分钟不接单即熔断
    const SLA_ARRIVE_LIMIT = 30 * 60; // 30分钟不到场即熔断

    public function list(Request $request)
    {
        $tenantId = $request->tenantId ?? 1;
        
        $list = Db::table('work_orders as w')
            ->where('w.tenant_id', $tenantId)
            ->leftJoin('admins as a', 'w.handler_id', '=', 'a.id')
            ->select('w.*', 'a.real_name as handler_name')
            ->orderBy('w.priority', 'desc')
            ->orderBy('w.id', 'desc')
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function add(Request $request)
    {
        Db::table('work_orders')->insert([
            'tenant_id' => $request->tenantId ?? 1,
            'title' => $request->post('title'),
            'description' => $request->post('description', ''),
            'reporter_name' => $request->post('reporter_name', '系统内部上报'),
            'priority' => intval($request->post('priority', 0)),
            'status' => 1, // 1待指派
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => '工单已抛入公共调度池']);
    }

    /**
     * 核心动作流转与 SLA 拦截计算引擎
     */
    public function action(Request $request)
    {
        $id = $request->post('id');
        $action = $request->post('action'); // assign, accept, arrive, resolve
        $workerId = $request->post('worker_id'); 
        $content = $request->post('content', ''); // 完工结单备注或消耗说明

        $order = Db::table('work_orders')->where('id', $id)->first();
        if (!$order) return json(['code' => 404, 'msg' => '工单迷失']);

        $now = time();
        $nowStr = date('Y-m-d H:i:s', $now);
        $updateData = ['updated_at' => $nowStr];
        $slaBreached = $order->sla_breached;

        switch ($action) {
            case 'assign':
            case 'accept':
                // 流转至: 处理中 (记录接单时间，并计算接单 SLA)
                if ($order->status >= 2) return json(['code' => 400, 'msg' => '逻辑互斥：工单已被接手']);
                $updateData['status'] = 2;
                $updateData['accepted_at'] = $nowStr;
                $updateData['handler_id'] = $workerId ?? ($request->user->id ?? 0);
                
                // SLA 计算: 创建时间与当前接单时间的差值
                if (($now - strtotime($order->created_at)) > self::SLA_ACCEPT_LIMIT) {
                    $slaBreached = 1; // 1: 接单超时
                }
                break;

            case 'arrive':
                // 流转至: 已到场勘察
                $updateData['arrived_at'] = $nowStr;
                // SLA 计算: 接单时间与当前到场时间的差值
                if ($order->accepted_at && ($now - strtotime($order->accepted_at)) > self::SLA_ARRIVE_LIMIT) {
                    $slaBreached = 2; // 2: 到场超时
                }
                break;

            case 'resolve':
                // 流转至: 完工结单
                $updateData['status'] = 4;
                $updateData['resolved_at'] = $nowStr;
                if ($content) {
                    $updateData['content'] = $content;
                }
                break;

            default:
                return json(['code' => 400, 'msg' => '未知动作字典']);
        }

        $updateData['sla_breached'] = $slaBreached;

        Db::table('work_orders')->where('id', $id)->update($updateData);

        $msgMap = [
            'assign' => '指令已派发并锁定负责人',
            'accept' => '接单成功，请尽快前往现场',
            'arrive' => '地理防区打卡成功，请开始作业',
            'resolve' => '故障已排除，工单流转闭环完成'
        ];

        $responseMsg = $msgMap[$action] ?? '状态流转成功';
        if ($slaBreached > 0) {
            $responseMsg .= " (警告：该节点已触发 SLA 违规预警)";
        }

        return json(['code' => 200, 'msg' => $responseMsg]);
    }
}