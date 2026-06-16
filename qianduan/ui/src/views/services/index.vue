<template>
  <div class="services-container">
    <el-tabs v-model="activeTab" type="border-card" class="services-tabs">
      
      <el-tab-pane label="后勤中控调度室 (工单池)" name="orders">
        <div class="toolbar">
          <el-button type="warning" icon="Download" @click="exportData('work_orders')">导出工单明细</el-button>
          <el-button icon="Refresh" style="margin-left: 10px;" @click="fetchOrders">刷新工单池</el-button>
        </div>

        <el-table :data="ordersData" v-loading="ordersLoading" border stripe style="width: 100%">
          <el-table-column prop="id" label="工单ID" width="80" align="center" />
          <el-table-column prop="title" label="任务主题" min-width="200" show-overflow-tooltip>
            <template #default="{ row }"><span style="font-weight: bold; color: #303133;">{{ row.title }}</span></template>
          </el-table-column>
          <el-table-column label="现场情况描述与证物" min-width="250">
            <template #default="{ row }">
              <div class="desc-box">
                <span class="desc-text">{{ parseDesc(row.description).text }}</span>
                <el-image 
                  v-if="parseDesc(row.description).image"
                  style="width: 40px; height: 40px; border-radius: 4px; margin-left: 10px; cursor: pointer; flex-shrink: 0;"
                  :src="parseDesc(row.description).image"
                  :preview-src-list="[parseDesc(row.description).image]"
                  fit="cover"
                  preview-teleported
                />
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="reporter_name" label="提报来源" width="120" align="center" />
          <el-table-column label="当前处理人" width="120" align="center">
            <template #default="{ row }">
              <span v-if="row.handler_id" style="color: #409eff; font-weight: bold;">{{ getStaffName(row.handler_id) }}</span>
              <span v-else style="color: #909399;">待指派</span>
            </template>
          </el-table-column>
          <el-table-column label="流转状态" width="120" align="center">
            <template #default="{ row }">
              <el-tag :type="getOrderStatusMeta(row.status).type" effect="dark">
                {{ getOrderStatusMeta(row.status).label }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="创建时间" width="160" align="center">
            <template #default="{ row }">{{ new Date(row.created_at).toLocaleString() }}</template>
          </el-table-column>
          <el-table-column label="调度操作" width="160" align="center" fixed="right">
            <template #default="{ row }">
              <el-button v-if="row.status === 1 && activeStaff.length > 0" type="primary" link icon="Position" @click="openAssignDialog(row)">派单指派</el-button>
              <el-popconfirm v-if="row.status === 3" title="确认该现场已处理完毕？" @confirm="verifyOrder(row.id)">
                <template #reference><el-button type="success" link icon="CircleCheck">验收结案</el-button></template>
              </el-popconfirm>
              <span v-if="row.status === 2" style="font-size: 12px; color: #e6a23c;">等待外勤打卡</span>
              <span v-if="row.status === 4" style="font-size: 12px; color: #909399;">工单已归档</span>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="基层服务人员户籍录" name="staff">
        <div class="toolbar">
          <el-button type="primary" icon="Plus" @click="openAddDialog">开通 H5 作业终端账号</el-button>
          <el-button type="warning" icon="Download" @click="exportData('service_staff')">导出员工名录</el-button>
          <el-button icon="Refresh" @click="fetchStaff">刷新列表</el-button>
        </div>

        <el-table :data="staffData" v-loading="staffLoading" border stripe style="width: 100%">
          <el-table-column prop="id" label="ID" width="60" align="center" />
          <el-table-column prop="real_name" label="姓名" width="100" align="center" />
          <el-table-column prop="position" label="岗位职位" width="120" align="center">
            <template #default="{ row }"><el-tag effect="dark" type="warning">{{ row.position }}</el-tag></template>
          </el-table-column>
          <el-table-column prop="username" label="登录账号(手机)" width="130" align="center">
            <template #default="{ row }"><span style="font-family: monospace; color: #303133;">{{ row.username }}</span></template>
          </el-table-column>
          <el-table-column prop="responsibility" label="岗位职责" min-width="180" show-overflow-tooltip />
          
          <el-table-column label="近期活跃轨迹" width="180" align="center">
            <template #default="{ row }">
              <div v-if="row.last_login_time">
                <div style="font-size: 12px; color: #409eff; font-weight: bold;">{{ row.last_login_time }}</div>
                <div style="font-size: 11px; color: #909399; margin-top: 2px;">终端 IP: {{ row.last_login_ip }}</div>
              </div>
              <span v-else style="color: #c0c4cc; font-size: 12px; font-style: italic;">尚未激活登录</span>
            </template>
          </el-table-column>

          <el-table-column label="状态" width="80" align="center">
            <template #default="{ row }"><el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status === 1 ? '正常' : '封禁' }}</el-tag></template>
          </el-table-column>
          <el-table-column label="操作" width="160" align="center" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" link icon="Edit" @click="openEditDialog(row)">编辑</el-button>
              <el-popconfirm title="确认彻底删除该人员账号？" @confirm="deleteStaff(row.id)">
                <template #reference><el-button type="danger" link icon="Delete">删除</el-button></template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

    </el-tabs>

    <el-dialog v-model="assignDialogVisible" title="调度台 - 任务下发指派" width="400px">
      <el-form label-position="top">
        <el-form-item label="选择外勤处理人员">
          <el-select v-model="selectedHandlerId" filterable placeholder="检索保安/保洁/维修人员" style="width: 100%;">
            <el-option v-for="staff in activeStaff" :key="staff.id" :label="`${staff.real_name} (${staff.position})`" :value="staff.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="assignDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitAssign">立即下发终端</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="staffDialogVisible" :title="isEdit ? '编辑人员信息' : '开通 H5 基层作业账号'" width="550px" @close="staffFormRef?.resetFields()">
      <el-form ref="staffFormRef" :model="staffForm" :rules="staffRules" label-width="110px">
        <div style="display: flex; gap: 15px;">
          <el-form-item label="真实姓名" prop="real_name" style="flex: 1;"><el-input v-model="staffForm.real_name" /></el-form-item>
          <el-form-item label="岗位职位" prop="position" style="flex: 1;">
            <el-select v-model="staffForm.position" style="width: 100%;">
              <el-option value="保洁员" label="保洁员" /><el-option value="安保专员" label="安保专员" /><el-option value="工程维修" label="工程维修" />
            </el-select>
          </el-form-item>
        </div>
        <el-form-item label="手机号(账号)" prop="phone" v-if="!isEdit"><el-input v-model="staffForm.phone" /></el-form-item>
        <el-form-item :label="isEdit ? '重置密码' : '初始密码'">
          <el-input v-model="staffForm.password" type="password" show-password :placeholder="isEdit ? '不填则保持原密码不变' : '默认 123456'" />
        </el-form-item>
        <el-form-item label="账号状态" v-if="isEdit">
           <el-radio-group v-model="staffForm.status"><el-radio :label="1">正常</el-radio><el-radio :label="0">封禁</el-radio></el-radio-group>
        </el-form-item>
        <el-form-item label="岗位职责"><el-input v-model="staffForm.responsibility" type="textarea" :rows="3" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="staffDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitSaveStaff">确认保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import request from '../../utils/request'

const activeTab = ref('orders')
const submitLoading = ref(false)

const ordersData = ref([])
const ordersLoading = ref(false)
const assignDialogVisible = ref(false)
const currentOrderId = ref('')
const selectedHandlerId = ref('')

const staffData = ref([])
const staffLoading = ref(false)
const staffDialogVisible = ref(false)
const isEdit = ref(false)
const staffFormRef = ref(null)
const staffForm = reactive({ id: '', real_name: '', position: '保洁员', phone: '', password: '', responsibility: '', status: 1 })
const staffRules = {
  real_name: [{ required: true, message: '姓名必填', trigger: 'blur' }],
  phone: [{ required: true, message: '手机号必填', trigger: 'blur' }]
}

const activeStaff = computed(() => staffData.value.filter(s => s.status === 1))

const parseDesc = (desc) => {
  if (!desc) return { text: '', image: '' }
  const match = desc.match(/【现场照片证物】:\s*(http.*)/)
  return match ? { text: desc.replace(match[0], '').trim(), image: match[1] } : { text: desc, image: '' }
}

const getOrderStatusMeta = (status) => ({ 1: { label: '待指派', type: 'danger' }, 2: { label: '处理中', type: 'warning' }, 3: { label: '待验', type: 'primary' }, 4: { label: '已结案', type: 'info' }[status] || { label: '未知', type: 'info' } })

const getStaffName = (id) => {
  const staff = staffData.value.find(s => s.id === id)
  return staff ? staff.real_name : '未知'
}

const fetchOrders = async () => { ordersLoading.value = true; const res = await request.get('/api/services/work-orders/list'); if (res.code === 200) ordersData.value = res.data; ordersLoading.value = false }
const fetchStaff = async () => { staffLoading.value = true; const res = await request.get('/api/services/staff/list'); if (res.code === 200) staffData.value = res.data; staffLoading.value = false }

const openAssignDialog = (row) => { currentOrderId.value = row.id; selectedHandlerId.value = ''; assignDialogVisible.value = true }
const submitAssign = async () => {
  if (!selectedHandlerId.value) return ElMessage.warning('请选择处理人员')
  submitLoading.value = true
  try {
    const res = await request.post('/api/services/work-orders/assign', { id: currentOrderId.value, handler_id: selectedHandlerId.value })
    if (res.code === 200) { ElMessage.success('指令已下发'); assignDialogVisible.value = false; fetchOrders() }
  } finally { submitLoading.value = false }
}

const verifyOrder = async (id) => { const res = await request.post('/api/services/work-orders/verify', { id }); if (res.code === 200) { ElMessage.success('工单已结案'); fetchOrders() } }

const exportData = async (moduleName) => {
  ElMessage.info('正在拉取离线加密档案...')
  const token = localStorage.getItem('saas_token')
  try {
    const res = await fetch(`http://47.120.52.65:8787/api/export/download?module=${moduleName}`, { headers: { 'Authorization': `Bearer ${token}` } })
    if (res.status === 200) {
      const blob = await res.blob()
      const a = document.createElement('a')
      a.href = window.URL.createObjectURL(blob)
      a.download = `后勤模块_${moduleName}_${new Date().getTime()}.csv`
      a.click()
      ElMessage.success('底账离线审计归档成功')
    }
  } catch (e) { ElMessage.error('导出失败') }
}

const openAddDialog = () => { isEdit.value = false; staffForm.id = ''; staffForm.password = ''; staffForm.phone = ''; if(staffFormRef.value) staffFormRef.value.resetFields(); staffDialogVisible.value = true }
const openEditDialog = (row) => { isEdit.value = true; staffForm.id = row.id; staffForm.real_name = row.real_name; staffForm.position = row.position; staffForm.responsibility = row.responsibility; staffForm.status = row.status; staffForm.password = ''; staffDialogVisible.value = true }
const submitSaveStaff = () => {
  staffFormRef.value.validate(valid => {
    if(!valid) return
    const url = isEdit.value ? '/api/services/staff/update' : '/api/services/staff/add'
    request.post(url, staffForm).then(res => { if (res.code === 200) { ElMessage.success('保存成功'); staffDialogVisible.value = false; fetchStaff() } else { ElMessage.error(res.msg) } })
  })
}
const deleteStaff = (id) => { request.post('/api/services/staff/delete', { id }).then(res => { if(res.code===200) { ElMessage.success('封禁成功'); fetchStaff() } }) }

onMounted(() => { fetchStaff().then(() => fetchOrders()) })
</script>

<style scoped>
.services-container { width: 100%; }
.services-tabs { box-shadow: none; border-radius: 4px; }
.toolbar { margin-bottom: 20px; display: flex; align-items: center; }
.desc-box { display: flex; align-items: flex-start; justify-content: space-between; }
.desc-text { flex: 1; font-size: 13px; color: #606266; line-height: 1.5; white-space: pre-wrap; }
</style>