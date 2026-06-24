<template>
  <div class="decoration-container">
    <el-row :gutter="20" class="stat-row">
      <el-col :span="6">
        <div class="stat-box" style="background: linear-gradient(135deg, #409EFF 0%, #66b1ff 100%)">
          <div class="label">待审批申报</div><div class="value">{{ stats.pending }} 笔</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-box" style="background: linear-gradient(135deg, #E6A23C 0%, #f3d19e 100%)">
          <div class="label">在施进行中</div><div class="value">{{ stats.building }} 笔</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-box" style="background: linear-gradient(135deg, #F56C6C 0%, #f78989 100%)">
          <div class="label">延期流转中</div><div class="value">{{ stats.delaying }} 笔</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-box" style="background: linear-gradient(135deg, #67C23A 0%, #85ce61 100%)">
          <div class="label">已竣工核销</div><div class="value">{{ stats.finished }} 笔</div>
        </div>
      </el-col>
    </el-row>

    <el-card class="filter-card" shadow="never">
      <div class="toolbar">
        <el-form :inline="true" :model="queryForm" size="default">
          <el-form-item label="企业名称">
            <el-input v-model="queryForm.entName" placeholder="输入企业查询" clearable />
          </el-form-item>
          <el-form-item label="报备状态">
            <el-select v-model="queryForm.status" placeholder="全部状态" style="width: 150px" clearable>
              <el-option label="待审核" value="0" />
              <el-option label="施工中" value="1" />
              <el-option label="延期审核中" value="2" />
              <el-option label="已完工" value="3" />
              <el-option label="驳回/终止" value="4" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="fetchList">查询</el-button>
          </el-form-item>
        </el-form>
        <el-button type="primary" :icon="Plus" @click="openApplyDialog">发起新施工报备</el-button>
      </div>
    </el-card>

    <el-table :data="tableData" border style="width: 100%; margin-top: 20px" v-loading="loading">
      <el-table-column prop="apply_no" label="报备单号" width="140" />
      <el-table-column prop="enterprise_name" label="申报企业" width="180" />
      <el-table-column prop="room_info" label="施工房源" width="140" />
      <el-table-column label="报备周期/天数" width="240">
        <template #default="{ row }">
          <div class="time-range">{{ row.start_date }} 至 {{ row.end_date }}</div>
          <el-tag size="small" type="info">核准工期：{{ row.total_days }} 天</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="装修状态" width="120" align="center">
        <template #default="{ row }">
          <el-tag :type="getStatusTag(row.status)">{{ getStatusText(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="deposit" label="装修押金" width="110">
        <template #default="{ row }">￥{{ row.deposit }}</template>
      </el-table-column>
      <el-table-column prop="manager" label="现场负责人" width="110" />
      <el-table-column label="综合操作流转">
        <template #default="{ row }">
          <template v-if="row.status == 0">
            <el-button size="small" type="success" @click="auditAction(row.id, 1)">批准施工</el-button>
            <el-button size="small" type="danger" @click="auditAction(row.id, 4)">驳回申请</el-button>
          </template>
          <template v-if="row.status == 1">
            <el-button size="small" type="warning" @click="triggerEarlyFinish(row)">提前完工验收</el-button>
            <el-button size="small" type="primary" plain @click="openDelayDialog(row)">申请延期施工</el-button>
          </template>
          <template v-if="row.status == 2">
            <el-button size="small" type="success" @click="auditAction(row.id, 1)">准予延期</el-button>
          </template>
          <span v-if="row.status == 3" class="finish-text">流程闭环(已退押金)</span>
          <span v-if="row.status == 4" style="color: #909399">已作废</span>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog title="新建企业装修进场报备单" v-model="applyVisible" width="550px">
      <el-form :model="applyForm" label-width="110px">
        <el-form-item label="选择企业">
          <el-select v-model="applyForm.enterprise_id" @change="handleEnterpriseChange" placeholder="请搜索并选择企业" filterable style="width: 100%">
            <el-option v-for="item in enterpriseList" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="施工房间">
          <el-select v-model="applyForm.space_id" placeholder="请先选择企业，系统将自动加载其名下房源" filterable style="width: 100%" :disabled="!applyForm.enterprise_id">
            <el-option v-for="item in filteredSpaceList" :key="item.id" :label="`${item.building_name}-${item.floor}F-${item.room_number}`" :value="item.id">
              <span style="float: left">{{ item.building_name }}-{{ item.floor }}F-{{ item.room_number }}</span>
              <span style="float: right; color: #8492a6; font-size: 13px">{{ item.enterprise_name }}</span>
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="计划工期">
          <el-date-picker v-model="applyForm.dateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" value-format="YYYY-MM-DD" @change="calculateFormDays" style="width: 100%" />
        </el-form-item>
        <el-form-item label="系统预算天数">
          <el-input v-model="applyForm.total_days" disabled style="width: 120px"><template #append>天</template></el-input>
        </el-form-item>
        <el-form-item label="特批装修押金">
          <el-input-number v-model="applyForm.deposit" :min="0" :step="1000" />
        </el-form-item>
        <el-form-item label="施工负责人">
          <el-input v-model="applyForm.manager" placeholder="姓名" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="applyVisible = false">取消</el-button>
        <el-button type="primary" @click="submitApply">提交审核</el-button>
      </template>
    </el-dialog>

    <el-dialog title="变更工期：提交装修延期二次申请" v-model="delayVisible" width="450px">
      <el-form :model="delayForm" label-width="110px">
        <el-form-item label="当前结束日期">
          <el-input v-model="delayForm.old_end_date" disabled />
        </el-form-item>
        <el-form-item label="拟申请延期至">
          <el-date-picker v-model="delayForm.new_end_date" type="date" value-format="YYYY-MM-DD" placeholder="新结束日期" style="width: 100%" />
        </el-form-item>
        <el-form-item label="延期原因说明">
          <el-input v-model="delayForm.reason" type="textarea" placeholder="填写物料迟滞或特殊施工工艺说明" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="delayVisible = false">取消</el-button>
        <el-button type="primary" @click="submitDelay">提交延期审批</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import request from '@/utils/request'

const loading = ref(false)
const applyVisible = ref(false)
const delayVisible = ref(false)

const stats = ref({ pending: 0, building: 0, delaying: 0, finished: 0 })
const tableData = ref([])
const enterpriseList = ref([]) 
const spaceList = ref([]) 

const queryForm = ref({ entName: '', status: '' })
const applyForm = ref({ enterprise_id: null, space_id: null, dateRange: [], total_days: 0, deposit: 5000, manager: '' })
const delayForm = ref({ id: null, old_end_date: '', new_end_date: '', reason: '' })

const getStatusText = (status) => ({ 0: '待审核', 1: '施工中', 2: '延期审核中', 3: '已完工', 4: '已驳回' }[status])
const getStatusTag = (status) => ({ 0: 'primary', 1: 'warning', 2: 'danger', 3: 'success', 4: 'info' }[status])

// 通过计算属性实现级联过滤：仅展示所选企业名下的房源资产
const filteredSpaceList = computed(() => {
  if (!applyForm.value.enterprise_id) return []
  return spaceList.value.filter(space => space.enterprise_id === applyForm.value.enterprise_id)
})

// 当重新选择企业时，必须防备性清空下层房源ID，防止幽灵数据提交
const handleEnterpriseChange = () => {
  applyForm.value.space_id = null
}

const fetchList = async () => {
  loading.value = true
  try {
    const res = await request.get('/api/v1/decoration/list', { params: queryForm.value })
    if (res.code === 200) {
      tableData.value = res.data
      stats.value = res.stats || { pending: 0, building: 0, delaying: 0, finished: 0 }
    }
  } finally {
    loading.value = false
  }
}

const fetchDictData = async () => {
  try {
    const [entRes, spaceRes] = await Promise.all([
      request.get('/api/enterprises/list'),
      request.get('/api/spaces/list')
    ])
    if (entRes.code === 200) enterpriseList.value = entRes.data
    if (spaceRes.code === 200) spaceList.value = spaceRes.data
  } catch (e) {
    console.error('字典数据加载失败', e)
  }
}

onMounted(() => {
  fetchList()
  fetchDictData()
})

const calculateFormDays = (val) => {
  if (val && val.length === 2) {
    const start = new Date(val[0]), end = new Date(val[1])
    applyForm.value.total_days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1
  } else {
    applyForm.value.total_days = 0
  }
}

const openApplyDialog = () => {
  applyForm.value = { enterprise_id: null, space_id: null, dateRange: [], total_days: 0, deposit: 5000, manager: '' }
  applyVisible.value = true
}

const submitApply = async () => {
  if (!applyForm.value.enterprise_id || !applyForm.value.space_id || applyForm.value.total_days <= 0) {
    return ElMessage.error('请按照级联顺序选择企业与房源，并填写完整工期')
  }
  
  const payload = {
    enterprise_id: applyForm.value.enterprise_id,
    space_id: applyForm.value.space_id,
    start_date: applyForm.value.dateRange[0],
    end_date: applyForm.value.dateRange[1],
    deposit: applyForm.value.deposit,
    manager: applyForm.value.manager
  }

  const res = await request.post('/api/v1/decoration/apply', payload)
  if (res.code === 200) {
    ElMessage.success(res.msg)
    applyVisible.value = false
    fetchList()
  }
}

const auditAction = async (id, targetStatus) => {
  const res = await request.post('/api/v1/decoration/audit', { id, status: targetStatus })
  if (res.code === 200) {
    ElMessage.success(res.msg)
    fetchList()
  } else {
    ElMessage.error(res.msg || '操作失败')
  }
}

const triggerEarlyFinish = (row) => {
  ElMessageBox.confirm(`系统将同步资产物理状态并恢复计费，确认验收通过？`, '验收确认', { type: 'info' }).then(() => {
    auditAction(row.id, 3) 
  })
}

const openDelayDialog = (row) => {
  delayForm.value = { id: row.id, old_end_date: row.end_date, new_end_date: '', reason: '' }
  delayVisible.value = true
}

const submitDelay = async () => {
  if (!delayForm.value.new_end_date) return ElMessage.error('请选择延期日期')
  const res = await request.post('/api/v1/decoration/delay', delayForm.value)
  if (res.code === 200) {
    ElMessage.warning(res.msg)
    delayVisible.value = false
    fetchList()
  }
}
</script>

<style scoped>
.decoration-container { padding: 20px; }
.stat-row { margin-bottom: 20px; }
.stat-box { padding: 20px; border-radius: 6px; color: #fff; box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05); }
.stat-box .label { font-size: 14px; opacity: 0.85; margin-bottom: 8px; }
.stat-box .value { font-size: 24px; font-weight: bold; }
.toolbar { display: flex; justify-content: space-between; align-items: center; }
.time-range { font-size: 13px; font-weight: bold; color: #303133; margin-bottom: 4px; }
.finish-text { color: #67C23A; font-size: 13px; font-weight: bold; }
</style>