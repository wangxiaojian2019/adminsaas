<?php
namespace app\controller;

use support\Request;
use support\Db;

class SpaceController
{
    public function list(Request $request)
    {
        $list = Db::table('spaces')->orderBy('building_name')->orderBy('floor')->orderBy('room_number')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function add(Request $request)
    {
        Db::table('spaces')->insert([
            'building_name' => $request->post('building_name'),
            'floor' => $request->post('floor'),
            'room_number' => $request->post('room_number'),
            'area' => $request->post('area'),
            'status' => 0, // 新录入默认空置
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function update(Request $request)
    {
        $id = $request->post('id');
        $data = [
            'room_number' => $request->post('room_number'),
            'area' => $request->post('area')
        ];
        
        // 核心修改：接收前端传来的物理工况状态
        if ($request->post('status') !== null) {
            $data['status'] = $request->post('status');
        }

        Db::table('spaces')->where('id', $id)->update($data);
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function delete(Request $request)
    {
        Db::table('spaces')->where('id', $request->post('id'))->delete();
        return json(['code' => 200, 'msg' => 'success']);
    }

    // 快捷工况流转专用接口
    public function updateStatus(Request $request)
    {
        $id = $request->post('id');
        $status = $request->post('status');
        Db::table('spaces')->where('id', $id)->update(['status' => $status]);
        return json(['code' => 200, 'msg' => 'success']);
    }
    
    // 预留前端级联选择器树状接口
    public function tree(Request $request) {
        return json(['code' => 200, 'msg' => 'success', 'data' => []]);
    }
}