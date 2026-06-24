<?php
namespace app\controller;

use support\Request;
use support\Db;

class DecorationController
{
    /**
     * 装修报备列表（多表关联查询）
     */
    public function list(Request $request)
    {
        $list = Db::table('decorations as d')
            ->leftJoin('enterprises as e', 'd.enterprise_id', '=', 'e.id')
            ->leftJoin('spaces as s', 'd.space_id', '=', 's.id')
            ->select(
                'd.*',
                'e.name as enterprise_name',
                Db::raw("CONCAT(s.building_name, '-', s.floor, 'F-', s.room_number) as room_info"),
                's.status as current_space_status'
            )
            ->orderBy('d.id', 'desc')
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 发起装修报备申请
     */
    public function apply(Request $request)
    {
        $enterpriseId = $request->post('enterprise_id');
        $spaceId = $request->post('space_id');
        $startDate = $request->post('start_date');
        $endDate = $request->post('end_date');
        $manager = $request->post('manager', '');
        $deposit = $request->post('deposit', 0.00);

        if (!$enterpriseId || !$spaceId || !$startDate || !$endDate) {
            return json(['code' => 400, 'msg' => '核心关联参数或工期缺失']);
        }

        // 计算物理天数
        $totalDays = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;

        $data = [
            'apply_no' => 'ZX' . date('YmdHis') . rand(1000, 9999),
            'enterprise_id' => $enterpriseId,
            'space_id' => $spaceId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => max(1, intval($totalDays)),
            'status' => 0, // 0: 待审核
            'deposit' => $deposit,
            'manager' => $manager,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        Db::table('decorations')->insert($data);
        return json(['code' => 200, 'msg' => '申请提交成功，等待物业中控室审核']);
    }

    /**
     * 物业中控室审批与房屋状态原子联动引擎
     */
    public function audit(Request $request)
    {
        $id = $request->post('id');
        $status = $request->post('status'); // 1:施工中, 3:已完工, 4:已驳回

        if (!in_array($status, [1, 3, 4])) {
            return json(['code' => 400, 'msg' => '非法的流转状态值']);
        }

        $decoration = Db::table('decorations')->where('id', $id)->first();
        if (!$decoration) {
            return json(['code' => 404, 'msg' => '未找到该报备记录']);
        }

        Db::beginTransaction();
        try {
            // 1. 更新报备表单状态
            Db::table('decorations')->where('id', $id)->update([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // 2. 触发空间资产状态机流转
            if ($status == 1) {
                // 审批通过进场施工 -> 房屋状态强转为 3:装修
                Db::table('spaces')->where('id', $decoration->space_id)->update([
                    'status' => 3,
                    'enterprise_name' => Db::table('enterprises')->where('id', $decoration->enterprise_id)->value('name')
                ]);
            } elseif ($status == 3) {
                // 装修完工结单 -> 动态研判该房屋当前是否存在生效中的租务合同
                $hasActiveContract = Db::table('contracts')
                    ->where('space_id', $decoration->space_id)
                    ->where('status', 1) // 1: 生效中
                    ->exists();

                // 如果有生效合同，还原为 1:在租；若无有效合同（如入驻前退场或纯毛坯装修），还原为 0:空置
                $targetStatus = $hasActiveContract ? 1 : 0;
                
                Db::table('spaces')->where('id', $decoration->space_id)->update([
                    'status' => $targetStatus
                ]);
            }

            Db::commit();
            return json(['code' => 200, 'msg' => '审批完成，空间资产状态已同步联动']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '联动失败，事务已回滚：' . $e->getMessage()]);
        }
    }

    /**
     * 施工延期报备
     */
    public function applyDelay(Request $request)
    {
        $id = $request->post('id');
        $newEndDate = $request->post('new_end_date');
        $reason = $request->post('delay_reason');

        if (!$id || !$newEndDate || !$reason) {
            return json(['code' => 400, 'msg' => '缺失延期核心参数']);
        }

        $decoration = Db::table('decorations')->where('id', $id)->first();
        if (!$decoration) {
            return json(['code' => 404, 'msg' => '未找到该报备记录']);
        }

        $totalDays = (strtotime($newEndDate) - strtotime($decoration->start_date)) / 86400 + 1;

        Db::table('decorations')->where('id', $id)->update([
            'end_date' => $newEndDate,
            'total_days' => max(1, intval($totalDays)),
            'delay_reason' => $reason,
            'status' => 2, // 2: 延期审核
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '延期工单已提交至中控台']);
    }
}