<template>
  <div class="finance-container">
    <el-tabs v-model="activeTab" type="border-card" class="custom-tabs">
      <el-tab-pane label="应收账单与核销中心" name="bills">
        <div class="toolbar">
          <el-button type="warning" icon="Download" @click="exportData">带水印导出财务报表</el-button>
          <el-button icon="Refresh" @click="fetchBills">刷新流水</el-button>
        </div>

        <el-table :data="billData" v-loading="billLoading" border stripe style="width: 100%">
          <el-table-column prop="id" label="单号" width="70" align="center" />
          <el-table-column prop="enterprise_name" label="付款方企业" min-width="160" show-overflow-tooltip />
          <el-table-column label="关联空间" width="140" align="center">
            <template #default="{ row }">{{ row.building_name }} - {{ row.room_number }}</template>
          </el-table-column>
          <el-table-column label="费用科目" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="getBillTypeColor(row.bill_type)" effect="plain">{{ getBillTypeLabel(row.bill_type) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="应收金额(元)" width="120" align="right">
            <template #default="{ row }"><span class="amount-text">￥{{ row.amount }}</span></template>
          </el-table-column>
          <el-table-column prop="due_date" label="最晚缴费日" width="120" align="center" />
          
          <el-table-column label="核销状态" width="120" align="center" fixed="right">
            <template #default="{ row }">
              <el-tag v-if="row.is_paid === 1" type="success">已结清</el-tag>
              <el-tag v-else-if="row.is_paid === 2" type="warning" effect="dark">待审核凭证</el-tag>
              <el-tag v-else type="danger">待收款</el-tag>
            </template>
          </el-table-column>

          <el-table-column label="操作" width="120" align="center" fixed="right">
            <template #default="{ row }">
              <el-button v-if="row.is_paid === 2" type="warning" link icon="View" @click="openReviewDialog(row)">审阅单据</el-button>
              <el-popconfirm v-else-if="row.is_paid === 0" title="确认收到线下款项并强制核销？" @confirm="handlePay(row.id, 'direct')">
                <template #reference>
                  <el-button type="primary" link icon="Select">强制核销</el-button>
                </template>
              </el-popconfirm>
              <span v-else class="paid-time">{{ row.paid_time }}</span>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="后勤能耗抄表台账" name="meters">
        <div class="toolbar">
          <el-button type="primary" icon="EditPen" @click="openMeterDialog">现场读表推单</el-button>
        </div>
        <el-empty description="抄表台账列表暂存，可通过上方推单引擎生成财务流水" />
      </el-tab-pane>
    </el-tabs>

    <el-dialog v-model="reviewDialogVisible" title="对公转账凭证审核室" width="500px">
      <div v-if="currentReviewBill.receipt_url" style="text-align: center;">
        <p class="review-tips">点击图片可放大查看流水号等细节</p>
        <el-image 
            :src="getFullImgUrl(currentReviewBill.receipt_url)" 
            :preview-src-list="[getFullImgUrl(currentReviewBill.receipt_url)]"
            fit="contain" class="receipt-img">
        </el-image>
        <div class="review-info">
            <p><strong>打款企业：</strong>{{ currentReviewBill.enterprise_name }}</p>
            <p><strong>应核销金额：</strong><span class="amount-text">￥{{ currentReviewBill.amount }}</span></p>
        </div>
      </div>
      <div v-else><el-empty description="未获取到电子回执单图片" :image-size="60" /></div>
      <template #footer>
        <div style="display: flex; justify-content: space-between; width: 100%;">
          <el-button type="danger" plain @click="handlePay(currentReviewBill.id, 'reject')">单据无效，直接驳回</el-button>
          <el-button type="success" @click="handlePay(currentReviewBill.id, 'approve')">确认到账，核销归档</el-button>
        </div>
      </template>
    </el-dialog>

    <el-dialog v-model="meterDialogVisible" title="后勤人员录入能耗表显" width="450px" @close="meterFormRef?.resetFields()">
      <el-form ref="meterFormRef" :model="meterForm" :rules="meterRules" label-width="100px">
        <el-form-item label="物理空间" prop="space_id">
          <el-select v-model="meterForm.space_id" placeholder="请选择目标房间" style="width: 100%;">
            <el-option v-for="item in spacesList" :key="item.id" :label="`${item.building_name} - ${item.room_number}`" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="仪表类型" prop="meter_type">
          <el-radio-group v-model="meterForm.meter_type">
            <el-radio :label="1">水表 (立方)</el-radio>
            <el-radio :label="2">电表 (度)</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="本期读数" prop="current_reading">
          <el-input v-model.number="meterForm.current_reading"><template #append>表显刻度</template></el-input>
        </el-form-item>
        <el-form-item label="归属账期" prop="record_month">
          <el-date-picker v-model="meterForm.record_month" type="month" value-format="YYYY-MM" placeholder="例如：2026-06" style="width: 100%;" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="meterDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitMeter">记录并生成缴费单</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import request from '../../utils/request'

const route = useRoute()
const router = useRouter()

const activeTab = ref('bills')
const submitLoading = ref(false)

const billData = ref([]); const billLoading = ref(false)
const spacesList = ref([])

const reviewDialogVisible = ref(false)
const currentReviewBill = ref({})

const meterDialogVisible = ref(false); const meterFormRef = ref(null)
const meterForm = reactive({ space_id: '', meter_type: 1, current_reading: '', record_month: '' })
const meterRules = {
  space_id: [{ required: true, message: '必须绑定物理房间', trigger: 'change' }],
  current_reading: [{ required: true, message: '读数不能为空', trigger: 'blur' }]
}

const getFullImgUrl = (url) => {
  if (!url) return ''
  return url.startsWith('http') ? url : `http://47.120.52.65:8787${url}`
}

// 提取共用的核对弹窗逻辑
const checkAutoReview = () => {
  const targetId = route.query.review_bill_id
  if (targetId) {
    const bill = billData.value.find(item => item.id === Number(targetId))
    if (bill && bill.is_paid === 2) {
      activeTab.value = 'bills'
      openReviewDialog(bill)
    }
  }
}

const fetchBills = async () => {
  billLoading.value = true
  try {
    const res = await request.get('/api/finance/receivables/list')
    if (res.code === 200) {
      billData.value = res.data
      checkAutoReview() // 数据就绪后，判定是否有需要自动拉起弹窗的指令
    }
  } finally { billLoading.value = false }
}

// 深度监听路由参数：解决“财务刚好停留在该页面时，点击通知毫无反应”的防抖拦截
watch(() => route.query.review_bill_id, (newId) => {
  if (newId) {
    const targetBill = billData.value.find(item => item.id === Number(newId))
    if (targetBill) {
      activeTab.value = 'bills'
      openReviewDialog(targetBill)
    } else {
      // 找不到说明是新打款单据，强制刷新一遍数据拉取
      fetchBills()
    }
  }
})

const openReviewDialog = (row) => {
  currentReviewBill.value = row
  reviewDialogVisible.value = true
}

const handlePay = async (id, action) => {
  try {
    const res = await request.post('/api/finance/receivables/pay', { id, action })
    if (res.code === 200) {
      ElMessage.success(res.msg)
      reviewDialogVisible.value = false
      // 核销完成，无痕清理 URL 上的强提醒指令，防止刷新页面后重复弹窗
      router.replace({ path: '/finance', query: {} })
      fetchBills()
    } else { ElMessage.error(res.msg) }
  } catch (e) { ElMessage.error('核销通讯失败') }
}

const exportData = async () => {
  ElMessage.info('加密导出中...')
  const token = localStorage.getItem('saas_token')
  try {
    const res = await fetch(`http://47.120.52.65:8787/api/export/download?module=finance`, {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    const blob = await res.blob()
    const a = document.createElement('a')
    a.href = window.URL.createObjectURL(blob)
    a.download = `业财流水报表_${new Date().getTime()}.csv`
    a.click()
    ElMessage.success('导出成功，已入库审计')
  } catch (e) { ElMessage.error('导出失败') }
}

const openMeterDialog = async () => {
  const res = await request.get('/api/spaces/list')
  if (res.code === 200) {
    spacesList.value = res.data 
    meterDialogVisible.value = true
  }
}

const submitMeter = () => {
  meterFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    try {
      const res = await request.post('/api/finance/meters/record', meterForm)
      if (res.code === 200) {
        ElMessage.success('表显已录入，系统已向所属企业派发能耗账单')
        meterDialogVisible.value = false
        activeTab.value = 'bills' 
        fetchBills()
      } else { ElMessage.error(res.msg) }
    } finally { submitLoading.value = false }
  })
}

const getBillTypeLabel = (type) => ({ 1: '场地租金', 2: '水费账单', 3: '电费账单', 4: '物业/车位', 5: '违约滞纳金' }[type] || '其他')
const getBillTypeColor = (type) => ({ 1: 'primary', 2: 'info', 3: 'warning', 4: 'success', 5: 'danger' }[type] || 'info')

onMounted(fetchBills)
</script>

<style scoped>
.finance-container { width: 100%; }
.custom-tabs { box-shadow: none; border-radius: 4px; }
.toolbar { margin-bottom: 20px; display: flex; gap: 10px; }
.amount-text { font-weight: bold; color: #f56c6c; font-family: monospace; font-size: 14px; }
.paid-time { font-size: 11px; color: #909399; }
.review-tips { font-size: 12px; color: #909399; margin-bottom: 10px; text-align: center;}
.receipt-img { width: 100%; max-height: 50vh; border: 1px dashed #d9d9d9; border-radius: 8px; cursor: pointer; }
.review-info { margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; text-align: left; }
.review-info p { margin: 5px 0; font-size: 14px; color: #303133; }
</style>