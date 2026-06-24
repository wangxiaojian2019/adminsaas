<?php
namespace app\utils;

/**
 * 智慧园区 SaaS 核心安全底座
 * 标准 HS256 JWT 加解密引擎（无第三方依赖）
 */
class JwtToken
{
    // 签名盐值（生产环境中建议极其复杂，不可泄露）
    private static $secret = 'YuanQu_SaaS_Super_Secret_Key_2026!@#';

    /**
     * 签发 Token
     * @param array $payload 载荷数据 (如用户ID, 租户ID等)
     * @param int $expire 过期时间(秒)
     */
    public static function encode(array $payload, int $expire = 86400)
    {
        // 1. 构建头部
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        // 2. 注入签发时间与过期时间
        $payload['iat'] = time();
        $payload['exp'] = time() + $expire;
        $payloadJson = json_encode($payload);

        // 3. Base64Url 安全编码
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payloadJson));

        // 4. SHA256 签名计算，防止数据被篡改
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * 验证并解析 Token
     * @return array|false 解析成功返回数组，被篡改或过期返回 false
     */
    public static function decode(string $token)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;

        // 验证签名完整性
        $validSignature = hash_hmac('sha256', $header . "." . $payload, self::$secret, true);
        $validBase64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($validSignature));

        // 如果客户端传来的签名与服务端计算出的签名不一致，说明数据被伪造
        if (!hash_equals($validBase64UrlSignature, $signature)) {
            return false;
        }

        $decodedPayload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        
        // 验证生命周期
        if (!$decodedPayload || !isset($decodedPayload['exp']) || $decodedPayload['exp'] < time()) {
            return false; 
        }

        return $decodedPayload;
    }
}