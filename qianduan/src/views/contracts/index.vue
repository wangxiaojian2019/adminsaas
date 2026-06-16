<template>
  <div class="contracts-container">
    <el-card shadow="never" class="main-card">
      <div class="toolbar">
        <el-button type="success" icon="DocumentAdd" @click="openContractDialog">起草新合同 (签约)</el-button>
        <el-button type="warning" icon="Download" @click="exportData">合同库带水印导出</el-button>
        <el-button icon="Refresh" @click="fetchContracts">刷新</el-button>
      </div>

      <div class="filter-panel">
        <div class="filter-item">
          <span class="filter-label">合同状态:</span>
          <el-select v-model="filterStatus" clearable placeholder="全部" style="width: 120px">
            <el-option label="全部" value="" />
            <el-option label="履约中" :value="1" />
            <el-option label="已退租/作废" :value="0" />
          </el-select>
        </div>
      </div>

      <el-table :data="processedTableData" v-loading="loading" border stripe style="width: 100%">
        <el-table-column prop="contract_no" label="合同编号" width="160" align="center" />
        <el-table-column prop="enterprise_name" label="承租企业" min-width="150" show-overflow-tooltip />
        <el-table-column label="承租空间" width="140" align="center">
          <template #default="{ row }">
            {{ row.building_name }} - {{ row.room_number }}
          </template>
        </el-table-column>
        <el-table-column label="起租/退租日期" width="130" align="center">
          <template #default="{ row }">
            <div style="font-size: 12px">{{ row.start_date }}</div>
            <div style="font-size: 12px; color: #909399;">至</div>
            <div style="font-size: 12px">{{ row.end_date }}</div>
          </template>
        </el-table-column>
        <el-table-column label="业财基准" width="140" align="right">
          <template #default="{ row }">
            <div style="font-weight: bold; color: #f56c6c;">租: ¥{{ row.monthly_rent }}</div>
            <div style="font-size: 12px; color: #909399;">物: ¥{{ row.property_fee }}</div>
          </template>
        </el-table-column>
        
        <el-table-column label="系统起草/录入时间" width="150" align="center">
          <template #default="{ row }">
            <div style="font-size: 12px; color: #909399;">{{ row.created_at }}</div>
          </template>
        </el-table-column>

        <el-table-column prop="vehicle_info" label="车辆备注" min-width="120" show-overflow-tooltip />
        <el-table-column label="合同状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'info'">
              {{ row.status === 1 ? '履约中' : '已失效' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="260" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link icon="Document" @click="openDocsDrawer(row)">电子档案</el-button>
            <el-button v-if="row.status === 1" type="danger" link icon="Wallet" @click="openCheckoutDrawer(row)">
              退租结算
            </el-button>
            <el-button v-if="row.status === 0" type="danger" plain link icon="RefreshLeft" @click="handleRevoke(row)">
              撤销退租
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="contractDialogVisible" title="起草新合同 (物理空间分配)" width="600px" @close="contractFormRef?.resetFields()">
      <el-form ref="contractFormRef" :model="contractForm" :rules="contractRules" label-width="120px">
        <el-form-item label="承租企业" prop="enterprise_id">
          <el-select v-model="contractForm.enterprise_id" filterable placeholder="检索已建档企业" style="width: 100%;">
            <el-option v-for="ent in enterprises" :key="ent.id" :label="ent.name" :value="ent.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="租赁物理空间" prop="space_id">
          <el-select v-model="contractForm.space_id" filterable placeholder="检索空置空间" style="width: 100%;">
            <el-option v-for="sp in availableSpaces" :key="sp.id" :label="`${sp.building_name} - ${sp.floor}F - ${sp.room_number}`" :value="sp.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="合同周期" prop="dateRange">
          <el-date-picker v-model="contractForm.dateRange" type="daterange" range-separator="至" start-placeholder="起租日" end-placeholder="到期日" value-format="YYYY-MM-DD" style="width: 100%;" />
        </el-form-item>
        
        <div style="display: flex; gap: 10px;">
          <el-form-item label="月租金(元)" prop="monthly_rent" style="flex: 1;">
            <el-input-number v-model="contractForm.monthly_rent" :min="0" style="width: 100%" controls-position="right" @change="handleRentChange" />
          </el-form-item>
          <el-form-item label="月物业费(元)" prop="property_fee" style="flex: 1;">
            <el-input-number v-model="contractForm.property_fee" :min="0" style="width: 100%" controls-position="right" />
          </el-form-item>
        </div>

        <el-divider content-position="left" style="margin: 15px 0;">业财收缴标准配置</el-divider>

        <el-form-item label="收费标准/周期" prop="payment_cycle">
          <div style="display: flex; gap: 10px; width: 100%;">
            <el-radio-group v-model="contractForm.payment_cycle" @change="handleCycleModeChange">
              <el-radio-button :label="1">月付</el-radio-button>
              <el-radio-button :label="3">季付</el-radio-button>
              <el-radio-button :label="6">半年付</el-radio-button>
              <el-radio-button :label="12">年付</el-radio-button>
              <el-radio-button :label="0">自定义</el-radio-button>
            </el-radio-group>
            <el-input-number v-if="contractForm.payment_cycle === 0 || isCustomCycle" v-model="customCycleValue" :min="1" :max="60" placeholder="月数" controls-position="right" style="width: 100px" @change="syncCustomCycle" />
          </div>
        </el-form-item>
        
        <el-form-item label="履约押金(元)" prop="deposit">
          <el-input-number v-model="contractForm.deposit" :min="0" style="width: 100%" controls-position="right" />
        </el-form-item>

        <el-form-item label="车辆及车位备注" prop="vehicle_info">
          <el-input v-model="contractForm.vehicle_info" type="textarea" :rows="2" placeholder="例如：赠送2个地下车位，京A88888" />
        </el-form-item>

        <div class="bill-preview">
          <div class="preview-title"><el-icon><Money /></el-icon> 首期应收账单沙盘演算</div>
          <div class="preview-content">
            <span class="formula">押金 (¥{{ contractForm.deposit || 0 }}) + [租金+物业] × {{ actualPaymentCycle }} 个月</span>
            <span class="total">¥ {{ firstBillTotal }}</span>
          </div>
        </div>

      </el-form>
      <template #footer>
        <el-button @click="contractDialogVisible = false">取消</el-button>
        <el-button type="success" :loading="submitLoading" @click="submitContract">确认签约并锁房</el-button>
      </template>
    </el-dialog>

    <el-drawer v-model="checkoutDrawerVisible" :title="`退租结算核算：${currentContract.contract_no}`" size="500px">
      <div class="checkout-container">
        <el-alert title="合同作废不可逆。退租后，物理房间将立即释放为【空置可租】状态，并自动生成财务打款单。" type="error" show-icon :closable="false" style="margin-bottom: 20px;" />
        
        <el-form ref="checkoutFormRef" :model="checkoutForm" label-position="top">
          <el-row :gutter="20">
            <el-col :span="12">
              <el-form-item label="该合同原始已缴押金(元)">
                <el-input v-model="checkoutForm.refund_deposit" disabled />
              </el-form-item>
            </el-col>
          </el-row>

          <el-divider content-position="left">退租罚没与损耗核算</el-divider>

          <el-row :gutter="20">
            <el-col :span="12">
              <el-form-item label="扣除违约租金/水电(元)">
                <el-input-number v-model="checkoutForm.deduct_rent" :min="0" style="width: 100%;" controls-position="right" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="扣除物损破坏费用(元)">
                <el-input-number v-model="checkoutForm.deduct_damage" :min="0" style="width: 100%;" controls-position="right" />
              </el-form-item>
            </el-col>
          </el-row>
          
          <el-form-item label="清算/扣款原因及备注说明">
            <el-input v-model="checkoutForm.remark" type="textarea" :rows="3" placeholder="请详述扣款原因，例如：提前半个月退租扣除租金、房间墙面破坏等，以便财务备查" />
          </el-form-item>

          <el-divider border-style="dashed" />

          <div class="checkout-result">
            <div class="result-label">最终财务应退款总计：</div>
            <div class="result-amount" :class="{ 'text-danger': actualRefund < 0 }">
              ¥ {{ actualRefund }}
            </div>
            <div v-if="actualRefund < 0" style="font-size: 12px; color: #f56c6c; margin-top: 5px;">
              * 扣除金额已超出原始押金，需向企业追缴欠款。
            </div>
          </div>

          <el-button type="danger" size="large" class="checkout-submit-btn" :loading="submitLoading" @click="submitCheckout">
            确认清算数据，作废合同并释放空间
          </el-button>
        </el-form>
      </div>
    </el-drawer>

    <el-drawer v-model="docsDrawerVisible" :title="`电子合同档案：${currentContract.contract_no}`" size="500px">
      <div class="docs-container" v-loading="docsLoading">
        <el-alert title="合同归档与电子签生成中心" type="info" show-icon :closable="false" style="margin-bottom: 20px;" />
        <div class="doc-actions">
          <el-button type="primary" icon="Printer" @click="generateElecDoc">系统生成标准制式合同</el-button>
          <el-upload class="upload-demo" action="http://47.120.52.65:8787/api/upload" :headers="uploadHeaders" :data="{ contract_id: currentContract.id }" :show-file-list="false" :on-success="handleUploadSuccess" :on-error="handleUploadError" :before-upload="beforeUpload">
            <el-button type="success" icon="Upload">上传盖章扫描件</el-button>
          </el-upload>
        </div>
        <el-divider content-position="left">已归档附件</el-divider>
        <ul class="doc-list">
          <li class="doc-item">
            <div class="doc-info">
              <el-icon class="doc-icon"><Document /></el-icon>
              <div class="doc-name-time">
                <span class="doc-name">电子制式合同</span>
                <span v-if="currentDocs.elec_contract_url" class="doc-audit-time">生成于: {{ currentDocs.updated_at }}</span>
              </div>
            </div>
            <div class="doc-ctrl">
              <el-button v-if="currentDocs.elec_contract_url" type="primary" link @click="previewDoc(currentDocs.elec_contract_url)">预览/下载</el-button>
              <span v-else style="font-size: 12px; color: #909399;">未生成</span>
            </div>
          </li>
          <li class="doc-item">
            <div class="doc-info">
              <el-icon class="doc-icon"><Picture /></el-icon>
              <div class="doc-name-time">
                <span class="doc-name">线下扫描归档件</span>
                <span v-if="currentDocs.paper_contract_url" class="doc-audit-time">上传于: {{ currentDocs.updated_at }}</span>
              </div>
            </div>
            <div class="doc-ctrl">
              <el-button v-if="currentDocs.paper_contract_url" type="primary" link @click="previewDoc(currentDocs.paper_contract_url)">预览/下载</el-button>
              <span v-else style="font-size: 12px; color: #909399;">未上传</span>
            </div>
          </li>
        </ul>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import request from '../../utils/request'

const tableData = ref([])
const loading = ref(false)
const filterStatus = ref(null)

const enterprises = ref([])
const availableSpaces = ref([])

const contractDialogVisible = ref(false)
const submitLoading = ref(false)
const contractFormRef = ref(null)

const contractForm = reactive({ 
  enterprise_id: '', 
  space_id: '', 
  dateRange: [], 
  monthly_rent: 0, 
  property_fee: 0, 
  payment_cycle: 3, 
  vehicle_info: '', 
  deposit: 0 
})

const isCustomCycle = ref(false)
const customCycleValue = ref(1)

const contractRules = {
  enterprise_id: [{ required: true, message: '请选择企业', trigger: 'change' }],
  space_id: [{ required: true, message: '请分配空间', trigger: 'change' }],
  dateRange: [{ required: true, message: '请设定周期', trigger: 'change' }],
  monthly_rent: [{ required: true, message: '请输入租金', trigger: 'blur' }]
}

const docsDrawerVisible = ref(false)
const docsLoading = ref(false)
const currentContract = ref({})
const currentDocs = ref({ elec_contract_url: null, paper_contract_url: null, updated_at: null })
const uploadHeaders = computed(() => ({ 'Authorization': `Bearer ${localStorage.getItem('saas_token')}` }))

const checkoutDrawerVisible = ref(false)
const checkoutFormRef = ref(null)
const checkoutForm = reactive({ refund_deposit: 0, deduct_rent: 0, deduct_damage: 0, remark: '' })

const handleRentChange = (val) => {
  if (contractForm.deposit === 0 || contractForm.deposit === '') {
    contractForm.deposit = val
  }
}

const handleCycleModeChange = (val) => {
  if (val === 0) {
    isCustomCycle.value = true
  } else {
    isCustomCycle.value = false
  }
}

const syncCustomCycle = (val) => {}

const actualPaymentCycle = computed(() => {
  return contractForm.payment_cycle === 0 ? customCycleValue.value : contractForm.payment_cycle
})

const firstBillTotal = computed(() => {
  const rent = Number(contractForm.monthly_rent) || 0
  const prop = Number(contractForm.property_fee) || 0
  const dep = Number(contractForm.deposit) || 0
  const cycle = actualPaymentCycle.value
  return (dep + (rent + prop) * cycle).toFixed(2)
})

const actualRefund = computed(() => {
  const deposit = Number(checkoutForm.refund_deposit) || 0
  const rent = Number(checkoutForm.deduct_rent) || 0
  const damage = Number(checkoutForm.deduct_damage) || 0
  return (deposit - rent - damage).toFixed(2)
})

const processedTableData = computed(() => {
  return tableData.value.filter(row => {
    if (typeof filterStatus.value === 'number' && row.status !== filterStatus.value) return false
    return true
  })
})

const fetchContracts = async () => {
  loading.value = true
  try {
    const res = await request.get('/api/contracts/list')
    if (res.code === 200) tableData.value = res.data
  } finally { loading.value = false }
}

const openContractDialog = async () => {
  contractDialogVisible.value = true
  isCustomCycle.value = false
  contractForm.payment_cycle = 3
  
  const entRes = await request.get('/api/enterprises/list')
  if (entRes.code === 200) enterprises.value = entRes.data
  const spRes = await request.get('/api/spaces/list')
  if (spRes.code === 200) availableSpaces.value = spRes.data.filter(s => s.status === 0)
}

const submitContract = () => {
  contractFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    try {
      const payload = { 
        ...contractForm, 
        start_date: contractForm.dateRange[0], 
        end_date: contractForm.dateRange[1],
        payment_cycle: actualPaymentCycle.value 
      }
      const res = await request.post('/api/contracts/add', payload)
      if (res.code === 200) {
        ElMessage.success('合同起草成功，房间已锁定')
        contractDialogVisible.value = false
        fetchContracts()
      } else { ElMessage.error(res.msg || '操作失败') }
    } finally { submitLoading.value = false }
  })
}

