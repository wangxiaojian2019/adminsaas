<?php
namespace app\controller;

use support\Request;
use support\Response;
use support\Db;

class BuildingController
{
    /**
     * 获取大厦项目列表
     */
    public function getList(Request $request): Response
    {
        $list = Db::table('buildings')->orderBy('id', 'asc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 设立新大厦/项目
     */
    public function add(Request $request): Response
    {
        $post = $request->post();
        
        if (empty($post['name'])) {
            return json(['code' => 400, 'msg' => '大厦/项目名称不可为空']);
        }

        Db::table('buildings')->insert([
            'name' => $post['name'],
            'property_type' => $post['property_type'] ?? 1,
            'total_floors' => $post['total_floors'] ?? 1,
            'building_area' => $post['building_area'] ?? 0.00,
            'manager_name' => $post['manager_name'] ?? ''
        ]);

        return json(['code' => 200, 'msg' => '资产项目设立成功']);
    }
}