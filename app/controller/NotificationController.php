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
        // 核心修复：直接读取由 AuthMiddleware 拦截并解码后注入的安全属性，彻底防伪造
        $enterpriseId = $request->enterprise_id ?? 0;

        if (empty($enterpriseId)) {
            return json(['code' => 401, 'msg' => '缺少租户身份，拒绝访问', 'data' => []]);
        }

        $list = Db::table('notifications')
            ->where('enterprise_id', $enterpriseId)
            ->orderBy('is_read', 'asc')
            ->orderBy('id', 'desc')
            ->limit(30)
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 消除未读红点（标记已读）
     */
    public function read(Request $request)
    {
        $id = $request->post('id');
        
        if ($id) {
            Db::table('notifications')->where('id', $id)->update(['is_read' => 1]);
        }
        
        return json(['code' => 200, 'msg' => '状态已同步']);
    }
}