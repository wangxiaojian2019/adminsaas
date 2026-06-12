<?php
namespace app\controller;

use support\Request;
use support\Db;

class LoginController
{
    /**
     * PC管理端与H5员工端统一登录入口
     */
    public function login(Request $request)
    {
        $username = $request->post('username');
        $password = $request->post('password');

        if (empty($username) || empty($password)) {
            return json(['code' => 400, 'msg' => '账号或密码不能为空']);
        }

        $admin = Db::table('admins')->where('username', $username)->first();

        if (!$admin) {
            return json(['code' => 400, 'msg' => '账号不存在']);
        }

        if ($admin->status !== 1) {
            return json(['code' => 403, 'msg' => '账号已被封禁']);
        }

        if (md5($password) !== $admin->password) {
            return json(['code' => 400, 'msg' => '密码错误']);
        }

        $token = base64_encode($admin->id . '|' . $admin->username . '|admin');

        $roleCode = 'admin'; 
        if ($admin->role_id == 2) $roleCode = 'manager';
        if ($admin->role_id == 3) $roleCode = 'finance';
        if ($admin->role_id == 4) $roleCode = 'worker';

        $userInfo = [
            'id' => $admin->id,
            'username' => $admin->username,
            'real_name' => $admin->real_name,
            'company_name' => $admin->company_name,
            'role' => $roleCode,
            'role_id' => $admin->role_id,
            'position' => $admin->position,
            'responsibility' => $admin->responsibility
        ];

        return json([
            'code' => 200,
            'msg' => '登录成功',
            'data' => [
                'token' => $token,
                'user_info' => $userInfo
            ]
        ]);
    }

    /**
     * 【全新拓展】企业入驻租户端专属移动登录引擎
     */
    public function tenantLogin(Request $request)
    {
        $phone = $request->post('username');
        $password = $request->post('password');

        if (empty($phone) || empty($password)) {
            return json(['code' => 400, 'msg' => '手机号或密码不能为空']);
        }

        // 使用联系人手机号作为企业租户的登录唯一标识
        $enterprise = Db::table('enterprises')->where('phone', $phone)->first();

        if (!$enterprise) {
            return json(['code' => 400, 'msg' => '该手机号未绑定任何园区入驻企业档案']);
        }

        if (md5($password) !== $enterprise->password) {
            return json(['code' => 400, 'msg' => '密码安全核验失败']);
        }

        // 生成带租户标识的独立 Token 令牌
        $token = base64_encode($enterprise->id . '|' . $enterprise->phone . '|tenant');

        $tenantInfo = [
            'enterprise_id' => $enterprise->id,
            'enterprise_name' => $enterprise->name,
            'contact_person' => $enterprise->contact_person,
            'phone' => $enterprise->phone,
            'role' => 'tenant'
        ];

        return json([
            'code' => 200,
            'msg' => '企业门户认证成功',
            'data' => [
                'token' => $token,
                'user_info' => $tenantInfo
            ]
        ]);
    }
}