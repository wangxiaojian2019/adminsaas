<?php
namespace app\controller;

use support\Request;
use support\Db;

class LeadController
{
    public function list(Request $request)
    {
        // 核心修改：利用子查询提取该线索在跟进表中的最新时间
        $list = Db::table('leads')
            ->select('leads.*', Db::raw('(SELECT MAX(created_at) FROM lead_follow_ups WHERE lead_id = leads.id) as last_follow_time'))
            ->orderBy('leads.id', 'desc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function add(Request $request)
    {
        $data = [
            'customer_name' => $request->post('customer_name'),
            'contact_person' => $request->post('contact_person', ''),
            'phone' => $request->post('phone', ''),
            'demand_area' => $request->post('demand_area', 0),
            'source' => $request->post('source', 1),
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        Db::table('leads')->insert($data);
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function followList(Request $request)
    {
        $leadId = $request->get('lead_id');
        $list = Db::table('lead_follow_ups')
            ->where('lead_id', $leadId)
            ->orderBy('id', 'desc')
            ->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function followAdd(Request $request)
    {
        $user = $request->user;
        
        $data = [
            'lead_id' => $request->post('lead_id'),
            'operator_name' => $user->real_name ?? '系统',
            'content' => $request->post('content'),
            'intent_level' => $request->post('intent_level', '中'),
            'next_follow_time' => $request->post('next_follow_time'),
            'created_at' => date('Y-m-d H:i:s')
        ];
        Db::table('lead_follow_ups')->insert($data);
        
        return json(['code' => 200, 'msg' => 'success']);
    }
}