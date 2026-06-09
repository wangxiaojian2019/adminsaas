<?php
namespace app\controller;

use support\Request;
use support\Response;
use support\Db;

class LeadsController
{
    /**
     * 获取招商线索列表 (支持按当前登录账号进行数据隔离)
     */
    public function getLeads(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        
        $list = Db::table('crm_leads')
            ->where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 新增意向客户线索 (已包含联系人与电话字段)
     */
    public function addLead(Request $request): Response
    {
        $tenantId = $request->tenant_id;
        $post = $request->post(); // 直接获取所有 POST 参数

        if (empty($post['customer_name'])) {
            return json(['code' => 400, 'msg' => '意向客户名称不能为空']);
        }

        Db::table('crm_leads')->insert([
            'tenant_id' => $tenantId,
            'admin_id' => 1, // 默认归属于系统主管理员，后续可根据登录态动态绑定
            'customer_name' => $post['customer_name'],
            'contact_person' => $post['contact_person'] ?? '',
            'phone' => $post['phone'] ?? '',
            'industry' => $post['industry'] ?? '',
            'demand_area' => $post['demand_area'] ?? 0,
            'budget' => $post['budget'] ?? 0,
            'source' => $post['source'] ?? 1,
            'status' => 1 // 1-跟进中
        ]);

        return json(['code' => 200, 'msg' => '线索录入成功']);
    }

    /**
     * 写入跟进与现场带看记录流水
     */
    public function addFollow(Request $request): Response
    {
        $leadId = $request->post('lead_id');
        $content = $request->post('content');
        $nextFollowTime = $request->post('next_follow_time');

        if (empty($leadId) || empty($content)) {
            return json(['code' => 400, 'msg' => '缺失线索关联或跟进详情']);
        }

        Db::table('crm_lead_follows')->insert([
            'lead_id' => $leadId,
            'admin_id' => 1,
            'content' => $content,
            'next_follow_time' => $nextFollowTime ?: null,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '跟进记录已固化归档']);
    }

    /**
     * 获取指定线索的全部历史跟进流水
     */
    public function getFollows(Request $request): Response
    {
        $leadId = $request->get('lead_id');
        if (empty($leadId)) {
            return json(['code' => 400, 'msg' => '参数缺失']);
        }

        $list = Db::table('crm_lead_follows')
            ->where('lead_id', $leadId)
            ->orderBy('id', 'desc')
            ->get();

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }
}