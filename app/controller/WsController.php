<?php
namespace app\controller;

use Webman\Redis\Redis;

class WsController
{
    // 在内存中维护 enterprise_id => connection 映射
    public static $connections = [];

    public function onConnect($connection)
    {
        // 建议在连接时携带 token 验证，这里简化逻辑
        echo "新连接: {$connection->id}\n";
    }

    public function onMessage($connection, $data)
    {
        $payload = json_decode($data, true);
        if (isset($payload['type']) && $payload['type'] === 'bind') {
            // 将租户ID绑定到该连接
            self::$connections[$payload['enterprise_id']] = $connection;
            echo "租户 {$payload['enterprise_id']} 已上线 WebSocket\n";
        }
    }

    public function onClose($connection)
    {
        // 清理断开的连接
        foreach (self::$connections as $id => $conn) {
            if ($conn === $connection) {
                unset(self::$connections[$id]);
            }
        }
    }
}