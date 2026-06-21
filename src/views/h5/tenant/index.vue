<template>
  <div class="mobile-container">
    <div class="mobile-header">
      <div class="user-greeting">
        <div class="label-text">当前登录企业：</div>
        <div class="enterprise-header">
          <span class="enterprise-name">{{ enterpriseName || '数据加载中...' }}</span>
          <el-tag size="small" type="success" effect="dark" class="enterprise-tag">入驻企业</el-tag>
        </div>
        <p><el-icon><OfficeBuilding /></el-icon> 园区专属移动服务门户</p>
      </div>
      
      <div class="header-actions">
        <div class="msg-bell" @click="openMsgDrawer">
          <el-badge :value="unreadCount" :hidden="unreadCount === 0" :max="99">
            <el-icon :size="24" color="#ffffff"><BellFilled /></el-icon>
          </el-badge>
        </div>
      </div>
    </div>

    <div v-show="activeTab === 'home'" class="h5-content floating-content" v-loading="overviewLoading">
      
      <div v-if="pendingReturns.length > 0" class="responsibility-card" style="border: 1px solid #faecd8; background-color: #fdf6ec; margin-bottom: 15px;">
        <div class="resp-title" style="color: #e6a23c; margin-bottom: 12px;">
          <el-icon><Box /></el-icon> 待归还物资提醒 ({{ pendingReturns.length }} 件)
        </div>
        <div v-for="inv in pendingReturns" :key="'ret-'+inv.id" class="quick-bill-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px dashed #faecd8;">
          <div class="qb-info">
            <div class="qb-title" style="margin-bottom: 4px;">
              <span class="qb-amount" style="color: #e6a23c; font-weight: bold; font-size: 15px;">{{ inv.item_name }}</span>
              <span style="font-size: 14px; margin-left: 10px; font-weight: bold; color: #f56c6c;">x{{ inv.quantity }}{{ inv.unit }}</span>
            </div>
            <div class="qb-date text-danger" v-if="inv.expected_return_date" style="font-size: 12px; color: #f56c6c;">
              <el-icon><Timer /></el-icon> 应于 {{ inv.expected_return_date }} 前归还
            </div>
          </div>
        </div>
      </div>

      <div v-if="overview.contracts && overview.contracts.length > 0">
        <div class="stats-panel" style="margin-top: 0;">
          <div class="stat-box">
            <div class="stat-num text-danger" style="font-size: 18px;">¥{{ totalMonthlyRent }}</div>
            <div class="stat-label">总月租金</div>
          </div>
          <div class="stat-box">
            <div class="stat-num text-success" style="font-size: 18px;">¥{{ totalDeposit }}</div>
            <div class="stat-label">总存管押金</div>
          </div>
          <div class="stat-box">
            <div class="stat-num" style="font-size: 18px;">{{ overview.contracts.length }}份</div>
            <div class="stat-label">生效中合同</div>
          </div>
        </div>

        <div v-for="(contract, index) in overview.contracts" :key="contract.id" class="responsibility-card" style="margin-bottom: 15px;">
          <div class="resp-title">
            <el-icon><House /></el-icon> 承租空间 {{ index + 1 }}
            <el-tag size="small" type="info" style="margin-left:auto" v-if="contract.alteration_type === 1">扩租补充</el-tag>
          </div>
          <div class="space-title text-primary" style="font-size: 18px; margin: 10px 0; font-weight: bold;">
            {{ contract.building_name }} - {{ contract.room_number }}
          </div>
          <el-divider border-style="dashed" style="margin: 12px 0;" />
          <div class="info-line"><span>公文契约号：</span><span class="text-code">{{ contract.contract_no }}</span></div>
          <div class="info-line"><span>履约周期：</span><span style="font-size: 12px;">{{ contract.start_date }} ~ {{ contract.end_date }}</span></div>
          <div class="info-line"><span>空间独立月租：</span><span class="text-danger font-bold">¥{{ contract.monthly_rent }}</span></div>
        </div>
      </div>

      <div v-if="unpaidBills.length > 0" class="responsibility-card" style="border: 1px solid #fde2e2; background-color: #fffafb;">
        <div class="resp-title text-danger" style="margin-bottom: 12px;">
          <el-icon><Bell /></el-icon> 待处理账单提醒 ({{ unpaidBills.length }} 笔)
        </div>
        <div v-for="bill in unpaidBills" :key="'quick-'+bill.id" class="quick-bill-item">
          <div class="qb-info">
            <div class="qb-title">
              <el-tag size="small" :type="getBillTypeColor(bill.bill_type)" effect="dark" style="margin-right: 6px;">
                {{ getBillTypeLabel(bill.bill_type) }}
              </el-tag>
              <span class="qb-amount">¥ {{ bill.amount }}</span>
            </div>
            <div class="qb-date">最晚需于 {{ bill.due_date }} 前结清</div>
            <div v-if="Number(bill.is_paid) === 3" class="reject-reason-text">
              <el-icon><WarningFilled /></el-icon> 被驳回: {{ bill.reject_reason || '凭证不符合要求' }}
            </div>
          </div>
          <div class="qb-action">
            <el-tag v-if="Number(bill.is_paid) === 2" type="warning" size="small" effect="plain">核销中</el-tag>
            <el-button v-else-if="Number(bill.is_paid) === 3" type="danger" plain size="small" @click="openPayDialog(bill)">重新提交</el-button>
            <el-button v-else type="danger" size="small" @click="openPayDialog(bill)">去支付</el-button>
          </div>
        </div>
      </div>

      <el-empty v-if="!overviewLoading && (!overview.contracts || overview.contracts.length === 0)" description="当前暂无生效中的租赁契约" :image-size="80" class="responsibility-card" />
    </div>

    <div v-show="activeTab === 'bills'" class="h5-content floating-content" v-loading="billsLoading">
      <div class="responsibility-card title-card">
        <span class="resp-title" style="margin:0;"><el-icon><Wallet /></el-icon> 财务账单中心</span>
        <el-tag size="small" type="primary" effect="light">{{ bills.length }} 笔出账</el-tag>
      </div>

      <el-empty v-if="bills.length === 0" description="当前没有任何账单" :image-size="80" />
      
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
          <div v-if="Number(bill.is_paid) === 3" class="reject-card">
            <div class="reject-title"><el-icon><CircleCloseFilled /></el-icon> 财务核销失败</div>
            <div class="reject-msg">原因：{{ bill.reject_reason || '系统未标注驳回原因，请联系园区物业。' }}</div>
          </div>
        </div>
        <div class="bill-footer">
          <el-tag v-if="Number(bill.is_paid) === 1" type="success" size="default">已结清</el-tag>
          <el-tag v-else-if="Number(bill.is_paid) === 2" type="warning" size="default" effect="plain">
            <el-icon><Timer /></el-icon> 凭证已传，等待核销
          </el-tag>
          <el-button v-else-if="Number(bill.is_paid) === 3" type="danger" plain size="default" @click="openPayDialog(bill)" class="full-btn">重新上传打款凭证</el-button>
          <el-button v-else type="primary" size="default" @click="openPayDialog(bill)" class="full-btn">上传打款回单</el-button>
        </div>
      </div>
    </div>

    <div v-show="activeTab === 'repair'" class="h5-content floating-content">
      <div class="responsibility-card">
        <div class="resp-title"><el-icon><Tools /></el-icon> 提交物业维保工单</div>
        <el-form ref="repairFormRef" :model="repairForm" :rules="repairRules" label-position="top" style="margin-top: 15px;">
          <el-form-item label="故障简述 (必填)" prop="title">
            <el-input v-model="repairForm.title" placeholder="例如：空调不制冷、网络端口断网" size="large" />
          </el-form-item>
          <el-form-item label="情况详述" prop="description">
            <el-input v-model="repairForm.description" type="textarea" :rows="3" placeholder="请详述具体方位与故障表现..." />
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
              <div v-else class="upload-trigger"><el-icon class="plus-icon"><Camera /></el-icon><div>点击拍照或选择照片</div></div>
            </el-upload>
          </el-form-item>
          <el-button type="primary" size="large" class="full-btn" style="margin-top: 10px;" :loading="repairLoading" @click="submitRepair">下发至调度室</el-button>
        </el-form>
      </div>
    </div>

    <div v-show="activeTab === 'inventory'" class="h5-content floating-content">
      <div class="responsibility-card title-card">
        <span class="resp-title" style="margin:0;"><el-icon><Box /></el-icon> 企业物资领用/借阅台账</span>
        <el-tag size="small" type="primary" effect="light">{{ inventoryList.length }} 笔记录</el-tag>
      </div>

      <el-empty v-if="inventoryList.length === 0" description="暂无园区后勤物资领用或外借记录" :image-size="80" class="responsibility-card" />
      
      <div v-for="inv in inventoryList" :key="inv.id" class="bill-card" style="padding: 15px;">
        <div class="bill-header" style="margin-bottom: 8px; padding-bottom: 8px;">
          <span class="amount" style="font-size: 16px;">{{ inv.item_name }}</span>
          <el-tag size="small" :type="inv.action_type === 3 ? 'warning' : (inv.action_type === 2 ? 'danger' : 'success')" effect="dark">
            {{ inv.action_type === 3 ? '外借中' : (inv.action_type === 2 ? '已消耗' : '已归还') }}
          </el-tag>
        </div>
        <div class="bill-body" style="margin-bottom: 0;">
          <div class="b-line" style="margin-bottom: 6px;">
            <strong>流转数量：</strong><span style="font-weight: bold; color: #f56c6c; font-family: monospace; font-size: 15px; margin: 0 4px;">{{ inv.quantity }}</span>{{ inv.unit }}
          </div>
          <div class="b-line" style="margin-bottom: 6px; color: #909399; font-size: 12px;">办理时间：{{ inv.created_at }}</div>
          <div v-if="inv.action_type === 3 && inv.expected_return_date" class="b-line text-danger" style="margin-bottom: 6px;">
            <strong style="color: #e6a23c;">协议应还日期：</strong><span style="color: #e6a23c;">{{ inv.expected_return_date }}</span>
          </div>
          <div v-if="inv.remark" class="b-line" style="background-color: #f8f9fa; padding: 8px; border-radius: 4px; font-size: 12px; color: #606266; margin-top: 8px;">
            登记备注：{{ inv.remark }}
          </div>
        </div>
      </div>
    </div>

    <div v-show="activeTab === 'profile'" class="h5-content profile-wrapper">
      <div class="profile-header">
        <div class="avatar"><el-icon><OfficeBuilding /></el-icon></div>
        <div class="info">
          <div class="position" style="margin-bottom: 5px;"><el-tag size="small" effect="dark" type="success">入驻企业</el-tag></div>
          <div class="name" style="word-break: break-all; font-size: 16px;">{{ enterpriseName || '数据加载中...' }}</div>
        </div>
      </div>
      <div class="profile-menu">
        <div class="menu-item" @click="pwdDialogVisible = true"><el-icon><Lock /></el-icon> <span>安全设置 (修改密码)</span> <el-icon class="arrow"><ArrowRight /></el-icon></div>
        <div class="menu-item text-danger" @click="logout(false)"><el-icon><SwitchButton /></el-icon> <span>安全退出移动门户</span></div>
      </div>
    </div>

    <div class="bottom-tabbar">
      <div :class="['tab-item', { active: activeTab === 'home' }]" @click="activeTab = 'home'"><el-icon><House /></el-icon><span>资产</span></div>
      <div :class="['tab-item', { active: activeTab === 'bills' }]" @click="activeTab = 'bills'"><el-icon><Wallet /></el-icon><span>账单</span></div>
      <div :class="['tab-item', { active: activeTab === 'repair' }]" @click="activeTab = 'repair'"><el-icon><Tools /></el-icon><span>报修</span></div>
      <div :class="['tab-item', { active: activeTab === 'inventory' }]" @click="activeTab = 'inventory'"><el-icon><Box /></el-icon><span>物资</span></div>
      <div :class="['tab-item', { active: activeTab === 'profile' }]" @click="activeTab = 'profile'"><el-icon><User /></el-icon><span>我的</span></div>
    </div>

    <el-drawer v-model="msgDrawerVisible" title="消息与预警中心" direction="btt" size="85%" :with-header="false" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
      <div class="drawer-header">
        <span style="font-size: 16px; font-weight: bold; color: #303133;">实时消息列表</span>
        <el-icon size="20" @click="msgDrawerVisible = false"><Close /></el-icon>
      </div>
      <div class="msg-list" v-loading="msgLoading">
        <el-empty v-if="msgList.length === 0" description="暂无服务通知" :image-size="60" />
        <div v-for="msg in msgList" :key="msg.id" :class="['msg-card', { unread: Number(msg.is_read) === 0 }]" @click="readMsg(msg)">
          <div class="msg-header">
            <span class="msg-title"><span v-if="Number(msg.is_read) === 0" class="red-dot"></span>{{ msg.title }}</span>
            <span class="msg-time">{{ msg.created_at }}</span>
          </div>
          <div class="msg-content">{{ msg.content }}</div>
        </div>
      </div>
    </el-drawer>

    <el-dialog v-model="payDialogVisible" title="提交财务打款凭证" width="90%" center top="10vh" append-to-body>
      <div class="upload-sandbox">
        <div class="pay-target">待核销金额: <span class="text-danger">¥ {{ currentBill.amount }}</span></div>
        <div v-if="Number(currentBill.is_paid) === 3" class="reject-alert"><el-icon><Warning /></el-icon> 上次凭证被驳回，请重新上传。</div>
        <p v-else class="tips">请将款项汇入园区指定账户，并将回单/截图上传至下方。</p>
        <el-upload class="cert-uploader" action="http://47.120.52.65:8787/api/upload" :headers="uploadHeaders" :show-file-list="false" :on-success="handleUploadSuccess" :before-upload="beforeUpload">
          <img v-if="uploadUrl" :src="getFullImgUrl(uploadUrl)" class="preview-img" />
          <div v-else class="upload-trigger"><el-icon class="plus-icon"><Plus /></el-icon><div>调起手机相册重新拍照</div></div>
        </el-upload>
      </div>
      <template #footer>
        <div style="display: flex; gap: 10px;">
          <el-button @click="payDialogVisible = false; uploadUrl = ''" style="flex: 1;">取消</el-button>
          <el-button type="success" :disabled="!uploadUrl" :loading="submitLoading" @click="submitPayment" style="flex: 2;">提交核销</el-button>
        </div>
      </template>
    </el-dialog>

    <el-dialog v-model="pwdDialogVisible" title="修改登录安全密码" width="90%" center top="15vh" append-to-body @close="pwdFormRef?.resetFields()">
      <el-form ref="pwdFormRef" :model="pwdForm" :rules="pwdRules" label-position="top">
        <el-form-item label="当前密码" prop="old_password"><el-input v-model="pwdForm.old_password" type="password" show-password size="large" /></el-form-item>
        <el-form-item label="新安全密码" prop="new_password"><el-input v-model="pwdForm.new_password" type="password" show-password size="large" /></el-form-item>
      </el-form>
      <template #footer>
        <div style="display: flex; gap: 10px;">
          <el-button @click="pwdDialogVisible = false" size="large" style="flex: 1;">取消</el-button>
          <el-button type="primary" size="large" :loading="pwdLoading" @click="submitPwd" style="flex: 1;">保存</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { House, Wallet, Tools, User, Timer, Camera, Plus, OfficeBuilding, Lock, SwitchButton, ArrowRight, Bell, WarningFilled, Warning, CircleCloseFilled, BellFilled, Close, Box } from '@element-plus/icons-vue'
