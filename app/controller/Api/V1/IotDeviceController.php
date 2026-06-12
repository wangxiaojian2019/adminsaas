<?php
namespace app\controller\Api\V1;

use support\Request;
use support\Response;
use app\model\IotDevice;

class IotDeviceController
{
    /**
     * 统一控制接口 (包含手动模式处理逻辑)
     */
    public function control(Request $request): Response
    {
        $tenantId = $request->tenantId;
        $deviceId = $request->post('device_id');
        $action = $request->post('action');

        $device = IotDevice::where('id', $deviceId)->where('tenant_id', $tenantId)->first();

        if (!$device) {
            return json(['code' => 404, 'msg' => '设备不存在或无权限']);
        }

        // 判断是否为手动模拟模式
        if ($device->driver_key === 'manual') {
            $desired = json_decode($device->desired_status, true) ?: [];
            $desired['last_action'] = $action;
            $desired['updated_at'] = date('Y-m-d H:i:s');
            
            $device->desired_status = json_encode($desired);
            $device->save();
            
            return json(['code' => 200, 'msg' => '手动模式：状态已记录']);
        }

        // TODO: 后期通过 Workerman 内部通讯将指令抛给对应的TCP/MQTT进程
        return json([
            'code' => 200, 
            'msg' => "指令已推送到 {$device->driver_key} 驱动列队等待下发"
        ]);
    }
}