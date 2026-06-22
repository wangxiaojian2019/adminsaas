<template>
  <div class="services-container">
    <div class="toolbar">
      <el-button type="primary" icon="Plus" @click="openAddDialog">开通 H5 作业终端账号</el-button>
      <el-button type="warning" icon="Download" @click="exportData('service_staff')">导出员工名录</el-button>
      <el-button icon="Refresh" @click="fetchStaff">刷新列表</el-button>
    </div>

    <el-table :data="staffData" v-loading="staffLoading" border stripe style="width: 100%; border-radius: 4px;">
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
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import request from '../../utils/request'

const submitLoading = ref(false)
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

const fetchStaff = async () => { 
  staffLoading.value = true
  const res = await request.get('/api/services/staff/list')
  if (res.code === 200) staffData.value = res.data
  staffLoading.value = false 
}

const exportData = async (moduleName) => {
  ElMessage.info('正在拉取离线加密档案...')
  const token = localStorage.getItem('saas_token')
  try {
    const res = await fetch(`http://47.120.52.65:8787/api/export/download?module=${moduleName}`, { headers: { 'Authorization': `Bearer ${token}` } })
    if (res.status === 200) {
      const blob = await res.blob()
      const a = document.createElement('a')
      a.href = window.URL.createObjectURL(blob)
      a.download = `基层名录_${moduleName}_${new Date().getTime()}.csv`
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
    submitLoading.value = true
    const url = isEdit.value ? '/api/services/staff/update' : '/api/services/staff/add'
    request.post(url, staffForm).then(res => { 
      if (res.code === 200) { ElMessage.success('保存成功'); staffDialogVisible.value = false; fetchStaff() } 
      else { ElMessage.error(res.msg) } 
    }).finally(() => { submitLoading.value = false })
  })
}

const deleteStaff = (id) => { 
  request.post('/api/services/staff/delete', { id }).then(res => { 
    if(res.code===200) { ElMessage.success('账号已封禁删除'); fetchStaff() } 
  }) 
}

onMounted(() => { fetchStaff() })
</script>

<style scoped>
.services-container { padding: 20px; background: #fff; height: 100%; }
.toolbar { margin-bottom: 20px; display: flex; align-items: center; }
</style>