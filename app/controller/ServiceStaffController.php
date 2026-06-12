<?php
namespace app\controller;

use support\Request;
use support\Db;

class ServiceStaffController
{
    public function list(Request $request)
    {
        $list = Db::table('admins')
            ->where('role_id', 4)
            ->orderBy('id', 'desc')
            ->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function add(Request $request)
    {
        $phone = $request->post('phone');
        $exists = Db::table('admins')->where('username', $phone)->first();
        if ($exists) return json(['code' => 400, 'msg' => '该手机号已注册，请勿重复添加']);

        $data = [
            'username' => $phone,
            'password' => md5($request->post('password')),
            'real_name' => $request->post('real_name'),
            'phone' => $phone,
            'company_name' => '高新科技产业园',
            'role_id' => 4,
            'status' => $request->post('status', 1),
            'position' => $request->post('position'),
            'responsibility' => $request->post('responsibility'),
            'created_at' => date('Y-m-d H:i:s')
        ];
        Db::table('admins')->insert($data);
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function update(Request $request)
    {
        $id = $request->post('id');
        $data = [
            'real_name' => $request->post('real_name'),
            'position' => $request->post('position'),
            'responsibility' => $request->post('responsibility'),
            'status' => $request->post('status', 1)
        ];
        
        $pwd = $request->post('password');
        if (!empty($pwd)) {
            $data['password'] = md5($pwd);
        }

        Db::table('admins')->where('id', $id)->update($data);
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function delete(Request $request)
    {
        $id = $request->post('id');
        Db::table('admins')->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => 'success']);
    }
}