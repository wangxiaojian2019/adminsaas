<?php
namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        // 遇到 OPTIONS 预检请求，直接拦截并返回空响应，不往下执行业务逻辑
        $response = $request->method() === 'OPTIONS' ? response('') : $handler($request);

        // 核心：强制为所有响应（包含OPTIONS和正常请求）附加跨域头
        $response->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin, tenant-id',
            'Access-Control-Allow-Credentials' => 'true'
        ]);

        return $response;
    }
}