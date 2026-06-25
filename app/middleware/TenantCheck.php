<?php
namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class TenantCheck implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        // 1. 绿灯放行机制：如果是登录相关接口，直接放行
        // 因为登录时尚未生成 Token，不可能有 tenantId
        $path = $request->path();
        if (strpos($path, '/api/login') === 0 || strpos($path, '/api/tenant/login') === 0) {
            return $handler($request);
        }

        // 2. 核心安全重构：读取上游 AuthMiddleware 依靠 JWT 签名严格解析出的 tenantId
        $tenantId = $request->tenantId;
        
        // 3. 拦截上下文丢失的非法请求
        if (!$tenantId) {
            return json(['code' => 403, 'msg' => '安全系统拦截：非法或丢失的租户上下文']);
        }
        
        return $handler($request);
    }
}