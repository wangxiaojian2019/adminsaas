<template>
  <div class="vehicles-container">
    <el-card shadow="never" class="main-card">
      <div class="toolbar">
        <div class="toolbar-left">
          <el-button type="primary" icon="Plus" @click="openAddDialog">办理车位月卡/固定车</el-button>
          <el-button type="warning" icon="Download" @click="exportData">带水印导出账册</el-button>
          <el-button icon="Refresh" @click="fetchVehicles">刷新数据</el-button>
        </div>
        <div class="toolbar-right">
          <div class="search-box">
            <el-input v-model="searchPlate" placeholder="输入车牌号检索" clearable prefix-icon="Search" style="width: 180px;" />
            <el-select v-model="filterStatus" clearable placeholder="卡片状态" style="width: 120px; margin-left: 10px;">
              <el-option label="全部" value="" />
              <el-option label="有效" :value="1" />
              <el-option label="已过期/停用" :value="0" />
            </el-select>
          </div>
        </div>
      </div>

      <el-table :data="processedTableData" v-loading="loading" border stripe style="width: 100%">
        <el-table-column prop="id" label="资产ID" width="80" align="center" />
        <el-table-column prop="plate_no" label="车牌号码" width="130" align="center">
          <template #default="{ row }">
            <span class="plate-badge">{{ row.plate_no }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="enterprise_name" label="所属企业" min-width="180" show-overflow-tooltip />
        <el-table-column prop="parking_space_no" label="分配车位/区域" width="140" align="center" />
        <el-table-column label="车卡类别" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="row.card_type === 1 ? 'primary' : 'success'" effect="light">
              {{ row.card_type === 1 ? '月卡泊车' : '产权固定车' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="monthly_fee" label="月托管费(元)" width="120" align="right">
          <template #default="{ row }">¥ {{ row.monthly_fee }}</template>
        </el-table-column>
        <el-table-column label="有效授权周期" width="220" align="center">
          <template #default="{ row }">
            <span class="date-text">{{ row.start_date }}</span>
            <span style="margin: 0 4px; color: #909399;">至</span>
            <span class="date-text" :class="{ 'text-expired': isExpired(row.end_date) }">{{ row.end_date }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 && !isExpired(row.end_date) ? 'success' : 'danger'" effect="dark">
              {{ row.status === 1 && !isExpired(row.end_date) ? '有效' : '已到期' }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="首次登记建档时间" width="160" align="center">
          <template #default="{ row }">
            <span style="font-size: 12px; color: #909399; font-family: monospace;">{{ row.created_at }}</span>
          </template>
        </el-table-column>

        <el-table-column label="操作" width="180" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link icon="Calendar" @click="openRenewDialog(row)">续费延期</el-button>
            <el-popconfirm title="确认永久注销此车辆车位授信？" @confirm="deleteVehicle(row.id)">
              <template #reference>
                <el-button type="danger" link icon="Delete">注销</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="addDialogVisible" title="办理车位月卡/固定车授信" width="520px" @close="addFormRef?.resetFields()">
      <el-form ref="addFormRef" :model="addForm" :rules="rules" label-width="110px">
        <div style="display: flex; gap: 15px;">
          <el-form-item label="车牌号码" prop="plate_no" style="flex: 1;">
            <el-input v-model="addForm.plate_no" placeholder="如: 粤B88888" />
          </el-form-item>
          <el-form-item label="车卡类别" prop="card_type" style="flex: 1;">
            <el-select v-model="addForm.card_type" style="width: 100%;">
              <el-option :value="1" label="月卡泊车" />
              <el-option :value="2" label="产权固定车" />
            </el-select>
          </el-form-item>
        </div>
        <el-form-item label="归属园区企业" prop="enterprise_id">
          <el-select v-model="addForm.enterprise_id" filterable placeholder="检索挂载企业档案" style="width: 100%;">
            <el-option v-for="ent in enterprises" :key="ent.id" :label="ent.name" :value="ent.id" />
          </el-select>
        </el-form-item>
        <div style="display: flex; gap: 15px;">
          <el-form-item label="指定车位/区域" prop="parking_space_no" style="flex: 1;">
            <el-input v-model="addForm.parking_space_no" placeholder="如: 负一楼B区102" />
          </el-form-item>
          <el-form-item label="月标准规费" prop="monthly_fee" style="flex: 1;">
            <el-input v-model.number="addForm.monthly_fee" placeholder="元/月" />
          </el-form-item>
        </div>
        <el-form-item label="授权有效期" prop="dateRange">
          <el-date-picker v-model="addForm.dateRange" type="daterange" range-separator="至" start-placeholder="生效日" end-placeholder="截止日" value-format="YYYY-MM-DD" style="width: 100%;" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="addDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitAdd">确认确权开通</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="renewDialogVisible" title="车场月卡续费延期" width="400px">
      <div class="renew-body">
        <div class="info-row"><span>车牌号码：</span><strong>{{ currentVehicle.plate_no }}</strong></div>
        <div class="info-row"><span>当前到期：</span><span>{{ currentVehicle.end_date }}</span></div>
        <el-divider border-style="dashed" style="margin: 15px 0;" />
        <el-form label-position="left" label-width="80px">
          <el-form-item label="续费周期">
            <el-input-number v-model="renewMonths" :min="1" :max="12" style="width: 150px;" />
            <span style="margin-left: 10px;">个月</span>
          </el-form-item>
        </el-form>
        <div class="calc-box">
          <div class="calc-label">应收车位规费总计：</div>
          <div class="calc-amount">¥ {{ (currentVehicle.monthly_fee * renewMonths).toFixed(2) }}</div>
          <p class="calc-tips">* 确认后，系统将自动向财务一体化中心推送一条待核销的应收物业账单。</p>
        </div>
      </div>
      <template #footer>
        <el-button @click="renewDialogVisible = false">取消</el-button>
        <el-button type="success" :loading="submitLoading" @click="submitRenew">生成账单并延期</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import request from '../../utils/request'

const tableData = ref([])
const enterprises = ref([])
const loading = ref(false)
const submitLoading = ref(false)

const searchPlate = ref('')
const filterStatus = ref(null)

const addDialogVisible = ref(false)
const addFormRef = ref(null)
const addForm = reactive({ plate_no: '', card_type: 1, enterprise_id: '', parking_space_no: '', monthly_fee: '', dateRange: [] })
const rules = {
  plate_no: [{ required: true, message: '车牌号必填', trigger: 'blur' }],
  enterprise_id: [{ required: true, message: '请选择关联企业', trigger: 'change' }],
  parking_space_no: [{ required: true, message: '请指定物理车位', trigger: 'blur' }]
}

const renewDialogVisible = ref(false)
const currentVehicle = ref({})
const renewMonths = ref(1)

const processedTableData = computed(() => {
  return tableData.value.filter(row => {
    if (searchPlate.value && !row.plate_no.toUpperCase().includes(searchPlate.value.trim().toUpperCase())) return false
    if (typeof filterStatus.value === 'number') {
      const active = row.status === 1 && !isExpired(row.end_date)
      if (filterStatus.value === 1 && !active) return false
      if (filterStatus.value === 0 && active) return false
    }
    return true
  })
})

const isExpired = (endDateStr) => {
  if (!endDateStr) return true
  return new Date(endDateStr + ' 23:59:59').getTime() < new Date().getTime()
}

const fetchVehicles = async () => {
  loading.value = true
  try {
    const res = await request.get('/api/vehicles/list')
    if (res.code === 200) tableData.value = res.data
  } finally { loading.value = false }
}

const openAddDialog = async () => {
  addDialogVisible.value = true
  const res = await request.get('/api/enterprises/list')
  if (res.code === 200) enterprises.value = res.data
}

const submitAdd = () => {
  addFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    try {
      const payload = {
        ...addForm,
        start_date: addForm.dateRange[0],
        end_date: addForm.dateRange[1]
      }
      const res = await request.post('/api/vehicles/add', payload)
      if (res.code === 200) {
        ElMessage.success('车辆车位资产绑定成功')
        addDialogVisible.value = false
        fetchVehicles()
      }
    } finally { submitLoading.value = false }
  })
}

const openRenewDialog = (row) => {
  currentVehicle.value = row
  renewMonths.value = 1
  renewDialogVisible.value = true
}

const submitRenew = async () => {
  submitLoading.value = true
  try {
    const res = await request.post('/api/vehicles/renew', {
      id: currentVehicle.value.id,
      months: renewMonths.value
    })
    if (res.code === 200) {
      ElMessage.success('月卡延期成功，能耗车位规费应收账单已推送到业财一体化中心')
      renewDialogVisible.value = false
      fetchVehicles()
    }
  } finally { submitLoading.value = false }
}

const deleteVehicle = async (id) => {
  const res = await request.post('/api/vehicles/delete', { id })
  if (res.code === 200) {
    ElMessage.success('车辆授信已永久注销')
    fetchVehicles()
  }
}

const exportData = async () => {
  ElMessage.info('加密组装离线防篡改数据中...')
  const token = localStorage.getItem('saas_token')
  try {
    const res = await fetch(`http://47.120.52.65:8787/api/export/download?module=vehicles`, {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    if (res.status === 200) {
      const blob = await res.blob()
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `车位月卡资产账册_${new Date().getTime()}.csv`
      a.click()
      ElMessage.success('安全外发文件下载成功，审计日志已锁死存证')
    }
  } catch (e) { ElMessage.error('导出失败') }
}

onMounted(fetchVehicles)
</script>

<style scoped>
.vehicles-container { width: 100%; }
.main-card { border-radius: 4px; box-shadow: none; }
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.toolbar-left { display: flex; gap: 10px; }
.search-box { display: flex; align-items: center; }

.plate-badge { background: linear-gradient(135deg, #2c3e50, #34495e); color: #f1c40f; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-family: monospace; font-size: 13px; border: 1px solid #f39c12; }
.date-text { font-family: monospace; font-size: 12px; }
.text-expired { color: #f56c6c; text-decoration: line-through; }

.renew-body { padding: 5px; }
.info-row { font-size: 14px; margin-bottom: 10px; color: #606266; }
.info-row strong { color: #303133; font-size: 16px; }
.calc-box { background-color: #fcf8e3; padding: 15px; border-radius: 6px; border: 1px dashed #faebcc; margin-top: 20px; text-align: center; }
.calc-label { font-size: 13px; color: #8a6d3b; margin-bottom: 6px; }
.calc-amount { font-size: 28px; font-weight: bold; color: #c7254e; font-family: monospace; }
.calc-tips { font-size: 11px; color: #a17d3a; margin-top: 8px; text-align: left; line-height: 1.4; }
</style>