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

    public function payBill(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $billId = $request->post('bill_id');
        $receiptUrl = $request->post('receipt_url');
        if (!$receiptUrl) return json(['code' => 400, 'msg' => '必须上传打款凭证照片或截图']);

        $bill = Db::table('receivables')->where('id', $billId)->where('enterprise_id', $enterpriseId)->first();
        if (!$bill) return json(['code' => 404, 'msg' => '账单防越权校验失败']);

        Db::table('receivables')->where('id', $billId)->update(['is_paid' => 2, 'receipt_url' => $receiptUrl, 'updated_at' => date('Y-m-d H:i:s')]);
        return json(['code' => 200, 'msg' => '凭证已提交，请等待核销']);
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

    // ==========================================
    // 核心联动：获取当前企业的物资借还明细
    // ==========================================
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
}