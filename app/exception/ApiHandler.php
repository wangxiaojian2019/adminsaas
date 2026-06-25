<?php
namespace app\exception;

use Webman\Http\Request;
use Webman\Http\Response;
use Throwable;
use support\Log;
use support\exception\Handler;

class ApiHandler extends Handler
{
    public function render(Request $request, Throwable $exception): Response
    {
        $code = $exception->getCode();
        $message = $exception->getMessage();
        
        $code = (is_numeric($code) && $code > 0) ? $code : 500;

        $errorLog = sprintf(
            "API Exception: %s in %s:%d\nStack trace:\n%s",
            $message,
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        Log::error($errorLog);

        $responseData = [
            'code' => $code,
            'msg'  => '系统运行异常，请联系管理员', 
            'data' => null
        ];

        if (config('app.debug')) {
            $responseData['msg'] = $message;
            $responseData['trace'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ];
        }

        // 核心修正：只输出 JSON，不再设置任何 Access-Control 头，全权交由中间件处理
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData, JSON_UNESCAPED_UNICODE));
    }
    
    protected function isHttpException(Throwable $e): bool
    {
        return $e instanceof \Webman\Exception\HttpException;
    }
}