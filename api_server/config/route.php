<?php
use Webman\Route;
use app\middleware\AuthMiddleware;

Route::any('/', fn() => response('SaaS API Server is running.'));
Route::any('/api/cron/daily-billing', [app\controller\FinanceController::class, 'dailyCronTask']);
Route::any('/api/login', [app\controller\AuthController::class, 'login']);

Route::group('/api', function () {
    Route::any('/dashboard', [app\controller\DashboardController::class, 'getIndex']);
    
    // --- 模块 2：系统与权限控制 ---
    Route::any('/system/roles/list', [app\controller\SystemController::class, 'getRoles']);
    Route::any('/system/roles/add', [app\controller\SystemController::class, 'addRole']);
    Route::any('/system/admins/list', [app\controller\SystemController::class, 'getAdmins']);
    Route::any('/system/admins/add', [app\controller\SystemController::class, 'addAdmin']);

    // --- 模块 3：大厦与资产管理 ---
    Route::any('/buildings/list', [app\controller\BuildingController::class, 'getList']);
    Route::any('/buildings/add', [app\controller\BuildingController::class, 'add']);
    Route::any('/spaces/tree', [app\controller\SpaceController::class, 'getSpaceTree']);
    Route::any('/spaces/list', [app\controller\SpaceController::class, 'getList']);
    Route::any('/spaces/add', [app\controller\SpaceController::class, 'addSpace']);
    Route::any('/spaces/status', [app\controller\SpaceController::class, 'updateStatus']);
    Route::any('/config', [app\controller\SpaceController::class, 'getConfig']);
    Route::any('/config/update', [app\controller\SpaceController::class, 'updateConfig']);

    // --- 模块 4：招商与线索 CRM ---
    Route::any('/leads/list', [app\controller\LeadsController::class, 'getLeads']);
    Route::any('/leads/add', [app\controller\LeadsController::class, 'addLead']);
    Route::any('/leads/follow/add', [app\controller\LeadsController::class, 'addFollow']);
    Route::any('/leads/follow/list', [app\controller\LeadsController::class, 'getFollows']);

    // --- 模块 5：租务与合同中心 ---
    Route::any('/enterprises/list', [app\controller\ContractController::class, 'getEnterprises']);
    Route::any('/enterprises/add', [app\controller\ContractController::class, 'addEnterprise']);
    Route::any('/contracts/list', [app\controller\ContractController::class, 'getContracts']);
    Route::any('/contracts/add', [app\controller\ContractController::class, 'addContract']);
    Route::any('/contracts/terminate', [app\controller\ContractController::class, 'terminateContract']);
    Route::any('/contracts/docs', [app\controller\ContractController::class, 'getContractDocs']);
    Route::any('/contracts/generate_elec', [app\controller\ContractController::class, 'generateElecContract']);
    Route::any('/upload', [app\controller\ContractController::class, 'uploadAttachment']);

    // --- 模块 6：业财一体化中心 ---
    Route::any('/finance/meters/record', [app\controller\FinanceController::class, 'recordMeter']); 
    Route::any('/finance/receivables/list', [app\controller\FinanceController::class, 'getReceivables']); 
    Route::any('/finance/receivables/pay', [app\controller\FinanceController::class, 'payBill']); 

    // --- 模块 7 & 8：安防巡检与闭环工单状态机接口 ---
    Route::any('/patrol/points/list', [app\controller\PatrolController::class, 'getPoints']);
    Route::any('/patrol/points/add', [app\controller\PatrolController::class, 'addPoint']);
    Route::any('/patrol/checkin', [app\controller\PatrolController::class, 'checkIn']);
    Route::any('/patrol/records', [app\controller\PatrolController::class, 'getRecords']);
    
    Route::any('/services/work-orders/list', [app\controller\PatrolController::class, 'getWorkOrders']);
    Route::any('/services/work-orders/assign', [app\controller\PatrolController::class, 'assignWorkOrder']);
    Route::any('/services/work-orders/complete', [app\controller\PatrolController::class, 'completeWorkOrder']);
    Route::any('/services/work-orders/verify', [app\controller\PatrolController::class, 'verifyWorkOrder']);

})->middleware([AuthMiddleware::class]);