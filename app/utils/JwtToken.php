<?php
namespace app\utils;

use support\Redis;

/**
 * 智慧园区 SaaS 核心安全底座
 * 标准 HS256 JWT 加解密引擎（带 Redis 黑名单阻断）
 */
class JwtToken
{
    /**
     * 获取签名盐值
     * 生产环境中必须通过 .env 配置，严禁硬编码
     */
    private static function getSecret(): string
    {
        return getenv('JWT_SECRET') ?: 'Fallback_Secret_Do_Not_Use_In_Prod_2026';
    }

    /**
     * 签发 Token
     * @param array $payload 载荷数据
     * @param int $expire 过期时间(秒)
     */
    public static function encode(array $payload, int $expire = 86400): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $payload['iat'] = time();
        $payload['exp'] = time() + $expire;
        $payloadJson = json_encode($payload);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payloadJson));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::getSecret(), true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * 验证并解析 Token
     * @return array|false
     */
    public static function decode(string $token)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;

        // 验证黑名单，阻断已被主动吊销的 Token
        if (Redis::exists("jwt_blacklist:{$signature}")) {
            return false;
        }

        $validSignature = hash_hmac('sha256', $header . "." . $payload, self::getSecret(), true);
        $validBase64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($validSignature));

        if (!hash_equals($validBase64UrlSignature, $signature)) {
            return false;
        }

        $decodedPayload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        
        if (!$decodedPayload || !isset($decodedPayload['exp']) || $decodedPayload['exp'] < time()) {
            return false; 
        }

        return $decodedPayload;
    }

    /**
     * 吊销指定 Token（强制登出/封号时调用）
     */
    public static function invalidate(string $token): void
    {
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
            $exp = $payload['exp'] ?? 0;
            $ttl = $exp - time();
            if ($ttl > 0) {
                // 将签名加入黑名单，过期时间与 Token 剩余寿命一致
                Redis::setex("jwt_blacklist:{$parts[2]}", $ttl, 1);
            }
        }
    }
}