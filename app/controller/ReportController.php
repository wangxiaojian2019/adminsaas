<?php
namespace app\controller;

use support\Request;
use support\Db;

class ReportController
{
    /**
     * 财务月度营收同环比与收缴率漏斗
     */
    public function financeStats(Request $request)
    {
        $list = Db::table('receivables')
            ->select(
                Db::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                Db::raw("SUM(amount) as total_receivable"),
                Db::raw("SUM(CASE WHEN is_paid = 1 THEN amount ELSE 0 END) as total_received")
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 招商渠道漏斗与转化结构分析
     */
    public function leadStats(Request $request)
    {
        // 渠道获客占比
        $sourceStats = Db::table('leads')
            ->select('source', Db::raw('count(*) as count'))
            ->groupBy('source')
            ->get();

        // 线索状态深度
        $statusStats = Db::table('leads')
            ->select('status', Db::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        return json([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'source' => $sourceStats,
                'status' => $statusStats
            ]
        ]);
    }

    /**
     * 空间资产去化率与面积留存结构
     */
    public function assetStats(Request $request)
    {
        $statusStats = Db::table('spaces')
            ->select('status', Db::raw('count(*) as count'), Db::raw('SUM(area) as total_area'))
            ->groupBy('status')
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $statusStats]);
    }
}