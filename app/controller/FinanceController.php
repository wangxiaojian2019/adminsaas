<?php
namespace app\controller;

use support\Request;
use support\Db;

class FinanceController
{
    // ==========================================
    // 区块一：标准应收账单控制流
    // ==========================================

    public function receivableList(Request $request)
    {
        $list = Db::table('receivables')
            ->leftJoin('enterprises', 'receivables.enterprise_id', '=', 'enterprises.id')
            ->leftJoin('spaces', 'receivables.space_id', '=', 'spaces.id')
            ->select('receivables.*', 'enterprises.name as enterprise_name', 'spaces.building_name', 'spaces.room_number')
            ->orderByRaw("CASE WHEN receivables.is_paid = 2 THEN 0 ELSE 1 END")
            ->orderBy('receivables.id', 'desc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function pay(Request $request)
    {
        $id = $request->post('id');
        $action = $request->post('action', 'approve'); 

        if ($action === 'reject') {
            Db::table('receivables')->where('id', $id)->update([
                'is_paid' => 0,
                'payment_method' => null,
                'receipt_url' => null
            ]);
            return json(['code' => 200, 'msg' => '凭证已驳回，已要求租户重新打款']);
        }

        Db::table('receivables')->where('id', $id)->update([
            'is_paid' => 1,
            'paid_time' => date('Y-m-d H:i:s')
        ]);
        
        return json(['code' => 200, 'msg' => '账款已确认到账，核销成功']);
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

            $contract = Db::table('contracts')->where('space_id', $spaceId)->where('status', 1)->first();
            if ($contract) {
                $price = $meterType == 1 ? 5.5 : 1.2;
                $amount = round($reading * $price, 2);
                
                Db::table('receivables')->insert([
                    'enterprise_id' => $contract->enterprise_id,
                    'space_id' => $spaceId,
                    'bill_type' => $meterType == 1 ? 2 : 3, 
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

    // ==========================================
    // 区块二：核心扩容 - 退租清算单控制流
    // ==========================================

    public function checkoutList(Request $request)
    {
        $list = Db::table('checkouts')
            ->leftJoin('contracts', 'checkouts.contract_id', '=', 'contracts.id')
            ->leftJoin('enterprises', 'checkouts.enterprise_id', '=', 'enterprises.id')
            ->leftJoin('spaces', 'contracts.space_id', '=', 'spaces.id')
            ->select(
                'checkouts.*',
                'contracts.contract_no',
                'enterprises.name as enterprise_name',
                'spaces.building_name',
                'spaces.room_number'
            )
            ->orderByRaw("CASE WHEN checkouts.status = 0 THEN 0 ELSE 1 END")
            ->orderBy('checkouts.id', 'desc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function payCheckout(Request $request)
    {
        $id = $request->post('id');
        
        Db::table('checkouts')->where('id', $id)->update([
            'status' => 1
        ]);
        
        return json(['code' => 200, 'msg' => '退租清算单已彻底核销，流程完美闭环']);
    }
}