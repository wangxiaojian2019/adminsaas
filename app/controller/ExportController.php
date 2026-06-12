<?php
namespace app\controller;

use support\Request;
use support\Db;
use Webman\Http\Response;

class ExportController
{
    public function download(Request $request)
    {
        $module = $request->get('module');
        $user = $request->user;
        
        $data = [];
        $headers = [];
        $filename = '';

        switch ($module) {
            case 'buildings':
                $headers = ['项目ID', '大厦名称', '物理楼层', '总建筑面积', '项目负责人'];
                $data = Db::table('buildings')->select('id', 'name', 'total_floors', 'building_area', 'manager_name')->get()->toArray();
                $filename = '空间资产台账';
                break;
            case 'leads':
                $headers = ['线索ID', '企业名称', '行业', '税号', '联系人', '电话', '需求面积'];
                $data = Db::table('leads')->select('id', 'customer_name', 'industry', 'tax_no', 'contact_person', 'phone', 'demand_area')->get()->toArray();
                $filename = '招商线索库';
                break;
            case 'contracts':
                $headers = ['合同编号', '企业ID', '空间ID', '起租日', '退租日', '月租金', '月物业费', '车辆备注'];
                $data = Db::table('contracts')->select('contract_no', 'enterprise_id', 'space_id', 'start_date', 'end_date', 'monthly_rent', 'property_fee', 'vehicle_info')->get()->toArray();
                $filename = '租务合同台账';
                break;
            case 'finance':
                $headers = ['流水单号', '企业ID', '空间ID', '费用科目', '金额', '最晚缴费日', '核销状态', '核销时间'];
                $data = Db::table('receivables')->select('id', 'enterprise_id', 'space_id', 'bill_type', 'amount', 'due_date', 'is_paid', 'paid_time')->get()->toArray();
                $filename = '业财流水报表';
                break;
            case 'dashboard':
                $headers = ['大厦名称', '楼层', '房间号', '建筑面积', '资产状态', '承租企业'];
                $data = Db::table('spaces')->select('building_name', 'floor', 'room_number', 'area', 'status', 'enterprise_name')->get()->toArray();
                $filename = '运营指挥大盘数据';
                break;
            case 'vehicles':
                $headers = ['登记ID', '车牌号码', '绑定车位号', '车卡类别(1月卡/2固定)', '月租金/管理费', '有效期始', '有效期止', '所属企业'];
                $data = Db::table('parking_vehicles')
                    ->join('enterprises', 'parking_vehicles.enterprise_id', '=', 'enterprises.id')
                    ->select('parking_vehicles.id', 'parking_vehicles.plate_no', 'parking_vehicles.parking_space_no', 'parking_vehicles.card_type', 'parking_vehicles.monthly_fee', 'parking_vehicles.start_date', 'parking_vehicles.end_date', 'enterprises.name')
                    ->get()->toArray();
                $filename = '车位资产与月卡台账';
                break;
                
            // --- 本次新增扩展的 5 个离线导出模块 ---
            case 'spaces':
                $headers = ['大厦名称', '楼层', '房间编号', '建筑面积(㎡)', '当前状态(0空置/1在租/2维修/3装修)', '承租企业名称'];
                $data = Db::table('spaces')->select('building_name', 'floor', 'room_number', 'area', 'status', 'enterprise_name')->orderBy('building_name')->orderBy('floor')->get()->toArray();
                $filename = '房源资产精细库';
                break;
            case 'patrol_records':
                $headers = ['流水号', '关联点位ID', '巡检物理点位', '打卡人员', '工况状态(1正常/0异常)', '隐患备注', '打卡时间'];
                $data = Db::table('patrol_records')->select('id', 'point_id', 'location', 'worker_name', 'status', 'remarks', 'created_at')->orderBy('id', 'desc')->get()->toArray();
                $filename = '安防巡检打卡流水';
                break;
            case 'patrol_points':
                $headers = ['点位ID', '物理点位名称', '设立时间'];
                $data = Db::table('patrol_points')->select('id', 'location', 'created_at')->get()->toArray();
                $filename = '安防巡检网格配置';
                break;
            case 'work_orders':
                $headers = ['工单ID', '任务主题', '故障描述', '提报来源', '处理人员ID', '流转状态(1待指派/2处理中/3待验/4结案)', '下发时间'];
                $data = Db::table('work_orders')->select('id', 'title', 'description', 'reporter_name', 'handler_id', 'status', 'created_at')->orderBy('id', 'desc')->get()->toArray();
                $filename = '中控调度工单池';
                break;
            case 'service_staff':
                $headers = ['人员ID', '真实姓名', '岗位职位', '移动端登录账号', '岗位职责', '账号状态(1正常/0封禁)', '建档时间'];
                $data = Db::table('admins')->where('role_id', 4)->select('id', 'real_name', 'position', 'username', 'responsibility', 'status', 'created_at')->get()->toArray();
                $filename = '基层服务人员户籍录';
                break;
                
            default:
                return json(['code' => 400, 'msg' => '未知的导出模块']);
        }

        // 强锁死安全审计日志
        Db::table('export_audit_logs')->insert([
            'admin_id' => $user->id,
            'admin_name' => $user->real_name ?? '未知人员',
            'module_name' => $filename,
            'data_count' => count($data),
            'ip_address' => $request->getRealIp(),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $csvContent = chr(0xEF).chr(0xBB).chr(0xBF);
        $csvContent .= implode(',', $headers) . "\n";
        
        foreach ($data as $row) {
            $rowArray = json_decode(json_encode($row), true);
            $safeRow = array_map(function($val) {
                return '"' . str_replace('"', '""', $val) . '"';
            }, $rowArray);
            $csvContent .= implode(',', $safeRow) . "\n";
        }

        // 注入离线溯源水印
        $csvContent .= "\n\n";
        $csvContent .= "====== 内部数据安全防伪溯源水印 ======\n";
        $csvContent .= "导出时间：," . date('Y-m-d H:i:s') . "\n";
        $csvContent .= "操作人员：," . ($user->real_name ?? '系统') . " (账号:" . ($user->username ?? '-') . ")\n";
        $csvContent .= "终端IP：," . $request->getRealIp() . "\n";
        $csvContent .= "警告：," . "本文件受企业数据资产法保护，严禁非法外传。\n";

        return new Response(200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '_' . date('YmdHi') . '.csv"',
        ], $csvContent);
    }

    public function auditLogs(Request $request)
    {
        $list = Db::table('export_audit_logs')->orderBy('id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }
}