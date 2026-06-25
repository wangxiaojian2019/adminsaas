<?php
namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use support\Db;
use support\Redis;
use app\utils\JwtToken;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $path = $request->path();
        if (strpos($path, '/api/login') === 0 || strpos($path, '/api/tenant/login') === 0) {
            return $handler($request);
        }

        $authHeader = $request->header('Authorization');
        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            return json(['code' => 401, 'msg' => '系统侦测：缺少安全授权凭证']);
        }

        $token = substr($authHeader, 7);
        $decoded = JwtToken::decode($token);
        
        if (!$decoded) {
            return json(['code' => 401, 'msg' => '系统侦测：凭证无效、被主动吊销或已过期']);
        }

        // 强隔离：如果 Token 中没有明确的租户 ID，直接拒绝，严禁默认降级为 1
        if (!isset($decoded['tenant_id'])) {
            return json(['code' => 403, 'msg' => '安全系统拦截：非法的租户上下文']);
        }
        $request->tenantId = $decoded['tenant_id'];

        if ($decoded['type'] === 'tenant') {
            $request->enterprise_id = intval($decoded['uid']);
        } else {
            $adminId = intval($decoded['uid']);
            $cacheKey = "saas_admin_context:{$adminId}";
            
            // L1 拦截：尝试从 Redis 读取已计算好的用户权限上下文
            $cachedContext = Redis::get($cacheKey);

            if ($cachedContext) {
                $request->user = json_decode($cachedContext);
            } else {
                // 缓存击穿兜底：穿透到 MySQL 查询
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

                $admin->department_admin_ids = [];
                if ($admin->data_scope == 2 && $admin->department_id > 0) {
                    $admin->department_admin_ids = Db::table('admins')
                        ->where('department_id', $admin->department_id)
                        ->pluck('id')
                        ->toArray();
                }

                // 写入缓存，生命周期 1 小时
                Redis::setex($cacheKey, 3600, json_encode($admin));
                $request->user = $admin;
            }
        }

        return $handler($request);
    }
}