import request from '../../../utils/request'

const router = useRouter()
const activeTab = ref('home')
const enterpriseId = ref(0)
const enterpriseName = ref('')

const overview = ref({ contracts: [] }) 
const overviewLoading = ref(false)
const bills = ref([])
const billsLoading = ref(false)
const inventoryList = ref([])

const totalMonthlyRent = computed(() => {
  if (!overview.value.contracts || overview.value.contracts.length === 0) return 0
  return overview.value.contracts.reduce((sum, contract) => sum + Number(contract.monthly_rent || 0), 0).toFixed(2)
})

const totalDeposit = computed(() => {
  if (!overview.value.contracts || overview.value.contracts.length === 0) return 0
  return overview.value.contracts.reduce((sum, contract) => sum + Number(contract.deposit || 0), 0).toFixed(2)
})

const unpaidBills = computed(() => bills.value.filter(bill => Number(bill.is_paid) === 0 || Number(bill.is_paid) === 3))

const pendingReturns = computed(() => {
  return inventoryList.value.filter(inv => inv.action_type === 3)
})

const msgDrawerVisible = ref(false)
const msgLoading = ref(false)
const msgList = ref([])
const unreadCount = computed(() => msgList.value.filter(m => Number(m.is_read) === 0).length)

let socket = null
let reconnectTimer = null

