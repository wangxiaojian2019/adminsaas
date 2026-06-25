<?php
use Webman\Route;
use support\Response;

// ==========================================
// 跨域 OPTIONS 请求处理
// ==========================================
Route::options('[{path:.+}]', function () {
    return new Response(204, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, token',
        'Access-Control-Max-Age' => '86400',
    ]);
});

// ==========================================
// 免鉴权区：IoT 边缘硬件网关直连通道
// ==========================================
Route::post('/api/iot/webhook', [app\controller\IotController::class, 'webhook']);

// ==========================================
// 登录与鉴权签发 (已接入原生 HS256 JWT 引擎)
// ==========================================
Route::post('/api/login', [app\controller\LoginController::class, 'login']);
Route::post('/api/tenant/login', [app\controller\LoginController::class, 'tenantLogin']);

// ==========================================
// 核心鉴权路由组 (受 AuthMiddleware 保护)
// ==========================================
Route::group('/api', function () {
    
    // ------------------------------------------
    // 1. 系统权限与中控大盘
    // ------------------------------------------
    Route::get('/system/getMyMenus', [app\controller\SystemController::class, 'getMyMenus']);
    Route::get('/dashboard', [app\controller\DashboardController::class, 'index']);
    Route::get('/system/roles/list', [app\controller\SystemController::class, 'roleList']);
    Route::post('/system/roles/add', [app\controller\SystemController::class, 'roleAdd']);
    Route::post('/system/roles/update', [app\controller\SystemController::class, 'roleUpdate']);
    Route::post('/system/roles/delete', [app\controller\SystemController::class, 'roleDelete']);
    Route::get('/system/admins/list', [app\controller\SystemController::class, 'adminList']);
    Route::post('/system/admins/add', [app\controller\SystemController::class, 'adminAdd']);
    Route::post('/system/admins/update', [app\controller\SystemController::class, 'adminUpdate']); 
    Route::post('/system/admins/delete', [app\controller\SystemController::class, 'adminDelete']); 
    
    // 数据导出与审计日志
    Route::get('/export/download', [app\controller\ExportController::class, 'download']);
    Route::get('/system/audit/logs', [app\controller\ExportController::class, 'auditLogs']);
    
    // ------------------------------------------
    // 2. 空间资产与车位底库 
    // ------------------------------------------
    Route::get('/buildings/list', [app\controller\BuildingController::class, 'list']);
    Route::post('/buildings/add', [app\controller\BuildingController::class, 'add']);
    Route::get('/spaces/list', [app\controller\SpaceController::class, 'list']);
    Route::post('/spaces/add', [app\controller\SpaceController::class, 'add']);
    Route::post('/spaces/update', [app\controller\SpaceController::class, 'update']);
    Route::post('/spaces/delete', [app\controller\SpaceController::class, 'delete']);
    Route::post('/spaces/status', [app\controller\SpaceController::class, 'updateStatus']);
    Route::get('/v1/assets/tree', [app\controller\SpaceController::class, 'tree']);
    
    Route::get('/vehicles/list', [app\controller\VehicleController::class, 'list']);
    Route::post('/vehicles/add', [app\controller\VehicleController::class, 'add']);
    Route::post('/vehicles/renew', [app\controller\VehicleController::class, 'renew']);
    Route::post('/vehicles/delete', [app\controller\VehicleController::class, 'delete']);
    
    // ------------------------------------------
    // 3. CRM 招商线索与企业户籍
    // ------------------------------------------
    Route::get('/leads/list', [app\controller\LeadController::class, 'list']);
    Route::post('/leads/add', [app\controller\LeadController::class, 'add']);
    Route::get('/leads/follow/list', [app\controller\LeadController::class, 'followList']);
    Route::post('/leads/follow/add', [app\controller\LeadController::class, 'followAdd']); 
    
    Route::get('/enterprises/list', [app\controller\EnterpriseController::class, 'list']);
    Route::post('/enterprises/add', [app\controller\EnterpriseController::class, 'add']);
    Route::post('/enterprises/reset_pwd', [app\controller\EnterpriseController::class, 'resetPwd']);

    // ------------------------------------------
    // 4. 租务与合同中心
    // ------------------------------------------
    Route::get('/contracts/list', [app\controller\ContractController::class, 'list']);
    Route::post('/contracts/add', [app\controller\ContractController::class, 'add']);
    Route::post('/contracts/terminate', [app\controller\ContractController::class, 'terminate']);
    Route::post('/contracts/revoke_terminate', [app\controller\ContractController::class, 'revokeTerminate']); 
    Route::get('/contracts/docs', [app\controller\ContractController::class, 'docs']);
    Route::post('/contracts/generate_elec', [app\controller\ContractController::class, 'generateElec']);
    Route::post('/contracts/alter', [app\controller\ContractController::class, 'alterContract']);
    Route::get('/contracts/history', [app\controller\ContractController::class, 'history']);
    
    // ------------------------------------------
    // 5. 业财一体化流水核销中心
    // ------------------------------------------
    Route::get('/finance/receivables/list', [app\controller\FinanceController::class, 'receivableList']);
    Route::get('/finance/transactions/list', [app\controller\FinanceController::class, 'transactions']);
    Route::post('/finance/transactions/audit', [app\controller\FinanceController::class, 'auditTransaction']);
    Route::get('/finance/checkouts/list', [app\controller\FinanceController::class, 'checkoutList']);
    Route::post('/finance/checkouts/pay', [app\controller\FinanceController::class, 'payCheckout']);
    Route::get('/finance/meters/list', [app\controller\FinanceController::class, 'meterList']);
    Route::post('/finance/meters/record', [app\controller\FinanceController::class, 'recordMeter']);
    Route::get('/finance/meterHistory', [app\controller\FinanceController::class, 'meterHistory']);
    
    Route::get('/reports/finance', [app\controller\ReportController::class, 'financeStats']);
    Route::get('/reports/leads', [app\controller\ReportController::class, 'leadStats']);
    Route::get('/reports/assets', [app\controller\ReportController::class, 'assetStats']);
    
    // ------------------------------------------
    // 6. 物业与外勤调度 
    // ------------------------------------------
    Route::get('/patrol/points/list', [app\controller\PatrolController::class, 'pointList']);
    Route::post('/patrol/points/add', [app\controller\PatrolController::class, 'pointAdd']);
    Route::post('/patrol/checkin', [app\controller\PatrolController::class, 'checkin']);
    Route::get('/patrol/records', [app\controller\PatrolController::class, 'records']);
    
    Route::get('/work_order/list', [app\controller\WorkOrderController::class, 'list']);
    Route::post('/work_order/add', [app\controller\WorkOrderController::class, 'add']);
    Route::post('/work_order/action', [app\controller\WorkOrderController::class, 'action']);
    
    Route::get('/services/staff/list', [app\controller\ServiceStaffController::class, 'list']);
    Route::post('/services/staff/add', [app\controller\ServiceStaffController::class, 'add']);
    Route::post('/services/staff/update', [app\controller\ServiceStaffController::class, 'update']);
    Route::post('/services/staff/delete', [app\controller\ServiceStaffController::class, 'delete']);
    
    Route::post('/upload', [app\controller\UploadController::class, 'upload']);
    
    Route::get('/notification/list', [app\controller\NotificationController::class, 'list']);
    Route::post('/notification/read', [app\controller\NotificationController::class, 'read']);

    // ------------------------------------------
    // 7. 高阶业务流转引擎
    // ------------------------------------------
    Route::get('/v1/decoration/list', [app\controller\DecorationController::class, 'list']);
    Route::post('/v1/decoration/apply', [app\controller\DecorationController::class, 'apply']);
    Route::post('/v1/decoration/audit', [app\controller\DecorationController::class, 'audit']);
    Route::post('/v1/decoration/delay', [app\controller\DecorationController::class, 'applyDelay']);
    
    Route::get('/v1/inventory/list', [app\controller\InventoryController::class, 'stockList']);
    // 【修复1】: 修正获取流水的控制器方法映射名为 logs
    Route::get('/v1/inventory/logs', [app\controller\InventoryController::class, 'logs']); 
    Route::post('/v1/inventory/inbound', [app\controller\InventoryController::class, 'inbound']);
    Route::post('/v1/inventory/outbound', [app\controller\InventoryController::class, 'outbound']);
    // 【修复2】: 补齐新建物料档案的路由注册
    Route::post('/v1/inventory/add', [app\controller\InventoryController::class, 'add']); 
    
    Route::get('/v1/meeting/rooms/list', [app\controller\MeetingController::class, 'roomList']);
    Route::post('/v1/meeting/rooms/add', [app\controller\MeetingController::class, 'roomAdd']);
    Route::post('/v1/meeting/rooms/update', [app\controller\MeetingController::class, 'roomUpdate']);
    Route::post('/v1/meeting/rooms/delete', [app\controller\MeetingController::class, 'roomDelete']);
    Route::get('/v1/meeting/list', [app\controller\MeetingController::class, 'bookingList']);
    Route::post('/v1/meeting/audit', [app\controller\MeetingController::class, 'audit']);
    Route::post('/v1/meeting/apply', [app\controller\MeetingController::class, 'apply']); 
    
    Route::get('/v1/fee-config/get', [app\controller\FeeConfigController::class, 'get']);
    Route::post('/v1/fee-config/save', [app\controller\FeeConfigController::class, 'save']);
    Route::get('/v1/iot/list', [app\controller\IotController::class, 'list']);
    Route::post('/v1/iot/control', [app\controller\IotController::class, 'control']);

    // ------------------------------------------
    // 8. 租户企业 H5 专属网关
    // ------------------------------------------
    Route::get('/tenant/overview', [app\controller\TenantPortalController::class, 'getOverview']);
    Route::get('/tenant/bills', [app\controller\TenantPortalController::class, 'getBills']);
    Route::get('/tenant/contracts', [app\controller\TenantPortalController::class, 'getContracts']);
    Route::post('/tenant/pay', [app\controller\TenantPortalController::class, 'payBill']);
    Route::post('/tenant/order/submit', [app\controller\TenantPortalController::class, 'submitOrder']); 
    Route::post('/tenant/password/update', [app\controller\TenantPortalController::class, 'updatePassword']); 
    Route::get('/tenant/inventory', [app\controller\TenantPortalController::class, 'getInventory']);
    Route::get('/tenant/decorations', [app\controller\TenantPortalController::class, 'getDecorations']);
    Route::post('/tenant/decoration/apply', [app\controller\TenantPortalController::class, 'applyDecoration']);
    Route::get('/tenant/meeting/rooms', [app\controller\TenantPortalController::class, 'getMeetingRooms']);
    Route::get('/tenant/meeting/list', [app\controller\TenantPortalController::class, 'getMyMeetings']);
    Route::post('/tenant/meeting/apply', [app\controller\TenantPortalController::class, 'applyMeeting']);

    // ------------------------------------------
    // 9. 基层员工/外勤师傅 H5 作业通道 
    // ------------------------------------------
    Route::post('/worker/work_order/report', [app\controller\WorkerPortalController::class, 'reportIssue']); 
    Route::get('/worker/tasks', [app\controller\WorkerPortalController::class, 'getTasks']);
    Route::post('/worker/tasks/complete', [app\controller\WorkerPortalController::class, 'completeTask']);
    Route::get('/worker/patrol/points', [app\controller\WorkerPortalController::class, 'getPatrolPoints']);
    Route::post('/worker/patrol/submit', [app\controller\WorkerPortalController::class, 'submitPatrol']);
    Route::get('/worker/patrol/records', [app\controller\WorkerPortalController::class, 'getPatrolRecords']); 
    Route::post('/worker/password/update', [app\controller\WorkerPortalController::class, 'updatePassword']); 
    Route::get('/worker/inventory', [app\controller\WorkerPortalController::class, 'getInventory']);
    Route::get('/worker/notifications', [app\controller\WorkerPortalController::class, 'getNotifications']);
    Route::post('/worker/notifications/read', [app\controller\WorkerPortalController::class, 'readNotification']);

})->middleware([
    app\middleware\AuthMiddleware::class
]);

// ==========================================
// 404 全局兜底
// ==========================================
Route::fallback(function (\support\Request $request) {
    return new Response(404, [
        'Content-Type' => 'application/json',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS'
    ], json_encode(['code' => 404, 'msg' => '致命错误：接口地址或请求方法不存在 -> ' . $request->path()]));
});