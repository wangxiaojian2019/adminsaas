<?php
namespace app\controller;

use support\Request;
use support\Db;

class VehicleController
{
    public function list(Request $request)
    {
        $list = Db::table('parking_vehicles')
            ->join('enterprises', 'parking_vehicles.enterprise_id', '=', 'enterprises.id')
            ->select('parking_vehicles.*', 'enterprises.name as enterprise_name')
            ->orderBy('parking_vehicles.id', 'desc')
            ->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function add(Request $request)
    {
        $plateNo = $request->post('plate_no');
        $exists = Db::table('parking_vehicles')->where('plate_no', $plateNo)->first();
        if ($exists) {
            return json(['code' => 400, 'msg' => '该车牌号已登记办理，不可重复录入']);
        }

        $data = [
            'plate_no' => $plateNo,
            'enterprise_id' => $request->post('enterprise_id'),
            'parking_space_no' => $request->post('parking_space_no'),
            'card_type' => $request->post('card_type', 1),
            'start_date' => $request->post('start_date'),
            'end_date' => $request->post('end_date'),
            'monthly_fee' => $request->post('monthly_fee', 0),
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        Db::table('parking_vehicles')->insert($data);
        return json(['code' => 200, 'msg' => 'success']);
    }

    /**
     * 月卡续费引擎：延期车位并自动向财务集成中台推送应收账单
     */
    public function renew(Request $request)
    {
        $id = $request->post('id');
        $months = intval($request->post('months', 1));
        
        $vehicle = Db::table('parking_vehicles')->where('id', $id)->first();
        if (!$vehicle) {
            return json(['code' => 400, 'msg' => '车辆档案不存在']);
        }

        $currentEnd = $vehicle->end_date;
        $baseDate = (strtotime($currentEnd) < time()) ? date('Y-m-d') : $currentEnd;
        $newEnd = date('Y-m-d', strtotime("+$months months", strtotime($baseDate)));

        $totalAmount = $vehicle->monthly_fee * $months;

        Db::beginTransaction();
        try {
            // 1. 延长到期时间
            Db::table('parking_vehicles')->where('id', $id)->update([
                'end_date' => $newEnd,
                'status' => 1
            ]);

            // 2. 联动业财一体化：向应收表自动派发催缴账单 (bill_type = 4 为物业费/车位规费)
            Db::table('receivables')->insert([
                'enterprise_id' => $vehicle->enterprise_id,
                'space_id' => 0, // 0代表车场固定泊位资产
                'bill_type' => 4, 
                'amount' => $totalAmount,
                'due_date' => date('Y-m-d', strtotime('+7 days')),
                'is_paid' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            Db::commit();
            return json(['code' => 200, 'msg' => 'success']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '续费财务流推送失败']);
        }
    }

    public function delete(Request $request)
    {
        $id = $request->post('id');
        Db::table('parking_vehicles')->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => 'success']);
    }
}