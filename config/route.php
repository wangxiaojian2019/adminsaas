<?php
use Webman\Route;
use support\Response;

// 1. 全局 OPTIONS 请求拦截与跨域头强力下发
Route::options('[{path:.+}]', function () {
    return new Response(204, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, token',
        'Access-Control-Max-Age' => '86400',
    ]);
});

Route::post('/api/login', [app\controller\LoginController::class, 'login']);
Route::post('/api/tenant/login', [app\controller\LoginController::class, 'tenantLogin']);

Route::group('/api', function () {
    
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
    
    Route::get('/export/download', [app\controller\ExportController::class, 'download']);
    Route::get('/system/audit/logs', [app\controller\ExportController::class, 'auditLogs']);
    
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
    
    Route::get('/leads/list', [app\controller\LeadController::class, 'list']);
    Route::post('/leads/add', [app\controller\LeadController::class, 'add']);
    Route::get('/leads/follow/list', [app\controller\LeadController::class, 'followList']);
    Route::post('/leads/follow/add', [app\controller\LeadController::class, 'followAdd']);
    
    Route::get('/enterprises/list', [app\controller\EnterpriseController::class, 'list']);
    Route::post('/enterprises/add', [app\controller\EnterpriseController::class, 'add']);
    Route::post('/enterprises/reset_pwd', [app\controller\EnterpriseController::class, 'resetPwd']);

    Route::get('/contracts/list', [app\controller\ContractController::class, 'list']);
    Route::post('/contracts/add', [app\controller\ContractController::class, 'add']);
    Route::post('/contracts/terminate', [app\controller\ContractController::class, 'terminate']);
    Route::post('/contracts/revoke_terminate', [app\controller\ContractController::class, 'revokeTerminate']); 
    Route::get('/contracts/docs', [app\controller\ContractController::class, 'docs']);
    Route::post('/contracts/generate_elec', [app\controller\ContractController::class, 'generateElec']);
    
    Route::get('/finance/receivables/list', [app\controller\FinanceController::class, 'receivableList']);
    Route::post('/finance/receivables/pay', [app\controller\FinanceController::class, 'pay']);
    Route::post('/finance/meters/record', [app\controller\FinanceController::class, 'recordMeter']);
    Route::get('/finance/checkouts/list', [app\controller\FinanceController::class, 'checkoutList']);
    Route::post('/finance/checkouts/pay', [app\controller\FinanceController::class, 'payCheckout']);
    
    Route::get('/reports/finance', [app\controller\ReportController::class, 'financeStats']);
    Route::get('/reports/leads', [app\controller\ReportController::class, 'leadStats']);
    Route::get('/reports/assets', [app\controller\ReportController::class, 'assetStats']);
    
    Route::get('/tenant/overview', [app\controller\TenantPortalController::class, 'getOverview']);
    Route::get('/tenant/bills', [app\controller\TenantPortalController::class, 'getBills']);
    Route::get('/tenant/contracts', [app\controller\TenantPortalController::class, 'getContracts']);
    Route::post('/tenant/pay', [app\controller\TenantPortalController::class, 'payBill']);
    Route::post('/tenant/order/submit', [app\controller\TenantPortalController::class, 'submitOrder']); 
    Route::post('/tenant/password/update', [app\controller\TenantPortalController::class, 'updatePassword']); 

    // 基层外勤端作业移动中台
    Route::get('/worker/tasks', [app\controller\WorkerPortalController::class, 'getTasks']);
    Route::post('/worker/tasks/complete', [app\controller\WorkerPortalController::class, 'completeTask']);
    Route::get('/worker/patrol/points', [app\controller\WorkerPortalController::class, 'getPatrolPoints']);
    Route::post('/worker/patrol/submit', [app\controller\WorkerPortalController::class, 'submitPatrol']);
    Route::post('/worker/password/update', [app\controller\WorkerPortalController::class, 'updatePassword']); // 核心新增：外勤人员改密通道
    
    Route::get('/patrol/points/list', [app\controller\PatrolController::class, 'pointList']);
    Route::post('/patrol/points/add', [app\controller\PatrolController::class, 'pointAdd']);
    Route::post('/patrol/checkin', [app\controller\PatrolController::class, 'checkin']);
    Route::get('/patrol/records', [app\controller\PatrolController::class, 'records']);
    
    Route::get('/services/work-orders/list', [app\controller\WorkOrderController::class, 'list']);
    Route::get('/services/workOrdersList', [app\controller\WorkOrderController::class, 'list']);
    Route::post('/services/work-orders/assign', [app\controller\WorkOrderController::class, 'assign']);
    Route::post('/services/workOrdersAssign', [app\controller\WorkOrderController::class, 'assign']);
    Route::post('/services/work-orders/complete', [app\controller\WorkOrderController::class, 'complete']);
    Route::post('/services/workOrdersComplete', [app\controller\WorkOrderController::class, 'complete']);
    Route::post('/services/work-orders/verify', [app\controller\WorkOrderController::class, 'verify']);
    Route::post('/services/workOrdersVerify', [app\controller\WorkOrderController::class, 'verify']);
    Route::get('/services/staff/list', [app\controller\ServiceStaffController::class, 'list']);
    Route::post('/services/staff/add', [app\controller\ServiceStaffController::class, 'add']);
    Route::post('/services/staff/update', [app\controller\ServiceStaffController::class, 'update']);
    Route::post('/services/staff/delete', [app\controller\ServiceStaffController::class, 'delete']);

    Route::post('/upload', [app\controller\UploadController::class, 'upload']);

})->middleware([
    app\middleware\AuthMiddleware::class
]);

Route::fallback(function (\support\Request $request) {
    return new Response(404, [
        'Content-Type' => 'application/json',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, token'
    ], json_encode(['code' => 404, 'msg' => '致命错误：后端接口地址不存在 -> ' . $request->path()]));
});