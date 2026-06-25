<?php
namespace app\controller;

use support\Request;
use support\Db;
use support\Log;
use app\utils\JwtToken;

class LoginController
{
    public function login(Request $request)
    {
        $username = $request->post('username');
        $password = $request->post('password');

        if (!$username || !$password) {
            return json(['code' => 400, 'msg' => '账号或密码不能为空']);
        }

        $admin = Db::table('admins')->where('username', $username)->where('status', 1)->first();
        if (!$admin) {
            return json(['code' => 401, 'msg' => '账号或密码错误，或该账号已被封禁']);
        }

        // 密码平滑升级逻辑：兼容旧 MD5，同时向原生 Hash 迁移
        if ($admin->password === md5($password)) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            Db::table('admins')->where('id', $admin->id)->update(['password' => $newHash]);
        } elseif (!password_verify($password, $admin->password)) {
            return json(['code' => 401, 'msg' => '账号或密码错误，或该账号已被封禁']);
        }

        try {
            Db::table('admins')->where('id', $admin->id)->update([
                'last_login_time' => date('Y-m-d H:i:s'),
                'last_login_ip' => $request->getRealIp()
            ]);
        } catch (\Throwable $e) {
            Log::error("管理员 {$username} 登录更新时间失败: " . $e->getMessage());
        }

        $token = JwtToken::encode([
            'uid' => $admin->id,
            'role_id' => $admin->role_id ?? 1,
            'tenant_id' => $admin->tenant_id ?? 1,
            'type' => 'admin'
        ], 86400);

        unset($admin->password);

        return json([
            'code' => 200, 
            'msg' => '登录成功', 
            'data' => [
                'token' => $token,
                'user' => $admin
            ]
        ]);
    }

    public function tenantLogin(Request $request)
    {
        $phone = $request->post('phone');
        $password = $request->post('password');

        if (!$phone || !$password) {
            return json(['code' => 400, 'msg' => '手机号或密码不能为空']);
        }

        $enterprise = Db::table('enterprises')->where('phone', $phone)->first();
        if (!$enterprise) {
            return json(['code' => 401, 'msg' => '手机号或密码错误']);
        }

        // 密码平滑升级逻辑
        if ($enterprise->password === md5($password)) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            Db::table('enterprises')->where('id', $enterprise->id)->update(['password' => $newHash]);
        } elseif (!password_verify($password, $enterprise->password)) {
            return json(['code' => 401, 'msg' => '手机号或密码错误']);
        }

        try {
            Db::table('enterprises')->where('id', $enterprise->id)->update([
                'last_login_time' => date('Y-m-d H:i:s'),
                'last_login_ip' => $request->getRealIp()
            ]);
        } catch (\Throwable $e) {
            Log::error("企业账号 {$phone} 登录更新时间失败: " . $e->getMessage());
        }

        $token = JwtToken::encode([
            'uid' => $enterprise->id,
            'tenant_id' => $enterprise->tenant_id ?? 1,
            'type' => 'tenant'
        ], 86400 * 7);

        unset($enterprise->password);

        return json([
            'code' => 200, 
            'msg' => '企业门户授权成功', 
            'data' => [
                'token' => $token,
                'tenant_info' => $enterprise
            ]
        ]);
    }
}