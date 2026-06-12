<?php
namespace app\controller;

use support\Request;
use support\Db;

class WorkOrderController
{
    public function list(Request $request) {
        $list = Db::table('work_orders')->orderBy('id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function assign(Request $request) {
        Db::table('work_orders')->where('id', $request->post('id'))->update([
            'handler_id' => $request->post('handler_id'),
            'status' => 2 // 流转为：处理中
        ]);
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function complete(Request $request) {
        Db::table('work_orders')->where('id', $request->post('id'))->update([
            'status' => 3 // 流转为：已完工，待中控室验收
        ]);
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function verify(Request $request) {
        Db::table('work_orders')->where('id', $request->post('id'))->update([
            'status' => 4 // 流转为：已验收结案
        ]);
        return json(['code' => 200, 'msg' => 'success']);
    }
}