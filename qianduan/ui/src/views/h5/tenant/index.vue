<template>
  <div class="tenant-container">
    <div class="tenant-header">
      <div class="user-greeting">
        <h3>{{ userInfo.enterprise_name || '入驻企业' }}</h3>
        <p><el-icon><User /></el-icon> 联络人: {{ userInfo.contact_person }} ({{ userInfo.phone }})</p>
      </div>
      <el-button type="info" plain size="small" @click="handleLogout" style="background: rgba(255,255,255,0.2); color:#fff; border:none;">退出</el-button>
    </div>

    <div class="stats-panel" v-loading="loading">
      <div class="stat-box"><div class="stat-num">{{ overview.contract_count }}</div><div class="stat-label">生效合同(份)</div></div>
      <div class="stat-box"><div class="stat-num text-danger">{{ overview.unpaid_count }}</div><div class="stat-label">待缴账单(笔)</div></div>
      <div class="stat-box"><div class="stat-num text-danger" style="font-size: 18px;">¥{{ overview.unpaid_amount }}</div><div class="stat-label">待缴总额</div></div>
    </div>

    <div class="action-grid">
      <div class="action-btn" @click="openOrderDialog">
        <div class="icon-wrap" style="background-color: #fdf6ec; color: #e6a23c;"><el-icon><WarnTriangleFilled /></el-icon></div><span>物业在线报修</span>
      </div>
      <div class="action-btn" @click="refreshAllData">
        <div class="icon-wrap" style="background-color: #ecf5ff; color: #409eff;"><el-icon><Refresh /></el-icon></div><span>刷新综合账册</span>
      </div>
    </div>

    <div class="bill-list-section" style="margin-bottom: 25px;">
      <div class="section-title"><el-icon><Document /></el-icon> 生效中的合同条约</div>
      <el-empty v-if="contracts.length === 0" description="暂无履约中的合同" :image-size="60" />
      <div v-for="c in contracts" :key="c.id" class="contract-card">
        <div class="c-no">编号: {{ c.contract_no }}</div>
        <div class="c-body">
          <p><strong>承租单元：</strong>{{ c.building_name }} - {{ c.floor }}F - {{ c.room_number }}</p>
          <p><strong>核心月金：</strong><span style="color:#e6a23c; font-weight:bold;">¥{{ c.monthly_rent }}/月</span> (物业费: ¥{{ c.property_fee }})</p>
          <p class="c-time">周期: {{ c.start_date }} 至 {{ c.end_date }}</p>
        </div>
      </div>
    </div>

    <div class="bill-list-section">
      <div class="section-title"><el-icon><Money /></el-icon> 园区应收缴费单据</div>
      <el-empty v-if="bills.length === 0" description="暂无历史账单数据" :image-size="60" />
      <div v-for="bill in bills" :key="bill.id" class="bill-card">
        <div class="bill-header">
          <span class="bill-type"><el-tag :type="getBillTypeColor(bill.bill_type)" size="small">{{ getBillTypeLabel(bill.bill_type) }}</el-tag></span>
          
          <span class="bill-status" :class="getBillStatusClass(bill.is_paid)">
            {{ getBillStatusLabel(bill.is_paid) }}
          </span>
        </div>
        <div class="bill-body">
          <div class="bill-amount">¥ {{ bill.amount }}</div>
          <div class="bill-info">
            <p><strong>关联单元：</strong>{{ bill.building_name }} - {{ bill.room_number || '综合车位' }}</p>
            <p><strong>最晚缴费：</strong>{{ bill.due_date }}</p>
            <p v-if="bill.is_paid === 1"><strong>核销时间：</strong>{{ bill.paid_time }}</p>
          </div>
        </div>
        <div v-if="bill.is_paid === 0" class="bill-footer">
          <el-button type="primary" class="pay-btn" @click="openCashier(bill)">立即结清账单</el-button>
        </div>
      </div>
    </div>

    <el-dialog v-model="orderDialogVisible" title="提交物业报修单" width="92%" custom-class="mobile-dialog">
      <el-form ref="orderFormRef" :model="orderForm" :rules="orderRules" label-position="top">
        <el-form-item label="服务类型与主题" prop="title"><el-input v-model="orderForm.title" placeholder="例如：A座501空调漏水" /></el-form-item>
        <el-form-item label="详细情况描述" prop="description"><el-input v-model="orderForm.description" type="textarea" :rows="3" placeholder="请详细描述故障情况..." /></el-form-item>
        <el-form-item label="现场照片附证">
          <el-upload action="/api/upload" :headers="uploadHeaders" list-type="picture-card" :limit="1" :on-success="handleUploadSuccess" :on-remove="handleUploadRemove">
            <el-icon><Plus /></el-icon>
          </el-upload>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="orderDialogVisible = false" style="width: 46%;">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitOrder" style="width: 46%;">确认提交</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="cashierVisible" title="园区安全收银台" width="92%" custom-class="mobile-dialog" @close="resetCashier">
      <div class="cashier-amount-box">
        <div class="cashier-label">支付金额</div>
        <div class="cashier-amount">¥ {{ currentPayBill.amount }}</div>
      </div>
      
      <div class="payment-methods">
        <div class="method-title">请选择支付方式</div>
        <el-radio-group v-model="payMethod" class="method-group">
          <el-radio label="wechat" class="method-radio">
            <div class="method-content"><span style="color: #07c160; font-weight: bold;">微信支付</span></div>
          </el-radio>
          <el-radio label="alipay" class="method-radio">
            <div class="method-content"><span style="color: #1677ff; font-weight: bold;">支付宝</span></div>
          </el-radio>
          <el-radio label="unionpay" class="method-radio">
            <div class="method-content"><span style="color: #e61d24; font-weight: bold;">云闪付</span></div>
          </el-radio>
          <el-radio label="transfer" class="method-radio">
            <div class="method-content"><span style="color: #606266; font-weight: bold;">对公银行转账</span></div>
          </el-radio>
        </el-radio-group>
      </div>

      <div v-if="payMethod === 'transfer'" class="receipt-upload-box">
        <div class="receipt-tips">请向园区对公账户打款，并在此上传电子回执单或水单截图，财务将在1-3个工作日内审核。</div>
        <el-upload
          class="receipt-uploader"
          action="/api/upload"
          :headers="uploadHeaders"
          :show-file-list="false"
          :on-success="handleReceiptSuccess"
        >
          <img v-if="receiptUrl" :src="receiptUrl" class="receipt-preview" />
          <el-icon v-else class="receipt-uploader-icon"><Camera /></el-icon>
        </el-upload>
      </div>

      <template #footer>
        <el-button @click="cashierVisible = false" style="width: 46%;">稍后支付</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitPayment" style="width: 46%;">确认提交付款</el-button>
      </template>
    </el-dialog>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { User, WarnTriangleFilled, Refresh, Document, Money, Plus, Camera } from '@element-plus/icons-vue'
