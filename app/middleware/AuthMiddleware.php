<?php
namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use support\Db;
use app\utils\JwtToken;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler) : Response
    {
        // 绿灯放行机制：登录接口免鉴权
        $path = $request->path();
        if (strpos($path, '/api/login') === 0 || strpos($path, '/api/tenant/login') === 0) {
            return $handler($request);
        }

        $authHeader = $request->header('Authorization');
        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            return json(['code' => 401, 'msg' => '系统侦测：缺少安全授权凭证 (Token Missing)']);
        }

        $token = substr($authHeader, 7);
        
        // 核心防御点：解码验证签名与生命周期
        $decoded = JwtToken::decode($token);
        if (!$decoded) {
            return json(['code' => 401, 'msg' => '系统侦测：凭证无效、被非法篡改或已过期 (Token Invalid/Expired)']);
        }

        // 绝对隔离法则：将解码后不可篡改的 tenant_id 强制挂载到请求上下文中
        // 配合上一步的 BaseModel，彻底杜绝 A 租户拉取 B 租户数据的越权漏洞
        $request->tenantId = $decoded['tenant_id'] ?? 1;

        // 依据 Token 标记进行流量分发提权
        if ($decoded['type'] === 'tenant') {
            // 租户 H5 流量引擎
            $request->enterprise_id = intval($decoded['uid']);
        } else {
            // PC 管理端 / 基层外勤端流量引擎
            $adminId = intval($decoded['uid']);

            $admin = Db::table('admins')
                ->leftJoin('roles', 'admins.role_id', '=', 'roles.id')
                ->where('admins.id', $adminId)
                ->select(
                    'admins.id', 
                    'admins.username', 
                    'admins.real_name',
                    'admins.role_id', 
                    'admins.department_id', 
                    'roles.data_scope'
                )
                ->first();

            if (!$admin) {
                return json(['code' => 401, 'msg' => '账户主体数据异常或已被中控台封禁']);
            }

            // 预加载部门数据权限链
            $admin->department_admin_ids = [];
            if ($admin->data_scope == 2 && $admin->department_id > 0) {
                $admin->department_admin_ids = Db::table('admins')
                    ->where('department_id', $admin->department_id)
                    ->pluck('id')
                    ->toArray();
            }

            $request->user = $admin;
        }

        return $handler($request);
    }
}