const initSocket = () => {
  if (!enterpriseId.value) return
  if (socket) socket.close()
  socket = new WebSocket('ws://47.120.52.65:8788')
  socket.onopen = () => {
    socket.send(JSON.stringify({ type: 'bind', enterprise_id: enterpriseId.value, token: localStorage.getItem('h5_tenant_token') }))
  }
  socket.onmessage = (event) => {
    try {
      const data = JSON.parse(event.data)
      if (data.type === 'notification' || data.type === 'reject') {
        fetchMessages() 
        if (data.type === 'notification' && data.msg && data.msg.includes('物资')) fetchInventory()

        if (data.type === 'reject') {
          ElMessage.warning(data.msg || '有一笔账单凭证被驳回')
          fetchBills() 
        } else { ElMessage.info(data.msg || '您有新的服务通知') }
      }
    } catch (e) {}
  }
  socket.onclose = () => {
    if (reconnectTimer) clearTimeout(reconnectTimer)
    reconnectTimer = setTimeout(() => { initSocket() }, 5000)
  }
  socket.onerror = () => {}
}

const fetchMessages = async () => {
  if (!enterpriseId.value) return
  try {
    const res = await request.get('/api/notification/list')
    if (res.code === 200) msgList.value = res.data || []
  } catch (e) {}
}

