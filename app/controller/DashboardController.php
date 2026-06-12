<?php
namespace app\controller;

use support\Request;
use support\Db;

class DashboardController
{
    public function index(Request $request)
    {
        // 1. 统计资产空间数据
        $totalSpaces = Db::table('spaces')->count();
        $rentedSpaces = Db::table('spaces')->where('status', 1)->count();
        $vacancyRate = $totalSpaces > 0 ? round((($totalSpaces - $rentedSpaces) / $totalSpaces) * 100, 2) . '%' : '0%';

        // 2. 统计业财流水数据 (聚合查询 receivables 表)
        $totalReceivable = Db::table('receivables')->sum('amount') ?: 0;
        $totalReceived = Db::table('receivables')->where('is_paid', 1)->sum('amount') ?: 0;
        $totalUnpaid = Db::table('receivables')->where('is_paid', 0)->sum('amount') ?: 0;

        // 3. 抓取突发安全隐患 (读取未结单状态 status < 4 的工单)
        $activeOrders = Db::table('work_orders')
            ->where('status', '<', 4)
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();
            
        $patrolAlerts = [];
        foreach ($activeOrders as $order) {
            $patrolAlerts[] = [
                'location' => $order->title,         // 映射前端的 location 字段
                'remarks' => $order->description,    // 映射前端的 remarks 字段
                'worker_name' => $order->reporter_name, // 映射前端的 worker_name
                'check_time' => $order->created_at   // 映射前端的 check_time
            ];
        }

        // 4. 热力图基础数据查询
        $heatMap = Db::table('spaces')
            ->select('building_name', 'floor', 'room_number', 'status')
            ->get();

        return json([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'asset' => [
                    'total_spaces' => $totalSpaces,
                    'rented_spaces' => $rentedSpaces,
                    'vacancy_rate' => $vacancyRate
                ],
                'finance' => [
                    // 格式化为保留两位小数的字符串，适配前端展示
                    'total_receivable' => number_format($totalReceivable, 2, '.', ''),
                    'total_received' => number_format($totalReceived, 2, '.', ''),
                    'total_unpaid' => number_format($totalUnpaid, 2, '.', '')
                ],
                'patrol_alerts' => $patrolAlerts,
                'heat_map' => $heatMap
            ]
        ]);
    }
}