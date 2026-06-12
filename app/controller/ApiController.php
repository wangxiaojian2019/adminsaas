<?php
namespace app\controller;

use support\Request;
use support\Response;
use support\Db;

class ApiController
{
    // 统一下发跨域头和JSON结构
    private function json($data = [], $msg = 'success', $code = 200)
    {
        return response(json_encode(['code' => $code, 'msg' => $msg, 'data' => $data]))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*');
    }

    // ================= 登录与大屏 =================
    public function login(Request $request)
    {
        $user = Db::table('sys_admins')->where('username', $request->post('username'))->first();
        if ($user && $user->password === $request->post('password')) {
            return $this->json([
                'token' => 'mock-jwt-token-888',
                'user_info' => [
                    'company_name' => '万科智慧物业',
                    'real_name' => $user->real_name,
                    'role' => $user->role
                ]
            ]);
        }
        return $this->json([], '账号或密码错误', 400);
    }

    public function dashboard(Request $request)
    {
        $spaces = Db::table('spaces')->get();
        $total_spaces = $spaces->count();
        $rented_spaces = $spaces->where('status', 1)->count();
        $vacancy_rate = $total_spaces > 0 ? round((($total_spaces - $rented_spaces) / $total_spaces) * 100, 2) . '%' : '0%';

        $finance = Db::table('finance_bills')->get();
        $total_receivable = $finance->sum('amount');
        $total_received = $finance->where('is_paid', 1)->sum('amount');
        $total_unpaid = $finance->where('is_paid', 0)->sum('amount');

        // 热力图数据直出
        $heat_map = Db::table('spaces')->select('building_name', 'floor', 'room_number', 'status')->get();

        return $this->json([
            'asset' => ['total_spaces' => $total_spaces, 'rented_spaces' => $rented_spaces, 'vacancy_rate' => $vacancy_rate],
            'finance' => ['total_receivable' => $total_receivable, 'total_received' => $total_received, 'total_unpaid' => $total_unpaid],
            'heat_map' => $heat_map,
            'patrol_alerts' => [
                ['location' => 'A座地下配电房', 'remarks' => '温度传感器预警', 'worker_name' => '系统巡检', 'check_time' => date('Y-m-d H:i')]
            ]
        ]);
    }

    // ================= 系统权限 =================
    public function getRoles() { return $this->json(Db::table('sys_roles')->get()); }
    public function addRole(Request $request) {
        Db::table('sys_roles')->insert($request->post());
        return $this->json();
    }
    public function getAdmins() { return $this->json(Db::table('sys_admins')->get()); }
    public function addAdmin(Request $request) {
        Db::table('sys_admins')->insert($request->post());
        return $this->json();
    }

    // ================= 资产中心 =================
    public function getBuildings() { return $this->json(Db::table('buildings')->get()); }
    public function addBuilding(Request $request) {
        Db::table('buildings')->insert($request->post());
        return $this->json();
    }
    public function getSpaces() { return $this->json(Db::table('spaces')->get()); }
    public function addSpace(Request $request) {
        Db::table('spaces')->insert($request->post());
        return $this->json();
    }
    public function changeSpaceStatus(Request $request) {
        Db::table('spaces')->where('id', $request->post('id'))->update(['status' => $request->post('status')]);
        return $this->json();
    }

    // ================= 招商线索 =================
    public function getLeads() { return $this->json(Db::table('leads')->get()); }
    public function addLead(Request $request) {
        Db::table('leads')->insert($request->post());
        return $this->json();
    }

