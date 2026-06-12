<?php
namespace app\controller;

use support\Request;
use support\Db;

class SystemController
{
    public function roleList(Request $request)
    {
        $roles = Db::table('roles')->orderBy('id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $roles]);
    }

    public function roleAdd(Request $request)
    {
        $data = [
            'role_name' => $request->post('role_name'),
            'data_scope' => $request->post('data_scope', 1),
            'created_at' => date('Y-m-d H:i:s')
        ];
        Db::table('roles')->insert($data);
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function adminList(Request $request)
    {
        $admins = Db::table('admins')->orderBy('id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $admins]);
    }

    public function adminAdd(Request $request)
    {
        $data = [
            'username' => $request->post('username'),
            'password' => md5($request->post('password')),
            'real_name' => $request->post('real_name'),
            'phone' => $request->post('phone', ''),
            'company_name' => '高新科技产业园',
            'role_id' => 2,
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        Db::table('admins')->insert($data);
        return json(['code' => 200, 'msg' => 'success']);
    }
}