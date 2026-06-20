<?php
namespace app\process;

use Workerman\Connection\TcpConnection;

class Websocket
{
    /**
     * @var array 保存租户标识与连接对象的映射池结构 [enterprise_id => [connection_id => connection]]
     * 采用二维数组以支持同一个企业账号在多台手机上同时登录和接收消息
     */
    public static $connections = [];

    /**
     * TCP 三次握手后的事件钩子
     */
    public function onConnect(TcpConnection $connection)
    {
        // 允许建立底层管道，等待鉴权消息绑定
    }

    /**
     * 收到前端消息（心跳或绑定指令）时触发
     */
    public function onMessage(TcpConnection $connection, $data)
    {
        $payload = json_decode($data, true);
        if (!$payload) return;

        // 识别前端身份绑定动作
        if (isset($payload['type']) && $payload['type'] === 'bind') {
            $enterpriseId = intval($payload['enterprise_id'] ?? 0);
            if ($enterpriseId) {
                // 将租户ID挂载至底层连接生命周期句柄中
                $connection->enterprise_id = $enterpriseId;
                // 注入企业长连接沙箱池
                self::$connections[$enterpriseId][$connection->id] = $connection;
            }
        }
    }

    /**
     * 客户端断网或关闭时的垃圾回收钩子
     */
    public function onClose(TcpConnection $connection)
    {
        // 从内存映射池中精准销毁对应的句柄，防止内存泄露
        if (isset($connection->enterprise_id)) {
            $eid = $connection->enterprise_id;
            if (isset(self::$connections[$eid][$connection->id])) {
                unset(self::$connections[$eid][$connection->id]);
                // 若该企业下所有设备已离线，清理该空数组键
                if (empty(self::$connections[$eid])) {
                    unset(self::$connections[$eid]);
                }
            }
        }
    }
    
    /**
     * 【跨进程触发引擎】 暴露给外部 HTTP API (如 FinanceController) 调用的直接推送阀门
     * * @param int $enterpriseId 目标接收企业
     * @param array $message 要发往前端进行驱动渲染的 JSON 数据结构
     */
    public static function sendToEnterprise($enterpriseId, array $message)
    {
        if (isset(self::$connections[$enterpriseId])) {
            $jsonPayload = json_encode($message, JSON_UNESCAPED_UNICODE);
            foreach (self::$connections[$enterpriseId] as $conn) {
                $conn->send($jsonPayload);
            }
        }
    }
}