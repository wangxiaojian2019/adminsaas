<template>
  <div class="work-order-container">
    <div class="header-stats">
      <el-alert title="中控调度室提示：请及时指派 [待指派] 的工单，并严谨核对 [待验收] 的完工照片。" type="warning" show-icon :closable="false" />
    </div>

    <div class="toolbar">
      <el-button type="primary" icon="Position" @click="fetchOrders">刷新工单池</el-button>
      <el-button type="warning" icon="Download" @click="exportData('work_orders')" plain>导出工单台账</el-button>
    </div>

    <el-table :data="ordersData" v-loading="ordersLoading" border stripe style="width: 100%; border-radius: 4px;">
      <el-table-column prop="id" label="单号" width="70" align="center" />
      <el-table-column label="工单等级" width="90" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.priority === 1" type="danger" effect="dark">P0 紧急</el-tag>
          <el-tag v-else type="info">普通</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="title" label="任务主题与描述" min-width="180">
        <template #default="{ row }">
          <div style="font-weight: bold; color: #303133; margin-bottom: 5px;">{{ row.title }}</div>
          <div style="font-size: 12px; color: #909399; white-space: pre-wrap;">{{ row.description }}</div>
        </template>
      </el-table-column>
      <el-table-column label="现场图(上报/完工)" width="150" align="center">
        <template #default="{ row }">
          <div style="display: flex; justify-content: center; gap: 10px;">
            <div v-if="row.report_image_url" class="img-box">
              <el-image :src="getFullImgUrl(row.report_image_url)" :preview-src-list="[getFullImgUrl(row.report_image_url)]" fit="cover" preview-teleported />
              <span class="img-label text-danger">隐患</span>
            </div>
            <div v-if="row.resolve_image_url" class="img-box">
              <el-image :src="getFullImgUrl(row.resolve_image_url)" :preview-src-list="[getFullImgUrl(row.resolve_image_url)]" fit="cover" preview-teleported />
              <span class="img-label text-success">验收</span>
            </div>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="执行人" width="100" align="center">
        <template #default="{ row }">
          <span v-if="row.handler_id > 0" style="color: #409eff; font-weight: bold;">{{ row.handler_name }}</span>
          <span v-else style="color: #909399;">待指派</span>
        </template>
      </el-table-column>
      <el-table-column label="当前状态" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="getOrderStatusMeta(row.status).type" effect="dark">{{ getOrderStatusMeta(row.status).label }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="调度动作与轨迹" width="220" align="center" fixed="right">
        <template #default="{ row }">
          <div style="display:flex; justify-content:center; gap:8px;">
            <el-button v-if="row.status === 1" type="primary" size="small" icon="Connection" @click="openAssignDialog(row)">指派</el-button>
            <el-button v-if="row.status === 3" type="success" size="small" icon="Stamp" @click="openAuditDialog(row)">验收</el-button>
            
            <el-button type="info" size="small" plain icon="Timer" @click="openTimelineDialog(row)">追踪轨迹</el-button>
          </div>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="assignDialogVisible" title="下发调度指令" width="400px">
      <el-form label-position="top">
        <el-form-item label="选择外勤责任人">
          <el-select v-model="selectedHandlerId" filterable placeholder="检索当前在岗外勤" style="width: 100%;">
            <el-option v-for="staff in staffData" :key="staff.id" :label="`${staff.real_name} (${staff.position})`" :value="staff.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer><el-button @click="assignDialogVisible = false">取消</el-button><el-button type="primary" :loading="submitLoading" @click="submitAssign">确认派单</el-button></template>
    </el-dialog>

    <el-dialog v-model="auditDialogVisible" title="完工照片审查与验收" width="500px">
      <div v-if="currentOrder" class="audit-preview">
        <div style="margin-bottom: 10px; color: #606266;">外勤上传的现场作业后照片：</div>
        <el-image style="width: 100%; height: 250px; border-radius: 8px;" :src="getFullImgUrl(currentOrder.resolve_image_url)" :preview-src-list="[getFullImgUrl(currentOrder.resolve_image_url)]" fit="cover" />
        <el-input v-model="auditContent" type="textarea" :rows="3" placeholder="（选填）审核批注，如要驳回重做请务必填写驳回理由" style="margin-top: 15px;" />
      </div>
      <template #footer>
        <el-button type="danger" plain icon="Close" :loading="submitLoading" @click="submitAudit('audit_reject')">驳回重做</el-button>
        <el-button type="success" icon="Check" :loading="submitLoading" @click="submitAudit('audit_pass')">合格通过</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="timelineDialogVisible" title="工单全链路生命周期追踪" width="500px">
      <el-timeline v-if="currentTimeline.length > 0">
        <el-timeline-item
          v-for="(activity, index) in currentTimeline"
          :key="index"
          :timestamp="activity.time"
          :type="getTimelineType(activity.title)"
          :hollow="index !== currentTimeline.length - 1"
          placement="top"
        >
          <el-card shadow="hover" style="border-radius: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
              <span style="font-weight:bold; font-size:15px; color:#303133;">{{ activity.title }}</span>
              <el-tag size="small" type="info" effect="plain">{{ activity.operator }}</el-tag>
            </div>
            <p style="margin:0; font-size:13px; color:#606266; line-height:1.5;">{{ activity.desc }}</p>
            <div v-if="activity.image" style="margin-top: 10px;">
              <el-image style="width: 60px; height: 60px; border-radius: 4px;" :src="getFullImgUrl(activity.image)" :preview-src-list="[getFullImgUrl(activity.image)]" fit="cover" />
            </div>
          </el-card>
        </el-timeline-item>
      </el-timeline>
      <el-empty v-else description="暂无追踪记录" :image-size="60" />
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
const auditDialogVisible = ref(false)
const timelineDialogVisible = ref(false)
const submitLoading = ref(false)
const currentOrder = ref(null)
const selectedHandlerId = ref('')
const auditContent = ref('')
const currentTimeline = ref([])

const fetchOrders = async () => { 
  ordersLoading.value = true
  const res = await request.get('/api/work_order/list')
  if (res.code === 200) ordersData.value = res.data
  ordersLoading.value = false 
}

const fetchStaff = async () => { 
  const res = await request.get('/api/services/staff/list')
  if (res.code === 200) staffData.value = res.data.filter(s => s.status === 1) 
}

const openAssignDialog = (row) => { currentOrder.value = row; selectedHandlerId.value = ''; assignDialogVisible.value = true }
const submitAssign = async () => {
  if (!selectedHandlerId.value) return ElMessage.warning('请选择执行人员')
  submitLoading.value = true
  try {
    const res = await request.post('/api/work_order/action', { id: currentOrder.value.id, action: 'assign', worker_id: selectedHandlerId.value })
    if (res.code === 200) { ElMessage.success('指令已下发'); assignDialogVisible.value = false; fetchOrders() }
  } finally { submitLoading.value = false }
}

const openAuditDialog = (row) => { currentOrder.value = row; auditContent.value = ''; auditDialogVisible.value = true }
const submitAudit = async (actionType) => {
  if (actionType === 'audit_reject' && !auditContent.value) return ElMessage.warning('驳回必须填写理由')
  submitLoading.value = true
  try {
    const res = await request.post('/api/work_order/action', { id: currentOrder.value.id, action: actionType, content: auditContent.value })
    if (res.code === 200) { ElMessage.success(actionType === 'audit_pass' ? '审核通过' : '已驳回'); auditDialogVisible.value = false; fetchOrders() }
  } finally { submitLoading.value = false }
}

// 打开时间轴
const openTimelineDialog = (row) => {
  currentTimeline.value = []
  if (row.process_log) {
    try { currentTimeline.value = JSON.parse(row.process_log) } catch (e) {}
  }
  timelineDialogVisible.value = true
}

const getFullImgUrl = (url) => url ? (url.startsWith('http') ? url : `http://47.120.52.65:8787${url}`) : ''

const getOrderStatusMeta = (status) => {
  const map = { 1: { label: '待指派', type: 'info' }, 2: { label: '作业中', type: 'warning' }, 3: { label: '待验收', type: 'primary' }, 4: { label: '已完结', type: 'success' } }
  return map[status] || { label: '未知', type: 'info' }
}

const getTimelineType = (title) => {
  if (title.includes('驳回')) return 'danger'
  if (title.includes('通过') || title.includes('闭环')) return 'success'
  if (title.includes('验收') || title.includes('上报')) return 'primary'
  return 'info'
}

onMounted(() => { fetchStaff().then(() => fetchOrders()) })
</script>

<style scoped>
.work-order-container { padding: 20px; background: #fff; height: 100%; }
.header-stats { margin-bottom: 20px; }
.toolbar { margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.img-box { position: relative; width: 50px; height: 50px; border: 1px solid #ebeef5; border-radius: 4px; overflow: hidden; }
.img-box .el-image { width: 100%; height: 100%; }
.img-label { position: absolute; bottom: 0; left: 0; width: 100%; font-size: 9px; text-align: center; background: rgba(255,255,255,0.9); font-weight: bold; padding: 1px 0;}
.text-danger { color: #f56c6c; }
.text-success { color: #67c23a; }
</style>