    // ================= 租务合同 =================
    public function getEnterprises() { return $this->json(Db::table('enterprises')->get()); }
    public function addEnterprise(Request $request) {
        Db::table('enterprises')->insert($request->post());
        return $this->json();
    }
    public function getContracts() { return $this->json(Db::table('contracts')->get()); }
    public function addContract(Request $request) {
        $post = $request->post();
        $post['contract_no'] = 'HT' . date('YmdHis');
        $ent = Db::table('enterprises')->where('id', $post['enterprise_id'])->first();
        $space = Db::table('spaces')->where('id', $post['space_id'])->first();
        $post['enterprise_name'] = $ent->name;
        $post['building_name'] = $space->building_name;
        $post['room_number'] = $space->room_number;
        
        Db::table('contracts')->insert($post);
        // 同步修改房源状态
        Db::table('spaces')->where('id', $post['space_id'])->update(['status' => 1, 'enterprise_name' => $ent->name, 'start_date' => $post['start_date'], 'end_date' => $post['end_date']]);
        return $this->json();
    }
    public function terminateContract(Request $request) {
        $contract = Db::table('contracts')->where('id', $request->post('id'))->first();
        Db::table('contracts')->where('id', $request->post('id'))->update(['status' => 0]);
        Db::table('spaces')->where('id', $contract->space_id)->update(['status' => 0, 'enterprise_name' => null, 'start_date' => null, 'end_date' => null]);
        return $this->json();
    }
    public function getContractDocs(Request $request) {
        return $this->json(Db::table('contracts')->select('elec_contract_url', 'paper_contract_url')->where('id', $request->get('contract_id'))->first());
    }
    public function generateElecDoc(Request $request) {
        Db::table('contracts')->where('id', $request->post('contract_id'))->update(['elec_contract_url' => '/mock-pdf-url.pdf']);
        return $this->json(['url' => '/mock-pdf-url.pdf'], '电子签章生成完毕');
    }

    // ================= 财务账单 =================
    public function getBills() { return $this->json(Db::table('finance_bills')->get()); }
    public function payBill(Request $request) {
        Db::table('finance_bills')->where('id', $request->post('id'))->update(['is_paid' => 1, 'paid_time' => date('Y-m-d H:i:s')]);
        return $this->json();
    }
    public function recordMeter(Request $request) {
        $space = Db::table('spaces')->where('id', $request->post('space_id'))->first();
        Db::table('finance_bills')->insert([
            'enterprise_name' => $space->enterprise_name ?: '未知个人',
            'building_name' => $space->building_name,
            'room_number' => $space->room_number,
            'bill_type' => $request->post('meter_type') == 1 ? 2 : 3,
            'amount' => rand(100, 800), // 模拟随机能耗费用
            'due_date' => date('Y-m-t'),
            'is_paid' => 0
        ]);
        return $this->json();
    }

    // ================= 工单与安防 =================
    public function getWorkOrders() { return $this->json(Db::table('work_orders')->get()); }
    public function assignWorkOrder(Request $request) {
        $handler = Db::table('sys_admins')->where('id', $request->post('handler_id'))->first();
        Db::table('work_orders')->where('id', $request->post('id'))->update(['status' => 2, 'handler_name' => $handler->real_name]);
        return $this->json();
    }
    public function completeWorkOrder(Request $request) {
        Db::table('work_orders')->where('id', $request->post('id'))->update(['status' => 3]);
        return $this->json();
    }
    public function verifyWorkOrder(Request $request) {
        Db::table('work_orders')->where('id', $request->post('id'))->update(['status' => 4]);
        return $this->json();
    }

    public function getPatrolPoints() { return $this->json(Db::table('patrol_points')->get()); }
    public function addPatrolPoint(Request $request) {
        Db::table('patrol_points')->insert($request->post());
        return $this->json();
    }
    public function getPatrolRecords() { return $this->json(Db::table('patrol_records')->get()); }
    public function addPatrolRecord(Request $request) {
        $point = Db::table('patrol_points')->where('id', $request->post('point_id'))->first();
        Db::table('patrol_records')->insert([
            'point_name' => $point->point_name,
            'operator_name' => '保安老李',
            'is_normal' => $request->post('is_normal'),
            'remark' => $request->post('remark')
        ]);
        if ($request->post('is_normal') == 0) {
            Db::table('work_orders')->insert(['title' => $point->point_name . '报修', 'description' => $request->post('remark'), 'reporter_name' => '安防中心']);
        }
        return $this->json([], '打卡成功');
    }
}