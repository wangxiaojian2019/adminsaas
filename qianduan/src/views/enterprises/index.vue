<template>
  <div class="enterprises-container">
    <el-card shadow="never" class="main-card">
      <div class="toolbar">
        <div class="toolbar-left">
          <el-button type="primary" icon="Plus" @click="openDialog">入驻企业建档</el-button>
          <el-button icon="Refresh" @click="fetchData">刷新档案池</el-button>
          <el-button type="warning" icon="Download" @click="exportData">带水印导出户籍</el-button>
        </div>
        <div class="toolbar-right">
          <span style="font-size: 13px; color: #606266; margin-right: 10px; font-weight: bold;">履约到期日筛查：</span>
          <el-date-picker
            v-model="filterDateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            style="width: 260px;"
            clearable
          />
        </div>
      </div>

      <el-alert title="提示：企业的【联系人手机号】即为该企业登录移动端租户服务门户的账号。初始密码默认为 123456。" type="info" show-icon style="margin-bottom: 15px;" />

      <el-table :data="processedTableData" v-loading="loading" border stripe style="width: 100%">
        <el-table-column prop="id" label="企业ID" width="80" align="center" />
        <el-table-column prop="name" label="入驻企业名称" min-width="160" show-overflow-tooltip />
        <el-table-column prop="contact_person" label="关键联系人" width="100" align="center" />
        <el-table-column label="门户登录账号" width="130" align="center">
          <template #default="{ row }"><span style="font-family: monospace; font-weight: bold; color: #409eff;">{{ row.phone }}</span></template>
        </el-table-column>
        
        <el-table-column label="在缴月租(元)" width="110" align="right">
          <template #default="{ row }"><span v-if="row.monthly_rent !== null">¥ {{ row.monthly_rent }}</span><span v-else>-</span></template>
        </el-table-column>
        <el-table-column label="存管押金(元)" width="110" align="right">
          <template #default="{ row }"><span v-if="row.deposit !== null">¥ {{ row.deposit }}</span><span v-else>-</span></template>
        </el-table-column>
        <el-table-column label="最近履约到期日" width="130" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.end_date" :type="isExpiringSoon(row.end_date) ? 'danger' : 'success'">{{ row.end_date }}</el-tag>
            <span v-else style="color: #909399; font-size: 12px;">未建约</span>
          </template>
        </el-table-column>
        
        <el-table-column label="系统建档时间" width="160" align="center">
          <template #default="{ row }">
            <span style="font-size: 12px; color: #909399;">{{ row.created_at }}</span>
          </template>
        </el-table-column>

        <el-table-column label="操作" width="140" align="center" fixed="right">
          <template #default="{ row }">
            <el-popconfirm title="确认将该企业的登录密码重置为 123456 吗？" @confirm="resetPassword(row.id)">
              <template #reference>
                <el-button type="warning" link icon="Key">重置密码</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="dialogVisible" title="入驻企业建档登记" width="600px" @close="formRef?.resetFields()">
      <el-form ref="formRef" :model="formData" :rules="rules" label-width="110px">
        <el-form-item label="企业全称" prop="name"><el-input v-model="formData.name" /></el-form-item>
        <el-form-item label="所属行业" prop="industry"><el-input v-model="formData.industry" placeholder="例如：软件开发、电商" /></el-form-item>
        
        <div style="display: flex; gap: 15px;">
          <el-form-item label="联系人姓名" prop="contact_person" style="flex: 1;"><el-input v-model="formData.contact_person" /></el-form-item>
          <el-form-item label="联系人手机" prop="phone" style="flex: 1;"><el-input v-model="formData.phone" placeholder="作为门户账号" /></el-form-item>
        </div>

        <el-divider content-position="left">空间指派与合约束定 (选填)</el-divider>

        <el-form-item v-if="isQuickLease" label="快捷关联空间">
          <el-input v-model="bindSpaceInfo" disabled>
            <template #append><el-tag type="success">热力图锁定</el-tag></template>
          </el-input>
        </el-form-item>
        
        <el-form-item v-else label="分配空置房间">
          <el-select v-model="formData.space_id" filterable clearable placeholder="可选：在此直接选择空置房间办理入驻" style="width: 100%;">
            <el-option v-for="sp in availableSpaces" :key="sp.id" :label="`${sp.building_name} - ${sp.floor}F - ${sp.room_number} (面积:${sp.area}㎡)`" :value="sp.id" />
          </el-select>
        </el-form-item>

        <template v-if="formData.space_id">
          <el-form-item label="入驻合同周期" prop="dateRange" :rules="[{ required: true, message: '分配房间后必须设定合同周期', trigger: 'change' }]">
            <el-date-picker 
              v-model="formData.dateRange" 
              type="daterange" 
              range-separator="至" 
              start-placeholder="合同起租日" 
              end-placeholder="合同到期日" 
              value-format="YYYY-MM-DD" 
              style="width: 100%;" 
            />
          </el-form-item>
          
          <el-row :gutter="15">
            <el-col :span="8">
              <el-form-item label="月租金(元)" prop="monthly_rent" :rules="[{ required: true, message: '必填', trigger: 'blur' }]">
                <el-input-number v-model="formData.monthly_rent" :min="0" style="width: 100%;" controls-position="right" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="月物业费(元)" prop="property_fee">
                <el-input-number v-model="formData.property_fee" :min="0" style="width: 100%;" controls-position="right" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="履约押金(元)" prop="deposit">
                <el-input-number v-model="formData.deposit" :min="0" style="width: 100%;" controls-position="right" />
              </el-form-item>
            </el-col>
          </el-row>

          <el-row :gutter="15">
            <el-col :span="12">
              <el-form-item label="初始水表(吨)" prop="water_meter">
                <el-input-number v-model="formData.water_meter" :min="0" :precision="2" :step="0.1" style="width: 100%;" controls-position="right" placeholder="入驻交房时的水表读数" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="初始电表(度)" prop="electric_meter">
                <el-input-number v-model="formData.electric_meter" :min="0" :precision="2" :step="0.1" style="width: 100%;" controls-position="right" placeholder="入驻交房时的电表读数" />
              </el-form-item>
            </el-col>
          </el-row>
        </template>
        
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitAdd">确认建档登记</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import request from '../../utils/request'

