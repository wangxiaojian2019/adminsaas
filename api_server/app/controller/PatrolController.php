<?php
namespace app\controller;

use support\Request;
use support\Response;
use support\Db;

class PatrolController
{
    /**
     * ================= 1. 现场巡检与点位管理 =================
     */
    public function getPoints(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $list = Db::table('patrol_points')
            ->where('tenant_id', $tenantId)
            ->orderBy('id', 'asc')
            ->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function addPoint(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $pointName = $request->post('point_name');
        $buildingId = $request->post('building_id', 1);

        if (empty($pointName)) return json(['code' => 400, 'msg' => '点位名称不可为空']);

        Db::table('patrol_points')->insert([
            'tenant_id' => $tenantId,
            'building_id' => $buildingId,
            'point_name' => $pointName,
            'qr_code_token' => 'QR-' . uniqid()
        ]);
        return json(['code' => 200, 'msg' => '防伪巡检点位设立成功']);
    }

    /**
     * 保安现场扫码打卡打卡（若选择异常，底层触发事务自动向后勤调度池生成工单）
     */
    public function checkIn(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $pointId = $request->post('point_id');
        $isNormal = $request->post('is_normal', 1);
        $remark = $request->post('remark', '');
        $operatorName = $request->post('operator_name', '现场巡检员');

        if (empty($pointId)) return json(['code' => 400, 'msg' => '缺失关联点位信息']);

        Db::beginTransaction();
        try {
            // 1. 写入巡检流水流水
            Db::table('patrol_records')->insert([
                'tenant_id' => $tenantId,
                'point_id' => $pointId,
                'operator_name' => $operatorName,
                'is_normal' => $isNormal,
                'remark' => $remark
            ]);

            // 2. 【核心状态机流转】：若现场有异常，自动生成待指派维修工单
            if ($isNormal == 0) {
                $point = Db::table('patrol_points')->where('id', $pointId)->first();
                
                Db::table('pm_work_orders')->insert([
                    'tenant_id' => $tenantId,
                    'space_id' => 1, // 默认挂载项目通用公共区域
                    'reporter_type' => 1, // 保安巡检上报
                    'reporter_name' => $operatorName,
                    'title' => '巡检发现异常: ' . ($point ? $point->point_name : '未名点位'),
                    'description' => $remark ?: '保安未填写详述，请工程人员现场查看',
                    'status' => 1 // 1 - 待指派
                ]);
            }

            Db::commit();
            return json(['code' => 200, 'msg' => $isNormal == 1 ? '巡检正常，打卡成功' : '异常已上报，系统已自动向后勤工单池推单调度']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '系统打卡异常']);
        }
    }

    public function getRecords(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $list = Db::table('patrol_records as r')
            ->join('patrol_points as p', 'r.point_id', '=', 'p.id')
            ->where('r.tenant_id', $tenantId)
            ->select('r.*', 'p.point_name')
            ->orderBy('r.id', 'desc')
            ->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * ================= 2. 统一工单流转状态机管理 =================
     */

    /**
     * 获取工单总调度池
     */
    public function getWorkOrders(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $list = Db::table('pm_work_orders as w')
            ->leftJoin('sys_admins as a', 'w.current_handler_id', '=', 'a.id')
            ->where('w.tenant_id', $tenantId)
            ->select('w.*', 'a.real_name as handler_name')
            ->orderBy('w.status', 'asc') // 待处理优先
            ->orderBy('w.id', 'desc')
            ->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 状态流转一：主管指派指定员工维修 (1 -> 2)
     */
    public function assignWorkOrder(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $orderId = $request->post('id');
        $handlerId = $request->post('handler_id'); // 接收被指派的员工账号ID

        if (empty($orderId) || empty($handlerId)) return json(['code' => 400, 'msg' => '参数缺失']);

        Db::table('pm_work_orders')->where('id', $orderId)->where('tenant_id', $tenantId)->update([
            'current_handler_id' => $handlerId,
            'status' => 2 // 流转为：2 - 处理中
        ]);
        return json(['code' => 200, 'msg' => '工单已下发，对应的工程维修人员已收到通知']);
    }

    /**
     * 状态流转二：现场维修员工提报修复完工 (2 -> 3)
     */
    public function completeWorkOrder(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $orderId = $request->post('id');

        Db::table('pm_work_orders')->where('id', $orderId)->where('tenant_id', $tenantId)->update([
            'status' => 3 // 流转为：3 - 待验收
        ]);
        return json(['code' => 200, 'msg' => '完工状态已提报，等待物业管理层验收']);
    }

    /**
     * 状态流转三：主管现场核验无误，结单销账 (3 -> 4)
     */
    public function verifyWorkOrder(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $orderId = $request->post('id');

        Db::table('pm_work_orders')->where('id', $orderId)->where('tenant_id', $tenantId)->update([
            'status' => 4 // 流转为：4 - 已结单归档
        ]);
        return json(['code' => 200, 'msg' => '工单验收通过，流程正式关闭']);
    }
}