<?php
namespace app\controller;

use support\Request;
use support\Response;
use support\Db;

class SpaceController
{
    /**
     * 获取物理空间明细列表
     */
    public function getList(Request $request): Response
    {
        $tenantId = $request->tenant_id ?: 1;
        $list = Db::table('spaces')
            ->where('tenant_id', $tenantId)
            ->orderBy('building_id', 'asc')
            ->orderBy('floor', 'asc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 获取空间树状结构（适配热力图与级联选择）
     */
    public function getSpaceTree(Request $request): Response
    {
        $tenantId = $request->tenant_id ?: 1;
        $list = Db::table('spaces')->where('tenant_id', $tenantId)->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 录入新物理空间（房间/商铺）
     */
    public function addSpace(Request $request): Response
    {
        $tenantId = $request->tenant_id ?: 1;
        $post = $request->post();

        if (empty($post['building_id']) || empty($post['room_number'])) {
            return json(['code' => 400, 'msg' => '必须指定归属大厦与空间编号']);
        }

        $building = Db::table('buildings')->where('id', $post['building_id'])->first();

        Db::table('spaces')->insert([
            'tenant_id' => $tenantId,
            'building_id' => $post['building_id'],
            'building_name' => $building ? $building->name : '未知项目',
            'floor' => $post['floor'] ?? 1,
            'room_number' => $post['room_number'],
            'area' => $post['area'] ?? 0.00,
            'status' => $post['status'] ?? 0 // 默认空置
        ]);

        return json(['code' => 200, 'msg' => '物理空间资产录入成功']);
    }

    /**
     * 更新空间工况状态
     */
    public function updateStatus(Request $request): Response
    {
        $tenantId = $request->tenant_id ?: 1;
        $id = $request->post('id');
        $status = $request->post('status');

        Db::table('spaces')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update(['status' => $status]);

        return json(['code' => 200, 'msg' => '资产状态更新成功']);
    }

    /**
     * 占位：获取资产配置字典
     */
    public function getConfig(Request $request): Response
    {
        return json(['code' => 200, 'msg' => 'success', 'data' => []]);
    }

    /**
     * 占位：更新资产配置
     */
    public function updateConfig(Request $request): Response
    {
        return json(['code' => 200, 'msg' => 'success']);
    }
}