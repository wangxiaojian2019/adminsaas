<?php
namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        // 1. 绝对安全隔离：拦截 OPTIONS 预检请求，使用原生 header 方法确保 100% 跨域放行
        if ($request->method() === 'OPTIONS') {
            $response = response('');
            $response->header('Access-Control-Allow-Origin', '*');
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, tenant-id');
            $response->header('Access-Control-Allow-Credentials', 'true');
            return $response;
        }

        // 2. 开发沙盘态底座注入（强制对齐 tenant_id = 1，防止业务层因空指针死锁）
        $request->tenant_id = 1;
        $request->admin_id = 1;

        try {
            // 3. 执行下一级业务控制器
            $response = $handler($request);
            
            // 确保执行结果被安全包裹为 Response 实例
            if (!$response instanceof Response) {
                $response = json($response);
            }
        } catch (\Throwable $e) {
            // 4. 【核心诊断自愈】：如果业务层、数据库或控制器发生任何崩溃，捕获并抛出真实死因
            $response = json([
                'code' => 500,
                'msg' => '业务层遭遇致命崩溃: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        // 5. 无论业务成功与否，强行附加跨域通行证，确保前端 F12 能看见真实结果
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, tenant-id');
        $response->header('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}