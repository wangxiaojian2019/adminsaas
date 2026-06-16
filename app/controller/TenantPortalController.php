<?php
namespace app\controller;

use support\Request;
use support\Db;

class TenantPortalController
{
    private function checkTenantAuth(Request $request)
    {
        if (!isset($request->enterprise_id) || empty($request->enterprise_id)) {
            return false;
        }
        return $request->enterprise_id; 
    }

    public function getOverview(Request $request)
    {
        $entId = $this->checkTenantAuth($request);
        if (!$entId) return json(['code' => 403, 'msg' => '非法越权访问拦截']);

        $enterprise = Db::table('enterprises')->where('id', $entId)->first();
        
        $contract = Db::table('contracts')
            ->join('spaces', 'contracts.space_id', '=', 'spaces.id')
            ->where('contracts.enterprise_id', $entId)
            ->where('contracts.status', 1)
            ->select('contracts.*', 'spaces.building_name', 'spaces.room_number')
            ->first();

        return json([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'enterprise' => $enterprise,
                'active_contract' => $contract
            ]
        ]);
    }

    public function getBills(Request $request)
    {
        $entId = $this->checkTenantAuth($request);
        if (!$entId) return json(['code' => 403, 'msg' => '非法越权访问拦截']);

        $list = Db::table('receivables')
            ->where('enterprise_id', $entId)
            ->orderByRaw("CASE WHEN is_paid = 0 THEN 0 WHEN is_paid = 2 THEN 1 ELSE 2 END")
            ->orderBy('id', 'desc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function payBill(Request $request)
    {
        $entId = $this->checkTenantAuth($request);
        if (!$entId) return json(['code' => 403, 'msg' => '非法越权访问拦截']);

        $billId = $request->post('bill_id');
        $receiptUrl = $request->post('receipt_url');

        if (empty($receiptUrl)) {
            return json(['code' => 400, 'msg' => '阻断：必须提供转账凭证电子回单']);
        }

        $affected = Db::table('receivables')
            ->where('id', $billId)
            ->where('enterprise_id', $entId)
            ->where('is_paid', 0)
            ->update([
                'is_paid' => 2, 
                'receipt_url' => $receiptUrl,
                'payment_method' => 'bank_transfer'
            ]);

        if ($affected) {
            return json(['code' => 200, 'msg' => '凭证已安全上传，请等待园区财务核销！']);
        }
        return json(['code' => 400, 'msg' => '账单已变更或系统校验失败']);
    }

    // 核心新增：物业报修引擎
    public function submitOrder(Request $request)
    {
        $entId = $this->checkTenantAuth($request);
        if (!$entId) return json(['code' => 403, 'msg' => '非法越权访问拦截']);

        $enterprise = Db::table('enterprises')->where('id', $entId)->first();
        if (!$enterprise) return json(['code' => 400, 'msg' => '企业户籍丢失']);

        $title = $request->post('title');
        $description = $request->post('description', '');
        $imageUrl = $request->post('image_url', '');

        if (empty($title)) {
            return json(['code' => 400, 'msg' => '报修故障简述不可为空']);
        }

        // 格式化证物图片拼接入描述中，以便 PC 端统一解析
        if (!empty($imageUrl)) {
            $description .= "\n\n【现场照片证物】: " . $imageUrl;
        }

        Db::table('work_orders')->insert([
            'title' => $title,
            'description' => $description,
            'reporter_name' => $enterprise->name . ' (' . $enterprise->contact_person . ')',
            'status' => 1, // 状态1：待中控室指派
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '报修工单已提交，中控调度中心将尽快安排专员处理！']);
    }

    // 核心新增：租户密码修改引擎
    public function updatePassword(Request $request)
    {
        $entId = $this->checkTenantAuth($request);
        if (!$entId) return json(['code' => 403, 'msg' => '非法越权访问拦截']);

        $oldPwd = $request->post('old_password');
        $newPwd = $request->post('new_password');

        if (empty($oldPwd) || empty($newPwd)) {
            return json(['code' => 400, 'msg' => '密码字段不可为空']);
        }

        $enterprise = Db::table('enterprises')->where('id', $entId)->first();

        // 校验原密码
        if (md5($oldPwd) !== $enterprise->password) {
            return json(['code' => 400, 'msg' => '原密码验证失败，拒绝修改']);
        }

        // 执行更迭
        Db::table('enterprises')->where('id', $entId)->update([
            'password' => md5($newPwd)
        ]);

        return json(['code' => 200, 'msg' => '安全密码修改成功，请妥善保管']);
    }
}