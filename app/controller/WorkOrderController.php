<?php
namespace app\controller;

use support\Request;
use support\Db;

class WorkOrderController
{
    const SLA_ACCEPT_LIMIT = 15 * 60; 
    const SLA_ARRIVE_LIMIT = 30 * 60; 

    public function list(Request $request)
    {
        $tenantId = $request->tenantId ?? 1;
        $list = Db::table('work_orders as w')
            ->where('w.tenant_id', $tenantId)
            ->leftJoin('admins as a', 'w.handler_id', '=', 'a.id')
            ->select('w.*', 'a.real_name as handler_name')
            ->orderBy('w.priority', 'desc')->orderBy('w.id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function add(Request $request)
    {
        $processLog = [
            [
                'title' => '工单系统创建',
                'operator' => $request->post('reporter_name', '中控室调度员'),
                'desc' => $request->post('description', '无补充说明'),
                'image' => $request->post('report_image_url', ''),
                'time' => date('Y-m-d H:i:s')
            ]
        ];

        Db::table('work_orders')->insert([
            'tenant_id' => $request->tenantId ?? 1,
            'title' => $request->post('title'),
            'description' => $request->post('description', ''),
            'reporter_name' => $request->post('reporter_name', '系统内部上报'),
            'priority' => intval($request->post('priority', 0)),
            'report_image_url' => $request->post('report_image_url', ''), 
            'status' => 1, 
            'process_log' => json_encode($processLog, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => '工单已抛入公共调度池']);
    }

    public function action(Request $request)
    {
        $id = $request->post('id');
        $action = $request->post('action'); 
        $workerId = $request->post('worker_id'); 
        $content = $request->post('content', ''); 
        $resolveImageUrl = $request->post('resolve_image_url', ''); 

        $order = Db::table('work_orders')->where('id', $id)->first();
        if (!$order) return json(['code' => 404, 'msg' => '工单迷失']);

        $now = time();
        $nowStr = date('Y-m-d H:i:s', $now);
        $updateData = []; 
        $slaBreached = $order->sla_breached ?? 0; 
        
        // 解析现有的生命周期轨迹
        $processLog = json_decode($order->process_log ?? '[]', true) ?: [];
        $logEntry = ['time' => $nowStr, 'operator' => '系统']; // 初始化节点

        switch ($action) {
            case 'assign':
                if ($order->status >= 2) return json(['code' => 400, 'msg' => '工单已在处理中']);
                $updateData['status'] = 2; $updateData['assigned_at'] = $nowStr; $updateData['handler_id'] = $workerId;
                
                $assignedWorker = clone Db::table('admins')->where('id', $workerId)->first();
                $logEntry['title'] = '调度室派单';
                $logEntry['operator'] = '中控室';
                $logEntry['desc'] = '明确指派给外勤：' . ($assignedWorker->real_name ?? '未知员工');
                break;

            case 'accept':
                if ($order->status >= 3) return json(['code' => 400, 'msg' => '工单已完工']);
                $updateData['status'] = 2; $updateData['accepted_at'] = $nowStr;
                $updateData['handler_id'] = $workerId ?? ($request->user->id ?? $order->handler_id);
                if (($now - strtotime($order->created_at)) > self::SLA_ACCEPT_LIMIT) $slaBreached = 1;
                
                $logEntry['title'] = '确认接单';
                $logEntry['operator'] = '外勤人员';
                $logEntry['desc'] = '已接收指令，准备前往现场';
                break;

            case 'resolve':
                if (!$resolveImageUrl) return json(['code' => 400, 'msg' => '必须上传现场照片']);
                $updateData['status'] = 3; $updateData['resolved_at'] = $nowStr; $updateData['resolve_image_url'] = $resolveImageUrl; 
                if ($content) $updateData['content'] = $content;
                
                $logEntry['title'] = '处理完毕提交验收';
                $logEntry['operator'] = '外勤人员';
                $logEntry['desc'] = '已上传完工凭证。备注：' . ($content ?: '无');
                $logEntry['image'] = $resolveImageUrl;
                break;

            case 'audit_pass':
                if ($order->status != 3) return json(['code' => 400, 'msg' => '状态错误']);
                $updateData['status'] = 4; 
                
                $logEntry['title'] = '验收通过';
                $logEntry['operator'] = '中控室';
                $logEntry['desc'] = '照片核实无误，工单正式归档闭环';
                break;

            case 'audit_reject':
                if ($order->status != 3) return json(['code' => 400, 'msg' => '状态错误']);
                $updateData['status'] = 2; $updateData['resolved_at'] = null; $updateData['resolve_image_url'] = ''; 
                if ($content) $updateData['content'] = ltrim(($order->content ?? '') . ' | [驳回]: ' . $content, ' | ');
                
                $logEntry['title'] = '验收被驳回';
                $logEntry['operator'] = '中控室';
                $logEntry['desc'] = '作业不合格，责令重做！理由：' . $content;
                break;

            default: return json(['code' => 400, 'msg' => '未知动作字典']);
        }

        $updateData['sla_breached'] = $slaBreached;
        
        // 压入最新的轨迹节点并保存
        $processLog[] = $logEntry;
        $updateData['process_log'] = json_encode($processLog, JSON_UNESCAPED_UNICODE);

        if (!empty($updateData)) {
            Db::table('work_orders')->where('id', $id)->update($updateData);

            // 触发消息铃铛
            if ($action === 'assign' && $workerId) {
                Db::table('worker_notifications')->insert(['worker_id' => $workerId, 'title' => '新任务派发', 'content' => "工单 [{$order->title}]，请查阅现场照片并前往处置。", 'created_at' => $nowStr]);
            } elseif ($action === 'audit_reject') {
                Db::table('worker_notifications')->insert(['worker_id' => $order->handler_id, 'title' => '验收被驳回重做', 'content' => "工单 [{$order->title}] 验收未通过，驳回理由：{$content}。", 'created_at' => $nowStr]);
            } elseif ($action === 'audit_pass') {
                 Db::table('worker_notifications')->insert(['worker_id' => $order->handler_id, 'title' => '验收通过结案', 'content' => "工单 [{$order->title}] 已核实闭环。", 'created_at' => $nowStr]);
            }
        }

        return json(['code' => 200, 'msg' => '状态流转成功']);
    }
}