const openMsgDrawer = () => {
  msgDrawerVisible.value = true
  fetchMessages()
}

const readMsg = async (msg) => {
  if (Number(msg.is_read) === 1) return
  msg.is_read = 1
  try { await request.post('/api/notification/read', { id: msg.id }) } catch (e) { msg.is_read = 0 }
}

const payDialogVisible = ref(false)
const submitLoading = ref(false)
const currentBill = ref({})
const uploadUrl = ref('')

const repairFormRef = ref(null)
const repairLoading = ref(false)
const repairForm = reactive({ title: '', description: '', image_url: '' })
const repairRules = { title: [{ required: true, message: '必填', trigger: 'blur' }] }

const pwdDialogVisible = ref(false)
const pwdFormRef = ref(null)
const pwdLoading = ref(false)
const pwdForm = reactive({ old_password: '', new_password: '' })
const pwdRules = {
  old_password: [{ required: true, message: '不可为空', trigger: 'blur' }],
  new_password: [{ required: true, message: '不可为空', trigger: 'blur' }]
}

const uploadHeaders = computed(() => ({ 'Authorization': `Bearer ${localStorage.getItem('h5_tenant_token')}` }))

const initUserInfo = () => {
  const infoStr = localStorage.getItem('tenant_info')
  if (!infoStr) { router.replace('/h5/tenant/login'); return }
  const info = JSON.parse(infoStr)
  enterpriseId.value = info.enterprise_id || info.id || 0
  enterpriseName.value = info.enterprise_name || info.name || info.tenant_name || ''
}

