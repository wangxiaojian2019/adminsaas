<template>
  <div class="h5-tenant-main">
    <div class="top-nav">
      <div class="user-greet">您好，{{ enterpriseName }}</div>
      <div class="nav-actions">
        <el-button link type="primary" size="small" @click="pwdDialogVisible = true">修改密码</el-button>
        <el-divider direction="vertical" />
        <el-button link type="danger" size="small" @click="() => logout(false)">安全退出</el-button>
      </div>
    </div>

    <el-tabs v-model="activeTab" class="mobile-tabs" stretch>
      
      <el-tab-pane label="企业资产" name="home">
        <div class="dashboard-content" v-loading="overviewLoading">
          <div v-if="overview.active_contract" class="asset-card">
            <div class="card-header">
              <el-icon><House /></el-icon> 当前承租物理空间
            </div>
            <div class="space-title text-primary">
              {{ overview.active_contract.building_name }} - {{ overview.active_contract.room_number }}
            </div>
            <el-divider border-style="dashed" style="margin: 15px 0;" />
            <div class="info-line"><span>公文契约号：</span><span class="text-code">{{ overview.active_contract.contract_no }}</span></div>
            <div class="info-line"><span>履约周期：</span><span>{{ overview.active_contract.start_date }} 至 {{ overview.active_contract.end_date }}</span></div>
            <div class="info-line"><span>交租频次：</span><el-tag size="small" type="warning">每 {{ overview.active_contract.payment_cycle }} 个月</el-tag></div>
            <div class="info-line"><span>场地月租金：</span><span class="text-danger font-bold">¥ {{ overview.active_contract.monthly_rent }}</span></div>
            <div class="info-line"><span>存管押金：</span><span>¥ {{ overview.active_contract.deposit }}</span></div>
          </div>
          <el-empty v-else description="当前暂无生效中的租赁契约" :image-size="80" />
        </div>
      </el-tab-pane>

      <el-tab-pane label="财务缴费" name="bills">
        <div class="bills-content" v-loading="billsLoading">
          <el-empty v-if="bills.length === 0" description="恭喜，当前没有任何账单" :image-size="80" />
          
          <div v-for="bill in bills" :key="bill.id" class="bill-card">
            <div class="bill-header">
              <el-tag size="small" :type="getBillTypeColor(bill.bill_type)" effect="dark">
                {{ getBillTypeLabel(bill.bill_type) }}
              </el-tag>
              <span class="amount">¥ {{ bill.amount }}</span>
            </div>
            <div class="bill-body">
              <div class="b-line">系统出账时间：{{ bill.created_at }}</div>
              <div class="b-line text-danger" style="font-weight: bold;">最晚缴费期限：{{ bill.due_date }}</div>
            </div>
            <div class="bill-footer">
              <el-tag v-if="bill.is_paid === 1" type="success" size="default">已结清结案</el-tag>
              <el-tag v-else-if="bill.is_paid === 2" type="warning" size="default" effect="plain">
                <el-icon><Timer /></el-icon> 凭证已传，等待财务核销
              </el-tag>
              <el-button v-else type="primary" size="default" @click="openPayDialog(bill)" style="width: 100%; border-radius: 8px;">
                去上传打款回单
              </el-button>
            </div>
          </div>
        </div>
      </el-tab-pane>

      <el-tab-pane label="物业报修" name="repair">
        <div class="repair-content">
          <div class="repair-card">
            <div class="card-header"><el-icon><Tools /></el-icon> 提交物业维保工单</div>
            <el-form ref="repairFormRef" :model="repairForm" :rules="repairRules" label-position="top" style="margin-top: 15px;">
              <el-form-item label="故障简述 (必填)" prop="title">
                <el-input v-model="repairForm.title" placeholder="例如：空调不制冷、网络端口断网" />
              </el-form-item>
              <el-form-item label="情况详述" prop="description">
                <el-input v-model="repairForm.description" type="textarea" :rows="3" placeholder="请详述具体方位与故障表现，方便维修人员携带工具..." />
              </el-form-item>
              <el-form-item label="故障现场照片 (推荐)">
                <el-upload
                  class="cert-uploader"
                  action="http://47.120.52.65:8787/api/upload"
                  :headers="uploadHeaders"
                  :show-file-list="false"
                  :on-success="handleRepairUpload"
                  :before-upload="beforeUpload"
                >
                  <img v-if="repairForm.image_url" :src="getFullImgUrl(repairForm.image_url)" class="preview-img" />
                  <div v-else class="upload-trigger">
                    <el-icon class="plus-icon"><Camera /></el-icon>
                    <div>点击拍照或选择照片</div>
                  </div>
                </el-upload>
              </el-form-item>
              <el-button type="primary" size="large" style="width: 100%; border-radius: 8px; font-weight: bold; margin-top: 10px;" :loading="repairLoading" @click="submitRepair">
                一键下发至中控调度室
              </el-button>
            </el-form>
          </div>
        </div>
      </el-tab-pane>

    </el-tabs>

    <el-dialog v-model="payDialogVisible" title="提交财务打款凭证" width="90%" center top="10vh" append-to-body>
      <div class="upload-sandbox">
        <div class="pay-target">
          待核销金额: <span class="text-danger">¥ {{ currentBill.amount }}</span>
        </div>
        <p class="tips">请通过对公转账或扫码将款项汇入园区指定账户，并将回单/支付截图上传至下方。</p>
        
        <el-upload
          class="cert-uploader"
          action="http://47.120.52.65:8787/api/upload"
          :headers="uploadHeaders"
          :show-file-list="false"
          :on-success="handleUploadSuccess"
          :before-upload="beforeUpload"
        >
          <img v-if="uploadUrl" :src="getFullImgUrl(uploadUrl)" class="preview-img" />
          <div v-else class="upload-trigger">
            <el-icon class="plus-icon"><Plus /></el-icon>
            <div>点击调起手机相册拍照</div>
          </div>
        </el-upload>
      </div>
      <template #footer>
        <div style="display: flex; gap: 10px;">
          <el-button @click="payDialogVisible = false; uploadUrl = ''" style="flex: 1;">取消</el-button>
          <el-button type="success" :disabled="!uploadUrl" :loading="submitLoading" @click="submitPayment" style="flex: 2;">
            确认提交核销
          </el-button>
        </div>
      </template>
    </el-dialog>

    <el-dialog v-model="pwdDialogVisible" title="修改登录安全密码" width="90%" center top="15vh" append-to-body @close="pwdFormRef?.resetFields()">
      <el-form ref="pwdFormRef" :model="pwdForm" :rules="pwdRules" label-position="top">
        <el-form-item label="当前密码" prop="old_password">
          <el-input v-model="pwdForm.old_password" type="password" show-password placeholder="请输入当前密码 (默认 123456)" />
        </el-form-item>
        <el-form-item label="全新安全密码" prop="new_password">
          <el-input v-model="pwdForm.new_password" type="password" show-password placeholder="请输入新的密码" />
        </el-form-item>
      </el-form>
      <template #footer>
        <div style="display: flex; gap: 10px;">
          <el-button @click="pwdDialogVisible = false" style="flex: 1;">取消</el-button>
          <el-button type="primary" :loading="pwdLoading" @click="submitPwd" style="flex: 1;">保存更改</el-button>
        </div>
      </template>
    </el-dialog>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import request from '../../../utils/request'

