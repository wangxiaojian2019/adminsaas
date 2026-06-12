<?php
namespace app\controller;

use support\Request;
use support\Db;

class TenantPortalController
{
    /**
     * 获取当前登录企业的移动端大盘明细
     */
    public function getOverview(Request $request)
    {
        $enterpriseId = $request->enterprise_id;

        $contractCount = Db::table('contracts')
            ->where('enterprise_id', $enterpriseId)
            ->where('status', 1)
            ->count();

        $unpaidList = Db::table('receivables')
            ->where('enterprise_id', $enterpriseId)
            ->where('is_paid', 0)
            ->get();

        return json([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'contract_count' => $contractCount,
                'unpaid_count' => $unpaidList->count(),
                'unpaid_amount' => number_format($unpaidList->sum('amount'), 2, '.', '')
            ]
        ]);
    }

    /**
     * 查询企业名下的全量账单历史明细
     */
    public function getBills(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $list = Db::table('receivables')
            ->leftJoin('spaces', 'receivables.space_id', '=', 'spaces.id')
            ->where('receivables.enterprise_id', $enterpriseId)
            ->select('receivables.*', 'spaces.building_name', 'spaces.room_number')
            ->orderBy('receivables.id', 'desc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 调阅当前企业专属 active 契约合同公文
     */
    public function getContracts(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $list = Db::table('contracts')
            ->join('spaces', 'contracts.space_id', '=', 'spaces.id')
            ->where('contracts.enterprise_id', $enterpriseId)
            ->select('contracts.*', 'spaces.building_name', 'spaces.floor', 'spaces.room_number')
            ->orderBy('contracts.id', 'desc')
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 租户自助在线提报故障/维修单 (支持照片上传流联动)
     */
    public function submitOrder(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $title = $request->post('title');
        $description = $request->post('description');
        $attachmentUrl = $request->post('attachment_url');
        
        if (empty($title)) {
            return json(['code' => 400, 'msg' => '提报故障主题不能为空']);
        }

        $entName = Db::table('enterprises')->where('id', $enterpriseId)->value('name');

        if (!empty($attachmentUrl)) {
            $description .= "\n【现场照片证物】: http://47.120.52.65:8787" . $attachmentUrl;
        }

        Db::table('work_orders')->insert([
            'title' => '[' . $entName . '] 自助提报: ' . $title,
            'description' => $description,
            'reporter_name' => $request->post('contact_person', '租户行政'),
            'status' => 1, 
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => 'success']);
    }

    /**
     * 移动端模拟支付收银台回调接口
     */
    public function payBill(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $billId = $request->post('id');

        $bill = Db::table('receivables')->where('id', $billId)->where('enterprise_id', $enterpriseId)->first();
        
        if (!$bill) return json(['code' => 400, 'msg' => '账单不存在或无权操作']);
        if ($bill->is_paid == 1) return json(['code' => 400, 'msg' => '账单已结清，无需重复支付']);

        // 模拟支付成功，核销账单
        Db::table('receivables')->where('id', $billId)->update([
            'is_paid' => 1,
            'paid_time' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '支付成功']);
    }
}