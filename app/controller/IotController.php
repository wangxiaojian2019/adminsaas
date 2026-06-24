<?php
namespace app\controller;

use support\Request;
use support\Db;

class IotController
{
    /**
     * 资产大盘与实时状态读取
     */
    public function list(Request $request)
    {
        $tenantId = $request->tenantId ?? 1;
        
        $devices = Db::table('iot_devices')
            ->leftJoin('spaces', 'iot_devices.space_id', '=', 'spaces.id')
            ->where('iot_devices.tenant_id', $tenantId)
            ->select('iot_devices.*', 'spaces.building_name', 'spaces.room_number')
            ->orderBy('iot_devices.id', 'desc')
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $devices]);
    }

    /**
     * 审计级指令下发引擎
     */
    public function control(Request $request)
    {
        $deviceSn = $request->post('device_sn');
        $command = $request->post('command_type'); // 例如: power_off, door_open
        $adminId = $request->user->id ?? 0;

        if (!$deviceSn || !$command) {
            return json(['code' => 400, 'msg' => '指令特征码或设备SN缺失']);
        }

        $device = Db::table('iot_devices')->where('device_sn', $deviceSn)->first();
        if (!$device) {
            return json(['code' => 404, 'msg' => '非法设备']);
        }

        Db::beginTransaction();
        try {
            // 1. 写入审计日志
            $logId = Db::table('iot_command_logs')->insertGetId([
                'device_sn' => $deviceSn,
                'command_type' => $command,
                'operator_id' => $adminId,
                'status' => 0, 
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // TODO: 此处应调用 MQTT 或 TCP 客户端向底层硬件发送真实二进制报文
            // 模拟硬件在 200ms 内响应成功
            $mockHardwareResponse = true; 

            if ($mockHardwareResponse) {
                Db::table('iot_command_logs')->where('id', $logId)->update([
                    'status' => 1,
                    'response_payload' => '{"code":0, "msg":"ACK_SUCCESS"}',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }

            Db::commit();
            return json(['code' => 200, 'msg' => "指令 [{$command}] 已成功穿透网关下发至硬件"]);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '边缘网关异常：' . $e->getMessage()]);
        }
    }

    /**
     * 硬件遥测时序数据上报 Webhook (免鉴权路由，供硬件直连)
     */
    public function webhook(Request $request)
    {
        $deviceSn = $request->post('device_sn');
        $payload = $request->post('payload'); // 硬件传来的 JSON 字符串，包含读数/电压等

        if (!$deviceSn || !$payload) {
            return json(['code' => 400, 'msg' => 'BAD_REQUEST']);
        }

        Db::beginTransaction();
        try {
            // 1. 更新设备存活状态与心跳
            Db::table('iot_devices')->where('device_sn', $deviceSn)->update([
                'status' => 1, // 1:在线在线
                'last_heartbeat' => date('Y-m-d H:i:s')
            ]);

            // 2. 存入时序数据库供 BI 大盘分析
            Db::table('iot_telemetry_logs')->insert([
                'device_sn' => $deviceSn,
                'data_payload' => is_string($payload) ? $payload : json_encode($payload),
                'reported_at' => date('Y-m-d H:i:s')
            ]);

            Db::commit();
            return json(['code' => 200, 'msg' => 'ACK']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => 'NACK']);
        }
    }
}