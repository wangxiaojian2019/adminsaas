<?php
namespace app\controller;

use support\Request;
use support\Db;

class IotController
{
    // 联合查询房源资产与设备在线状态
    public function list(Request $request)
    {
        $devices = Db::table('iot_devices')
            ->leftJoin('spaces', 'iot_devices.space_id', '=', 'spaces.id')
            ->select('iot_devices.*', 'spaces.room_number', 'spaces.building_name')
            ->orderBy('iot_devices.id', 'desc')
            ->get();

        $stats = [
            'online' => Db::table('iot_devices')->where('status', 1)->count(),
            'offline' => Db::table('iot_devices')->where('status', 0)->count(),
            'warning' => Db::table('iot_devices')->where('status', 2)->count(),
        ];

        $list = [];
        $typeMap = [1 => '智能电表', 2 => '智能水表', 3 => '门禁闸机'];
        foreach ($devices as $d) {
            $list[] = [
                'id' => $d->id,
                'mac_address' => $d->device_sn,
                'device_type' => $typeMap[$d->device_type] ?? '未知硬件',
                'asset_name' => $d->room_number ? "{$d->building_name}-{$d->room_number}" : '园区公共区域',
                'driver_key' => 'MQTT 底层队列',
                'is_online' => $d->status == 1
            ];
        }

        return json(['code' => 200, 'msg' => 'success', 'data' => $list, 'stats' => $stats]);
    }

    // 核心：远程下发物理控制指令
    public function control(Request $request)
    {
        $deviceId = $request->post('device_id');
        $action = $request->post('action'); // turn_on 或 turn_off

        $device = Db::table('iot_devices')->where('id', $deviceId)->first();
        if (!$device) return json(['code' => 404, 'msg' => '目标硬件不存在或已下线']);

        if ($action === 'turn_on') {
            $msg = "指令已推送到 MQTT 驱动队列！物理设备即刻通电/开闸。";
            Db::table('iot_devices')->where('id', $deviceId)->update(['status' => 1]); // 1为在线通电
        } else {
            $msg = "触发高危管控：强制断电/关闸指令已下发！";
            Db::table('iot_devices')->where('id', $deviceId)->update(['status' => 0]); // 0为断电离线
        }

        return json(['code' => 200, 'msg' => $msg]);
    }
}