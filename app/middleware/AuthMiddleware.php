<?php
namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler) : Response
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            return json(['code' => 401, 'msg' => 'Token Missing']);
        }

        $token = substr($authHeader, 7);
        $decoded = base64_decode($token);
        if (!$decoded) {
            return json(['code' => 401, 'msg' => 'Token Invalid']);
        }

        $parts = explode('|', $decoded);
        if (count($parts) < 3) {
            return json(['code' => 401, 'msg' => 'Token Formatter Error']);
        }

        // 识别 Token 类型
        if ($parts[2] === 'tenant') {
            // 移动租户端流量：动态向请求体挂载企业ID
            $request->enterprise_id = intval($parts[0]);
        } else {
            // 管理端/基层外勤端流量
            $request->user = (object)[
                'id' => intval($parts[0]),
                'username' => $parts[1]
            ];
        }

        return $handler($request);
    }
}