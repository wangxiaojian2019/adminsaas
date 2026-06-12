<?php
namespace app\controller;

use support\Request;
use support\Db;

class FinanceController
{
    public function receivableList(Request $request)
    {
        $list = Db::table('receivables')
            ->leftJoin('enterprises', 'receivables.enterprise_id', '=', 'enterprises.id')
            ->leftJoin('spaces', 'receivables.space_id', '=', 'spaces.id')
            ->select('receivables.*', 'enterprises.name as enterprise_name', 'spaces.building_name', 'spaces.room_number')
            ->orderBy('receivables.id', 'desc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function pay(Request $request)
    {
        $id = $request->post('id');
        Db::table('receivables')->where('id', $id)->update([
            'is_paid' => 1,
            'paid_time' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function recordMeter(Request $request)
    {
        $spaceId = $request->post('space_id');
        $meterType = $request->post('meter_type');
        $reading = $request->post('current_reading');
        $month = $request->post('record_month');

        Db::beginTransaction();
        try {
            Db::table('meters')->insert([
                'space_id' => $spaceId,
                'meter_type' => $meterType,
                'current_reading' => $reading,
                'record_month' => $month,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // 根据空间查找当前生效的承租合同，生成账单
            $contract = Db::table('contracts')->where('space_id', $spaceId)->where('status', 1)->first();
            if ($contract) {
                // 模拟单价：水费 5.5 元/m³，电费 1.2 元/度
                $price = $meterType == 1 ? 5.5 : 1.2;
                $amount = round($reading * $price, 2);
                
                Db::table('receivables')->insert([
                    'enterprise_id' => $contract->enterprise_id,
                    'space_id' => $spaceId,
                    'bill_type' => $meterType == 1 ? 2 : 3, // 2:水费 3:电费
                    'amount' => $amount,
                    'due_date' => date('Y-m-t', strtotime($month . '-01')),
                    'is_paid' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            Db::commit();
            return json(['code' => 200, 'msg' => 'success']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '生成账单失败']);
        }
    }
}