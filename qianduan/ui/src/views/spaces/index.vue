<template>
  <div class="spaces-container">
    <el-card shadow="never" class="main-card">
      <div class="toolbar">
        <div class="toolbar-left">
          <el-select v-model="filterBuilding" clearable placeholder="按大厦筛选" style="width: 160px">
            <el-option v-for="b in buildings" :key="b" :label="b" :value="b" />
          </el-select>
          <el-select v-model="filterStatus" clearable placeholder="按状态筛选" style="width: 130px; margin-left: 10px;">
            <el-option label="空置可租" :value="0" />
            <el-option label="在租运营" :value="1" />
            <el-option label="设备维修" :value="2" />
            <el-option label="装修施工" :value="3" />
          </el-select>
          <el-button type="warning" icon="Download" style="margin-left: 10px;" @click="exportData">带水印导出资产</el-button>
          <el-button icon="Refresh" @click="fetchSpaces">刷新</el-button>
        </div>
      </div>

      <el-table :data="processedTableData" v-loading="loading" border stripe style="width: 100%">
        <el-table-column prop="id" label="空间ID" width="90" align="center" />
        <el-table-column prop="building_name" label="所属大厦/项目" min-width="150" />
        <el-table-column prop="floor" label="物理楼层" width="100" align="center">
          <template #default="{ row }">{{ row.floor }} F</template>
        </el-table-column>
        <el-table-column prop="room_number" label="房间编号" width="120" align="center">
          <template #default="{ row }"><strong style="color: #303133;">{{ row.room_number }}</strong></template>
        </el-table-column>
        <el-table-column prop="area" label="建筑面积 (㎡)" width="130" align="right">
          <template #default="{ row }">{{ row.area }} ㎡</template>
        </el-table-column>
        
        <el-table-column label="当前承租企业" min-width="180">
          <template #default="{ row }">
            <el-button
              v-if="row.enterprise_name"
              type="primary"
              link
              style="font-weight: bold; font-size: 14px; text-decoration: underline; text-underline-offset: 4px;"
              @click="jumpToDetail(row)"
            >
              {{ row.enterprise_name }}
            </el-button>
            <span v-else style="color: #909399; font-style: italic;">暂无归属</span>
          </template>
        </el-table-column>

        <el-table-column label="资产状态" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" effect="dark">{{ getStatusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        
        <el-table-column label="操作" width="140" align="center" fixed="right">
          <template #default="{ row }">
            <el-button v-if="row.status !== 1" type="primary" link icon="Switch" @click="openStatusDialog(row)">工况流转</el-button>
            <el-tooltip v-else content="在租房源需通过【合同中心】的退租流程进行释放" placement="top">
               <el-button type="info" link icon="Lock" disabled>受约锁定</el-button>
            </el-tooltip>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="isDetailMode" fullscreen :show-close="false" destroy-on-close class="fullscreen-detail">
      <template #header>
        <div class="detail-header">
          <el-button @click="isDetailMode = false" type="info" plain>← 返回资产大盘</el-button>
          <span class="detail-title">《{{ currentRow.enterprise_name }}》 业财与户籍综合档案</span>
        </div>
      </template>
      
      <div class="detail-body">
        <div class="info-section">
          <div class="section-title">
            <span>🏢 企业户籍联络网</span>
            <div class="action-btn">
              <el-button v-if="!isEditing" type="primary" link icon="Edit" @click="startEdit">快捷编辑档案</el-button>
              <div v-else>
                <el-button type="info" link @click="cancelEdit">取消更改</el-button>
                <el-button type="success" link icon="Check" :loading="saveLoading" @click="saveEnterpriseInfo">保存更新</el-button>
              </div>
            </div>
          </div>
          <div class="info-grid">
            <div class="info-item">
              <span class="label">法人/联络人：</span>
              <span v-if="!isEditing" class="value">{{ currentRow.contact_person || '未录入' }}</span>
              <el-input v-else v-model="editForm.contact_person" size="small" style="width: 180px;" placeholder="录入联络人姓名" />
            </div>
            <div class="info-item">
              <span class="label">联系电话：</span>
              <span v-if="!isEditing" class="value text-primary">{{ currentRow.phone || '未录入' }}</span>
              <el-input v-else v-model="editForm.phone" size="small" style="width: 180px;" placeholder="录入联系电话" />
            </div>
            <div class="info-item">
              <span class="label">所属产业：</span>
              <span v-if="!isEditing" class="value">{{ currentRow.industry || '未分配' }}</span>
              <el-input v-else v-model="editForm.industry" size="small" style="width: 180px;" placeholder="例：互联网/电商" />
            </div>
            <div class="info-item">
              <span class="label">承租空间：</span>
              <span class="value font-bold">{{ currentRow.building_name }} - {{ currentRow.room_number }}</span>
            </div>
          </div>
        </div>

        <div class="info-section mt-20">
          <div class="section-title">
            <span>
              📑 现行契约与资费核算矩阵
              <el-tag size="small" type="danger" effect="plain" style="margin-left: 10px; font-weight: normal;">强审计物理锁定</el-tag>
            </span>
            <el-button type="warning" link icon="Document" @click="goToContracts">发起合同变更 / 续签流程</el-button>
          </div>
          <div class="info-grid disabled-zone">
            <div class="info-item"><span class="label">合同公文号：</span><span class="value text-code">{{ currentRow.contract_no }}</span></div>
            <div class="info-item"><span class="label">履约周期：</span><span class="value">{{ currentRow.start_date }} 至 {{ currentRow.end_date }}</span></div>
            <div class="info-item"><span class="label">月度场地租金：</span><span class="value text-danger font-bold">￥{{ currentRow.monthly_rent }}</span></div>
            <div class="info-item"><span class="label">月度后勤物业：</span><span class="value">￥{{ currentRow.property_fee }}</span></div>
            <div class="info-item full-width mt-10">
              <div class="price-highlight">
                <span class="label">核心算法：日均摊核算单价</span>
                <span class="value text-warning">￥{{ currentRow.unit_price }}</span> 
                <span class="unit">(元/㎡/天)</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </el-dialog>

    <el-dialog v-model="statusDialogVisible" title="房源工况快捷流转" width="400px" append-to-body>
      <el-form label-position="top">
        <el-form-item label="变更物理工况状态">
          <el-select v-model="currentStatusForm.status" style="width: 100%;">
            <el-option label="空置可租" :value="0" />
            <el-option label="设备维修" :value="2" />
            <el-option label="装修施工" :value="3" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="statusDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitStatusChange">确认生效</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import request from '../../utils/request'

