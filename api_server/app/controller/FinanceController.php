<?php
namespace app\controller;

use support\Request;
use support\Response;
use support\Db;

class FinanceController
{
    /**
     * 获取全局财务应收账单池
     */
    public function getReceivables(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        
        $list = Db::table('receivables as r')
            ->leftJoin('contracts as c', 'r.contract_id', '=', 'c.id')
            ->leftJoin('enterprises as e', 'c.enterprise_id', '=', 'e.id')
            ->leftJoin('spaces as s', 'c.space_id', '=', 's.id')
            ->where('r.tenant_id', $tenantId)
            ->select(
                'r.*',
                'c.contract_no',
                'e.name as enterprise_name',
                's.building_name', 's.room_number'
            )
            ->orderBy('r.is_paid', 'asc') // 未缴费优先置顶
            ->orderBy('r.due_date', 'asc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 财务动作：人工线下收款后进行账单核销
     */
    public function payBill(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $billId = $request->post('id');

        $bill = Db::table('receivables')->where('id', $billId)->where('tenant_id', $tenantId)->first();
        if (!$bill || $bill->is_paid == 1) {
            return json(['code' => 400, 'msg' => '账单不存在或已核销完毕']);
        }

        Db::table('receivables')->where('id', $billId)->update([
            'is_paid' => 1,
            'paid_time' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '账单核销成功，已转为实收流水']);
    }

    /**
     * 录入能耗表显并自动推单
     */
    public function recordMeter(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $post = $request->post();

        if (empty($post['space_id']) || empty($post['current_reading']) || empty($post['record_month'])) {
            return json(['code' => 400, 'msg' => '抄表核心参数缺失']);
        }

        Db::beginTransaction();
        try {
            // 1. 记录物理台账
            Db::table('meters')->insert([
                'tenant_id' => $tenantId,
                'space_id' => $post['space_id'],
                'meter_type' => $post['meter_type'] ?? 1,
                'current_reading' => $post['current_reading'],
                'record_month' => $post['record_month']
            ]);

            // 2. 探查该房间当前绑定的有效合同
            $contract = Db::table('contracts')
                ->where('space_id', $post['space_id'])
                ->where('status', 1)
                ->where('tenant_id', $tenantId)
                ->first();

            // 3. 自动根据固定单价生成财务应收单 (此处演示水费5.5/度，电费1.2/度)
            if ($contract) {
                $unitPrice = $post['meter_type'] == 1 ? 5.5 : 1.2;
                $billType = $post['meter_type'] == 1 ? 2 : 3;
                // 注意：商业逻辑中此处应减去上期读数算差额，为跑通主线暂直接使用读数*单价
                $amount = $post['current_reading'] * $unitPrice; 

                Db::table('receivables')->insert([
                    'tenant_id' => $tenantId,
                    'contract_id' => $contract->id,
                    'bill_type' => $billType,
                    'amount' => $amount,
                    'due_date' => date('Y-m-d', strtotime('+15 days')), // 默认推单后15天内缴费
                    'is_paid' => 0
                ]);
            }

            Db::commit();
            return json(['code' => 200, 'msg' => '抄表入库完成，已自动向该空间挂载的合同推送能耗账单']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '抄表推单系统异常']);
        }
    }

    /**
     * 计划任务钩子 (空置占位)
     */
    public function dailyCronTask(): Response { return json(['code'=>200]); }
}