const openCheckoutDrawer = (row) => {
  currentContract.value = row
  checkoutForm.refund_deposit = row.deposit || 0
  checkoutForm.deduct_rent = 0
  checkoutForm.deduct_damage = 0
  checkoutForm.remark = ''
  checkoutDrawerVisible.value = true
}

const submitCheckout = () => {
  ElMessageBox.confirm(
    `清算后，实体空间 ${currentContract.value.building_name}-${currentContract.value.room_number} 将被释放供其他企业租赁，是否确认？`,
    '最后警告',
    { confirmButtonText: '确认清算', cancelButtonText: '取消', type: 'warning' }
  ).then(async () => {
    submitLoading.value = true
    try {
      const payload = {
        id: currentContract.value.id,
        refund_deposit: checkoutForm.refund_deposit,
        deduct_rent: checkoutForm.deduct_rent,
        deduct_damage: checkoutForm.deduct_damage,
        actual_refund: actualRefund.value,
        remark: checkoutForm.remark
      }
      const res = await request.post('/api/contracts/terminate', payload)
      if (res.code === 200) {
        ElMessage.success('退租完毕！清算账单已提交至财务流。')
        checkoutDrawerVisible.value = false
        fetchContracts()
      } else {
        ElMessage.error(res.msg || '退租处理异常')
      }
    } finally { submitLoading.value = false }
  }).catch(() => {})
}