const router = useRouter()
const tableData = ref([])
const loading = ref(false)
const filterBuilding = ref(null)
const filterStatus = ref(null)

const statusDialogVisible = ref(false)
const submitLoading = ref(false)
const currentStatusForm = reactive({ id: '', status: 0 })

const isDetailMode = ref(false)
const currentRow = ref({})

// 动态编辑态引擎
const isEditing = ref(false)
const saveLoading = ref(false)
const editForm = reactive({ contact_person: '', phone: '', industry: '' })

const buildings = computed(() => {
  const set = new Set(tableData.value.map(item => item.building_name))
  return Array.from(set)
})

const processedTableData = computed(() => {
  return tableData.value.filter(row => {
    if (filterBuilding.value && row.building_name !== filterBuilding.value) return false
    if (typeof filterStatus.value === 'number' && row.status !== filterStatus.value) return false
    return true
  })
})

const fetchSpaces = async () => {
  loading.value = true
  try {
    const res = await request.get('/api/spaces/list')
    if (res.code === 200) tableData.value = res.data
  } finally { loading.value = false }
}

const openStatusDialog = (row) => {
  currentStatusForm.id = row.id
  currentStatusForm.status = row.status
  statusDialogVisible.value = true
}

const submitStatusChange = async () => {
  submitLoading.value = true
  try {
    const res = await request.post('/api/spaces/status', currentStatusForm)
    if (res.code === 200) {
      ElMessage.success('工况流转成功')
      statusDialogVisible.value = false
      fetchSpaces() 
    }
  } finally { submitLoading.value = false }
}

const jumpToDetail = (row) => {
  currentRow.value = row
  isEditing.value = false // 每次切入视图强制重置为阅读模式
  isDetailMode.value = true
}

// 激活绿灯区快捷编辑模式
const startEdit = () => {
  editForm.contact_person = currentRow.value.contact_person
  editForm.phone = currentRow.value.phone
  editForm.industry = currentRow.value.industry
  isEditing.value = true
}

const cancelEdit = () => {
  isEditing.value = false
}

