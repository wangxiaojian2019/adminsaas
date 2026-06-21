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
            // 【优化排序逻辑】：1=已结清沉底，未结清(0,2,3)浮顶，然后严格按最新出账时间(id倒序)排列
            ->orderByRaw("CASE WHEN receivables.is_paid = 1 THEN 1 ELSE 0 END")
            ->orderBy('receivables.id', 'desc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function pay(Request $request)
    {
        $id = $request->post('id');
        $action = $request->post('action', 'approve'); 
        $rejectReason = $request->post('reject_reason', '凭证不清晰或金额不符');

        $bill = Db::table('receivables')->where('id', $id)->first();
        if (!$bill) return json(['code' => 404, 'msg' => '流水不存在']);

        if ($action === 'reject') {
            Db::table('receivables')->where('id', $id)->update([
                'is_paid' => 3,
                'reject_reason' => $rejectReason
            ]);

            Db::table('notifications')->insert([
                'enterprise_id' => $bill->enterprise_id,
                'title' => '打款凭证被驳回',
                'content' => "您的账单(￥{$bill->amount})凭证被财务驳回。原因：{$rejectReason}。请重新上传。",
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            \app\process\Websocket::sendToEnterprise($bill->enterprise_id, [
                'type' => 'reject',
                'msg' => '您的账单凭证刚刚被财务驳回，请重新补交！'
            ]);

            return json(['code' => 200, 'msg' => '凭证已驳回，系统将下发站内信通知租户']);
        }

        Db::table('receivables')->where('id', $id)->update([
            'is_paid' => 1,
            'paid_time' => date('Y-m-d H:i:s'),
            'reject_reason' => null
        ]);
        
        Db::table('notifications')->insert([
            'enterprise_id' => $bill->enterprise_id,
            'title' => '账单核销成功',
            'content' => "您的账单(￥{$bill->amount})已完成财务核销结清。感谢您的配合。",
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        \app\process\Websocket::sendToEnterprise($bill->enterprise_id, [
            'type' => 'notification',
            'msg' => "账单(￥{$bill->amount})已完成核销结清"
        ]);

        return json(['code' => 200, 'msg' => '账款已确认到账，核销成功']);
    }

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
        Db::table('checkouts')->where('id', $id)->update(['status' => 1]);
        return json(['code' => 200, 'msg' => '退租清算单已彻底核销，流程完美闭环']);
    }

    public function meterList(Request $request)
    {
        $spaces = Db::table('spaces')
            ->join('contracts', 'spaces.id', '=', 'contracts.space_id')
            ->join('enterprises', 'contracts.enterprise_id', '=', 'enterprises.id')
            ->where('contracts.status', 1)
            ->select('spaces.id as space_id', 'spaces.building_name', 'spaces.room_number', 'enterprises.name as enterprise_name', 'contracts.enterprise_id')
            ->get();

        foreach ($spaces as $sp) {
            $lastWater = Db::table('meters')->where('space_id', $sp->space_id)->where('meter_type', 1)->orderBy('id', 'desc')->first();
            $sp->last_water = $lastWater ? floatval($lastWater->current_reading) : 0;
            $sp->last_water_date = $lastWater ? date('Y-m-d H:i', strtotime($lastWater->created_at)) : '入驻初始底数';

            $lastElec = Db::table('meters')->where('space_id', $sp->space_id)->where('meter_type', 2)->orderBy('id', 'desc')->first();
            $sp->last_elec = $lastElec ? floatval($lastElec->current_reading) : 0;
            $sp->last_elec_date = $lastElec ? date('Y-m-d H:i', strtotime($lastElec->created_at)) : '入驻初始底数';
        }
        
        return json(['code' => 200, 'msg' => 'success', 'data' => $spaces]);
    }

    public function recordMeter(Request $request)
    {
        $spaceId = $request->post('space_id');
        $entId = $request->post('enterprise_id');
        $meterType = $request->post('meter_type'); 
        $currentReading = floatval($request->post('current_reading'));
        
        // 【允许前台动态传参计费单价】
        $price = floatval($request->post('price', $meterType == 1 ? 5.5 : 1.2));

        $lastMeter = Db::table('meters')->where('space_id', $spaceId)->where('meter_type', $meterType)->orderBy('id', 'desc')->first();
        $lastReading = $lastMeter ? floatval($lastMeter->current_reading) : 0;

        if ($currentReading < $lastReading) {
            return json(['code' => 400, 'msg' => "业务拦截：本次读数({$currentReading}) 不能小于系统存档的上次底数({$lastReading})"]);
        }

        $usage = $currentReading - $lastReading;
        if ($usage == 0) {
            return json(['code' => 400, 'msg' => '本期用量为0，无需生成账单，请检查是否抄表有误']);
        }

        $amount = round($usage * $price, 2);

        Db::beginTransaction();
        try {
            Db::table('meters')->insert([
                'space_id' => $spaceId,
                'meter_type' => $meterType,
                'current_reading' => $currentReading,
                'record_month' => date('Y-m'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Db::table('receivables')->insert([
                'enterprise_id' => $entId,
                'space_id' => $spaceId,
                'bill_type' => $meterType == 1 ? 2 : 3, 
                'amount' => $amount,
                'due_date' => date('Y-m-d', strtotime('+7 days')), 
                'is_paid' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            Db::table('notifications')->insert([
                'enterprise_id' => $entId,
                'title' => '新账单出账提醒',
                'content' => "您的能耗费账单(￥{$amount})已生成，请在截止日期前进入服务门户处理。",
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            \app\process\Websocket::sendToEnterprise($entId, [
                'type' => 'notification',
                'msg' => "您有一笔新的能耗费账单(￥{$amount})出账，请及时处理"
            ]);

            Db::commit();
            return json(['code' => 200, 'msg' => '抄表存档成功！已根据用量自动扣费生单，租户端将收到缴费提醒。']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '抄表入账异常']);
        }
    }

    // 【专门拉取对应房间的抄表历史事件】
    public function meterHistory(Request $request)
    {
        $spaceId = $request->get('space_id');
        $meterType = $request->get('meter_type');
        
        $list = Db::table('meters')
            ->where('space_id', $spaceId)
            ->where('meter_type', $meterType)
            ->orderBy('id', 'desc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }
}