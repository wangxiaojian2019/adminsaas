<?php
namespace app\controller;

use support\Request;
use support\Db;

class SystemController
{
    public function getMyMenus(Request $request)
    {
        // 核心修复：使用 Token 鉴权中的 $request->user，而不是 Session
        $admin = $request->user;
        if (!$admin) return json(['code' => 401, 'msg' => '登录已失效']);

        $roleId = $admin->role_id ?? 1;

        if ($roleId == 1) {
            $menus = Db::table('permissions')->where('type', 1)->orderBy('sort', 'asc')->get();
        } else {
            $role = Db::table('roles')->where('id', $roleId)->first();
            $menuIds = [];
            
            if ($role && isset($role->menu_ids) && !empty($role->menu_ids)) {
                $menuIds = explode(',', $role->menu_ids);
            }

            if (empty($menuIds)) {
                $menus = []; 
            } else {
                $menus = Db::table('permissions')
                    ->whereIn('id', $menuIds)
                    ->where('type', 1)
                    ->orderBy('sort', 'asc')
                    ->get();
            }
        }

        return json(['code' => 200, 'msg' => 'success', 'data' => $menus]);
    }

    public function roleList(Request $request)
    {
        $list = Db::table('roles')->get();
        foreach ($list as $key => $role) {
            $pIds = !empty($role->menu_ids) ? explode(',', $role->menu_ids) : [];
            $list[$key]->permissions = array_map('intval', $pIds);
        }
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function roleAdd(Request $request)
    {
        $perms = $request->post('permissions', []);
        $menu_ids = is_array($perms) ? implode(',', $perms) : '';

        $roleId = Db::table('roles')->insertGetId([
            'role_name' => $request->post('role_name') ?: $request->post('name'),
            'data_scope' => $request->post('data_scope', 1),
            'menu_ids' => $menu_ids,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        if (!empty($perms)) {
            $inserts = [];
            foreach ($perms as $pid) {
                $inserts[] = ['role_id' => $roleId, 'permission_id' => $pid];
            }
            Db::table('role_permissions')->insert($inserts);
        }
        return json(['code' => 200, 'msg' => '角色新增成功']);
    }

    public function roleUpdate(Request $request)
    {
        $id = $request->post('id');
        $perms = $request->post('permissions', []);
        $menu_ids = is_array($perms) ? implode(',', $perms) : '';

        Db::table('roles')->where('id', $id)->update([
            'role_name' => $request->post('role_name') ?: $request->post('name'),
            'data_scope' => $request->post('data_scope'),
            'menu_ids' => $menu_ids
        ]);

        Db::table('role_permissions')->where('role_id', $id)->delete();
        if (!empty($perms)) {
            $inserts = [];
            foreach ($perms as $pid) {
                $inserts[] = ['role_id' => $id, 'permission_id' => $pid];
            }
            Db::table('role_permissions')->insert($inserts);
        }
        return json(['code' => 200, 'msg' => '角色权限分配已生效']);
    }

    public function roleDelete(Request $request)
    {
        $id = $request->post('id');
        $count = Db::table('admins')->where('role_id', $id)->count();
        if ($count > 0) return json(['code' => 403, 'msg' => '有账号绑定该角色，无法删除']);
        Db::table('roles')->where('id', $id)->delete();
        Db::table('role_permissions')->where('role_id', $id)->delete();
        return json(['code' => 200, 'msg' => '角色已删除']);
    }

    public function adminList(Request $request)
    {
        $list = Db::table('admins')
            ->leftJoin('roles', 'admins.role_id', '=', 'roles.id')
            ->leftJoin('admins as parent', 'admins.parent_id', '=', 'parent.id')
            ->select(
                'admins.*', 
                'roles.role_name', 
                'parent.real_name as parent_name'
            )
            ->orderBy('admins.id', 'asc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function adminAdd(Request $request)
    {
        $exists = Db::table('admins')->where('username', $request->post('username'))->first();
        if ($exists) return json(['code' => 400, 'msg' => '账号已存在']);

        Db::table('admins')->insert([
            'username' => $request->post('username'),
            'password' => md5($request->post('password')),
            'real_name' => $request->post('real_name'),
            'phone' => $request->post('phone'),
            'role_id' => $request->post('role_id'),
            'parent_id' => $request->post('parent_id') ?: 0,
            'status' => $request->post('status', 1),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => '账号分配成功']);
    }

    public function adminUpdate(Request $request)
    {
        $id = $request->post('id');
        $admin = $request->user;

        if ($id == 1 && $admin->id != 1) {
            return json(['code' => 403, 'msg' => '不可越权修改系统超管档案']);
        }

        $data = [
            'username' => $request->post('username'),
            'real_name' => $request->post('real_name'),
            'phone' => $request->post('phone'),
            'role_id' => $request->post('role_id'),
            'parent_id' => $request->post('parent_id') ?: 0,
            'status' => $request->post('status', 1)
        ];

        $password = $request->post('password');
        if (!empty($password)) {
            $data['password'] = md5($password);
        }

        Db::table('admins')->where('id', $id)->update($data);
        return json(['code' => 200, 'msg' => '信息已更新']);
    }

    public function adminDelete(Request $request)
    {
        if ($request->post('id') == 1) return json(['code' => 403, 'msg' => '创始账号不可删除']);
        Db::table('admins')->where('id', $request->post('id'))->delete();
        return json(['code' => 200, 'msg' => '账号已收回']);
    }
}