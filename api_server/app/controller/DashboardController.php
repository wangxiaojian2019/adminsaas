<?php
namespace app\controller;

use support\Request;
use support\Response;
use support\Db;

class DashboardController
{
    public function getIndex(Request $request): Response
    {
        try {
            // 强兼容性降级：如果未获取到登录态租户ID，强制降级为 1 读取沙盘数据
            $tenantId = $request->tenant_id ?: 1;

            // ================= 1. 资产与销控数据 =================
            $totalSpaces = Db::table('spaces')->where('tenant_id', $tenantId)->count() ?: 0;
            $rentedSpaces = Db::table('spaces')->where('tenant_id', $tenantId)->where('status', 1)->count() ?: 0;
            $vacancyRate = $totalSpaces > 0 ? round((($totalSpaces - $rentedSpaces) / $totalSpaces) * 100, 2) : 0;

            // ================= 2. 业财数据透视 =================
            $totalReceivable = Db::table('receivables')->where('tenant_id', $tenantId)->sum('amount') ?: 0;
            $totalReceived = Db::table('receivables')->where('tenant_id', $tenantId)->where('is_paid', 1)->sum('amount') ?: 0;
            $totalUnpaid = Db::table('receivables')->where('tenant_id', $tenantId)->where('is_paid', 0)->sum('amount') ?: 0;

            // ================= 3. 楼宇物理网格热力图 =================
            $heatMapData = Db::table('spaces')
                ->where('tenant_id', $tenantId)
                ->select('building_name', 'floor', 'room_number', 'status')
                ->orderBy('building_id', 'asc')
                ->orderBy('floor', 'asc')
                ->get();

            // ================= 4. 安防巡检异常告警 =================
            $patrolAlerts = Db::table('patrol_records as r')
                ->join('patrol_points as p', 'r.point_id', '=', 'p.id')
                ->where('r.tenant_id', $tenantId)
                ->where('r.is_normal', 0)
                ->select(
                    'r.created_at as check_time',
                    'r.operator_name as worker_name',
                    'r.remark as remarks',
                    'p.point_name as point_name',
                    'p.point_name as location'
                )
                ->orderBy('r.id', 'desc')
                ->limit(5)
                ->get();

            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => [
                    'asset' => [
                        'total_spaces' => $totalSpaces,
                        'rented_spaces' => $rentedSpaces,
                        'vacancy_rate' => $vacancyRate . '%',
                    ],
                    'finance' => [
                        'total_receivable' => number_format((float)$totalReceivable, 2, '.', ''),
                        'total_received' => number_format((float)$totalReceived, 2, '.', ''),
                        'total_unpaid' => number_format((float)$totalUnpaid, 2, '.', ''),
                    ],
                    'heat_map' => $heatMapData,
                    'patrol_alerts' => $patrolAlerts
                ]
            ]);
            
        } catch (\Throwable $e) {
            // 【核心防御】：一旦发生底层断层，不抛500崩溃，返回友好的结构化空数据，并暴露真实死因
            return json([
                'code' => 500,
                'msg' => '底层链路被强行阻断: ' . $e->getMessage() . ' | 发生于第 ' . $e->getLine() . ' 行',
                'data' => [
                    'asset' => ['total_spaces' => 0, 'rented_spaces' => 0, 'vacancy_rate' => '0%'],
                    'finance' => ['total_receivable' => '0.00', 'total_received' => '0.00', 'total_unpaid' => '0.00'],
                    'heat_map' => [],
                    'patrol_alerts' => []
                ]
            ]);
        }
    }
}