const router = useRouter()
const activeTab = ref('home')

const enterpriseName = ref('')
const overview = ref({})
const overviewLoading = ref(false)

const bills = ref([])
const billsLoading = ref(false)

const payDialogVisible = ref(false)
const submitLoading = ref(false)
const currentBill = ref({})
const uploadUrl = ref('')

const repairFormRef = ref(null)
const repairLoading = ref(false)
const repairForm = reactive({ title: '', description: '', image_url: '' })
const repairRules = { title: [{ required: true, message: '故障简述必填', trigger: 'blur' }] }

const pwdDialogVisible = ref(false)
const pwdFormRef = ref(null)
const pwdLoading = ref(false)
const pwdForm = reactive({ old_password: '', new_password: '' })
const pwdRules = {
  old_password: [{ required: true, message: '原密码不可为空', trigger: 'blur' }],
  new_password: [{ required: true, message: '新密码不可为空', trigger: 'blur' }]
}

const uploadHeaders = computed(() => ({ 'Authorization': `Bearer ${localStorage.getItem('saas_token')}` }))

const initUserInfo = () => {
  const infoStr = localStorage.getItem('tenant_info')
  if (!infoStr) {
    router.replace('/h5/tenant/login')
    return
  }
  const info = JSON.parse(infoStr)
  enterpriseName.value = info.enterprise_name || '尊贵的客户'
}

const fetchOverview = async () => {
  overviewLoading.value = true
  try {
    const res = await request.get('/api/tenant/overview')
    if (res.code === 200) overview.value = res.data
  } finally { overviewLoading.value = false }
}

const fetchBills = async () => {
  billsLoading.value = true
  try {
    const res = await request.get('/api/tenant/bills')
    if (res.code === 200) bills.value = res.data
  } finally { billsLoading.value = false }
}

const openPayDialog = (bill) => {
  currentBill.value = bill
  uploadUrl.value = ''
  payDialogVisible.value = true
}

const getFullImgUrl = (url) => url.startsWith('http') ? url : `http://47.120.52.65:8787${url}`
const beforeUpload = (file) => file.size / 1024 / 1024 < 10
const handleUploadSuccess = (res) => {
  if (res.code === 200) { uploadUrl.value = res.data.url; ElMessage.success('凭证读取成功') }
  else { ElMessage.error('图片上传异常') }
}

