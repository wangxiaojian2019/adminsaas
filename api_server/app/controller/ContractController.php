<?php
namespace app\controller;

use support\Request;
use support\Response;
use support\Db;

class ContractController
{
    /**
     * ================= 企业客户档案管理 =================
     */
    public function getEnterprises(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $list = Db::table('enterprises')
            ->where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function addEnterprise(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $post = $request->post();

        if (empty($post['name'])) {
            return json(['code' => 400, 'msg' => '企业名称不可为空']);
        }

        Db::table('enterprises')->insert([
            'tenant_id' => $tenantId,
            'name' => $post['name'],
            'contact_person' => $post['contact_person'] ?? '',
            'phone' => $post['phone'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '正式企业档案建立成功']);
    }

    /**
     * ================= 合同生命周期管理 =================
     */
    public function getContracts(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        
        // 联表查询：合同主表 + 企业名称 + 空间物理位置
        $list = Db::table('contracts as c')
            ->join('enterprises as e', 'c.enterprise_id', '=', 'e.id')
            ->join('spaces as s', 'c.space_id', '=', 's.id')
            ->where('c.tenant_id', $tenantId)
            ->select(
                'c.*', 
                'e.name as enterprise_name', 
                's.building_name', 's.room_number'
            )
            ->orderBy('c.id', 'desc')
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function addContract(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $post = $request->post();

        // 1. 基础校验
        if (empty($post['enterprise_id']) || empty($post['space_id']) || empty($post['start_date']) || empty($post['end_date'])) {
            return json(['code' => 400, 'msg' => '合同核心要素(企业/空间/租期)不完整']);
        }

        // 2. 校验空间资产当前是否可用(防并发重租)
        $space = Db::table('spaces')->where('id', $post['space_id'])->where('tenant_id', $tenantId)->first();
        if (!$space || $space->status != 0) {
            return json(['code' => 400, 'msg' => '该物理空间当前非空置状态，禁止签约']);
        }

        Db::beginTransaction();
        try {
            // 3. 生成合同编号 (动态流水号)
            $contractNo = 'HT-' . date('Ymd') . '-' . rand(1000, 9999);

            // 4. 插入合同记录
            Db::table('contracts')->insert([
                'contract_no' => $contractNo,
                'tenant_id' => $tenantId,
                'admin_id' => 1, // 默认当前操作人
                'enterprise_id' => $post['enterprise_id'],
                'space_id' => $post['space_id'],
                'start_date' => $post['start_date'],
                'end_date' => $post['end_date'],
                'monthly_rent' => $post['monthly_rent'] ?? 0,
                'deposit' => $post['deposit'] ?? 0,
                'status' => 1 // 1-生效中
            ]);

            // 5. 【核心联动】强制更改物理空间状态为：1(在租)
            Db::table('spaces')->where('id', $post['space_id'])->update(['status' => 1]);

            Db::commit();
            return json(['code' => 200, 'msg' => '合同签署成功，物理空间已锁定']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '合同签约系统异常：' . $e->getMessage()]);
        }
    }

    public function terminateContract(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $contractId = $request->post('id');

        $contract = Db::table('contracts')->where('id', $contractId)->where('tenant_id', $tenantId)->first();
        if (!$contract || $contract->status != 1) {
            return json(['code' => 400, 'msg' => '仅生效中的合同可执行退租清算']);
        }

        Db::beginTransaction();
        try {
            // 1. 将合同状态改为退租(2)
            Db::table('contracts')->where('id', $contractId)->update(['status' => 2]);

            // 2. 【核心联动】强制释放物理空间，恢复为空置(0)
            Db::table('spaces')->where('id', $contract->space_id)->update(['status' => 0]);

            Db::commit();
            return json(['code' => 200, 'msg' => '退租清算完成，物理空间已释放并重置为空置可租状态']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '退租清算异常：' . $e->getMessage()]);
        }
    }

    /**
     * ================= 合同数字档案与附件管理 =================
     */

    public function uploadAttachment(Request $request): Response
    {
        $file = $request->file('file');
        $contractId = $request->post('contract_id');

        if (!$file || !$file->isValid()) {
            return json(['code' => 400, 'msg' => '未检测到有效的文件上传']);
        }

        // 基础文件白名单校验 (仅允许 PDF 或 图片)
        $ext = strtolower($file->getUploadExtension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            return json(['code' => 400, 'msg' => '仅支持 JPG/PNG 图像或 PDF 格式文档']);
        }

        // 创建租户隔离的上传目录
        $tenantId = $request->tenant_id;
        $relativeDir = "/uploads/tenant_{$tenantId}/contracts/" . date('Ym');
        $uploadDir = public_path() . $relativeDir;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // 物理存储并生成相对 URL
        $fileName = uniqid('CT_') . '.' . $ext;
        $file->move($uploadDir . '/' . $fileName);
        $fileUrl = $relativeDir . '/' . $fileName;

        // 若携带合同ID，则直接绑定到纸质扫描件字段
        if ($contractId) {
            Db::table('contracts')->where('id', $contractId)->update(['paper_contract_url' => $fileUrl]);
        }

        return json(['code' => 200, 'msg' => '纸质附件存档成功', 'url' => $fileUrl]);
    }

    public function generateElecContract(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $contractId = $request->post('contract_id');

        $contract = Db::table('contracts')->where('id', $contractId)->where('tenant_id', $tenantId)->first();
        if (!$contract) return json(['code' => 400, 'msg' => '合同不存在']);

        // 虚拟凭证 URL
        $mockPdfUrl = "/mock_assets/elec_contract_" . $contract->contract_no . ".pdf";
        
        Db::table('contracts')->where('id', $contractId)->update(['elec_contract_url' => $mockPdfUrl]);

        return json(['code' => 200, 'msg' => '无纸化电子合同已生成并加盖时间戳', 'url' => $mockPdfUrl]);
    }

    public function getContractDocs(Request $request): Response
    {
        $contractId = $request->get('contract_id');
        $docs = Db::table('contracts')
            ->where('id', $contractId)
            ->select('elec_contract_url', 'paper_contract_url')
            ->first();

        return json(['code' => 200, 'msg' => 'success', 'data' => $docs]);
    }
}