const handleRevoke = (row) => {
  ElMessageBox.confirm(
    `发现误操作？确认要撤销【${row.contract_no}】的退租吗？如果财务尚未结清，系统将彻底销毁退款单，并将该合同与空间强制恢复至“履约中”状态。`,
    '启动状态机回滚',
    { confirmButtonText: '确认撤销回滚', cancelButtonText: '取消', type: 'error' }
  ).then(async () => {
    try {
      const res = await request.post('/api/contracts/revoke_terminate', { contract_id: row.id })
      if (res.code === 200) {
        ElMessage.success(res.msg)
        fetchContracts()
      } else {
        ElMessage.error(res.msg)
      }
    } catch (e) {
      ElMessage.error('回滚通讯失败')
    }
  }).catch(() => {})
}

const exportData = async () => {
  const token = localStorage.getItem('saas_token')
  const res = await fetch(`http://47.120.52.65:8787/api/export/download?module=contracts`, { headers: { 'Authorization': `Bearer ${token}` } })
  const blob = await res.blob()
  const a = document.createElement('a'); a.href = window.URL.createObjectURL(blob); a.download = `租务合同台账.csv`; a.click()
}
const openDocsDrawer = (row) => { currentContract.value = row; docsDrawerVisible.value = true; fetchDocs(row.id) }
const fetchDocs = async (id) => {
  docsLoading.value = true
  const res = await request.get('/api/contracts/docs', { params: { contract_id: id } })
  if (res.code === 200) currentDocs.value = res.data || { elec_contract_url: null, paper_contract_url: null, updated_at: null }
  docsLoading.value = false
}
const generateElecDoc = async () => {
  docsLoading.value = true
  const res = await request.post('/api/contracts/generate_elec', { contract_id: currentContract.value.id })
  if (res.code === 200) { ElMessage.success('系统文书生成成功'); fetchDocs(currentContract.value.id) }
  docsLoading.value = false
}
const beforeUpload = (file) => file.size / 1024 / 1024 < 5
const handleUploadSuccess = async (res) => { if (res.code === 200) { ElMessage.success('上传成功'); fetchDocs(currentContract.value.id) } }
const handleUploadError = () => ElMessage.error('上传异常')
const previewDoc = (url) => window.open(`http://47.120.52.65:8787${url}`, '_blank')

