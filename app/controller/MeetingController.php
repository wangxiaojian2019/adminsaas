<?php
namespace app\controller;

use support\Request;
use support\Db;

class MeetingController
{
    // ==========================================
    // 会议室资产管理 (PC后台使用)
    // ==========================================

    public function roomList(Request $request)
    {
        $list = Db::table('meeting_rooms')->orderBy('id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function roomAdd(Request $request)
    {
        $name = $request->post('name');
        if (!$name) {
            return json(['code' => 400, 'msg' => '会议室名称不能为空']);
        }

        Db::table('meeting_rooms')->insert([
            'name' => $name,
            'capacity' => intval($request->post('capacity', 10)),
            'free_hours' => floatval($request->post('free_hours', 0)),
            'price_per_hour' => floatval($request->post('price_per_hour', 0)),
            'has_projector' => intval($request->post('has_projector', 0)),
            'has_video_conf' => intval($request->post('has_video_conf', 0)),
            'status' => $request->post('status', 'active'),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '添加成功']);
    }

    public function roomUpdate(Request $request)
    {
        $id = $request->post('id');
        if (!$id) {
            return json(['code' => 400, 'msg' => '缺少ID']);
        }

        Db::table('meeting_rooms')->where('id', $id)->update([
            'name' => $request->post('name'),
            'capacity' => intval($request->post('capacity', 10)),
            'free_hours' => floatval($request->post('free_hours', 0)),
            'price_per_hour' => floatval($request->post('price_per_hour', 0)),
            'has_projector' => intval($request->post('has_projector', 0)),
            'has_video_conf' => intval($request->post('has_video_conf', 0)),
            'status' => $request->post('status', 'active')
        ]);

        return json(['code' => 200, 'msg' => '更新成功']);
    }

    public function roomDelete(Request $request)
    {
        $id = $request->post('id');
        $hasBooking = Db::table('meeting_bookings')->where('room_id', $id)->where('status', '<', 2)->exists();
        if ($hasBooking) {
            return json(['code' => 403, 'msg' => '该会议室存在未完结的预订记录，禁止删除，建议将其状态设为停用']);
        }

        Db::table('meeting_rooms')->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }

    // ==========================================
    // 会议室预订订单审批与代客下单 (PC后台使用)
    // ==========================================

    public function bookingList(Request $request)
    {
        // 强制使用 enterprise_id 联表查询企业真实名称
        $list = Db::table('meeting_bookings as mb')
            ->join('meeting_rooms as mr', 'mb.room_id', '=', 'mr.id')
            ->leftJoin('enterprises as e', 'mb.enterprise_id', '=', 'e.id')
            ->select('mb.*', 'mr.name as room_name', 'e.name as enterprise_name')
            ->orderBy('mb.date', 'desc')
            ->orderBy('mb.start_time', 'desc')
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function apply(Request $request)
    {
        $enterpriseId = $request->post('enterprise_id');
        $roomId = $request->post('room_id');
        $date = $request->post('date');
        $startTime = $request->post('start_time');
        $endTime = $request->post('end_time');
        $topic = $request->post('topic', '内部会议');

        if (!$enterpriseId || !$roomId || !$date || !$startTime || !$endTime) {
            return json(['code' => 400, 'msg' => '缺少必要的预订参数']);
        }
        if (strtotime($startTime) >= strtotime($endTime)) {
            return json(['code' => 400, 'msg' => '逻辑错误：结束时间必须晚于开始时间']);
        }

        $conflict = Db::table('meeting_bookings')
            ->where('room_id', $roomId)
            ->where('date', $date)
            ->where('status', '<', 2)
            ->where(function($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)->where('end_time', '>', $startTime);
            })->exists();

        if ($conflict) {
            return json(['code' => 409, 'msg' => '时间冲突拦截：该时段会议室已被占用']);
        }

        $room = Db::table('meeting_rooms')->where('id', $roomId)->first();
        $durationHours = round((strtotime($endTime) - strtotime($startTime)) / 3600, 1);
        $freeHours = isset($room->free_hours) ? floatval($room->free_hours) : 0;
        $billableHours = max(0, $durationHours - $freeHours);
        $cost = $billableHours * $room->price_per_hour;

        Db::table('meeting_bookings')->insert([
            'booking_no' => 'MR' . date('YmdHis') . rand(100, 999),
            'enterprise_id' => $enterpriseId,
            'room_id' => $roomId,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration' => $durationHours,
            'cost' => $cost,
            'topic' => $topic,
            'status' => 0, 
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '代客预订成功']);
    }

    public function audit(Request $request)
    {
        $id = $request->post('id');
        $status = $request->post('status');

        if (!in_array($status, [1, 2, 3])) {
            return json(['code' => 400, 'msg' => '非法状态值']);
        }

        $booking = Db::table('meeting_bookings')->where('id', $id)->first();
        if (!$booking) return json(['code' => 404, 'msg' => '未找到该预订记录']);

        Db::beginTransaction();
        try {
            Db::table('meeting_bookings')->where('id', $id)->update([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            if ($status == 1 && $booking->cost > 0) {
                Db::table('receivables')->insert([
                    'tenant_id' => 1, 
                    'enterprise_id' => $booking->enterprise_id,
                    'space_id' => 0,
                    'bill_type' => 4, // 4代表场地服务费
                    'amount' => $booking->cost,
                    'is_paid' => 0,
                    'due_date' => date('Y-m-d', strtotime('+7 days')),
                    'remark' => "共享会议室使用费({$booking->date} {$booking->start_time}-{$booking->end_time})",
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            Db::commit();
            return json(['code' => 200, 'msg' => '审核操作已完成，资金流已同步']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '系统异常：' . $e->getMessage()]);
        }
    }
}