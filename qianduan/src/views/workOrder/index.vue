<template>
  <div class="work-order-container">
    <div class="header-stats">
      <el-alert title="中控调度室警告：优先处理标红的 P0 紧急与 SLA 违规工单，超时将触发主管复盘机制" type="warning" show-icon :closable="false" />
    </div>

    <div class="toolbar">
      <el-button type="primary" icon="Position" @click="fetchOrders">刷新工单池</el-button>
      <el-button type="warning" icon="Download" @click="exportData('work_orders')" plain>导出工单台账</el-button>
    </div>

    <el-table :data="ordersData" v-loading="ordersLoading" border stripe style="width: 100%; border-radius: 4px;">
      <el-table-column prop="id" label="工单号" width="80" align="center" />
      
      <el-table-column label="工单等级" width="100" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.priority === 1" type="danger" effect="dark">P0 紧急</el-tag>
          <el-tag v-else type="info">普通工单</el-tag>
        </template>
      </el-table-column>

      <el-table-column prop="title" label="任务主题" min-width="180" show-overflow-tooltip>
        <template #default="{ row }"><span style="font-weight: bold; color: #303133;">{{ row.title }}</span></template>
      </el-table-column>

      <el-table-column label="现场情况描述与证物" min-width="250">
        <template #default="{ row }">
          <div class="desc-box">
            <span class="desc-text">{{ parseDesc(row.description).text }}</span>
            <el-image 
              v-if="parseDesc(row.description).image"
              class="cert-img"
              :src="getFullImgUrl(parseDesc(row.description).image)"
              :preview-src-list="[getFullImgUrl(parseDesc(row.description).image)]"
              fit="cover" preview-teleported
            />
          </div>
        </template>
      </el-table-column>

      <el-table-column label="SLA 效能状态" min-width="180">
        <template #default="{ row }">
          <div v-if="row.is_timeout" class="sla-alert">⚠️ SLA超时违规</div>
          <div class="time-metric">接单响应: <span :class="{'text-danger': row.response_time_seconds > 900}">{{ formatSeconds(row.response_time_seconds) }}</span></div>
          <div class="time-metric" v-if="row.resolve_time_seconds">处理耗时: <span>{{ formatSeconds(row.resolve_time_seconds) }}</span></div>
        </template>
      </el-table-column>

      <el-table-column label="执行人(外勤)" width="140" align="center">
        <template #default="{ row }">
          <div v-if="row.handler_id > 0">
            <span style="color: #409eff; font-weight: bold;">{{ row.handler_name || '已锁定' }}</span>
            <div style="font-size: 11px; color: #909399; margin-top: 2px;">(员工ID: {{ row.handler_id }})</div>
          </div>
          <span v-else style="color: #909399;">待中控派发</span>
        </template>
      </el-table-column>

      <el-table-column label="当前状态" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="getOrderStatusMeta(row.status).type" effect="plain">{{ getOrderStatusMeta(row.status).label }}</el-tag>
        </template>
      </el-table-column>

      <el-table-column label="调度动作" width="120" align="center" fixed="right">
        <template #default="{ row }">
          <el-button v-if="row.status === 1" type="primary" size="small" icon="Connection" @click="openAssignDialog(row)">人工指派</el-button>
          <el-popconfirm v-if="row.status === 2" title="强制介入并手动结案？" @confirm="resolveOrder(row.id)">
            <template #reference><el-button type="success" size="small" icon="Check">中控结案</el-button></template>
          </el-popconfirm>
          <span v-if="row.status >= 3" style="font-size: 12px; color: #909399;">已归档闭环</span>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="assignDialogVisible" title="后勤调度 - 强制指派工单" width="400px">
      <el-form label-position="top">
        <el-form-item label="选择外勤执行人员">
          <el-select v-model="selectedHandlerId" filterable placeholder="检索当前在岗外勤" style="width: 100%;">
            <el-option v-for="staff in staffData" :key="staff.id" :label="`${staff.real_name} (${staff.position})`" :value="staff.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="assignDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitAssign">立即下发终端</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import request from '../../utils/request'

