<?php
namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use support\Db;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler) : Response
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            return json(['code' => 401, 'msg' => 'Token Missing']);
        }

        $token = substr($authHeader, 7);
        $decoded = base64_decode($token);
        if (!$decoded) {
            return json(['code' => 401, 'msg' => 'Token Invalid']);
        }

        $parts = explode('|', $decoded);
        if (count($parts) < 3) {
            return json(['code' => 401, 'msg' => 'Token Formatter Error']);
        }

        // 识别 Token 类型
        if ($parts[2] === 'tenant') {
            // 移动租户端流量
            $request->enterprise_id = intval($parts[0]);
        } else {
            // PC管理端/基层外勤端流量
            $adminId = intval($parts[0]);

            // 核心重构：数据提权引擎
            // 每次请求实时抓取所属部门及角色表中的 data_scope 权限域
            $admin = Db::table('admins')
                ->leftJoin('roles', 'admins.role_id', '=', 'roles.id')
                ->where('admins.id', $adminId)
                ->select(
                    'admins.id', 
                    'admins.username', 
                    'admins.role_id', 
                    'admins.department_id', 
                    'roles.data_scope'
                )
                ->first();

            if (!$admin) {
                return json(['code' => 401, 'msg' => 'User Not Found Or Disabled']);
            }

            // 预计算阵列：如果数据权限为2(本部门)，直接查出同部门所有员工的 ID 池
            $admin->department_admin_ids = [];
            if ($admin->data_scope == 2 && $admin->department_id > 0) {
                $admin->department_admin_ids = Db::table('admins')
                    ->where('department_id', $admin->department_id)
                    ->pluck('id')
                    ->toArray();
            }

            // 挂载到全局请求生命周期
            $request->user = $admin;
        }

        return $handler($request);
    }
}