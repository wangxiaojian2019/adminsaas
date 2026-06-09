<?php
namespace app\controller;

use support\Request;
use support\Response;
use support\Db;

class SystemController
{
    /**
     * 获取当前租户下的所有角色
     */
    public function getRoles(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $list = Db::table('sys_roles')
            ->where('tenant_id', $tenantId)
            ->orderBy('id', 'asc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 新增业务角色
     */
    public function addRole(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $roleName = $request->post('role_name');
        $dataScope = $request->post('data_scope', 1);

        if (empty($roleName)) {
            return json(['code' => 400, 'msg' => '角色名称不能为空']);
        }

        Db::table('sys_roles')->insert([
            'tenant_id' => $tenantId,
            'role_name' => $roleName,
            'data_scope' => $dataScope
        ]);

        return json(['code' => 200, 'msg' => '角色设立成功']);
    }

    /**
     * 获取当前租户下的所有子账号
     */
    public function getAdmins(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $list = Db::table('sys_admins')
            ->where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->select('id', 'username', 'real_name', 'phone', 'status', 'created_at') // 严禁将哈希密码返回给前端
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 开通全新子账号
     */
    public function addAdmin(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $username = $request->post('username');
        $password = $request->post('password');
        $realName = $request->post('real_name');
        $phone = $request->post('phone', '');

        if (empty($username) || empty($password) || empty($realName)) {
            return json(['code' => 400, 'msg' => '账号、密码及真实姓名缺一不可']);
        }

        // 校验账号唯一性
        $exists = Db::table('sys_admins')->where('tenant_id', $tenantId)->where('username', $username)->exists();
        if ($exists) {
            return json(['code' => 400, 'msg' => '该登录账号已存在，请更换']);
        }

        Db::table('sys_admins')->insert([
            'tenant_id' => $tenantId,
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT), // 工业标准单向哈希加密
            'real_name' => $realName,
            'phone' => $phone,
            'status' => 1
        ]);

        return json(['code' => 200, 'msg' => '子账号开通成功']);
    }
}