import request from '../../../utils/request'

const router = useRouter()
const userInfo = ref({})
const loading = ref(false)
const overview = ref({ contract_count: 0, unpaid_count: 0, unpaid_amount: '0.00' })
const bills = ref([])
const contracts = ref([])

// 工单状态库
const orderDialogVisible = ref(false)
const submitLoading = ref(false)
const orderFormRef = ref(null)
const orderForm = reactive({ title: '', description: '', attachment_url: '' })
const orderRules = { title: [{ required: true, message: '请填写报修主题', trigger: 'blur' }] }
const uploadHeaders = computed(() => ({ 'Authorization': `Bearer ${localStorage.getItem('h5_tenant_token')}` }))

// 收银台状态库
const cashierVisible = ref(false)
const currentPayBill = ref({})
const payMethod = ref('wechat')
const receiptUrl = ref('')

const refreshAllData = async () => {
  loading.value = true
  try {
    const [ovRes, billRes, ctRes] = await Promise.all([request.get('/api/tenant/overview'), request.get('/api/tenant/bills'), request.get('/api/tenant/contracts')])
    if (ovRes.code === 200) overview.value = ovRes.data
    if (billRes.code === 200) bills.value = billRes.data
    if (ctRes.code === 200) contracts.value = ctRes.data
  } finally { loading.value = false }
}

