<template>
  <div class="patrol-container">
    <el-tabs v-model="activeTab" type="border-card" class="patrol-tabs">
      
      <el-tab-pane label="巡检打卡实时流水" name="records">
        <div class="toolbar">
          <el-button type="warning" icon="Download" @click="exportData('patrol_records')" plain>导出打卡流水</el-button>
          <el-button type="primary" icon="Refresh" @click="fetchRecords">刷新打卡大屏</el-button>
        </div>
        <el-table :data="recordsData" v-loading="recordsLoading" border stripe style="width: 100%; border-radius: 4px;">
          <el-table-column prop="id" label="流水号" width="80" align="center" />
          <el-table-column prop="worker_name" label="巡更人员" width="120" align="center">
            <template #default="{ row }"><span style="font-weight: bold; color: #409eff;">{{ row.worker_name }}</span></template>
          </el-table-column>
          <el-table-column prop="location" label="巡检物理点位" min-width="160" />
          <el-table-column label="现场实勘照片" width="140" align="center">
            <template #default="{ row }">
              <div v-if="row.image_url" style="display: flex; justify-content: center;">
                <el-image 
                  style="width: 50px; height: 50px; border-radius: 4px; border: 1px solid #ebeef5; cursor: zoom-in;" 
                  :src="getFullImgUrl(row.image_url)" 
                  :preview-src-list="[getFullImgUrl(row.image_url)]" 
                  fit="cover" 
                  preview-teleported 
                />
              </div>
              <span v-else style="color: #c0c4cc; font-size: 12px;">无图像记录</span>
            </template>
          </el-table-column>
          <el-table-column prop="remarks" label="异常备注与隐患说明" min-width="180" show-overflow-tooltip />
          <el-table-column label="工况状态" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'danger'" effect="dark">
                {{ row.status === 1 ? '安全正常' : '隐患异常' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="物理打卡时间" width="180" align="center">
            <template #default="{ row }">
              <span style="font-size: 12px; color: #909399; font-family: monospace;">{{ row.created_at }}</span>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="物理巡检点位矩阵" name="points">
        <div class="toolbar">
          <el-button type="primary" icon="Plus" @click="openAddDialog">设立巡检网格点</el-button>
          <el-button type="warning" icon="Download" @click="exportData('patrol_points')" plain>导出网格配置</el-button>
          <el-button icon="Refresh" @click="fetchPoints">刷新点位矩阵</el-button>
        </div>
        <el-table :data="pointsData" v-loading="pointsLoading" border stripe style="width: 100%">
          
          <el-table-column type="expand">
            <template #default="{ row }">
              <div style="padding: 20px 40px; background-color: #fafbfd; border-radius: 8px; margin: 10px;">
                <h4 style="margin-top: 0; margin-bottom:15px; color: #409EFF; display: flex; align-items: center;">
                  <el-icon style="margin-right: 8px;"><List /></el-icon>
                  【{{ row.location }}】 近期巡检打卡确切留档
                </h4>
                <el-table :data="row.historyRecords || []" border size="small" style="width: 100%; box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05);">
                  <el-table-column prop="created_at" label="打卡确切时间" width="180" align="center" />
                  <el-table-column prop="worker_name" label="巡检人员" width="120" align="center">
                    <template #default="{ row: hRow }"><b>{{ hRow.worker_name }}</b></template>
                  </el-table-column>
                  <el-table-column label="工况状态" width="120" align="center">
                    <template #default="{ row: hRow }">
                      <el-tag :type="hRow.status === 1 ? 'success' : 'danger'" effect="plain" size="small">
                        {{ hRow.status === 1 ? '安全正常' : '隐患异常' }}
                      </el-tag>
                    </template>
                  </el-table-column>
                  <el-table-column prop="remarks" label="异常备注与隐患说明" min-width="200" />
                </el-table>
                <div v-if="!row.historyRecords || row.historyRecords.length === 0" style="color: #909399; font-size: 13px; margin-top: 10px;">
                  暂无巡检记录留档
                </div>
              </div>
            </template>
          </el-table-column>

          <el-table-column prop="id" label="点位ID" width="80" align="center" />
          <el-table-column prop="location" label="防区点位名称" min-width="180" />
          
          <el-table-column label="频次与时间段规则" min-width="260">
            <template #default="{ row }">
              <div style="margin-bottom: 5px;">
                <el-tag size="small" effect="plain" style="margin-right: 6px;">{{ getTaskTypeName(row.task_type) }}</el-tag>
                <el-tag size="small" type="info" effect="dark">{{ getFrequencyName(row.frequency) }}</el-tag>
              </div>
              <div v-if="row.time_slots && row.time_slots.length > 0">
                <el-tag v-for="(slot, idx) in row.time_slots" :key="idx" size="small" type="warning" effect="plain" style="margin-right: 4px; margin-top: 4px;">
                  {{ slot.start }} - {{ slot.end }}
                </el-tag>
              </div>
              <span v-else style="color: #c0c4cc; font-size: 12px;">随时可查</span>
            </template>
          </el-table-column>

          <el-table-column label="当前时段互斥状态" width="160" align="center">
            <template #default="{ row }">
              <el-tooltip v-if="row.current_status === 'already_checked'" effect="dark" content="当前时段已有同事打过卡，其他人无需重复打卡" placement="top">
                <el-tag type="success" effect="dark">
                  <el-icon><Check /></el-icon> 区域已巡逻 ({{ row.checked_by }})
                </el-tag>
              </el-tooltip>
              <el-tag v-else type="danger" effect="plain">
                待巡检打卡
              </el-tag>
            </template>
          </el-table-column>

          <el-table-column label="网格配置操作" width="140" align="center" fixed="right">
            <template #default="{ row }">
              <el-button size="small" type="primary" link @click="handleEdit(row)">编辑</el-button>
              <el-button size="small" type="danger" link @click="handleDelete(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <el-dialog 
      v-model="dialogVisible" 
      :title="dialogType === 'add' ? '设立智能防区巡检点' : '修改防区网格配置'" 
      width="550px" 
      @close="pointFormRef?.resetFields()"
    >
      <el-form ref="pointFormRef" :model="pointForm" label-width="100px" label-position="left">
        <el-form-item label="点位名称" prop="location" :rules="[{ required: true, message: '请填写物理点位名称' }]">
          <el-input v-model="pointForm.location" placeholder="例如：地下车库B2层西北角 / 园区西侧消防栓" />
        </el-form-item>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="任务类型" prop="task_type">
              <el-select v-model="pointForm.task_type" style="width: 100%;">
                <el-option label="安防巡逻 (Security)" value="security" />
                <el-option label="消防设施 (Fire Control)" value="fire" />
                <el-option label="环境卫生 (Hygiene)" value="hygiene" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="巡检频次" prop="frequency">
              <el-select v-model="pointForm.frequency" style="width: 100%;">
                <el-option label="每日 (Daily)" value="daily" />
                <el-option label="每周 (Weekly)" value="weekly" />
                <el-option label="每月 (Monthly)" value="monthly" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="巡更时段配置" style="margin-bottom: 0;">
          <div style="color: #909399; font-size: 12px; margin-bottom: 10px; line-height: 1.4;">
            配置需打卡的精确时段（如早中晚一天三次）。若同此时段内人员A已打卡，系统将对BCD人员显示该区域已巡查。
          </div>
          <div v-for="(slot, index) in pointForm.time_slots" :key="index" style="display: flex; gap: 10px; margin-bottom: 12px; align-items: center; background: #f5f7fa; padding: 10px; border-radius: 4px;">
            <span style="font-size:13px; font-weight:bold; color:#606266;">时段{{ index + 1 }}</span>
            <el-time-select v-model="slot.start" start="00:00" step="00:30" end="23:30" placeholder="起始时间" style="width: 120px;" />
            <span style="color:#909399;">至</span>
            <el-time-select v-model="slot.end" start="00:00" step="00:30" end="23:30" placeholder="结束时间" style="width: 120px;" />
            <el-button type="danger" icon="Delete" circle size="small" @click="removeTimeSlot(index)" plain />
          </div>
          <el-button type="primary" plain size="small" icon="Plus" @click="addTimeSlot" style="margin-top: 5px; width: 100%; border-style: dashed;">
            增添新的巡检时间段
          </el-button>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitPoint">确认保存配置</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { List, Check } from '@element-plus/icons-vue'
import request from '../../utils/request'

const activeTab = ref('records')
const recordsData = ref([])
const recordsLoading = ref(false)
const pointsData = ref([])
const pointsLoading = ref(false)

const dialogVisible = ref(false)
const dialogType = ref('add') // 'add' 新增 | 'edit' 编辑
const submitLoading = ref(false)
const pointFormRef = ref(null)

// 包含数据回显的响应式表单
const pointForm = reactive({ 
  id: '',
  location: '',
  task_type: 'security',
  frequency: 'daily',
  time_slots: [{ start: '08:00', end: '10:00' }]
})

const getTaskTypeName = (val) => {
  const map = { security: '安防巡查', fire: '消防稽查', hygiene: '园区卫生' }
  return map[val] || val || '常规巡检'
}

const getFrequencyName = (val) => {
  const map = { daily: '每日', weekly: '每周', monthly: '每月' }
  return map[val] || val || '按需检查'
}

// 打开添加弹窗
const openAddDialog = () => {
  dialogType.value = 'add'
  pointForm.id = ''
  pointForm.location = ''
  pointForm.task_type = 'security'
  pointForm.frequency = 'daily'
  pointForm.time_slots = [{ start: '08:00', end: '10:00' }]
  dialogVisible.value = true
}

// 打开二次编辑弹窗
const handleEdit = (row) => {
  dialogType.value = 'edit'
  pointForm.id = row.id
  pointForm.location = row.location
  pointForm.task_type = row.task_type || 'security'
  pointForm.frequency = row.frequency || 'daily'
  // 深度拷贝时间段，防止在弹窗里修改时直接影响背后的表格数据
  pointForm.time_slots = row.time_slots && row.time_slots.length > 0 
    ? JSON.parse(JSON.stringify(row.time_slots)) 
    : [{ start: '08:00', end: '10:00' }]
  
  dialogVisible.value = true
}

// 确认删除点位
const handleDelete = (row) => {
  ElMessageBox.confirm(
    `确定要彻底删除网格点位【${row.location}】吗？删除后外勤人员将无法在移动端进行打卡操作！`,
    '高危防区撤编确认',
    {
      confirmButtonText: '确认删除',
      cancelButtonText: '取消',
      type: 'error',
    }
  ).then(async () => {
    const res = await request.post('/api/patrol/points/delete', { id: row.id })
    if (res.code === 200) {
      ElMessage.success('网格点位删除成功')
      fetchPoints() // 重新刷新点位矩阵
    } else {
      ElMessage.error(res.msg || '删除拦截')
    }
  }).catch(() => {
    // 操作取消，不执行任何逻辑
  })
}

const addTimeSlot = () => {
  pointForm.time_slots.push({ start: '', end: '' })
}

const removeTimeSlot = (idx) => {
  pointForm.time_slots.splice(idx, 1)
}

const getFullImgUrl = (url) => {
  if (!url) return ''
  return url.startsWith('http') ? url : `http://47.120.52.65:8787${url}`
}

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

// 统一的保存（包含新增和更新）
const submitPoint = () => {
  pointFormRef.value.validate(async (valid) => {
    if (!valid) return
    
    for (const slot of pointForm.time_slots) {
      if (!slot.start || !slot.end) {
        ElMessage.warning('系统提醒：请将时间段的起始和结束时间填写完整')
        return
      }
    }

    submitLoading.value = true
    // 动态判断当前调用哪个接口
    const url = dialogType.value === 'add' ? '/api/patrol/points/add' : '/api/patrol/points/update'
    
    const res = await request.post(url, pointForm)
    if (res.code === 200) {
      ElMessage.success(dialogType.value === 'add' ? '智能防区点位设立成功' : '防区配置更新成功')
      dialogVisible.value = false
      fetchPoints()
    } else {
      ElMessage.error(res.msg || '操作异常，已被网关拦截')
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
.patrol-container { width: 100%; background: #fff; padding: 20px; height: 100%; box-sizing: border-box; }
.patrol-tabs { box-shadow: none; border-radius: 4px; border: none; }
.toolbar { margin-bottom: 20px; display: flex; gap: 10px; }
:deep(.el-tabs--border-card > .el-tabs__header) { background-color: #f5f7fa; border-bottom: 1px solid #e4e7ed; margin: 0; }
:deep(.el-tabs--border-card > .el-tabs__content) { padding: 20px 0; }

/* 展开行内层表格美化 */
:deep(.el-table__expanded-cell) {
  padding: 0 !important;
  background-color: #fafbfd !important;
}
</style>