const route = useRoute()
const router = useRouter()

const tableData = ref([])
const availableSpaces = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const submitLoading = ref(false)
const formRef = ref(null)
const isQuickLease = ref(false)
const bindSpaceInfo = ref('')

const filterDateRange = ref([])

const formData = reactive({ 
  name: '', industry: '', contact_person: '', phone: '', space_id: null, 
  dateRange: [], monthly_rent: 0, property_fee: 0, deposit: 0,
  water_meter: 0, electric_meter: 0 
})

const rules = {
  name: [{ required: true, message: '企业名称必填', trigger: 'blur' }],
  contact_person: [{ required: true, message: '联系人必填', trigger: 'blur' }],
  phone: [{ required: true, message: '手机号必填', trigger: 'blur' }]
}

const processedTableData = computed(() => {
  if (!filterDateRange.value || filterDateRange.value.length !== 2) {
    return tableData.value
  }
  const startF = new Date(filterDateRange.value[0]).getTime()
  const endF = new Date(filterDateRange.value[1] + ' 23:59:59').getTime()
  
  return tableData.value.filter(row => {
    if (!row.end_date) return false
    const currentEnd = new Date(row.end_date).getTime()
    return currentEnd >= startF && currentEnd <= endF
  })
})

const isExpiringSoon = (dateStr) => {
  if (!dateStr) return false
  const diff = new Date(dateStr).getTime() - new Date().getTime()
  return diff < 30 * 24 * 3600 * 1000 
}

const fetchData = async () => {
  loading.value = true
  const res = await request.get('/api/enterprises/list')
  if (res.code === 200) tableData.value = res.data
  loading.value = false
}

const loadAvailableSpaces = async () => {
  const res = await request.get('/api/spaces/list')
  if (res.code === 200) {
    availableSpaces.value = res.data.filter(s => s.status === 0)
  }
}

const openDialog = () => {
  isQuickLease.value = false
  formData.space_id = null
  formData.dateRange = []
  formData.monthly_rent = 0
  formData.property_fee = 0
  formData.deposit = 0
  formData.water_meter = 0
  formData.electric_meter = 0
  if (formRef.value) formRef.value.resetFields()
  loadAvailableSpaces()
  dialogVisible.value = true
}

const submitAdd = () => {
  formRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    const res = await request.post('/api/enterprises/add', formData)
    if (res.code === 200) {
      ElMessage.success(formData.space_id ? '建档成功，所选空置空间已被自动锁定并生成正式合同！' : '企业基础建档成功')
      dialogVisible.value = false
      fetchData()
    } else {
      ElMessage.error(res.msg || '建档失败')
    }
    submitLoading.value = false
  })
}

const resetPassword = async (id) => {
  const res = await request.post('/api/enterprises/reset_pwd', { id })
  if (res.code === 200) ElMessage.success(res.msg)
}

// 核心新增：企业户籍加密导出逻辑
const exportData = async () => {
  ElMessage.info('正在生成脱敏户籍档案...')
  const token = localStorage.getItem('saas_token')
  try {
    const res = await fetch(`http://47.120.52.65:8787/api/export/download?module=enterprises`, {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    const blob = await res.blob()
    const a = document.createElement('a')
    a.href = window.URL.createObjectURL(blob)
    a.download = `企业户籍档案_${new Date().getTime()}.csv`
    a.click()
    ElMessage.success('户籍导出成功，审计留痕已写入')
  } catch (e) { ElMessage.error('导出通讯失败') }
}

onMounted(() => { 
  fetchData() 
  if (route.query.space_id) {
    isQuickLease.value = true
    formData.space_id = Number(route.query.space_id)
    formData.dateRange = []
    formData.monthly_rent = 0
    formData.property_fee = 0
    formData.deposit = 0
    formData.water_meter = 0
    formData.electric_meter = 0
    bindSpaceInfo.value = `${route.query.b_name} - ${route.query.r_num}`
    dialogVisible.value = true
    router.replace('/enterprises')
  }
})
</script>

<style scoped>
.enterprises-container { width: 100%; }
.main-card { border-radius: 4px; box-shadow: none; }
.toolbar { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
.toolbar-left { display: flex; gap: 10px; }
.toolbar-right { display: flex; align-items: center; }
</style>