const ordersData = ref([])
const ordersLoading = ref(false)
const staffData = ref([])

const assignDialogVisible = ref(false)
const submitLoading = ref(false)
const currentOrderId = ref('')
const selectedHandlerId = ref('')

const fetchOrders = async () => { 
  ordersLoading.value = true
  const res = await request.get('/api/work_order/list')
  if (res.code === 200) ordersData.value = res.data
  ordersLoading.value = false 
}

const fetchStaff = async () => { 
  const res = await request.get('/api/services/staff/list')
  if (res.code === 200) {
    staffData.value = res.data.filter(s => s.status === 1) 
  }
}

const openAssignDialog = (row) => { 
  currentOrderId.value = row.id
  selectedHandlerId.value = ''
  assignDialogVisible.value = true 
}

const submitAssign = async () => {
  if (!selectedHandlerId.value) return ElMessage.warning('请选择执行人员')
  submitLoading.value = true
  try {
    const res = await request.post('/api/work_order/action', { 
      id: currentOrderId.value, 
      action: 'accept',
      handler_id: selectedHandlerId.value 
    })
    if (res.code === 200) { 
      ElMessage.success('指派指令已下发外勤终端')
      assignDialogVisible.value = false
      fetchOrders() 
    } else {
      ElMessage.error(res.msg || '流转失败')
    }
  } finally { submitLoading.value = false }
}

const resolveOrder = async (id) => { 
  const res = await request.post('/api/work_order/action', { id, action: 'resolve', result_remark: '中控室强制验证闭环' })
  if (res.code === 200) { 
    ElMessage.success('工单状态机已推进至结案')
    fetchOrders() 
  } else {
    ElMessage.error(res.msg || '流转受阻')
  }
}

const formatSeconds = (sec) => {
  if (!sec || sec <= 0) return '尚未产生记录'
  if (sec < 60) return `${sec} 秒`
  const min = Math.floor(sec / 60)
  const remainder = sec % 60
  return `${min} 分 ${remainder} 秒`
}

const parseDesc = (desc) => {
  if (!desc) return { text: '', image: '' }
  const match = desc.match(/【现场照片证物】:\s*(.*)/)
  return match ? { text: desc.replace(match[0], '').trim(), image: match[1].trim() } : { text: desc, image: '' }
}

const getFullImgUrl = (url) => {
  if (!url) return ''
  return url.startsWith('http') ? url : `http://47.120.52.65:8787${url}`
}

const getOrderStatusMeta = (status) => {
  const statusMap = {
    1: { label: '待指派', type: 'danger' },
    2: { label: '作业中', type: 'warning' },
    3: { label: '待验收', type: 'primary' },
    4: { label: '已结案', type: 'success' }
  }
  return statusMap[status] || { label: '未知', type: 'info' }
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
      a.download = `中控大盘_${moduleName}_${new Date().getTime()}.csv`
      a.click()
      ElMessage.success('导出成功')
    }
  } catch (e) { ElMessage.error('导出网络失败') }
}

onMounted(() => { 
  fetchStaff().then(() => fetchOrders()) 
})
</script>

<style scoped>
.work-order-container { padding: 20px; background: #fff; height: 100%; }
.header-stats { margin-bottom: 20px; }
.toolbar { margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.desc-box { display: flex; align-items: flex-start; justify-content: space-between; }
.desc-text { flex: 1; font-size: 13px; color: #606266; line-height: 1.5; white-space: pre-wrap; }
.cert-img { width: 40px; height: 40px; border-radius: 4px; margin-left: 10px; cursor: pointer; flex-shrink: 0; }
.time-metric { font-size: 12px; color: #606266; line-height: 1.8; }
.text-danger { color: #f56c6c; font-weight: bold; }
.sla-alert { color: #fff; background-color: #f56c6c; padding: 2px 6px; border-radius: 4px; font-size: 12px; margin-bottom: 4px; display: inline-block; font-weight: bold; }
</style>