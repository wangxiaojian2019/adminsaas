<?php
namespace app\controller;

use support\Request;
use support\Db;

class TenantPortalController
{
    public function getOverview(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $enterprise = Db::table('enterprises')->where('id', $enterpriseId)->first();
        if (!$enterprise) return json(['code' => 404, 'msg' => '企业主体不存在']);

        $activeContracts = Db::table('contracts')
            ->leftJoin('spaces', 'contracts.space_id', '=', 'spaces.id')
            ->where('contracts.enterprise_id', $enterpriseId)
            ->where('contracts.status', 1)
            ->select('contracts.*', 'spaces.building_name', 'spaces.room_number', 'spaces.floor', 'spaces.area')
            ->orderBy('contracts.id', 'asc') 
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => ['enterprise' => $enterprise, 'contracts' => $activeContracts ]]);
    }

    public function getBills(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $bills = Db::table('receivables')
            ->where('enterprise_id', $enterpriseId)
            ->orderByRaw("CASE WHEN is_paid = 3 THEN 0 WHEN is_paid = 0 THEN 1 WHEN is_paid = 2 THEN 2 ELSE 3 END")
            ->orderBy('due_date', 'asc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $bills]);
    }

    public function getContracts(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $contracts = Db::table('contracts')->where('enterprise_id', $enterpriseId)->orderBy('status', 'desc')->orderBy('id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $contracts]);
    }

    // 核心重构：写入流水表而非覆盖账单表
    public function payBill(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $billId = $request->post('bill_id');
        $receiptUrl = $request->post('receipt_url');
        if (!$receiptUrl) return json(['code' => 400, 'msg' => '必须上传打款凭证照片或截图']);

        Db::beginTransaction();
        try {
            $bill = Db::table('receivables')->where('id', $billId)->where('enterprise_id', $enterpriseId)->lockForUpdate()->first();
            if (!$bill) {
                Db::rollBack();
                return json(['code' => 404, 'msg' => '账单防越权校验失败']);
            }
            if ($bill->is_paid == 1) {
                Db::rollBack();
                return json(['code' => 400, 'msg' => '该账单已彻底结清，无需再次上传']);
            }

            // 计算该账单还剩多少钱没给（本次流水的金额标的）
            $remainAmount = max(0, $bill->amount - $bill->paid_amount);

            Db::table('payment_transactions')->insert([
                'tenant_id' => $bill->tenant_id,
                'receivable_id' => $bill->id,
                'enterprise_id' => $enterpriseId,
                'pay_amount' => $remainAmount, 
                'receipt_url' => $receiptUrl,
                'status' => 0, // 0待审核
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // 将账单主表状态置为2(核销中)，防范前端重复提示支付
            Db::table('receivables')->where('id', $billId)->update([
                'is_paid' => 2, 
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            Db::commit();
            return json(['code' => 200, 'msg' => '凭证已安全上传，请等待财务对账核销']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '流水写入异常']);
        }
    }

    public function submitOrder(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $title = $request->post('title');
        if (!$title) return json(['code' => 400, 'msg' => '故障简述必填']);
        Db::table('work_orders')->insert([
            'enterprise_id' => $enterpriseId, 'title' => $title, 'description' => $request->post('description', ''),
            'image_url' => $request->post('image_url', ''), 'status' => 1, 'created_at' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => '工单已流转至中控调度室']);
    }

    public function updatePassword(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $enterprise = Db::table('enterprises')->where('id', $enterpriseId)->first();
        if ($enterprise->password !== md5($request->post('old_password'))) return json(['code' => 400, 'msg' => '原密码不正确']);

        Db::table('enterprises')->where('id', $enterpriseId)->update(['password' => md5($request->post('new_password')), 'updated_at' => date('Y-m-d H:i:s')]);
        return json(['code' => 200, 'msg' => '密码更新成功']);
    }

    public function getInventory(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $ent = Db::table('enterprises')->where('id', $enterpriseId)->first();
        if (!$ent) return json(['code' => 404, 'msg' => '企业不存在']);

        $list = Db::table('inventory_records')
            ->join('inventory_items', 'inventory_records.item_id', '=', 'inventory_items.id')
            ->where('inventory_records.related_person', 'like', "%{$ent->name}%")
            ->select('inventory_records.*', 'inventory_items.name as item_name', 'inventory_items.unit', 'inventory_items.category')
            ->orderBy('inventory_records.id', 'desc')
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function getDecorations(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $list = Db::table('decorations as d')
            ->leftJoin('spaces as s', 'd.space_id', '=', 's.id')
            ->where('d.enterprise_id', $enterpriseId)
            ->select('d.*', 's.building_name', 's.floor', 's.room_number')
            ->orderBy('d.id', 'desc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function applyDecoration(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $spaceId = $request->post('space_id');
        $startDate = $request->post('start_date');
        $endDate = $request->post('end_date');
        $manager = $request->post('manager', '');

        if (!$spaceId || !$startDate || !$endDate) return json(['code' => 400, 'msg' => '请填写完整的工期数据']);

        $owns = Db::table('contracts')->where('enterprise_id', $enterpriseId)->where('space_id', $spaceId)->where('status', 1)->exists();
        if (!$owns) return json(['code' => 403, 'msg' => '越权拦截：只能为您名下生效承租的房源提交报备']);

        $totalDays = max(1, intval((strtotime($endDate) - strtotime($startDate)) / 86400 + 1));
        Db::table('decorations')->insert([
            'apply_no' => 'ZX' . date('YmdHis') . rand(1000, 9999),
            'enterprise_id' => $enterpriseId, 'space_id' => $spaceId, 'start_date' => $startDate,
            'end_date' => $endDate, 'total_days' => $totalDays, 'status' => 0, 'deposit' => 0, 
            'manager' => $manager, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        return json(['code' => 200, 'msg' => '报备已成功提交至总控中心，请等待审批']);
    }

    public function getMeetingRooms(Request $request)
    {
        $rooms = Db::table('meeting_rooms')->where('status', '!=', 'disabled')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $rooms]);
    }

    public function getMyMeetings(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $list = Db::table('meeting_bookings as mb')
            ->join('meeting_rooms as mr', 'mb.room_id', '=', 'mr.id')
            ->where('mb.enterprise_id', $enterpriseId)
            ->select('mb.*', 'mr.name as room_name', 'mr.has_projector', 'mr.has_video_conf')
            ->orderBy('mb.date', 'desc')->orderBy('mb.start_time', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function applyMeeting(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $roomId = $request->post('room_id');
        $date = $request->post('date');
        $startTime = $request->post('start_time');
        $endTime = $request->post('end_time');
        $topic = $request->post('topic', '内部会议');

        if (!$roomId || !$date || !$startTime || !$endTime) return json(['code' => 400, 'msg' => '缺少必要的预订参数']);
        if (strtotime($startTime) >= strtotime($endTime)) return json(['code' => 400, 'msg' => '逻辑错误：结束时间必须晚于开始时间']);

        $conflict = Db::table('meeting_bookings')->where('room_id', $roomId)->where('date', $date)->where('status', '<', 2) 
            ->where(function($q) use ($startTime, $endTime) {
                $q->where(function($q1) use ($startTime, $endTime) {
                    $q1->where('start_time', '<', $endTime)->where('end_time', '>', $startTime);
                });
            })->exists();

        if ($conflict) return json(['code' => 409, 'msg' => '抱歉冲突，该时段会议室已被他人预订，请更换时间']);

        $room = Db::table('meeting_rooms')->where('id', $roomId)->first();
        $durationHours = round((strtotime($endTime) - strtotime($startTime)) / 3600, 1);
        $freeHours = isset($room->free_hours) ? floatval($room->free_hours) : 0;
        $billableHours = max(0, $durationHours - $freeHours);
        $cost = $billableHours * $room->price_per_hour;

        Db::table('meeting_bookings')->insert([
            'booking_no' => 'MR' . date('YmdHis') . rand(100, 999),
            'enterprise_id' => $enterpriseId, 'room_id' => $roomId, 'date' => $date,
            'start_time' => $startTime, 'end_time' => $endTime, 'duration' => $durationHours,
            'cost' => $cost, 'topic' => $topic, 'status' => 0, 'created_at' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => '预订成功进入审核池，可在服务追踪面板查看']);
    }
}