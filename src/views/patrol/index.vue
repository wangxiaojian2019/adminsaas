<template>
  <div class="patrol-container">
    <el-tabs v-model="activeTab" type="border-card" class="patrol-tabs">
      
      <el-tab-pane label="安防巡检打卡流水" name="records">
        <div class="toolbar">
          <el-button type="warning" icon="Download" @click="exportData('patrol_records')">导出打卡流水</el-button>
          <el-button icon="Refresh" @click="fetchRecords">刷新打卡流</el-button>
        </div>
        <el-table :data="recordsData" v-loading="recordsLoading" border stripe style="width: 100%">
          <el-table-column prop="id" label="流水号" width="80" align="center" />
          <el-table-column prop="worker_name" label="巡更人员" width="150" align="center">
            <template #default="{ row }"><span style="font-weight: bold; color: #409eff;">{{ row.worker_name }}</span></template>
          </el-table-column>
          <el-table-column prop="location" label="巡检物理点位" min-width="180" />
          <el-table-column prop="remarks" label="异常备注与隐患说明" min-width="200" show-overflow-tooltip />
          <el-table-column label="工况状态" width="120" align="center">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status === 1 ? '安全正常' : '隐患异常' }}</el-tag>
            </template>
          </el-table-column>
          
          <el-table-column label="物理打卡时间" width="180" align="center">
            <template #default="{ row }">
              <span style="font-size: 12px; color: #909399; font-family: monospace;">{{ row.created_at }}</span>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="物理巡检点位配置" name="points">
        <div class="toolbar">
          <el-button type="primary" icon="Plus" @click="dialogVisible = true">设立巡检网格点</el-button>
          <el-button type="warning" icon="Download" @click="exportData('patrol_points')">导出网格配置</el-button>
          <el-button icon="Refresh" @click="fetchPoints">刷新点位</el-button>
        </div>
        <el-table :data="pointsData" v-loading="pointsLoading" border stripe style="width: 100%">
          <el-table-column prop="id" label="点位ID" width="100" align="center" />
          <el-table-column prop="location" label="防区点位名称" min-width="200" />
          
          <el-table-column label="系统设立时间" width="200" align="center">
            <template #default="{ row }">
              <span style="font-size: 12px; color: #909399; font-family: monospace;">{{ row.created_at }}</span>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <el-dialog v-model="dialogVisible" title="设立防区巡检点" width="400px" @close="pointFormRef?.resetFields()">
      <el-form ref="pointFormRef" :model="pointForm" label-width="80px">
        <el-form-item label="点位名称" prop="location" :rules="[{ required: true, message: '必填' }]">
          <el-input v-model="pointForm.location" placeholder="例如：地下车库B2层西北角" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitPoint">确认设立</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import request from '../../utils/request'

const activeTab = ref('records')
const recordsData = ref([])
const recordsLoading = ref(false)
const pointsData = ref([])
const pointsLoading = ref(false)

const dialogVisible = ref(false)
const submitLoading = ref(false)
const pointFormRef = ref(null)
const pointForm = reactive({ location: '' })

const fetchRecords = async () => {
  recordsLoading.value = true
  const res = await request.get('/api/patrol/records')
  if (res.code === 200) recordsData.value = res.data
  recordsLoading.value = false
}

const fetchPoints = async () => {
  pointsLoading.value = true
  const res = await request.get('/api/patrol/points/list')
  if (res.code === 200) pointsData.value = res.data
  pointsLoading.value = false
}

const submitPoint = () => {
  pointFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    const res = await request.post('/api/patrol/points/add', pointForm)
    if (res.code === 200) {
      ElMessage.success('防区点位设立成功')
      dialogVisible.value = false
      fetchPoints()
    }
    submitLoading.value = false
  })
}

const exportData = async (moduleName) => {
  ElMessage.info('安防数字档案加密存证脱密中...')
  const token = localStorage.getItem('saas_token')
  try {
    const res = await fetch(`http://47.120.52.65:8787/api/export/download?module=${moduleName}`, {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    if (res.status === 200) {
      const blob = await res.blob()
      const a = document.createElement('a')
      a.href = window.URL.createObjectURL(blob)
      a.download = `安防模块_${moduleName}_${new Date().getTime()}.csv`
      a.click()
      ElMessage.success('安防底账离线审计归档成功')
    }
  } catch (e) { ElMessage.error('安全网关拦截导出') }
}

onMounted(() => {
  fetchRecords()
  fetchPoints()
})
</script>

<style scoped>
.patrol-container { width: 100%; }
.patrol-tabs { box-shadow: none; border-radius: 4px; }
.toolbar { margin-bottom: 20px; display: flex; gap: 10px; }
</style>