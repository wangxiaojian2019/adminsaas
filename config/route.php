<?php
use Webman\Route;
use support\Response;

// 跨域 OPTIONS 请求处理
Route::options('[{path:.+}]', function () {
    return new Response(204, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, token',
        'Access-Control-Max-Age' => '86400',
    ]);
});

// 登录模块
Route::post('/api/login', [app\controller\LoginController::class, 'login']);
Route::post('/api/tenant/login', [app\controller\LoginController::class, 'tenantLogin']);

Route::group('/api', function () {
    
    // 菜单与仪表盘
    Route::get('/system/getMyMenus', [app\controller\SystemController::class, 'getMyMenus']);
    Route::get('/dashboard', [app\controller\DashboardController::class, 'index']);
    
    // 系统权限与管理员
    Route::get('/system/roles/list', [app\controller\SystemController::class, 'roleList']);
    Route::post('/system/roles/add', [app\controller\SystemController::class, 'roleAdd']);
    Route::post('/system/roles/update', [app\controller\SystemController::class, 'roleUpdate']);
    Route::post('/system/roles/delete', [app\controller\SystemController::class, 'roleDelete']);
    Route::get('/system/admins/list', [app\controller\SystemController::class, 'adminList']);
    Route::post('/system/admins/add', [app\controller\SystemController::class, 'adminAdd']);
    Route::post('/system/admins/update', [app\controller\SystemController::class, 'adminUpdate']); 
    Route::post('/system/admins/delete', [app\controller\SystemController::class, 'adminDelete']); 
    
    // 数据导出
    Route::get('/export/download', [app\controller\ExportController::class, 'download']);
    Route::get('/system/audit/logs', [app\controller\ExportController::class, 'auditLogs']);
    
    // 楼宇与空间房源资产
    Route::get('/buildings/list', [app\controller\BuildingController::class, 'list']);
    Route::post('/buildings/add', [app\controller\BuildingController::class, 'add']);
    Route::get('/spaces/list', [app\controller\SpaceController::class, 'list']);
    Route::post('/spaces/add', [app\controller\SpaceController::class, 'add']);
    Route::post('/spaces/update', [app\controller\SpaceController::class, 'update']);
    Route::post('/spaces/delete', [app\controller\SpaceController::class, 'delete']);
    Route::post('/spaces/status', [app\controller\SpaceController::class, 'updateStatus']);
    Route::get('/v1/assets/tree', [app\controller\SpaceController::class, 'tree']);
    
    // 车辆管理
    Route::get('/vehicles/list', [app\controller\VehicleController::class, 'list']);
    Route::post('/vehicles/add', [app\controller\VehicleController::class, 'add']);
    Route::post('/vehicles/renew', [app\controller\VehicleController::class, 'renew']);
    Route::post('/vehicles/delete', [app\controller\VehicleController::class, 'delete']);
    
    // 招商线索与公海
    Route::get('/leads/list', [app\controller\LeadController::class, 'list']);
    Route::post('/leads/add', [app\controller\LeadController::class, 'add']);
    Route::post('/leads/claim', [app\controller\LeadController::class, 'claim']); 
    Route::get('/leads/follow/list', [app\controller\LeadController::class, 'followList']);
    Route::post('/leads/follow/add', [app\controller\LeadController::class, 'followAdd']);
    
    // 企业户籍
    Route::get('/enterprises/list', [app\controller\EnterpriseController::class, 'list']);
    Route::post('/enterprises/add', [app\controller\EnterpriseController::class, 'add']);
    Route::post('/enterprises/reset_pwd', [app\controller\EnterpriseController::class, 'resetPwd']);

    // 合同中心
    Route::get('/contracts/list', [app\controller\ContractController::class, 'list']);
    Route::post('/contracts/add', [app\controller\ContractController::class, 'add']);
    Route::post('/contracts/terminate', [app\controller\ContractController::class, 'terminate']);
    Route::post('/contracts/revoke_terminate', [app\controller\ContractController::class, 'revokeTerminate']); 
    Route::get('/contracts/docs', [app\controller\ContractController::class, 'docs']);
    Route::post('/contracts/generate_elec', [app\controller\ContractController::class, 'generateElec']);
    Route::post('/contracts/alter', [app\controller\ContractController::class, 'alterContract']);
    Route::get('/contracts/history', [app\controller\ContractController::class, 'history']);
    
    // 财务收支与能耗
    Route::get('/finance/receivables/list', [app\controller\FinanceController::class, 'receivableList']);
    Route::post('/finance/receivables/pay', [app\controller\FinanceController::class, 'pay']);
    Route::get('/finance/checkouts/list', [app\controller\FinanceController::class, 'checkoutList']);
    Route::post('/finance/checkouts/pay', [app\controller\FinanceController::class, 'payCheckout']);
    Route::get('/finance/meters/list', [app\controller\FinanceController::class, 'meterList']);
    Route::post('/finance/meters/record', [app\controller\FinanceController::class, 'recordMeter']);
    Route::get('/finance/meterHistory', [app\controller\FinanceController::class, 'meterHistory']);
    
    // 统计报表
    Route::get('/reports/finance', [app\controller\ReportController::class, 'financeStats']);
    Route::get('/reports/leads', [app\controller\ReportController::class, 'leadStats']);
    Route::get('/reports/assets', [app\controller\ReportController::class, 'assetStats']);
    
    // 租户H5门户
    Route::get('/tenant/overview', [app\controller\TenantPortalController::class, 'getOverview']);
    Route::get('/tenant/bills', [app\controller\TenantPortalController::class, 'getBills']);
    Route::get('/tenant/contracts', [app\controller\TenantPortalController::class, 'getContracts']);
    Route::post('/tenant/pay', [app\controller\TenantPortalController::class, 'payBill']);
    Route::post('/tenant/order/submit', [app\controller\TenantPortalController::class, 'submitOrder']); 
    Route::post('/tenant/password/update', [app\controller\TenantPortalController::class, 'updatePassword']); 
    Route::get('/tenant/inventory', [app\controller\TenantPortalController::class, 'getInventory']);

    // 员工H5门户
    Route::get('/worker/tasks', [app\controller\WorkerPortalController::class, 'getTasks']);
    Route::post('/worker/tasks/complete', [app\controller\WorkerPortalController::class, 'completeTask']);
    Route::get('/worker/patrol/points', [app\controller\WorkerPortalController::class, 'getPatrolPoints']);
    Route::post('/worker/patrol/submit', [app\controller\WorkerPortalController::class, 'submitPatrol']);
    Route::post('/worker/password/update', [app\controller\WorkerPortalController::class, 'updatePassword']); 
    Route::get('/worker/inventory', [app\controller\WorkerPortalController::class, 'getInventory']);
    Route::get('/worker/notifications', [app\controller\WorkerPortalController::class, 'getNotifications']);
    Route::post('/worker/notifications/read', [app\controller\WorkerPortalController::class, 'readNotification']);
    
    // 巡更管理
    Route::get('/patrol/points/list', [app\controller\PatrolController::class, 'pointList']);
    Route::post('/patrol/points/add', [app\controller\PatrolController::class, 'pointAdd']);
    Route::post('/patrol/checkin', [app\controller\PatrolController::class, 'checkin']);
    Route::get('/patrol/records', [app\controller\PatrolController::class, 'records']);
    
    // 外勤工单大盘核心引擎
    Route::get('/work_order/list', [app\controller\WorkOrderController::class, 'list']);
    Route::post('/work_order/add', [app\controller\WorkOrderController::class, 'add']);
    Route::post('/work_order/action', [app\controller\WorkOrderController::class, 'action']);
    
    // 物业服务基层员工
    Route::get('/services/staff/list', [app\controller\ServiceStaffController::class, 'list']);
    Route::post('/services/staff/add', [app\controller\ServiceStaffController::class, 'add']);
    Route::post('/services/staff/update', [app\controller\ServiceStaffController::class, 'update']);
    Route::post('/services/staff/delete', [app\controller\ServiceStaffController::class, 'delete']);

    // 通用上传
    Route::post('/upload', [app\controller\UploadController::class, 'upload']);

    // 站内信
    Route::get('/notification/list', [app\controller\NotificationController::class, 'list']);
    Route::post('/notification/read', [app\controller\NotificationController::class, 'read']);

    // 物资库存
    Route::get('/inventory/list', [app\controller\InventoryController::class, 'list']);
    Route::post('/inventory/add', [app\controller\InventoryController::class, 'add']);
    Route::post('/inventory/action', [app\controller\InventoryController::class, 'action']);
    Route::get('/inventory/records', [app\controller\InventoryController::class, 'records']);

})->middleware([
    app\middleware\AuthMiddleware::class
]);

Route::fallback(function (\support\Request $request) {
    return new Response(404, [
        'Content-Type' => 'application/json',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS'
    ], json_encode(['code' => 404, 'msg' => '致命错误：接口地址不存在 -> ' . $request->path()]));
});