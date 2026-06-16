<template>
  <div class="system-container">
    <el-tabs v-model="activeTab" type="border-card" class="system-tabs">
      
      <el-tab-pane label="角色与数据范围定义" name="roles">
        <div class="toolbar">
          <el-button type="primary" icon="Plus" @click="openAddRole">设立业务角色</el-button>
          <el-button icon="Refresh" @click="fetchRoles">刷新列表</el-button>
        </div>
        <el-table :data="roleData" v-loading="roleLoading" border stripe style="width: 100%">
          <el-table-column prop="id" label="角色ID" width="80" align="center" />
          <el-table-column prop="role_name" label="业务角色名称" min-width="150" />
          <el-table-column label="数据权限边界" min-width="200">
            <template #default="{ row }">
              <el-tag :type="getScopeColor(row.data_scope)" effect="dark">{{ getScopeLabel(row.data_scope) }}</el-tag>
            </template>
          </el-table-column>
          
          <el-table-column label="操作" width="160" align="center" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" link icon="Edit" @click="openEditRole(row)">参数配置</el-button>
              <el-popconfirm title="确认彻底粉碎并删除此角色？" @confirm="submitDeleteRole(row.id)">
                <template #reference>
                  <el-button type="danger" link icon="Delete">强行擦除</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="组织架构与子账号管理" name="admins">
        <div class="toolbar">
          <el-button type="primary" icon="User" @click="openAddAdmin">开通内部子账号</el-button>
          <el-button icon="Refresh" @click="fetchAdmins">刷新列表</el-button>
        </div>
        <el-table :data="adminData" v-loading="adminLoading" border stripe style="width: 100%">
          <el-table-column prop="id" label="账号ID" width="80" align="center" />
          <el-table-column prop="username" label="登录账号" min-width="120" />
          <el-table-column prop="real_name" label="真实姓名" width="100" align="center" />
          
          <el-table-column label="业务角色绑定" width="130" align="center">
            <template #default="{ row }">
              <el-tag effect="plain" type="primary">{{ row.role_name || '未分配' }}</el-tag>
            </template>
          </el-table-column>

          <!-- 核心新增：主管信息反显 -->
          <el-table-column label="直属层级" width="120" align="center">
            <template #default="{ row }">
              <span v-if="row.parent_name" style="color: #67c23a; font-weight: bold;"><el-icon><Avatar /></el-icon> {{ row.parent_name }}</span>
              <span v-else style="color: #909399;">顶层/无主管</span>
            </template>
          </el-table-column>

          <el-table-column prop="phone" label="联系电话" width="130" align="center" />
          <el-table-column label="账号状态" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status === 1 ? '正常启用' : '已封禁' }}</el-tag>
            </template>
          </el-table-column>

          <el-table-column label="操作" width="160" align="center" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" link icon="Edit" @click="openEditAdmin(row)">编辑</el-button>
              <el-popconfirm title="危险：确认注销该人员账号？" @confirm="submitDeleteAdmin(row.id)">
                <template #reference>
                  <el-button type="danger" link icon="Delete" :disabled="row.id === 1">注销</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="数据防泄露与审计追踪" name="audits">
        <div class="toolbar" style="display: flex; justify-content: space-between; align-items: center;">
          <el-alert title="所有列表数据的导出操作均会打上隐形安全水印，并在此处永久留痕溯源。" type="warning" show-icon :closable="false" style="flex: 1; margin-right: 15px;" />
          <el-button icon="Refresh" @click="fetchAudits">刷新审计日志</el-button>
        </div>
        <el-table :data="auditData" v-loading="auditLoading" border stripe style="width: 100%">
          <el-table-column prop="id" label="审计流水号" width="120" align="center" />
          <el-table-column label="操作人员" width="180">
            <template #default="{ row }">
              <span style="font-weight: bold; color: #409eff;">{{ row.admin_name }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="module_name" label="导出业务模块" min-width="150" />
          <el-table-column prop="data_count" label="泄露风险量(条)" width="150" align="center">
            <template #default="{ row }">
              <el-tag :type="row.data_count > 100 ? 'danger' : 'info'">{{ row.data_count }} 条</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="ip_address" label="操作终端 IP" width="150" align="center" />
          <el-table-column label="操作时间" width="180" align="center">
            <template #default="{ row }">{{ new Date(row.created_at).toLocaleString() }}</template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

    </el-tabs>

    <el-dialog v-model="roleDialogVisible" :title="isRoleEditMode ? '修改角色参数与功能覆盖域' : '设立系统业务角色'" width="550px" @close="roleFormRef?.resetFields()">
      <el-form ref="roleFormRef" :model="roleForm" :rules="roleRules" label-width="120px">
        <el-form-item label="角色名称" prop="role_name"><el-input v-model="roleForm.role_name" placeholder="例：高级招商专员" /></el-form-item>
        
        <el-form-item label="授权功能模块">
          <div class="permission-tree-box">
            <el-tree
              ref="permissionTreeRef"
              :data="systemModules"
              show-checkbox
              node-key="id"
              :props="{ label: 'name' }"
            />
          </div>
        </el-form-item>

        <el-form-item label="数据权限边界">
          <el-select v-model="roleForm.data_scope" style="width: 100%;">
            <el-option :value="1" label="单点隔离(仅本人)" /><el-option :value="2" label="树状穿透(本部门)" /><el-option :value="3" label="全局透视(全园区)" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="roleDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitRole">{{ isRoleEditMode ? '强制覆写' : '确定设立' }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="adminDialogVisible" :title="isAdminEditMode ? '更新子账号档案' : '开通内部子账号'" width="530px" @close="adminFormRef?.resetFields()">
      <el-form ref="adminFormRef" :model="adminForm" :rules="adminRules" label-width="120px">
        <el-form-item label="登录账号" prop="username"><el-input v-model="adminForm.username" /></el-form-item>
        
        <el-form-item label="登入密码" prop="password">
          <el-input v-model="adminForm.password" type="password" show-password :placeholder="isAdminEditMode ? '若不修改密码请留空' : '必须设置初始密码'" />
        </el-form-item>
        
        <el-form-item label="真实姓名" prop="real_name"><el-input v-model="adminForm.real_name" /></el-form-item>
        
        <el-form-item label="分配业务角色" prop="role_id">
          <el-select v-model="adminForm.role_id" placeholder="必须为子账号绑定角色" style="width: 100%">
            <el-option v-for="role in roleData" :key="role.id" :label="role.role_name" :value="role.id" />
          </el-select>
        </el-form-item>

        <!-- 核心新增：直属主管绑定引擎 -->
        <el-form-item label="绑定直属主管" prop="parent_id">
          <el-select v-model="adminForm.parent_id" placeholder="业务类人员必填" clearable style="width: 100%">
            <el-option v-for="manager in managerOptions" :key="manager.id" :label="manager.real_name + ' (' + manager.role_name + ')'" :value="manager.id" />
          </el-select>
        </el-form-item>

        <el-form-item label="联系电话" prop="phone"><el-input v-model="adminForm.phone" /></el-form-item>

        <el-form-item label="系统工况" prop="status" v-if="isAdminEditMode">
          <el-switch v-model="adminForm.status" :active-value="1" :inactive-value="0" active-text="正常启用" inactive-text="阻断封禁" />
        </el-form-item>

      </el-form>
      <template #footer>
        <el-button @click="adminDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitAdmin">{{ isAdminEditMode ? '保存修改' : '确认开通' }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick, computed } from 'vue'
import { ElMessage } from 'element-plus'
import request from '../../utils/request'

const activeTab = ref('roles')
const submitLoading = ref(false)

const roleData = ref([]); const roleLoading = ref(false)
const adminData = ref([]); const adminLoading = ref(false)
const auditData = ref([]); const auditLoading = ref(false)

const roleDialogVisible = ref(false); const roleFormRef = ref(null)
const isRoleEditMode = ref(false)
const roleForm = reactive({ id: null, role_name: '', data_scope: 1 })
const roleRules = { role_name: [{ required: true, message: '必须定义角色名称', trigger: 'blur' }] }

const systemModules = [
  { id: 1, name: '运营数据指挥舱' },
  { id: 2, name: '大厦与资产大盘' },
  { id: 3, name: '房源资产精细库' },
  { id: 4, name: '车位月卡与收费' },
  { id: 5, name: '招商与线索中心' },
  { id: 6, name: '企业户籍档案' },
  { id: 7, name: '租务与合同中心' },
  { id: 8, name: '业财一体化中心' },
  { id: 9, name: '智能安防巡检' },
  { id: 10, name: '基层服务人员管理' },
  { id: 11, name: '报表与 BI 中心' },
  { id: 12, name: '系统与权限控制' }
]
const permissionTreeRef = ref(null)

const adminDialogVisible = ref(false); const adminFormRef = ref(null)
const isAdminEditMode = ref(false)
const adminForm = reactive({ id: null, username: '', password: '', real_name: '', phone: '', role_id: '', parent_id: null, status: 1 })

// 智能剔除：在选择主管时，不能将自己设为自己的主管
const managerOptions = computed(() => {
  return adminData.value.filter(item => item.id !== adminForm.id)
})

const validatePassword = (rule, value, callback) => {
  if (!isAdminEditMode.value && !value) {
    callback(new Error('必须设置初始密码'))
  } else {
    callback()
  }
}

// 核心规则：动态拦截器
const validateParentId = (rule, value, callback) => {
  const selectedRole = roleData.value.find(r => r.id === adminForm.role_id)
  if (selectedRole) {
    const rName = selectedRole.role_name || ''
    if (rName.includes('业务') || rName.includes('专员') || rName.includes('一线') || rName.includes('销售')) {
      if (!value) {
        callback(new Error('系统级硬性约束：此类人员必须挂靠直属主管'))
        return
      }
    }
  }
  callback()
}

const adminRules = { 
  username: [{ required: true, message: '请输入登录账号', trigger: 'blur' }], 
  password: [{ validator: validatePassword, trigger: 'blur' }], 
  real_name: [{ required: true, message: '请输入真实姓名', trigger: 'blur' }],
  role_id: [{ required: true, message: '必须选择业务角色', trigger: 'change' }],
  parent_id: [{ validator: validateParentId, trigger: 'change' }] 
}

const fetchRoles = async () => {
  roleLoading.value = true
  const res = await request.get('/api/system/roles/list')
  if (res.code === 200) roleData.value = res.data
  roleLoading.value = false
}

const fetchAdmins = async () => {
  adminLoading.value = true
  const res = await request.get('/api/system/admins/list')
  if (res.code === 200) adminData.value = res.data
  adminLoading.value = false
}

const fetchAudits = async () => {
  auditLoading.value = true
  const res = await request.get('/api/system/audit/logs')
  if (res.code === 200) auditData.value = res.data
  auditLoading.value = false
}

const openAddRole = () => {
  isRoleEditMode.value = false
  roleForm.id = null
  roleForm.role_name = ''
  roleForm.data_scope = 1
  roleDialogVisible.value = true
  nextTick(() => { if (permissionTreeRef.value) permissionTreeRef.value.setCheckedKeys([]) })
}

const openEditRole = (row) => {
  isRoleEditMode.value = true
  roleForm.id = row.id
  roleForm.role_name = row.role_name
  roleForm.data_scope = row.data_scope
  roleDialogVisible.value = true
  nextTick(() => { if (permissionTreeRef.value) permissionTreeRef.value.setCheckedKeys(row.permissions || []) })
}

const submitRole = () => {
  roleFormRef.value.validate(async (valid) => {
    if (!valid) return
    const checkedPermissions = permissionTreeRef.value.getCheckedKeys()
    if (checkedPermissions.length === 0) {
      ElMessage.warning('请至少为该角色分配一个功能模块')
      return
    }
    submitLoading.value = true
    const payload = { ...roleForm, permissions: checkedPermissions }
    const endpoint = isRoleEditMode.value ? '/api/system/roles/update' : '/api/system/roles/add'
    const res = await request.post(endpoint, payload)
    if (res.code === 200) { 
      ElMessage.success(res.msg || '操作成功')
      roleDialogVisible.value = false
      fetchRoles() 
    }
    submitLoading.value = false
  })
}

const submitDeleteRole = async (id) => {
  const res = await request.post('/api/system/roles/delete', { id })
  if (res.code === 200) {
    ElMessage.success('节点已成功摧毁')
    fetchRoles()
  } else {
    ElMessage.error(res.msg || '删除拦截')
  }
}

const openAddAdmin = () => {
  isAdminEditMode.value = false
  adminForm.id = null
  adminForm.username = ''
  adminForm.password = ''
  adminForm.real_name = ''
  adminForm.phone = ''
  adminForm.role_id = ''
  adminForm.parent_id = null
  adminForm.status = 1
  adminDialogVisible.value = true
}

const openEditAdmin = (row) => {
  isAdminEditMode.value = true
  adminForm.id = row.id
  adminForm.username = row.username
  adminForm.password = '' 
  adminForm.real_name = row.real_name
  adminForm.phone = row.phone
  adminForm.role_id = row.role_id
  adminForm.parent_id = row.parent_id === 0 ? null : row.parent_id
  adminForm.status = row.status
  adminDialogVisible.value = true
}

const submitAdmin = () => {
  adminFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    const endpoint = isAdminEditMode.value ? '/api/system/admins/update' : '/api/system/admins/add'
    
    // 清洗参数，保证后端收到 0 而非 null
    const payload = { ...adminForm }
    if (!payload.parent_id) payload.parent_id = 0

    const res = await request.post(endpoint, payload)
    if (res.code === 200) { 
      ElMessage.success(res.msg || '操作成功')
      adminDialogVisible.value = false
      fetchAdmins() 
    } else {
      ElMessage.error(res.msg)
    }
    submitLoading.value = false
  })
}

const submitDeleteAdmin = async (id) => {
  const res = await request.post('/api/system/admins/delete', { id })
  if (res.code === 200) {
    ElMessage.success(res.msg)
    fetchAdmins()
  } else {
    ElMessage.error(res.msg)
  }
}

const getScopeLabel = (s) => ({ 1: '单点隔离(仅本人)', 2: '树状穿透(本部门)', 3: '全局透视(全园区)' }[s])
const getScopeColor = (s) => ({ 1: 'info', 2: 'warning', 3: 'danger' }[s])

onMounted(() => { 
  fetchRoles()
  fetchAdmins()
  fetchAudits() 
})
</script>

<style scoped>
.system-container { width: 100%; }
.system-tabs { box-shadow: none; border-radius: 4px; }
.toolbar { margin-bottom: 20px; display: flex; gap: 10px; align-items: center; }
.permission-tree-box { border: 1px solid #dcdfe6; border-radius: 4px; padding: 10px; width: 100%; max-height: 200px; overflow-y: auto; background-color: #fafafa; }
</style>