const handleRepairUpload = (res) => {
  if (res.code === 200) { repairForm.image_url = res.data.url; ElMessage.success('现场照片上传成功') }
  else { ElMessage.error('照片读取失败') }
}

const submitPayment = async () => {
  submitLoading.value = true
  try {
    const res = await request.post('/api/tenant/pay', {
      bill_id: currentBill.value.id,
      receipt_url: uploadUrl.value
    })
    if (res.code === 200) {
      ElMessage.success(res.msg)
      payDialogVisible.value = false
      fetchBills()
    } else {
      ElMessage.error(res.msg)
    }
  } finally { submitLoading.value = false }
}

const submitRepair = () => {
  repairFormRef.value.validate(async (valid) => {
    if (!valid) return
    repairLoading.value = true
    try {
      const res = await request.post('/api/tenant/order/submit', repairForm)
      if (res.code === 200) {
        ElMessage.success(res.msg)
        repairFormRef.value.resetFields()
        repairForm.image_url = ''
        activeTab.value = 'home' 
      } else {
        ElMessage.error(res.msg)
      }
    } finally { repairLoading.value = false }
  })
}

const submitPwd = () => {
  pwdFormRef.value.validate(async (valid) => {
    if (!valid) return
    pwdLoading.value = true
    try {
      const res = await request.post('/api/tenant/password/update', pwdForm)
      if (res.code === 200) {
        ElMessage.success(res.msg)
        pwdDialogVisible.value = false
        logout(true) 
      } else {
        ElMessage.error(res.msg)
      }
    } finally { pwdLoading.value = false }
  })
}

// 核心修复：引入原生 confirm 防断层
const logout = (silent = false) => {
  const doLogout = () => {
    localStorage.removeItem('saas_token')
    localStorage.removeItem('tenant_info')
    router.replace('/h5/tenant/login')
  }
  if (silent === true) {
    doLogout()
  } else {
    if (window.confirm('确认要退出移动门户吗？')) {
      doLogout()
    }
  }
}

const getBillTypeLabel = (type) => ({ 1: '场地租金', 2: '水费账单', 3: '电费账单', 4: '物业/车位', 5: '违约滞纳金', 6: '履约押金' }[type] || '其他')
const getBillTypeColor = (type) => ({ 1: 'primary', 2: 'info', 3: 'warning', 4: 'success', 5: 'danger', 6: 'info' }[type] || 'info')

onMounted(() => {
  initUserInfo()
  fetchOverview()
  fetchBills()
})
</script>

<style scoped>
.h5-tenant-main { min-height: 100vh; background-color: #f4f6f9; display: flex; flex-direction: column; }
.top-nav { background: #fff; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); z-index: 10; }
.user-greet { font-size: 15px; font-weight: bold; color: #303133; }
.nav-actions { display: flex; align-items: center; }

:deep(.mobile-tabs .el-tabs__header) { margin: 0; background: #fff; }
:deep(.mobile-tabs .el-tabs__nav-wrap) { padding: 0 10px; }
:deep(.mobile-tabs .el-tabs__item) { font-size: 15px; height: 50px; line-height: 50px; }

.dashboard-content, .bills-content, .repair-content { padding: 15px; }

.asset-card, .repair-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
.card-header { font-size: 13px; color: #909399; margin-bottom: 8px; display: flex; align-items: center; gap: 5px; }
.space-title { font-size: 20px; font-weight: bold; letter-spacing: 1px; margin-bottom: 5px; }
.info-line { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f8f9fa; font-size: 14px; color: #606266; }
.info-line:last-child { border-bottom: none; padding-bottom: 0; }
.text-code { font-family: monospace; font-weight: bold; color: #303133; }
.text-danger { color: #f56c6c; }
.text-primary { color: #409eff; }
.font-bold { font-weight: bold; font-size: 16px; }

.bill-card { background: #fff; border-radius: 12px; padding: 18px; margin-bottom: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
.bill-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #ebeef5; }
.amount { font-size: 22px; font-weight: bold; color: #303133; font-family: monospace; }
.bill-body { font-size: 13px; color: #606266; line-height: 1.8; margin-bottom: 15px; }
.bill-footer { text-align: right; }

.upload-sandbox { text-align: center; }
.pay-target { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
.tips { font-size: 12px; color: #909399; margin-bottom: 20px; line-height: 1.5; }
.cert-uploader { border: 1px dashed #d9d9d9; border-radius: 8px; cursor: pointer; position: relative; overflow: hidden; display: block; width: 100%; height: 200px; background-color: #fafafa; }
.cert-uploader:hover { border-color: #409EFF; }
.upload-trigger { display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100%; color: #8c939d; font-size: 13px; }
.plus-icon { font-size: 30px; margin-bottom: 10px; }
.preview-img { width: 100%; height: 100%; object-fit: contain; }
</style>