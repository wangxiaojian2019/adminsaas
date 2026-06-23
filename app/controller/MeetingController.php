<?php
namespace app\controller;

use support\Request;
use support\Db;

class MeetingController
{
    public function roomList(Request $request)
    {
        $rooms = Db::table('meeting_rooms')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $rooms]);
    }

    public function bookingList(Request $request)
    {
        $bookings = Db::table('meeting_bookings')
            ->join('meeting_rooms', 'meeting_bookings.room_id', '=', 'meeting_rooms.id')
            ->select('meeting_bookings.*', 'meeting_rooms.name as room_name')
            ->orderBy('meeting_bookings.id', 'desc')
            ->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $bookings]);
    }

    /**
     * 预订会议室 (核心：防重叠算法与免额计费)
     */
    public function apply(Request $request)
    {
        $data = $request->post();
        $roomId = $data['room_id'];
        $date = $data['date'];
        $startTime = $data['start_time'];
        $endTime = $data['end_time'];

        $room = Db::table('meeting_rooms')->where('id', $roomId)->first();
        if (!$room) return json(['code' => 404, 'msg' => '会议室不存在']);

        // 1. 防冲突重叠校验 (SQL层校验：同一天、同一房间、非驳回状态、时间有交集)
        $conflict = Db::table('meeting_bookings')
            ->where('room_id', $roomId)
            ->where('date', $date)
            ->where('status', '!=', 2)
            ->where(function($query) use ($startTime, $endTime) {
                // 新开始时间 < 旧结束时间 AND 新结束时间 > 旧开始时间
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            })->first();

        if ($conflict) {
            return json([
                'code' => 409, 
                'msg' => "时间冲突拦截！已被单号 {$conflict->booking_no} 占用 ({$conflict->start_time}-{$conflict->end_time})"
            ]);
        }

        // 2. 计费引擎计算 (免首2小时逻辑)
        $startTs = strtotime("{$date} {$startTime}:00");
        $endTs = strtotime("{$date} {$endTime}:00");
        $duration = round(($endTs - $startTs) / 3600, 1);
        
        if ($duration <= 0) return json(['code' => 400, 'msg' => '结束时间必须大于开始时间']);

        $chargeableHours = max(0, $duration - 2); // 减去2小时免费额度
        $cost = round($chargeableHours * $room->price_per_hour, 2);

        // 3. 落地落盘
        $bookingNo = 'MT' . date('YmdHis') . rand(10, 99);
        Db::table('meeting_bookings')->insert([
            'booking_no' => $bookingNo,
            'enterprise_name' => $data['enterprise_name'],
            'room_id' => $roomId,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration' => $duration,
            'cost' => $cost,
            'topic' => $data['topic'] ?? '',
            'status' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => "预订成功。总时长{$duration}H, 系统扣减免额后计费 ￥{$cost}"]);
    }

    /**
     * 审核预订 (核心：联动财务账单)
     */
    public function audit(Request $request)
    {
        $id = $request->post('id');
        $status = $request->post('status'); // 1:同意, 2:驳回

        $booking = Db::table('meeting_bookings')->where('id', $id)->first();
        if (!$booking) return json(['code' => 404, 'msg' => '记录不存在']);

        Db::beginTransaction();
        try {
            Db::table('meeting_bookings')->where('id', $id)->update([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $msg = '操作成功';
            
            // 业财联动：如果同意并且产生了费用，自动向财务中心生单
            if ($status == 1 && $booking->cost > 0) {
                // 查找企业ID (此处为严谨，需联查enterprises表。为演示全流程闭环，我们直接写入财务流水)
                $ent = Db::table('enterprises')->where('name', $booking->enterprise_name)->first();
                $entId = $ent ? $ent->id : 0; // 若无匹配视为散客记0
                
                Db::table('receivables')->insert([
                    'enterprise_id' => $entId,
                    'space_id' => 0,
                    'bill_type' => 4, // 设定 4 为 共享空间预订费
                    'amount' => $booking->cost,
                    'due_date' => date('Y-m-d', strtotime('+3 days')),
                    'is_paid' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $msg = "预订已批准！因产生￥{$booking->cost}费用，已自动向业财中心推送应收账款。";
            }

            Db::commit();
            return json(['code' => 200, 'msg' => $msg]);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '执行异常: ' . $e->getMessage()]);
        }
    }
}