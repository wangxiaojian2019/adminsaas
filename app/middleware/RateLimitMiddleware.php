<?php
namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use support\Redis;
use support\Log;

class RateLimitMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $path = $request->path();
        $ip = $request->getRealIp();

        // 策略 A：高敏感接口（如登录授权），执行极严频控 (单 IP 每分钟最多 5 次)
        if (strpos($path, '/api/login') === 0 || strpos($path, '/api/tenant/login') === 0) {
            $key = "rate_limit:login:{$ip}";
            $count = Redis::incr($key);
            
            // 首次访问，设置 60 秒过期时间
            if ($count === 1) {
                Redis::expire($key, 60);
            }
            
            if ($count > 5) {
                Log::warning("安全系统预警：IP {$ip} 正在尝试暴力破解登录接口，已被拦截。");
                return json(['code' => 429, 'msg' => '系统防御系统触发：请求过于频繁，请1分钟后再试']);
            }
        }

        // 策略 B：普通 API 接口，防恶意并发/CC攻击 (单 IP 每秒最多 50 次)
        if (strpos($path, '/api/') === 0) {
            // 按秒级时间戳作为 Key
            $apiKey = "rate_limit:api:{$ip}:" . time();
            $apiCount = Redis::incr($apiKey);
            
            if ($apiCount === 1) {
                Redis::expire($apiKey, 2); // 留出缓冲，2秒后自动销毁
            }
            
            if ($apiCount > 50) {
                Log::warning("安全系统预警：IP {$ip} 接口并发过高，已触发限流熔断。");
                return json(['code' => 429, 'msg' => '系统防御系统触发：接口并发过高，请减缓访问速度']);
            }
        }

        return $handler($request);
    }
}