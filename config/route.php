<?php
use Webman\Route;

Route::options('[{path:.+}]', function (){
    return response('');
});

Route::post('/api/login', [app\controller\LoginController::class, 'login']);
Route::post('/api/tenant/login', [app\controller\LoginController::class, 'tenantLogin']);

Route::group('/api', function () {
    Route::get('/dashboard', [app\controller\DashboardController::class, 'index']);
    
    // 系统控制与数据脱密审计
    Route::get('/system/roles/list', [app\controller\SystemController::class, 'roleList']);
    Route::post('/system/roles/add', [app\controller\SystemController::class, 'roleAdd']);
    Route::get('/system/admins/list', [app\controller\SystemController::class, 'adminList']);
    Route::post('/system/admins/add', [app\controller\SystemController::class, 'adminAdd']);
    Route::get('/export/download', [app\controller\ExportController::class, 'download']);
    Route::get('/system/audit/logs', [app\controller\ExportController::class, 'auditLogs']);
    
    // 空间资产台账
    Route::get('/buildings/list', [app\controller\BuildingController::class, 'list']);
    Route::post('/buildings/add', [app\controller\BuildingController::class, 'add']);
    Route::get('/spaces/list', [app\controller\SpaceController::class, 'list']);
    Route::post('/spaces/add', [app\controller\SpaceController::class, 'add']);
    Route::post('/spaces/update', [app\controller\SpaceController::class, 'update']);
    Route::post('/spaces/delete', [app\controller\SpaceController::class, 'delete']);
    Route::post('/spaces/status', [app\controller\SpaceController::class, 'updateStatus']);
    Route::get('/v1/assets/tree', [app\controller\SpaceController::class, 'tree']);
    
    // 智能车场月卡资产核心管理
    Route::get('/vehicles/list', [app\controller\VehicleController::class, 'list']);
    Route::post('/vehicles/add', [app\controller\VehicleController::class, 'add']);
    Route::post('/vehicles/renew', [app\controller\VehicleController::class, 'renew']);
    Route::post('/vehicles/delete', [app\controller\VehicleController::class, 'delete']);
    
    // CRM 线索管理
    Route::get('/leads/list', [app\controller\LeadController::class, 'list']);
    Route::post('/leads/add', [app\controller\LeadController::class, 'add']);
    Route::get('/leads/follow/list', [app\controller\LeadController::class, 'followList']);
    Route::post('/leads/follow/add', [app\controller\LeadController::class, 'followAdd']);
    
    // 企业户籍档案与租户账号管理
    Route::get('/enterprises/list', [app\controller\EnterpriseController::class, 'list']);
    Route::post('/enterprises/add', [app\controller\EnterpriseController::class, 'add']);
    Route::post('/enterprises/reset_pwd', [app\controller\EnterpriseController::class, 'resetPwd']);

    // 合同管理与退租清算
    Route::get('/contracts/list', [app\controller\ContractController::class, 'list']);
    Route::post('/contracts/add', [app\controller\ContractController::class, 'add']);
    Route::post('/contracts/terminate', [app\controller\ContractController::class, 'terminate']);
    Route::get('/contracts/docs', [app\controller\ContractController::class, 'docs']);
    Route::post('/contracts/generate_elec', [app\controller\ContractController::class, 'generateElec']);
    
    // 业财核销控制
    Route::get('/finance/receivables/list', [app\controller\FinanceController::class, 'receivableList']);
    Route::post('/finance/receivables/pay', [app\controller\FinanceController::class, 'pay']);
    Route::post('/finance/meters/record', [app\controller\FinanceController::class, 'recordMeter']);
    
    // BI 数据魔方中心
    Route::get('/reports/finance', [app\controller\ReportController::class, 'financeStats']);
    Route::get('/reports/leads', [app\controller\ReportController::class, 'leadStats']);
    Route::get('/reports/assets', [app\controller\ReportController::class, 'assetStats']);
    
    // 移动租户端专用多维服务中台接口
    Route::get('/tenant/overview', [app\controller\TenantPortalController::class, 'getOverview']);
    Route::get('/tenant/bills', [app\controller\TenantPortalController::class, 'getBills']);
    Route::get('/tenant/contracts', [app\controller\TenantPortalController::class, 'getContracts']);
    Route::post('/tenant/order/submit', [app\controller\TenantPortalController::class, 'submitOrder']);
    Route::post('/tenant/pay', [app\controller\TenantPortalController::class, 'payBill']);
    
    // 安防网格中心
    Route::get('/patrol/points/list', [app\controller\PatrolController::class, 'pointList']);
    Route::post('/patrol/points/add', [app\controller\PatrolController::class, 'pointAdd']);
    Route::post('/patrol/checkin', [app\controller\PatrolController::class, 'checkin']);
    Route::get('/patrol/records', [app\controller\PatrolController::class, 'records']);
    
    // 工单服务与基层人员管理
    Route::get('/services/work-orders/list', [app\controller\WorkOrderController::class, 'list']);
    Route::post('/services/work-orders/assign', [app\controller\WorkOrderController::class, 'assign']);
    Route::post('/services/work-orders/complete', [app\controller\WorkOrderController::class, 'complete']);
    Route::post('/services/work-orders/verify', [app\controller\WorkOrderController::class, 'verify']);
    Route::get('/services/staff/list', [app\controller\ServiceStaffController::class, 'list']);
    Route::post('/services/staff/add', [app\controller\ServiceStaffController::class, 'add']);
    Route::post('/services/staff/update', [app\controller\ServiceStaffController::class, 'update']);
    Route::post('/services/staff/delete', [app\controller\ServiceStaffController::class, 'delete']);

    Route::post('/upload', [app\controller\UploadController::class, 'upload']);

})->middleware([
    app\middleware\AuthMiddleware::class
]);