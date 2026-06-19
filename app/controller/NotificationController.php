<?php
namespace app\controller;

use support\Request;
use support\Db;

class NotificationController
{
    /**
     * 轮询拉取当前租户的消息队列
     */
    public function list(Request $request)
    {
        // 核心修复：精准捕获前端通过 params 传递的 GET 参数
        $enterpriseId = $request->get('enterprise_id') ?? $request->header('X-Enterprise-Id', 0);

        if (empty($enterpriseId)) {
            return json(['code' => 401, 'msg' => '缺少租户标识，无法拉取隔离消息', 'data' => []]);
        }

        $list = Db::table('notifications')
            ->where('enterprise_id', $enterpriseId)
            ->orderBy('is_read', 'asc') // 未读红点优先
            ->orderBy('id', 'desc')     // 最新时间优先
            ->limit(30)                 // 控制通信体积
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 消除未读红点（标记已读）
     */
    public function read(Request $request)
    {
        // 兼容 JSON Payload 和传统 POST 表单
        $id = $request->post('id');
        
        if ($id) {
            Db::table('notifications')->where('id', $id)->update(['is_read' => 1]);
        }
        
        return json(['code' => 200, 'msg' => '状态已同步']);
    }
}