onMounted(() => { fetchContracts() })
</script>

<style scoped>
.contracts-container { width: 100%; }
.main-card { border-radius: 4px; box-shadow: none; }
.toolbar { margin-bottom: 20px; display: flex; gap: 10px; }
.filter-panel { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; padding: 12px 15px; background-color: #f8f9fa; border-radius: 6px; border: 1px solid #eef1f6; }
.filter-item { display: flex; align-items: center; gap: 8px; }
.filter-label { font-size: 13px; color: #606266; font-weight: bold; }

.checkout-container { padding: 0 10px 20px 10px; }
.checkout-result { background-color: #f4f4f5; padding: 20px; border-radius: 8px; margin-bottom: 30px; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 1px dashed #dcdfe6; }
.result-label { font-size: 14px; color: #606266; margin-bottom: 10px; }
.result-amount { font-size: 32px; font-weight: bold; color: #67c23a; font-family: monospace; }
.text-danger { color: #f56c6c; }
.checkout-submit-btn { width: 100%; letter-spacing: 1px; font-weight: bold; }

.docs-container { padding: 0 10px; }
.doc-actions { display: flex; gap: 10px; margin-bottom: 20px; }
.upload-demo { display: inline-block; }
.doc-list { list-style: none; padding: 0; margin: 0; }
.doc-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 1px solid #ebeef5; border-radius: 4px; margin-bottom: 10px; background-color: #fafafa; transition: all 0.3s; }
.doc-item:hover { background-color: #f0f7ff; border-color: #c6e2ff; }
.doc-info { display: flex; align-items: center; gap: 10px; }
.doc-icon { font-size: 24px; color: #909399; }
.doc-name-time { display: flex; flex-direction: column; }
.doc-name { font-size: 14px; color: #303133; }
.doc-audit-time { font-size: 11px; color: #a8abb2; margin-top: 2px; font-family: monospace; }

.bill-preview { background-color: #fdf6ec; border: 1px solid #faecd8; padding: 12px 20px; border-radius: 4px; margin-bottom: 20px; }
.preview-title { font-size: 13px; color: #e6a23c; font-weight: bold; margin-bottom: 8px; display: flex; align-items: center; gap: 5px; }
.preview-content { display: flex; justify-content: space-between; align-items: center; }
.formula { font-size: 12px; color: #909399; font-family: monospace; }
.total { font-size: 24px; font-weight: bold; color: #f56c6c; font-family: monospace; }
</style>