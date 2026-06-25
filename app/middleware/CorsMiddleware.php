<?php
namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $response = $request->method() === 'OPTIONS' ? response('') : $handler($request);
        
        // 核心修正：使用 header() 方法强行覆盖已有值，彻底杜绝 '*, *' 的情况
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type,Authorization,X-Requested-With,Accept,Origin');
        $response->header('Access-Control-Allow-Credentials', 'true');
        
        return $response;
    }
}