// 提交局部越权保存指令
const saveEnterpriseInfo = async () => {
  saveLoading.value = true
  try {
    const res = await request.post('/api/spaces/update', {
      id: currentRow.value.id,
      is_enterprise_update: 1, // 发送核心隐蔽越权标识
      contact_person: editForm.contact_person,
      phone: editForm.phone,
      industry: editForm.industry
    })
    if (res.code === 200) {
      ElMessage.success(res.msg)
      // Vue 原生双向绑定，静默直接替换表格深层数据，拒绝全局闪烁
      currentRow.value.contact_person = editForm.contact_person
      currentRow.value.phone = editForm.phone
      currentRow.value.industry = editForm.industry
      isEditing.value = false
    } else {
      ElMessage.error(res.msg || '保存失败')
    }
  } catch (e) {
    ElMessage.error('网络通讯异常')
  } finally {
    saveLoading.value = false
  }
}

// 红灯区引导路由分流
const goToContracts = () => {
  isDetailMode.value = false
  router.push({ path: '/contracts', query: { search_name: currentRow.value.enterprise_name } })
}

const exportData = async () => {
  ElMessage.info('正在拉取房源资产离线加密档案...')
  const token = localStorage.getItem('saas_token')
  try {
    const res = await fetch('http://47.120.52.65:8787/api/export/download?module=spaces', {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    if (res.status === 200) {
      const blob = await res.blob()
      const a = document.createElement('a')
      a.href = window.URL.createObjectURL(blob)
      a.download = `房源房产数据台账_${new Date().getTime()}.csv`
      a.click()
      ElMessage.success('安全审计账册外发下载成功')
    }
  } catch (e) { ElMessage.error('导出失败') }
}

const getStatusLabel = (status) => ({ 0: '空置可租', 1: '在租运营', 2: '设备维修', 3: '装修施工' }[status] || '未知')
const getStatusType = (status) => ({ 0: 'success', 1: 'danger', 2: 'warning', 3: 'info' }[status] || 'info')

onMounted(fetchSpaces)
</script>

<style scoped>
.spaces-container { width: 100%; min-height: 500px;}
.main-card { border-radius: 4px; box-shadow: none; }
.toolbar { margin-bottom: 20px; display: flex; gap: 10px; }

:deep(.fullscreen-detail .el-dialog__header) {
  margin-right: 0;
  border-bottom: 1px solid #ebeef5;
  padding-bottom: 20px;
  background-color: #f8f9fa;
}
.detail-header { display: flex; align-items: center; gap: 20px; }
.detail-title { font-size: 18px; font-weight: bold; color: #303133; }
.detail-body { padding: 10px 20px 30px; max-width: 1000px; margin: 0 auto; }

.section-title { 
  display: flex; 
  justify-content: space-between; 
  align-items: center; 
  font-size: 16px; 
  font-weight: bold; 
  color: #303133; 
  margin-bottom: 15px; 
  border-bottom: 1px solid #ebeef5; 
  padding-bottom: 10px; 
}
.action-btn { font-weight: normal; font-size: 14px; }
.mt-20 { margin-top: 30px; }
.mt-10 { margin-top: 15px; }

.info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; align-items: center; }
.info-item { display: flex; align-items: center; font-size: 14px; min-height: 32px; }
.info-item.full-width { grid-column: span 2; }
.info-item .label { color: #909399; width: 130px; flex-shrink: 0; }
.info-item .value { color: #303133; }

/* 财务锁定视觉隔离区 */
.disabled-zone { background-color: #fafafa; padding: 15px; border-radius: 8px; border: 1px dashed #e4e7ed; pointer-events: none; }

.font-bold { font-weight: bold; }
.text-primary { color: #409eff; }
.text-danger { color: #f56c6c; }
.text-warning { color: #e6a23c; }
.text-code { font-family: monospace; font-weight: bold; background: #f4f4f5; padding: 2px 6px; border-radius: 4px; }

.price-highlight { background: #fdf6ec; padding: 15px 20px; border-radius: 6px; display: flex; align-items: baseline; border: 1px dashed #f3d19e; }
.price-highlight .label { color: #e6a23c; margin-right: 15px; width: auto; font-weight: bold;}
.price-highlight .value { font-size: 24px; font-weight: bold; margin-right: 5px; }
.price-highlight .unit { font-size: 13px; color: #909399; }
</style>