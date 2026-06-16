<?php
namespace app\controller;

use support\Request;
use support\Db;

class DashboardController
{
    public function index(Request $request)
    {
        // 1. 空间资产去化率推演
        $spaceStats = Db::table('spaces')
            ->selectRaw('count(*) as total, sum(case when status=1 then 1 else 0 end) as rented, sum(case when status=0 then 1 else 0 end) as vacant, sum(case when status in (2,3) then 1 else 0 end) as maintain')
            ->first();

        // 2. 业财资金归集盘 (全量流水核对)
        $financeStats = Db::table('receivables')
            ->selectRaw('COALESCE(sum(amount), 0) as total_receivable, COALESCE(sum(case when is_paid=1 then amount else 0 end), 0) as actual_received')
            ->first();

        // 3. 户籍与契约活跃度
        $enterpriseCount = Db::table('enterprises')->count();
        $activeContracts = Db::table('contracts')->where('status', 1)->count();

        // 4. 后勤调度预警：提取中控室亟需处理的工单 (待指派、待验)
        $urgentOrders = Db::table('work_orders')
            ->whereIn('status', [1, 3])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // 5. 财务核销预警：强制使用 created_at 替代 updated_at 规避 1054 报错
        $pendingBills = Db::table('receivables')
            ->leftJoin('enterprises', 'receivables.enterprise_id', '=', 'enterprises.id')
            ->where('is_paid', 2)
            ->select('receivables.id', 'receivables.amount', 'enterprises.name as enterprise_name', 'receivables.created_at')
            ->orderBy('receivables.created_at', 'desc')
            ->limit(5)
            ->get();

        return json([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'space' => $spaceStats,
                'finance' => $financeStats,
                'enterprise_count' => $enterpriseCount,
                'contract_count' => $activeContracts,
                'urgent_orders' => $urgentOrders,
                'pending_bills' => $pendingBills
            ]
        ]);
    }
}