const fetchInventory = async () => {
  if (!enterpriseId.value) return
  try {
    const res = await request.get('/api/tenant/inventory')
    if (res.code === 200) inventoryList.value = res.data
  } catch (e) {}
}

const fetchOverview = async () => {
  overviewLoading.value = true
  try {
    const res = await request.get('/api/tenant/overview')
    if (res.code === 200) {
      overview.value = res.data
      if (res.data.enterprise && res.data.enterprise.id) {
        enterpriseName.value = res.data.enterprise.name
        enterpriseId.value = res.data.enterprise.id
        fetchMessages() 
        initSocket()
        fetchInventory()
        
        const infoStr = localStorage.getItem('tenant_info')
        if (infoStr) {
          try {
            const info = JSON.parse(infoStr)
            info.enterprise_name = res.data.enterprise.name
            info.enterprise_id = res.data.enterprise.id
            localStorage.setItem('tenant_info', JSON.stringify(info))
          } catch (e) {}
        }
      }
    }
  } finally { overviewLoading.value = false }
}

const fetchBills = async () => {
  billsLoading.value = true
  try {
    const res = await request.get('/api/tenant/bills')
    if (res.code === 200) bills.value = res.data
  } finally { billsLoading.value = false }
}

const openPayDialog = (bill) => { currentBill.value = bill; uploadUrl.value = ''; payDialogVisible.value = true }
const getFullImgUrl = (url) => url.startsWith('http') ? url : `http://47.120.52.65:8787${url}`
const beforeUpload = (file) => file.size / 1024 / 1024 < 10
const handleUploadSuccess = (res) => {
  if (res.code === 200) { uploadUrl.value = res.data.url; ElMessage.success('成功') }
  else { ElMessage.error('异常') }
}
const handleRepairUpload = (res) => {
  if (res.code === 200) { repairForm.image_url = res.data.url; ElMessage.success('成功') }
  else { ElMessage.error('失败') }
}