const openOrderDialog = () => {
  if (orderFormRef.value) orderFormRef.value.resetFields()
  orderForm.title = ''; orderForm.description = ''; orderForm.attachment_url = ''
  orderDialogVisible.value = true
}

const handleUploadSuccess = (res) => {
  if (res.code === 200) { orderForm.attachment_url = res.data.url; ElMessage.success('故障照片上传成功') }
}
const handleUploadRemove = () => { orderForm.attachment_url = '' }

const submitOrder = () => {
  orderFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    try {
      const res = await request.post('/api/tenant/order/submit', { ...orderForm, contact_person: userInfo.value.contact_person })
      if (res.code === 200) { ElMessage.success('工单已指派至中控室'); orderDialogVisible.value = false }
    } finally { submitLoading.value = false }
  })
}

// 唤起收银台控制器
const openCashier = (bill) => {
  currentPayBill.value = bill
  resetCashier()
  cashierVisible.value = true
}

const resetCashier = () => {
  payMethod.value = 'wechat'
  receiptUrl.value = ''
}

const handleReceiptSuccess = (res) => {
  if (res.code === 200) { 
    receiptUrl.value = res.data.url; 
    ElMessage.success('打款电子凭证上传成功') 
  }
}

// 执行安全支付提交
const submitPayment = async () => {
  if (payMethod.value === 'transfer' && !receiptUrl.value) {
    ElMessage.warning('对公转账必须上传打款电子回执单截图')
    return
  }
  
  submitLoading.value = true
  try {
    const res = await request.post('/api/tenant/pay', { 
      id: currentPayBill.value.id,
      payment_method: payMethod.value,
      receipt_url: receiptUrl.value
    })
    
    if (res.code === 200) {
      if (payMethod.value === 'transfer') {
        ElMessage.success('凭证已提交，请等待财务进行人工核销确认')
      } else {
        ElMessage.success('线上支付流水处理成功，账款已结清')
      }
      cashierVisible.value = false
      refreshAllData() 
    }
  } finally {
    submitLoading.value = false
  }
}

const handleLogout = () => { localStorage.removeItem('h5_tenant_token'); localStorage.removeItem('h5_tenant_user'); router.push('/h5/tenant/login') }

// 枚举字典
const getBillTypeLabel = (type) => ({ 1: '场地租金', 2: '水费账单', 3: '电费账单', 4: '物业/车位费', 5: '违约滞纳金' }[type] || '其他费用')
const getBillTypeColor = (type) => ({ 1: 'primary', 2: 'info', 3: 'warning', 4: 'success', 5: 'danger' }[type] || 'info')

// 状态字典解析
const getBillStatusLabel = (status) => ({ 0: '待缴费', 1: '已结清', 2: '财务核销中' }[status] || '未知')
const getBillStatusClass = (status) => ({ 0: 'text-danger', 1: 'text-success', 2: 'text-warning' }[status] || '')

onMounted(() => {
  const storedUser = localStorage.getItem('h5_tenant_user')
  if (storedUser) { userInfo.value = JSON.parse(storedUser); refreshAllData() } 
  else { router.push('/h5/tenant/login') }
})
</script>

