<?php
namespace app\controller;

use support\Request;
use support\Db;
use Webman\Http\Response;

class ExportController
{
    /**
     * 核心导出引擎 (带层级权限拦截)
     */
    public function download(Request $request)
    {
        $module = $request->get('module');
        $user = $request->user;
        
        // 1. 数据资产防泄漏拦截 (DLP)
        // 仅数据权限为全局(3)的超管可以直接下载。其他层级(1本人, 2部门)必须拥有授权。
        if ($user->data_scope != 3) {
            $hasApproval = Db::table('export_applications')
                ->where('applicant_id', $user->id)
                ->where('module_name', $module)
                ->where('status', 1) 
                ->where('expired_at', '>', date('Y-m-d H:i:s'))
                ->exists();

            if (!$hasApproval) {
                // 返回 JSON 触发前端弹窗，而不是直接下载文件
                return json([
                    'code' => 403, 
                    'msg' => '权限受限：该操作涉及敏感数据资产，需向总控中心提交导出申请。'
                ]);
            }
        }
        
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
                $headers = ['线索ID', '企业名称', '行业', '税号', '联系人', '电话', '需求面积', '系统获取时间'];
                $data = Db::table('leads')->select('id', 'customer_name', 'industry', 'tax_no', 'contact_person', 'phone', 'demand_area', 'created_at')->get()->toArray();
                $filename = '招商线索库';
                break;
            case 'enterprises':
                $headers = ['企业ID', '企业全称', '所属行业', '关键联系人', '移动端登录手机', '系统建档时间'];
                $data = Db::table('enterprises')->select('id', 'name', 'industry', 'contact_person', 'phone', 'created_at')->get()->toArray();
                $filename = '企业户籍档案池';
                break;
            case 'contracts':
                $headers = ['合同编号', '企业ID', '空间ID', '起租日', '退租日', '月租金', '月物业费', '车辆备注', '系统起草录入时间'];
                $data = Db::table('contracts')->select('contract_no', 'enterprise_id', 'space_id', 'start_date', 'end_date', 'monthly_rent', 'property_fee', 'vehicle_info', 'created_at')->get()->toArray();
                $filename = '租务合同台账';
                break;
            case 'finance':
                $headers = ['流水单号', '企业ID', '空间ID', '费用科目', '金额', '最晚缴费日', '系统出账时间', '核销状态', '核销打款时间'];
                $data = Db::table('receivables')->select('id', 'enterprise_id', 'space_id', 'bill_type', 'amount', 'due_date', 'created_at', 'is_paid', 'paid_time')->get()->toArray();
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
            case 'spaces':
                $headers = ['大厦名称', '楼层', '房间编号', '建筑面积(㎡)', '当前状态(0空置/1在租/2维修/3装修)', '承租企业名称', '资产建档时间'];
                $data = Db::table('spaces')->select('building_name', 'floor', 'room_number', 'area', 'status', 'enterprise_name', 'created_at')->orderBy('building_name')->orderBy('floor')->get()->toArray();
                $filename = '房源资产精细库';
                break;
            case 'patrol_records':
                $headers = ['流水号', '关联点位ID', '巡检物理点位', '打卡人员', '工况状态(1正常/0异常)', '隐患备注', '打卡时间'];
                $data = Db::table('patrol_records')->select('id', 'point_id', 'location', 'worker_name', 'status', 'remarks', 'created_at')->orderBy('id', 'desc')->get()->toArray();
                $filename = '安防巡检打卡流水';
                break;
            case 'work_orders':
                $headers = ['工单ID', '任务主题', '故障描述', '提报来源', '处理人员ID', '流转状态(1待指派/2处理中/3待验/4结案)', '下发时间'];
                $data = Db::table('work_orders')->select('id', 'title', 'description', 'reporter_name', 'handler_id', 'status', 'created_at')->orderBy('id', 'desc')->get()->toArray();
                $filename = '中控调度工单池';
                break;
            case 'inventory':
                $headers = ['物料编码', '物料名称', '分类', '结余库存', '单位', '加权平均单价', '在库总货值'];
                $data = Db::table('inventory_items')->selectRaw('sku_code, name, category, qty, unit, avg_price, (qty * avg_price) as total_value')->get()->toArray();
                $filename = '物资资产期末盘点表';
                break;
            case 'decorations':
                $headers = ['报备单号', '企业ID', '空间ID', '进场日期', '完工日期', '核准工期天数', '当前状态', '现场负责人'];
                $data = Db::table('decorations')->select('apply_no', 'enterprise_id', 'space_id', 'start_date', 'end_date', 'total_days', 'status', 'manager')->get()->toArray();
                $filename = '装修报备及工程台账';
                break;
            default:
                return json(['code' => 400, 'msg' => '未知的导出模块标识']);
        }

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

        $csvContent .= "\n\n====== 内部数据安全防伪溯源水印 ======\n";
        $csvContent .= "导出时间：," . date('Y-m-d H:i:s') . "\n";
        $csvContent .= "操作人员：," . ($user->real_name ?? '系统') . " (账号:" . ($user->username ?? '-') . ")\n";
        $csvContent .= "终端IP：," . $request->getRealIp() . "\n";
        $csvContent .= "警告：," . "本文件受企业数据资产法保护，严禁非法外传。\n";

        return new Response(200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '_' . date('YmdHi') . '.csv"',
        ], $csvContent);
    }

    /**
     * 提交导出审批申请
     */
    public function apply(Request $request)
    {
        $user = $request->user;
        $module = $request->post('module');
        $reason = $request->post('reason');

        if (!$module || !$reason) return json(['code' => 400, 'msg' => '参数不完整']);

        $pending = Db::table('export_applications')->where('applicant_id', $user->id)->where('module_name', $module)->where('status', 0)->exists();
        if ($pending) return json(['code' => 400, 'msg' => '您已有该模块的审核正在进行中，请勿重复提交']);

        Db::table('export_applications')->insert([
            'tenant_id' => $user->tenant_id ?? 1,
            'applicant_id' => $user->id,
            'applicant_name' => $user->real_name,
            'module_name' => $module,
            'reason' => $reason,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '导出申请已流转至总控审批中心']);
    }

    /**
     * 拉取待审批及历史审批流
     */
    public function applicationList(Request $request)
    {
        $user = $request->user;
        if ($user->data_scope != 3) {
            // 基层只看自己的申请
            $list = Db::table('export_applications')->where('applicant_id', $user->id)->orderBy('id', 'desc')->get();
        } else {
            // 超管看全部
            $list = Db::table('export_applications')->orderBy('status', 'asc')->orderBy('id', 'desc')->get();
        }
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    /**
     * 超管执行审批动作
     */
    public function approve(Request $request)
    {
        $user = $request->user;
        if ($user->data_scope != 3) return json(['code' => 403, 'msg' => '越权操作']);

        $id = $request->post('id');
        $status = $request->post('status'); // 1通过 2驳回

        Db::table('export_applications')->where('id', $id)->update([
            'status' => $status,
            'auditor_id' => $user->id,
            'expired_at' => $status == 1 ? date('Y-m-d H:i:s', strtotime('+24 hours')) : null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '审批已下发']);
    }

    public function auditLogs(Request $request)
    {
        $list = Db::table('export_audit_logs')->orderBy('id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }
}