const submitPayment = async () => {
  submitLoading.value = true
  try {
    const res = await request.post('/api/tenant/pay', { bill_id: currentBill.value.id, receipt_url: uploadUrl.value })
    if (res.code === 200) { ElMessage.success(res.msg); payDialogVisible.value = false; fetchBills() } 
    else { ElMessage.error(res.msg) }
  } finally { submitLoading.value = false }
}

const submitRepair = () => {
  repairFormRef.value.validate(async (valid) => {
    if (!valid) return
    repairLoading.value = true
    try {
      const res = await request.post('/api/tenant/order/submit', repairForm)
      if (res.code === 200) { ElMessage.success(res.msg); repairFormRef.value.resetFields(); repairForm.image_url = ''; activeTab.value = 'home' } 
      else { ElMessage.error(res.msg) }
    } finally { repairLoading.value = false }
  })
}

const submitPwd = () => {
  pwdFormRef.value.validate(async (valid) => {
    if (!valid) return
    pwdLoading.value = true
    try {
      const res = await request.post('/api/tenant/password/update', pwdForm)
      if (res.code === 200) { ElMessage.success(res.msg); pwdDialogVisible.value = false; logout(true) } 
      else { ElMessage.error(res.msg) }
    } finally { pwdLoading.value = false }
  })
}

