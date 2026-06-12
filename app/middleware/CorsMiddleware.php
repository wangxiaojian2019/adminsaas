<?php
namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        // 1. 拦截跨域预检请求，直接放行
        if ($request->method() == 'OPTIONS') {
            $response = response('');
        } else {
            try {
                // 2. 正常执行业务逻辑
                $response = $handler($request);
            } catch (\Throwable $e) {
                // 3. 【核心修复】拦截所有后端崩溃报错，包装成 JSON 并强行打上跨域头
                $response = json([
                    'code' => 500,
                    'msg'  => '后端服务异常: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
                ]);
            }
        }

        // 强行注入跨域头，确保前端能拿到响应
        $response->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE,OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type,Authorization,X-Requested-With,tenant-id,Accept',
        ]);

        return $response;
    }
}