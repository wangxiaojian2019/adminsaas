<?php
namespace app\controller;

use support\Request;
use support\Db;

class ReportController
{
    public function financeStats(Request $request)
    {
        // 逆向生成近 6 个月时间轴作为计算基准
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = date('Y-m', strtotime("-$i month"));
        }

        $trendData = [];
        foreach ($months as $month) {
            // 穿透当月账单池，计算应收与实收核销净额
            $stats = Db::table('receivables')
                ->where('due_date', 'like', $month . '%')
                ->selectRaw('COALESCE(sum(amount), 0) as total, COALESCE(sum(case when is_paid=1 then amount else 0 end), 0) as paid')
                ->first();
                
            $trendData[] = [
                'month' => $month,
                'total' => round($stats->total, 2),
                'paid' => round($stats->paid, 2)
            ];
        }

        return json(['code' => 200, 'msg' => 'success', 'data' => $trendData]);
    }

    public function assetStats(Request $request)
    {
        // 穿透计算各大厦的物理空间占比与去化率
        $buildings = Db::table('spaces')
            ->selectRaw('building_name, count(*) as total, sum(case when status=1 then 1 else 0 end) as rented')
            ->groupBy('building_name')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $buildings]);
    }

    public function leadStats(Request $request)
    {
        // 招商漏斗流量穿透
        $stats = Db::table('leads')
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->toArray();

        $total = Db::table('leads')->count();
        
        $funnel = [
            'total' => $total, // 100% 流量池
            'following' => isset($stats[1]) ? clone $stats[1] : (object)['count' => 0], // 跟进转化中
            'won' => isset($stats[2]) ? clone $stats[2] : (object)['count' => 0], // 签约落地
            'lost' => isset($stats[3]) ? clone $stats[3] : (object)['count' => 0], // 流失沉默
        ];

        return json([
            'code' => 200, 
            'msg' => 'success', 
            'data' => [
                'total' => $funnel['total'],
                'following' => $funnel['following']->count,
                'won' => $funnel['won']->count,
                'lost' => $funnel['lost']->count
            ]
        ]);
    }
}