<style scoped>
.tenant-container { width: 100%; max-width: 480px; margin: 0 auto; min-height: 100vh; background-color: #f5f7fa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; padding-bottom: 20px;}
.tenant-header { background: linear-gradient(135deg, #182848, #4b6cb7); color: #fff; padding: 30px 20px 40px; display: flex; justify-content: space-between; align-items: flex-start; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
.user-greeting h3 { margin: 0 0 8px 0; font-size: 20px; font-weight: bold; }
.user-greeting p { margin: 0; font-size: 13px; opacity: 0.8; display: flex; align-items: center; gap: 5px;}

.stats-panel { display: flex; margin: -25px 15px 20px; background: #fff; border-radius: 12px; padding: 15px 0; box-shadow: 0 4px 15px rgba(0,0,0,0.05); position: relative; z-index: 10; }
.stat-box { flex: 1; text-align: center; border-right: 1px solid #f0f0f0; }
.stat-box:last-child { border-right: none; }
.stat-num { font-size: 20px; font-weight: bold; color: #303133; margin-bottom: 5px; font-family: monospace; }
.stat-label { font-size: 12px; color: #909399; }
.text-danger { color: #f56c6c; }
.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }

.action-grid { display: flex; gap: 15px; padding: 0 15px; margin-bottom: 20px; }
.action-btn { flex: 1; background: #fff; border-radius: 12px; padding: 15px 0; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); cursor: pointer; color: #303133; font-size: 14px; font-weight: bold;}
.icon-wrap { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; }

.bill-list-section { padding: 0 15px; }
.section-title { font-size: 15px; font-weight: bold; color: #303133; margin-bottom: 15px; display: flex; align-items: center; gap: 6px; }

.contract-card { background: #fff; border-radius: 10px; padding: 15px; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); border-left: 4px solid #409eff; }
.c-no { font-size: 12px; color: #909399; font-family: monospace; margin-bottom: 8px; border-bottom: 1px dashed #f0f0f0; padding-bottom: 4px;}
.c-body p { margin: 0 0 5px 0; font-size: 12px; color: #606266; }
.c-time { font-size: 11px; color: #909399; margin-top: 5px; }

.bill-card { background: #fff; border-radius: 10px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.bill-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px dashed #ebeef5; padding-bottom: 10px; }
.bill-status { font-size: 13px; font-weight: bold; }
.bill-amount { font-size: 24px; font-weight: bold; color: #303133; font-family: monospace; }
.bill-info p { margin: 0 0 5px 0; font-size: 12px; color: #606266; }
.bill-footer { margin-top: 15px; }
.pay-btn { width: 100%; border-radius: 8px; letter-spacing: 1px; font-weight: bold; }

/* 收银台专项UI */
.cashier-amount-box { text-align: center; margin-bottom: 25px; padding: 20px 0; background: #f8f9fa; border-radius: 8px; }
.cashier-label { font-size: 13px; color: #909399; margin-bottom: 5px; }
.cashier-amount { font-size: 32px; font-weight: bold; color: #f56c6c; font-family: monospace; }
.method-title { font-size: 14px; font-weight: bold; color: #303133; margin-bottom: 15px; }
.method-group { width: 100%; display: flex; flex-direction: column; gap: 10px; }
.method-radio { margin: 0; padding: 15px; border: 1px solid #ebeef5; border-radius: 8px; transition: all 0.3s; display: flex; align-items: center; }
.method-radio.is-checked { border-color: #409eff; background-color: #f0f7ff; }
.method-content { display: flex; align-items: center; width: 100%; font-size: 15px; }

.receipt-upload-box { margin-top: 20px; padding-top: 15px; border-top: 1px dashed #ebeef5; }
.receipt-tips { font-size: 12px; color: #e6a23c; background-color: #fdf6ec; padding: 10px; border-radius: 6px; margin-bottom: 15px; line-height: 1.5; }
.receipt-uploader { border: 1px dashed #d9d9d9; border-radius: 6px; cursor: pointer; position: relative; overflow: hidden; width: 100%; height: 140px; display: flex; align-items: center; justify-content: center; background-color: #fafafa; }
.receipt-uploader:hover { border-color: #409eff; }
.receipt-uploader-icon { font-size: 28px; color: #8c939d; }
.receipt-preview { width: 100%; height: 100%; object-fit: cover; }

:deep(.mobile-dialog) { border-radius: 12px; }
</style>