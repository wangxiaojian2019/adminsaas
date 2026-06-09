<?php
namespace app\controller;

use support\Request;
use support\Response;
use support\Db;
use Firebase\JWT\JWT;

class AuthController
{
    // 必须与 AuthMiddleware 中的密钥保持绝对一致
    const JWT_SECRET = 'Smart_Property_SaaS_2026_Secure_Key';

    public function login(Request $request): Response
    {
        $username = $request->post('username');
        $password = $request->post('password');

        if (empty($username) || empty($password)) {
            return json(['code' => 400, 'msg' => '账号或密码不能为空']);
        }

        // 1. 查找用户及其所属租户状态
        $user = Db::table('users as u')
            ->join('tenants as t', 'u.tenant_id', '=', 't.id')
            ->where('u.username', $username)
            ->select('u.*', 't.status as tenant_status', 't.company_name')
            ->first();

        if (!$user) {
            return json(['code' => 404, 'msg' => '账号不存在']);
        }

        if ($user->status == 0) {
            return json(['code' => 403, 'msg' => '您的账号已被禁用，请联系管理员']);
        }

        if ($user->tenant_status == 0) {
            return json(['code' => 403, 'msg' => '您所属的物业公司（租户）系统已停用，请联系服务商']);
        }

        // 2. 校验密码 (这里用 password_verify 校验我们在 SQL 里预埋的哈希值)
        if (!password_verify($password, $user->password_hash)) {
            return json(['code' => 401, 'msg' => '密码错误']);
        }

        // 3. 签发 JWT 令牌 (包含多租户隔离基因)
        $payload = [
            'iss' => 'SaaS_Property_System', // 签发者
            'iat' => time(), // 签发时间
            'exp' => time() + (86400 * 7), // 7天过期
            'tenant_id' => $user->tenant_id, // 核心：租户隔离ID
            'uid' => $user->id,
            'role' => $user->role
        ];

        $token = JWT::encode($payload, self::JWT_SECRET, 'HS256');

        return json([
            'code' => 200, 
            'msg' => '登录成功', 
            'data' => [
                'token' => $token,
                'user_info' => [
                    'real_name' => $user->real_name,
                    'role' => $user->role,
                    'company_name' => $user->company_name
                ]
            ]
        ]);
    }
}