<?php
namespace app\controller;

use support\Request;
use support\Db;

class TenantPortalController
{
    /**
     * 获取租户资产全局看板
     */
    public function getOverview(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $enterprise = Db::table('enterprises')->where('id', $enterpriseId)->first();
        
        if (!$enterprise) {
            return json(['code' => 404, 'msg' => '企业主体不存在']);
        }

        // 核心修改：模式一，聚合查询该企业名下【所有】生效中的独立合同及空间信息
        $activeContracts = Db::table('contracts')
            ->leftJoin('spaces', 'contracts.space_id', '=', 'spaces.id')
            ->where('contracts.enterprise_id', $enterpriseId)
            ->where('contracts.status', 1)
            ->select('contracts.*', 'spaces.building_name', 'spaces.room_number', 'spaces.floor', 'spaces.area')
            ->orderBy('contracts.id', 'asc') // 按签约时间顺序展现
            ->get();

        return json([
            'code' => 200, 
            'msg' => 'success', 
            'data' => [
                'enterprise' => $enterprise,
                // 输出为数组列表，前端通过计算属性(computed)求和
                'contracts' => $activeContracts 
            ]
        ]);
    }

    public function getBills(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        
        $bills = Db::table('receivables')
            ->where('enterprise_id', $enterpriseId)
            ->orderByRaw("CASE WHEN is_paid = 3 THEN 0 WHEN is_paid = 0 THEN 1 WHEN is_paid = 2 THEN 2 ELSE 3 END")
            ->orderBy('due_date', 'asc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $bills]);
    }

    public function getContracts(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $contracts = Db::table('contracts')
            ->where('enterprise_id', $enterpriseId)
            ->orderBy('status', 'desc')
            ->orderBy('id', 'desc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $contracts]);
    }

    public function payBill(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $billId = $request->post('bill_id');
        $receiptUrl = $request->post('receipt_url');

        if (!$receiptUrl) {
            return json(['code' => 400, 'msg' => '必须上传打款凭证照片或截图']);
        }

        $bill = Db::table('receivables')->where('id', $billId)->where('enterprise_id', $enterpriseId)->first();
        if (!$bill) {
            return json(['code' => 404, 'msg' => '账单防越权校验失败']);
        }

        Db::table('receivables')->where('id', $billId)->update([
            'is_paid' => 2, // 状态改为：待财务核销
            'receipt_url' => $receiptUrl,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '打款凭证已提交，请等待财务中心核销。在核销完成前您无需重复缴费。']);
    }

    public function submitOrder(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $title = $request->post('title');
        $description = $request->post('description', '');
        $imageUrl = $request->post('image_url', '');

        if (!$title) {
            return json(['code' => 400, 'msg' => '故障简述为必填项']);
        }

        Db::table('work_orders')->insert([
            'enterprise_id' => $enterpriseId,
            'title' => $title,
            'description' => $description,
            'image_url' => $imageUrl,
            'status' => 1, // 状态：待调度指派
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '工单已流转至中控调度室，我们将尽快指派师傅上门处理']);
    }

    public function updatePassword(Request $request)
    {
        $enterpriseId = $request->enterprise_id;
        $oldPwd = $request->post('old_password');
        $newPwd = $request->post('new_password');

        $enterprise = Db::table('enterprises')->where('id', $enterpriseId)->first();
        if ($enterprise->password !== md5($oldPwd)) {
            return json(['code' => 400, 'msg' => '原密码不正确，请重新输入']);
        }

        Db::table('enterprises')->where('id', $enterpriseId)->update([
            'password' => md5($newPwd),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '门户安全登录密码已更新，请使用新密码重新登入']);
    }
}