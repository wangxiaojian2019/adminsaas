<?php
namespace app\controller;

use support\Request;
use support\Response;
use support\Db;

class PropertyController
{
    /**
     * 获取可视化大屏核心统计指标
     */
    public function getDashboardData(Request $request): Response
    {
        $totalSpaces = Db::table('spaces')->count();
        $rentedSpaces = Db::table('spaces')->where('status', 1)->count();
        $vacancyRate = $totalSpaces > 0 ? round((($totalSpaces - $rentedSpaces) / $totalSpaces) * 100, 2) : 100;

        $totalReceivable = Db::table('receivables')->sum('amount') ?? 0;
        $totalPaid = Db::table('receivables')->where('is_paid', 1)->sum('amount') ?? 0;
        $unpaid = Db::table('receivables')->where('is_paid', 0)->sum('amount') ?? 0;

        $billTypeMapping = [1 => '租金', 2 => '水费', 3 => '电费', 4 => '物业费'];
        $typeData = Db::table('receivables')
            ->select('bill_type', Db::raw('SUM(amount) as total'))
            ->groupBy('bill_type')
            ->get();

        $pieData = [];
        foreach ($typeData as $item) {
            $pieData[] = [
                'name' => $billTypeMapping[$item->bill_type] ?? '其他',
                'value' => (float)$item->total
            ];
        }

        return json([
            'code' => 200, 'msg' => 'success',
            'data' => [
                'cards' => [
                    'total_spaces' => $totalSpaces, 'rented_spaces' => $rentedSpaces, 'vacancy_rate' => $vacancyRate . '%',
                    'total_receivable' => number_format($totalReceivable, 2), 'total_paid' => number_format($totalPaid, 2), 'unpaid' => number_format($unpaid, 2)
                ],
                'pie_chart' => $pieData
            ]
        ]);
    }

    /**
     * [新增] 获取空间分布情况 (左侧楼宇列表，右侧房间网格数据)
     */
    public function getSpaceDistribution(Request $request): Response
    {
        $spaces = Db::table('spaces')->orderBy('floor', 'asc')->orderBy('room_number', 'asc')->get();
        $distribution = [];
        $buildings = [];

        foreach ($spaces as $space) {
            $building = $space->building_name;
            if (!in_array($building, $buildings)) {
                $buildings[] = $building;
            }
            if (!isset($distribution[$building])) {
                $distribution[$building] = [];
            }
            // 按楼层分组房间
            if (!isset($distribution[$building][$space->floor])) {
                $distribution[$building][$space->floor] = [];
            }
            $distribution[$building][$space->floor][] = $space;
        }

        return json(['code' => 200, 'msg' => 'success', 'data' => ['buildings' => $buildings, 'distribution' => $distribution]]);
    }

