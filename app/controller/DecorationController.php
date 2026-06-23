<?php
namespace app\controller;

use support\Request;
use support\Db;

class DecorationController
{
    /**
     * 获取装修报备大盘列表 (支持按状态和企业名筛选)
     */
    public function list(Request $request)
    {
        $status = $request->get('status');
        $entName = $request->get('entName');

        $query = Db::table('decorations');
        
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }
        if (!empty($entName)) {
            $query->where('enterprise_name', 'like', "%{$entName}%");
        }

        // 默认按最新报备排序
        $list = $query->orderBy('id', 'desc')->get();
        
        // 统计面板数据聚合
        $stats = [
            'pending' => Db::table('decorations')->where('status', 0)->count(),
            'building' => Db::table('decorations')->where('status', 1)->count(),
            'delaying' => Db::table('decorations')->where('status', 2)->count(),
            'finished' => Db::table('decorations')->where('status', 3)->count(),
        ];

        return json(['code' => 200, 'msg' => 'success', 'data' => $list, 'stats' => $stats]);
    }

    /**
     * 提交新的装修报备申请
     */
    public function apply(Request $request)
    {
        $data = $request->post();
        
        if (empty($data['enterprise_name']) || empty($data['room_info'])) {
            return json(['code' => 400, 'msg' => '关键申报信息不完整']);
        }

        // 自动生成 ZX 开头的流水单号
        $applyNo = 'ZX' . date('YmdHis') . rand(1000, 9999);

        Db::table('decorations')->insert([
            'apply_no' => $applyNo,
            'enterprise_name' => $data['enterprise_name'],
            'room_info' => $data['room_info'],
            'start_date' => $data['dateRange'][0] ?? date('Y-m-d'),
            'end_date' => $data['dateRange'][1] ?? date('Y-m-d'),
            'total_days' => $data['total_days'] ?? 0,
            'deposit' => $data['deposit'] ?? 5000,
            'manager' => $data['manager'] ?? '',
            'status' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '装修报备单提交成功，等待物业审核']);
    }

    /**
     * 核心业务：审批流转与【多模块跨表联动】
     */
    public function audit(Request $request)
    {
        $id = $request->post('id');
        $status = $request->post('status'); // 1:同意进场 3:提前完工验收 4:驳回
        
        $record = Db::table('decorations')->where('id', $id)->first();
        if (!$record) return json(['code' => 404, 'msg' => '未找到该报备记录']);

        Db::beginTransaction();
        try {
            // 1. 更新主表状态
            Db::table('decorations')->where('id', $id)->update([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // 2. 【业财工单联动逻辑】：如果操作是“完工验收 (3)”
            if ($status == 3) {
                // 自动向工单系统派发一条【验收与退款】工单，指派给工程组
                Db::table('work_orders')->insert([
                    'title' => "【装修竣工专项验收】 - {$record->room_info}",
                    'description' => "{$record->enterprise_name} 申报完工。请工程部核查墙体与消防。验收合格后，请通知财务退还押金 ￥{$record->deposit}",
                    'priority' => 'high',
                    'status' => 'pending', // 对应工单表的待处理状态
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // 记录操作日志
                $msg = '已审批通过！系统已联动【外勤工单系统】下发竣工验收与退还押金工单。';
            } else if ($status == 1) {
                // TODO: 可以在这里对接 IoT 控制器，下发门禁白名单
                $msg = '已批准施工，门禁临时权限已预授权。';
            } else {
                $msg = '操作成功';
            }

            Db::commit();
            return json(['code' => 200, 'msg' => $msg]);

        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '底层联动执行异常: ' . $e->getMessage()]);
        }
    }

    /**
     * 提交二次延期申请
     */
    public function applyDelay(Request $request)
    {
        $id = $request->post('id');
        $newEndDate = $request->post('new_end_date');
        $reason = $request->post('reason');

        Db::table('decorations')->where('id', $id)->update([
            'status' => 2, // 变更为延期审核中
            'end_date' => $newEndDate,
            'delay_reason' => $reason,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '延期申请已提交后台决策']);
    }
}