const logout = (silent = false) => {
  const doLogout = () => {
    localStorage.removeItem('h5_tenant_token'); localStorage.removeItem('tenant_info')
    router.replace('/h5/tenant/login')
  }
  if (silent === true) { doLogout() } else { if (window.confirm('确认要退出吗？')) { doLogout() } }
}

const getBillTypeLabel = (type) => ({ 1: '场地租金', 2: '水费账单', 3: '电费账单', 4: '物业/车位', 5: '违约滞纳金', 6: '履约押金' }[type] || '其他')
const getBillTypeColor = (type) => ({ 1: 'primary', 2: 'info', 3: 'warning', 4: 'success', 5: 'danger', 6: 'info' }[type] || 'info')

onMounted(() => { initUserInfo(); fetchOverview(); fetchBills() })
onUnmounted(() => { if (socket) socket.close(); if (reconnectTimer) clearTimeout(reconnectTimer) })
</script>

<style scoped>
.mobile-container { width: 100%; max-width: 480px; margin: 0 auto; min-height: 100vh; background-color: #f5f7fa; padding-bottom: 70px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; position: relative;}
.mobile-header { background: linear-gradient(135deg, #2c3e50, #3498db); color: #fff; padding: 25px 20px 50px 20px; display: flex; justify-content: space-between; align-items: flex-start; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
.user-greeting { flex: 1; min-width: 0; }
.header-actions { flex-shrink: 0; margin-left: 15px; padding-top: 5px; }
.msg-bell { cursor: pointer; padding: 5px; }
.label-text { font-size: 13px; color: rgba(255, 255, 255, 0.85); margin-bottom: 6px; }
.enterprise-header { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.enterprise-name { font-size: 20px; font-weight: bold; line-height: 1.4; word-break: break-all; }
.enterprise-tag { border-radius: 12px; border: none; flex-shrink: 0; }
.user-greeting p { margin: 0; font-size: 13px; opacity: 0.9; display: flex; align-items: center; gap: 4px; }
.floating-content { padding: 0 15px; margin-top: -30px; position: relative; z-index: 10; }
.title-card { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px !important; margin-bottom: 15px; }
.responsibility-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
.resp-title { font-size: 14px; font-weight: bold; color: #409eff; margin-bottom: 8px; display: flex; align-items: center; gap: 5px; }
.info-line { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; font-size: 14px; color: #606266; }
.text-code { font-family: monospace; font-weight: bold; color: #303133; }
.stats-panel { display: flex; margin: 15px 0; background: #fff; border-radius: 10px; padding: 15px 0; box-shadow: 0 2px 12px rgba(0,0,0,0.03); }
.stat-box { flex: 1; text-align: center; border-right: 1px solid #f0f0f0; }
.stat-box:last-child { border-right: none; }
.stat-num { font-size: 20px; font-weight: bold; margin-bottom: 5px; font-family: monospace; }
.stat-label { font-size: 12px; color: #909399; }
.text-danger { color: #f56c6c; }
.text-success { color: #67c23a; }
.font-bold { font-weight: bold; font-size: 16px; }
.quick-bill-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed #fbd9d9; }
.quick-bill-item:last-child { border-bottom: none; padding-bottom: 0; }
.qb-info { flex: 1; }
.qb-title { display: flex; align-items: center; margin-bottom: 6px; }
.qb-amount { font-size: 16px; font-weight: bold; color: #f56c6c; font-family: monospace; }
.qb-date { font-size: 12px; color: #909399; }
.reject-reason-text { font-size: 11px; color: #f56c6c; margin-top: 5px; background: #fef0f0; padding: 4px 6px; border-radius: 4px; display: inline-block;}
.qb-action { margin-left: 10px; }
.bill-card { background: #fff; border-radius: 12px; padding: 18px; margin-bottom: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.04); border: 1px solid #f0f2f5; }
.bill-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #ebeef5; }
.amount { font-size: 20px; font-weight: bold; color: #303133; font-family: monospace; }
.bill-body { font-size: 13px; color: #606266; line-height: 1.8; margin-bottom: 15px; }
.bill-footer { text-align: right; }
.b-line { margin-bottom: 4px; }
.reject-card { background-color: #fef0f0; border-left: 3px solid #f56c6c; padding: 10px; margin-top: 10px; border-radius: 0 4px 4px 0; }
.reject-title { font-weight: bold; color: #f56c6c; margin-bottom: 4px; display: flex; align-items: center; gap: 4px; }
.reject-msg { color: #f56c6c; font-size: 12px; }
.full-btn { width: 100%; border-radius: 8px; font-weight: bold; letter-spacing: 1px; }
.profile-wrapper { padding: 0 15px; margin-top: -30px; position: relative; z-index: 10; }
.profile-header { background: #fff; border-radius: 10px; padding: 25px 20px; display: flex; align-items: center; gap: 15px; margin-bottom: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
.profile-header .avatar { width: 60px; height: 60px; background: #e6f1fc; color: #409eff; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 30px; }
.profile-header .name { font-weight: bold; color: #303133; margin-bottom: 5px; }
.profile-menu { background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,0.03); border: 1px solid #f0f2f5; }
.menu-item { display: flex; align-items: center; padding: 18px 20px; font-size: 15px; color: #303133; border-bottom: 1px solid #fafafa; cursor: pointer; }
.menu-item:active { background-color: #f5f7fa; }
.menu-item:last-child { border-bottom: none; }
.menu-item .el-icon { margin-right: 10px; font-size: 18px; color: #909399; }
.menu-item .arrow { margin-left: auto; color: #c0c4cc; margin-right: 0; }
.bottom-tabbar { position: fixed; bottom: 0; left: 0; right: 0; max-width: 480px; margin: 0 auto; height: 55px; background: #fff; display: flex; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); z-index: 100; border-top: 1px solid #ebeef5; }
.tab-item { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; color: #909399; font-size: 11px; cursor: pointer; }
.tab-item .el-icon { font-size: 22px; margin-bottom: 3px; }
.tab-item.active { color: #409eff; }
.drawer-header { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f2f5; }
.msg-list { padding: 15px; height: calc(100% - 55px); overflow-y: auto; background-color: #f5f7fa; }
.msg-card { background: #fff; border-radius: 8px; padding: 15px; margin-bottom: 12px; cursor: pointer; border: 1px solid #ebeef5; }
.msg-card.unread { border-left: 3px solid #f56c6c; box-shadow: 0 2px 8px rgba(245, 108, 108, 0.1); }
.msg-header { display: flex; justify-content: space-between; margin-bottom: 8px; align-items: center;}
.msg-title { font-size: 14px; font-weight: bold; color: #303133; position: relative; }
.red-dot { display: inline-block; width: 6px; height: 6px; background-color: #f56c6c; border-radius: 50%; vertical-align: middle; margin-right: 4px; }
.msg-time { font-size: 11px; color: #909399; }
.msg-content { font-size: 13px; color: #606266; line-height: 1.5; }
.upload-sandbox { text-align: center; }
.pay-target { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
.tips { font-size: 12px; color: #909399; margin-bottom: 20px; line-height: 1.5; }
.reject-alert { background: #fef0f0; color: #f56c6c; padding: 8px; border-radius: 6px; font-size: 12px; margin-bottom: 15px; text-align: left; display: flex; align-items: center; gap: 5px; border: 1px solid #fde2e2; }
.cert-uploader { border: 1px dashed #d9d9d9; border-radius: 8px; cursor: pointer; position: relative; overflow: hidden; display: block; width: 100%; height: 200px; background-color: #fafafa; }
.cert-uploader:hover { border-color: #409EFF; }
.upload-trigger { display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100%; color: #8c939d; font-size: 13px; }
.plus-icon { font-size: 30px; margin-bottom: 10px; }
.preview-img { width: 100%; height: 100%; object-fit: contain; }
</style>