<?php
namespace app\controller;

use support\Request;
use support\Db;

class SystemController
{
    // ==========================================
    // 区块一：角色业务控制区
    // ==========================================

    public function roleList(Request $request)
    {
        $list = Db::table('roles')->get();
        foreach ($list as $key => $role) {
            $list[$key]->permissions = Db::table('role_permissions')
                ->where('role_id', $role->id)
                ->pluck('permission_id')
                ->toArray();
        }
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function roleAdd(Request $request)
    {
        $roleId = Db::table('roles')->insertGetId([
            'role_name' => $request->post('role_name'),
            'data_scope' => $request->post('data_scope', 1),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $permissions = $request->post('permissions', []);
        if (!empty($permissions)) {
            $inserts = [];
            foreach ($permissions as $pid) {
                $inserts[] = ['role_id' => $roleId, 'permission_id' => $pid];
            }
            Db::table('role_permissions')->insert($inserts);
        }
        return json(['code' => 200, 'msg' => '角色创立及授权成功']);
    }

    public function roleUpdate(Request $request)
    {
        $id = $request->post('id');
        Db::table('roles')->where('id', $id)->update([
            'role_name' => $request->post('role_name'),
            'data_scope' => $request->post('data_scope')
        ]);

        Db::table('role_permissions')->where('role_id', $id)->delete();
        $permissions = $request->post('permissions', []);
        if (!empty($permissions)) {
            $inserts = [];
            foreach ($permissions as $pid) {
                $inserts[] = ['role_id' => $id, 'permission_id' => $pid];
            }
            Db::table('role_permissions')->insert($inserts);
        }
        return json(['code' => 200, 'msg' => '角色参数更新生效']);
    }

    public function roleDelete(Request $request)
    {
        $id = $request->post('id');
        $count = Db::table('admins')->where('role_id', $id)->count();
        if ($count > 0) {
            return json(['code' => 403, 'msg' => '删除驳回：当前仍有子账号正在使用该业务角色！']);
        }
        Db::table('roles')->where('id', $id)->delete();
        Db::table('role_permissions')->where('role_id', $id)->delete();
        return json(['code' => 200, 'msg' => '节点删除成功']);
    }

    // ==========================================
    // 区块二：子账号生命周期控制区 (核心算法对齐)
    // ==========================================

    public function adminList(Request $request)
    {
        $list = Db::table('admins')
            ->leftJoin('roles', 'admins.role_id', '=', 'roles.id')
            ->select(
                'admins.id', 
                'admins.username', 
                'admins.real_name', 
                'admins.phone', 
                'admins.status', 
                'admins.role_id',
                'admins.created_at', 
                'roles.role_name'
            )
            ->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function adminAdd(Request $request)
    {
        Db::table('admins')->insert([
            'username' => $request->post('username'),
            // 核心修复：强制降维对齐登录底座的 MD5 算法
            'password' => md5($request->post('password', '123456')),
            'real_name' => $request->post('real_name'),
            'phone' => $request->post('phone'),
            'role_id' => $request->post('role_id'),
            'department_id' => $request->post('department_id', 0),
            'status' => $request->post('status', 1),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function adminUpdate(Request $request)
    {
        $id = $request->post('id');
        $user = $request->user;

        if ($id == 1 && $user->id != 1) {
            return json(['code' => 403, 'msg' => '越权拦截：不可修改系统超管档案']);
        }

        $data = [
            'username' => $request->post('username'),
            'real_name' => $request->post('real_name'),
            'phone' => $request->post('phone'),
            'role_id' => $request->post('role_id'),
            'status' => $request->post('status', 1)
        ];

        $password = $request->post('password');
        if (!empty($password)) {
            // 核心修复：强制降维对齐登录底座的 MD5 算法
            $data['password'] = md5($password);
        }

        Db::table('admins')->where('id', $id)->update($data);
        return json(['code' => 200, 'msg' => '子账号档案更新成功']);
    }

    public function adminDelete(Request $request)
    {
        $id = $request->post('id');
        
        if ($id == 1) {
            return json(['code' => 403, 'msg' => '系统物理保护：初始超级管理员不可被抹除']);
        }

        Db::table('admins')->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '子账号已安全注销并释放']);
    }

    // ==========================================
    // 区块三：动态权限引擎区
    // ==========================================

    public function getMyMenus(Request $request)
    {
        $user = $request->user;
        if (!$user) {
            return json(['code' => 401, 'msg' => '未授权访问']);
        }

        $menus = Db::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $user->role_id)
            ->where('permissions.type', 1) 
            ->select('permissions.*')
            ->orderBy('permissions.sort', 'asc')
            ->get();

        $tree = $this->buildTree($menus->toArray());

        return json(['code' => 200, 'msg' => 'success', 'data' => $tree]);
    }

    private function buildTree($elements, $parentId = 0) 
    {
        $branch = array();
        foreach ($elements as $element) {
            $elementArr = (array) $element;
            if ($elementArr['parent_id'] == $parentId) {
                $children = $this->buildTree($elements, $elementArr['id']);
                if ($children) {
                    $elementArr['children'] = $children;
                }
                $branch[] = $elementArr;
            }
        }
        return $branch;
    }
}