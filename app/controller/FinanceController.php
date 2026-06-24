<?php
namespace app\controller;

use support\Request;
use support\Db;

class FinanceController
{
    // ==========================================
    // 1. 应收账单分页大盘
    // ==========================================
    public function receivableList(Request $request)
    {
        $page = (int)$request->get('page', 1);
        $pageSize = (int)$request->get('limit', 15);
        $tenantId = $request->tenantId ?? 1;

        $query = Db::table('receivables as r')
            ->where('r.tenant_id', $tenantId)
            ->leftJoin('enterprises as e', 'r.enterprise_id', '=', 'e.id')
            ->leftJoin('spaces as s', 'r.space_id', '=', 's.id')
            ->select('r.*', 'e.name as enterprise_name', 's.building_name', 's.room_number')
            ->orderByRaw("CASE WHEN r.is_paid = 2 THEN 0 WHEN r.is_paid = 0 THEN 1 WHEN r.is_paid = 3 THEN 2 ELSE 3 END")
            ->orderBy('r.due_date', 'asc');

        if ($request->get('enterprise_name')) {
            $query->where('e.name', 'like', '%' . $request->get('enterprise_name') . '%');
        }

        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        return json([
            'code' => 200, 
            'msg' => 'success', 
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage()
            ]
        ]);
    }

    // ==========================================
    // 2. 财务流水提取与智能核销引擎
    // ==========================================
    public function transactions(Request $request) 
    {
        $billId = $request->get('bill_id');
        $tenantId = $request->tenantId ?? 1;
        $list = Db::table('payment_transactions')
            ->where('receivable_id', $billId)
            ->where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function auditTransaction(Request $request) 
    {
        $transId = $request->post('transaction_id');
        $status = $request->post('status'); // 1: 凭证无误确认核销, 2: 凭证异常驳回
        $rejectReason = $request->post('reject_reason', '');
        
        Db::beginTransaction();
        try {
            $trans = Db::table('payment_transactions')->where('id', $transId)->lockForUpdate()->first();
            if (!$trans || $trans->status != 0) {
                Db::rollBack();
                return json(['code' => 400, 'msg' => '拦截：该笔流水已被其他财务人员处理或不存在']);
            }

            $bill = Db::table('receivables')->where('id', $trans->receivable_id)->lockForUpdate()->first();

            // 更新流水的生命周期
            Db::table('payment_transactions')->where('id', $transId)->update([
                'status' => $status,
                'reject_reason' => $rejectReason,
                'audit_time' => date('Y-m-d H:i:s')
            ]);

            if ($status == 1) {
                // 核销通过，资金入账
                $newPaid = $bill->paid_amount + $trans->pay_amount;
                
                // 智能判定该账单是否已全部结清
                $isPaid = ($newPaid >= $bill->amount) ? 1 : ($bill->is_paid == 2 ? 0 : $bill->is_paid);
                
                // 防呆：如果没有其他待审核的流水，且账单没结清，恢复为未支付(0)
                $pendingCount = Db::table('payment_transactions')->where('receivable_id', $bill->id)->where('status', 0)->count();
                if ($isPaid != 1 && $pendingCount == 0) {
                    $isPaid = 0; 
                }

                Db::table('receivables')->where('id', $bill->id)->update([
                    'paid_amount' => $newPaid,
                    'is_paid' => $isPaid,
                    'paid_time' => $isPaid == 1 ? date('Y-m-d H:i:s') : $bill->paid_time,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            } else {
                // 驳回
                $pendingCount = Db::table('payment_transactions')->where('receivable_id', $bill->id)->where('status', 0)->count();
                if ($pendingCount == 0 && $bill->is_paid != 1) {
                    Db::table('receivables')->where('id', $bill->id)->update([
                        'is_paid' => 3, // 3代表有流水被驳回且需重新上传
                        'reject_reason' => $rejectReason,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            Db::commit();
            return json(['code' => 200, 'msg' => '账务流水对账完毕，账单状态已自动同步']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '核销失败：底层事务回滚']);
        }
    }

    // ==========================================
    // 3. 退租清算与能耗大盘
    // ==========================================
    public function checkoutList(Request $request)
    {
        $page = (int)$request->get('page', 1);
        $pageSize = (int)$request->get('limit', 15);
        $tenantId = $request->tenantId ?? 1;

        $query = Db::table('checkouts as c')
            ->where('c.tenant_id', $tenantId)
            ->leftJoin('enterprises as e', 'c.enterprise_id', '=', 'e.id')
            ->leftJoin('contracts as ct', 'c.contract_id', '=', 'ct.id')
            ->select('c.*', 'e.name as enterprise_name', 'ct.contract_no')
            ->orderBy('c.status', 'asc')
            ->orderBy('c.id', 'desc');

        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        return json([
            'code' => 200, 'msg' => 'success', 'data' => $paginator->items(),
            'meta' => ['total' => $paginator->total()]
        ]);
    }

    public function payCheckout(Request $request)
    {
        $id = $request->post('id');
        Db::table('checkouts')->where('id', $id)->update(['status' => 1, 'paid_time' => date('Y-m-d H:i:s')]);
        return json(['code' => 200, 'msg' => '财务打款已结清']);
    }

    public function meterList(Request $request)
    {
        $page = (int)$request->get('page', 1);
        $pageSize = (int)$request->get('limit', 15);
        $tenantId = $request->tenantId ?? 1;

        $query = Db::table('spaces')
            ->where('spaces.tenant_id', $tenantId)
            ->where('spaces.status', '>', 0)
            ->where('spaces.is_deleted', 0) // 过滤软删除的物理空间
            ->leftJoin('contracts', function($join) {
                $join->on('spaces.id', '=', 'contracts.space_id')
                     ->where('contracts.status', '=', 1);
            })
            ->leftJoin('enterprises', 'contracts.enterprise_id', '=', 'enterprises.id')
            ->select(
                'spaces.id as space_id', 'spaces.building_name', 'spaces.room_number', 
                'spaces.water_meter as current_water', 'spaces.electric_meter as current_electric',
                'enterprises.id as enterprise_id', 'enterprises.name as enterprise_name'
            )
            ->orderBy('spaces.building_name')
            ->orderBy('spaces.room_number');

        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        return json([
            'code' => 200, 'msg' => 'success', 'data' => $paginator->items(),
            'meta' => ['total' => $paginator->total()]
        ]);
    }

    public function recordMeter(Request $request)
    {
        $spaceId = $request->post('space_id');
        $enterpriseId = $request->post('enterprise_id');
        $type = $request->post('type'); // 1水表 2电表
        $newReading = $request->post('reading');
        $month = $request->post('month', date('Y-m'));

        $space = Db::table('spaces')->where('id', $spaceId)->first();
        $oldReading = $type == 1 ? $space->water_meter : $space->electric_meter;

        if ($newReading < $oldReading) return json(['code' => 400, 'msg' => '新表底数不能小于旧表底数']);

        Db::beginTransaction();
        try {
            $usage = $newReading - $oldReading;
            Db::table('meters')->insert([
                'tenant_id' => $space->tenant_id, 'space_id' => $spaceId, 'meter_type' => $type,
                'current_reading' => $newReading, 'last_reading' => $oldReading,
                'usage_amount' => $usage, 'record_month' => $month, 'created_at' => date('Y-m-d H:i:s')
            ]);

            $updateField = $type == 1 ? 'water_meter' : 'electric_meter';
            Db::table('spaces')->where('id', $spaceId)->update([$updateField => $newReading]);

            if ($enterpriseId) {
                $feeConfig = Db::table('system_configs')->where('config_key', 'fee_config')->value('config_value');
                $rates = $feeConfig ? json_decode($feeConfig, true) : ['waterPrice' => 5.5, 'electricityPrice' => 1.2];
                $price = $type == 1 ? $rates['waterPrice'] : $rates['electricityPrice'];
                $amount = $usage * $price;
                
                if ($amount > 0) {
                    Db::table('receivables')->insert([
                        'tenant_id' => $space->tenant_id, 'enterprise_id' => $enterpriseId, 'space_id' => $spaceId,
                        'bill_type' => $type == 1 ? 2 : 3, 'amount' => $amount, 'due_date' => date('Y-m-t'), 'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            Db::commit();
            return json(['code' => 200, 'msg' => '抄表成功并已自动生成本期能耗账单']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '异常：' . $e->getMessage()]);
        }
    }

    public function meterHistory(Request $request)
    {
        $spaceId = $request->get('space_id');
        $type = $request->get('type');
        $list = Db::table('meters')->where('space_id', $spaceId)->where('meter_type', $type)->orderBy('id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }
}