    /**
     * [新增] 获取楼宇管理聚合列表
     */
    public function getBuildings(Request $request): Response
    {
        $list = Db::table('spaces')
            ->select('building_name', Db::raw('COUNT(id) as room_count'), Db::raw('SUM(area) as total_area'))
            ->groupBy('building_name')
            ->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * [新增] 获取楼层管理聚合列表
     */
    public function getFloors(Request $request): Response
    {
        $list = Db::table('spaces')
            ->select('building_name', 'floor', Db::raw('COUNT(id) as room_count'), Db::raw('SUM(area) as total_area'))
            ->groupBy('building_name', 'floor')
            ->orderBy('building_name', 'asc')
            ->orderBy('floor', 'asc')
            ->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 获取房源空间(房间)明细列表
     */
    public function getSpaces(Request $request): Response
    {
        $list = Db::table('spaces')->orderBy('id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function addSpace(Request $request): Response
    {
        $building = $request->post('building_name');
        $floor = $request->post('floor');
        $room = $request->post('room_number');
        $area = $request->post('area');

        if (empty($building) || empty($floor) || empty($room) || empty($area)) {
            return json(['code' => 400, 'msg' => '缺少必要参数']);
        }

        Db::table('spaces')->insert([
            'building_name' => $building, 'floor' => $floor, 'room_number' => $room, 'area' => $area, 'status' => 0
        ]);
        return json(['code' => 200, 'msg' => '房源录入成功']);
    }

    public function getContracts(Request $request): Response
    {
        $list = Db::table('contracts as c')
            ->join('enterprises as e', 'c.enterprise_id', '=', 'e.id')
            ->join('spaces as s', 'c.space_id', '=', 's.id')
            ->select('c.*', 'e.name as enterprise_name', 's.building_name', 's.room_number')
            ->orderBy('c.id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function addContract(Request $request): Response
    {
        $contractNo = 'HT-' . time();
        $enterpriseName = $request->post('enterprise_name');
        $contact = $request->post('contact_person');
        $phone = $request->post('phone');
        $spaceId = $request->post('space_id');
        $startDate = $request->post('start_date');
        $endDate = $request->post('end_date');
        $rent = $request->post('monthly_rent');
        $deposit = $request->post('deposit');

        if (empty($enterpriseName) || empty($spaceId) || empty($startDate) || empty($endDate) || empty($rent)) {
            return json(['code' => 400, 'msg' => '参数不完整']);
        }

        Db::beginTransaction();
        try {
            $enterpriseId = Db::table('enterprises')->insertGetId([
                'name' => $enterpriseName, 'contact_person' => $contact, 'phone' => $phone, 'status' => 1
            ]);
            Db::table('contracts')->insert([
                'contract_no' => $contractNo, 'enterprise_id' => $enterpriseId, 'space_id' => $spaceId,
                'start_date' => $startDate, 'end_date' => $endDate, 'monthly_rent' => $rent, 'deposit' => $deposit, 'status' => 1
            ]);
            Db::table('spaces')->where('id', $spaceId)->update(['status' => 1]);
            Db::table('receivables')->insert([
                'enterprise_id' => $enterpriseId, 'bill_type' => 1, 'amount' => $rent,
                'due_date' => date('Y-m-d', strtotime('+7 days')), 'is_paid' => 0
            ]);
            Db::commit();
            return json(['code' => 200, 'msg' => '合同签署与入住手续办理成功']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '系统错误: ' . $e->getMessage()]);
        }
    }

    public function getMeters(Request $request): Response
    {
        $list = Db::table('utility_meters as m')
            ->join('enterprises as e', 'm.enterprise_id', '=', 'e.id')
            ->join('spaces as s', 'm.space_id', '=', 's.id')
            ->select('m.*', 'e.name as enterprise_name', 's.building_name', 's.room_number')
            ->orderBy('m.id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function recordMeter(Request $request): Response
    {
        $spaceId = $request->post('space_id');
        $meterType = $request->post('meter_type');
        $currentReading = $request->post('current_reading');

        if (empty($spaceId) || empty($meterType) || isset($currentReading) === false) {
            return json(['code' => 400, 'msg' => '必要参数缺失']);
        }

        $contract = Db::table('contracts')->where('space_id', $spaceId)->where('status', 1)->first();
        if (!$contract) return json(['code' => 404, 'msg' => '当前空间无有效在租企业，无需抄表']);

        $lastRecord = Db::table('utility_meters')->where('space_id', $spaceId)->where('meter_type', $meterType)->orderBy('id', 'desc')->first();
        $previousReading = $lastRecord ? $lastRecord->current_reading : 0.00;

        if ($currentReading < $previousReading) return json(['code' => 400, 'msg' => "读数不能小于历史读数 ({$previousReading})"]);

        $usage = $currentReading - $previousReading;
        $unitPrice = ($meterType == 1) ? 4.50 : 1.20;
        $billAmount = round($usage * $unitPrice, 2);

        Db::beginTransaction();
        try {
            Db::table('utility_meters')->insert([
                'enterprise_id' => $contract->enterprise_id, 'space_id' => $spaceId, 'meter_type' => $meterType,
                'previous_reading' => $previousReading, 'current_reading' => $currentReading, 'record_date' => date('Y-m-d')
            ]);
            if ($billAmount > 0) {
                Db::table('receivables')->insert([
                    'enterprise_id' => $contract->enterprise_id, 'bill_type' => ($meterType == 1) ? 2 : 3,
                    'amount' => $billAmount, 'due_date' => date('Y-m-d', strtotime('+10 days')), 'is_paid' => 0
                ]);
            }
            Db::commit();
            return json(['code' => 200, 'msg' => '抄表成功，已自动核算费用并生成账单']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '系统错误: ' . $e->getMessage()]);
        }
    }

    public function getReceivables(Request $request): Response
    {
        $list = Db::table('receivables as r')
            ->join('enterprises as e', 'r.enterprise_id', '=', 'e.id')
            ->select('r.*', 'e.name as enterprise_name')
            ->orderBy('r.id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function payBill(Request $request): Response
    {
        $id = $request->post('id');
        if (empty($id)) return json(['code' => 400, 'msg' => '未指定销账单ID']);
        Db::table('receivables')->where('id', $id)->update(['is_paid' => 1, 'paid_date' => date('Y-m-d H:i:s')]);
        return json(['code' => 200, 'msg' => '账单销账核销成功']);
    }
}