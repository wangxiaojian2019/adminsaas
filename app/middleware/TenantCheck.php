<?php
namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class TenantCheck implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        // 强制拦截并验证 tenant-id 头部
        $tenantId = $request->header('tenant-id');
        
        if (!$tenantId) {
            return json(['code' => 403, 'msg' => 'Missing Tenant-ID']);
        }
        
        // 将租户ID挂载到请求对象上，供下游控制器调用
        $request->tenantId = $tenantId;
        
        return $handler($request);
    }
}