<template>
  <div class="finance-container">
    <el-tabs v-model="activeTab" type="border-card" class="custom-tabs">
      
      <el-tab-pane label="常规应收账单中心" name="bills">
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
          
          <el-table-column label="系统出账时间" width="150" align="center">
            <template #default="{ row }">
              <div style="font-size: 12px; color: #909399;">{{ row.created_at }}</div>
            </template>
          </el-table-column>
          
          <el-table-column label="核销状态" width="120" align="center" fixed="right">
            <template #default="{ row }">
              <el-tag v-if="row.is_paid === 1" type="success">已结清</el-tag>
              <el-tag v-else-if="row.is_paid === 2" type="warning" effect="dark">待审核凭证</el-tag>
              <el-tooltip v-else-if="row.is_paid === 3" :content="row.reject_reason || '凭证被驳回'" placement="top">
                <el-tag type="danger" effect="plain" style="cursor: pointer;"><el-icon><Warning /></el-icon> 凭证被驳回</el-tag>
              </el-tooltip>
              <el-tag v-else type="danger">待收款</el-tag>
            </template>
          </el-table-column>

          <el-table-column label="操作/核销记录" width="140" align="center" fixed="right">
            <template #default="{ row }">
              <el-button v-if="row.is_paid === 2" type="warning" link icon="View" @click="openReviewDialog(row)">审阅单据</el-button>
              <el-popconfirm v-else-if="row.is_paid === 0 || row.is_paid === 3" title="确认收到线下款项并强制核销？" @confirm="handlePay(row.id, 'direct')">
                <template #reference>
                  <el-button type="primary" link icon="Select">强制核销</el-button>
                </template>
              </el-popconfirm>
              <div v-else class="timeline-text text-success" title="财务执行核销确认的物理时间">核销于: {{ row.paid_time }}</div>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="退租清算与退款沙盘" name="checkouts">
        <div class="toolbar">
          <el-button icon="Refresh" @click="fetchCheckouts">重新抓取清算数据</el-button>
        </div>
        <el-table :data="checkoutData" v-loading="checkoutLoading" border stripe style="width: 100%">
          <el-table-column prop="id" label="单号" width="70" align="center" />
          <el-table-column label="底层契约号" width="160" align="center">
            <template #default="{ row }"><span class="text-code">{{ row.contract_no }}</span></template>
          </el-table-column>
          <el-table-column prop="enterprise_name" label="退租企业" min-width="150" show-overflow-tooltip />
          
          <el-table-column label="核算明细" width="160">
            <template #default="{ row }">
              <div style="font-size: 12px;">押金：¥{{ row.refund_deposit }}</div>
              <div style="font-size: 12px;" class="text-danger">罚没：-¥{{ row.deduct_rent }}</div>
              <div style="font-size: 12px;" class="text-danger">物损：-¥{{ row.deduct_damage }}</div>
            </template>
          </el-table-column>

          <el-table-column prop="remark" label="清算备注" min-width="150" show-overflow-tooltip>
            <template #default="{ row }">
              <span v-if="row.remark">{{ row.remark }}</span>
              <span v-else style="color: #c0c4cc; font-style: italic;">无说明</span>
            </template>
          </el-table-column>
          
          <el-table-column label="结算净额" width="130" align="right">
            <template #default="{ row }">
              <span v-if="row.actual_refund >= 0" style="color: #67c23a; font-weight: bold; font-size: 14px;">
                应退: ¥{{ row.actual_refund }}
              </span>
              <span v-else style="color: #f56c6c; font-weight: bold; font-size: 14px;">
                追缴: ¥{{ Math.abs(row.actual_refund) }}
              </span>
            </template>
          </el-table-column>

          <el-table-column label="流程时间轴" width="160" align="center">
            <template #default="{ row }">
              <div class="timeline-text" title="业务员在前端发起退租作废操作的时间">发: {{ row.created_at }}</div>
              <div v-if="row.status === 1" class="timeline-text text-success" title="财务确认打款或追缴结清的绝对时间">结: {{ row.paid_time }}</div>
              <div v-else class="timeline-text text-danger">结: 待财务核销</div>
            </template>
          </el-table-column>

          <el-table-column label="控制引擎" width="130" align="center" fixed="right">
            <template #default="{ row }">
              <el-popconfirm v-if="row.status === 0" :title="row.actual_refund >= 0 ? '确认已线下打款退还？' : '确认已收回追缴款？'" @confirm="handleCheckoutPay(row.id)">
                <template #reference>
                  <el-button type="success" link icon="WalletCheck">
                    {{ row.actual_refund >= 0 ? '打款结清' : '追缴结清' }}
                  </el-button>
                </template>
              </el-popconfirm>
              <el-tag v-else type="info">已终结归档</el-tag>
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

    <el-dialog v-model="reviewDialogVisible" title="对公转账凭证审核室" width="550px">
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
        
        <div style="margin-top: 15px; text-align: left;">
          <p style="font-size: 13px; color: #606266; margin-bottom: 8px;">若需驳回，请填写驳回原因：</p>
          <el-input 
            v-model="rejectReasonText" 
            type="textarea" 
            :rows="2" 
            placeholder="例如：凭证截图不完整、金额与应收不符等..." 
          />
        </div>
      </div>
      <div v-else><el-empty description="未获取到电子回执单图片" :image-size="60" /></div>
      
      <template #footer>
        <div style="display: flex; justify-content: space-between; width: 100%;">
          <el-button type="danger" plain @click="handlePay(currentReviewBill.id, 'reject')">单据无效，执行驳回</el-button>
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
import { ElMessage, ElMessageBox } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import { Warning } from '@element-plus/icons-vue'
import request from '../../utils/request'

const route = useRoute()
const router = useRouter()

const activeTab = ref('bills')
const submitLoading = ref(false)

const billData = ref([]); const billLoading = ref(false)
const spacesList = ref([])

const checkoutData = ref([]); const checkoutLoading = ref(false)

const reviewDialogVisible = ref(false)
const currentReviewBill = ref({})
const rejectReasonText = ref('') // 绑定驳回原因文本

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
      checkAutoReview() 
    }
  } finally { billLoading.value = false }
}

const fetchCheckouts = async () => {
  checkoutLoading.value = true
  try {
    const res = await request.get('/api/finance/checkouts/list')
    if (res.code === 200) {
      checkoutData.value = res.data
    }
  } finally { checkoutLoading.value = false }
}

watch(() => route.query.review_bill_id, (newId) => {
  if (newId) {
    const targetBill = billData.value.find(item => item.id === Number(newId))
    if (targetBill) {
      activeTab.value = 'bills'
      openReviewDialog(targetBill)
    } else {
      fetchBills()
    }
  }
})

const openReviewDialog = (row) => {
  currentReviewBill.value = row
  rejectReasonText.value = '' // 初始化驳回原因
  reviewDialogVisible.value = true
}

const handlePay = async (id, action) => {
  // 若执行驳回，必须进行二次确认拦截
  if (action === 'reject') {
    if (!rejectReasonText.value.trim()) {
      return ElMessage.warning('执行驳回必须填写驳回原因，以便通知租户。')
    }
    try {
      await ElMessageBox.confirm('确认将此凭证驳回，并通知租户重新打款吗？', '驳回风险提示', {
        type: 'warning', confirmButtonText: '坚决驳回', cancelButtonText: '再看看'
      })
    } catch (e) {
      return // 取消操作
    }
  }

  try {
    const payload = { id, action }
    if (action === 'reject') payload.reject_reason = rejectReasonText.value
    
    const res = await request.post('/api/finance/receivables/pay', payload)
    if (res.code === 200) {
      ElMessage.success(res.msg)
      reviewDialogVisible.value = false
      router.replace({ path: '/finance', query: {} })
      fetchBills()
    } else { ElMessage.error(res.msg) }
  } catch (e) { ElMessage.error('核销通讯失败') }
}

const handleCheckoutPay = async (id) => {
  try {
    const res = await request.post('/api/finance/checkouts/pay', { id })
    if (res.code === 200) {
      ElMessage.success(res.msg)
      fetchCheckouts()
    } else {
      ElMessage.error(res.msg)
    }
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

const getBillTypeLabel = (type) => ({ 1: '场地租金', 2: '水费账单', 3: '电费账单', 4: '物业/车位', 5: '违约滞纳金', 6: '履约押金' }[type] || '其他')
const getBillTypeColor = (type) => ({ 1: 'primary', 2: 'info', 3: 'warning', 4: 'success', 5: 'danger', 6: 'info' }[type] || 'info')

onMounted(() => {
  fetchBills()
  fetchCheckouts()
})
</script>

<style scoped>
.finance-container { width: 100%; }
.custom-tabs { box-shadow: none; border-radius: 4px; }
.toolbar { margin-bottom: 20px; display: flex; gap: 10px; }
.amount-text { font-weight: bold; color: #f56c6c; font-family: monospace; font-size: 14px; }
.text-code { font-family: monospace; font-weight: bold; background: #f4f4f5; padding: 4px 8px; border-radius: 4px; color: #606266; }
.text-danger { color: #f56c6c; }
.text-success { color: #67c23a; }
.paid-time { font-size: 11px; color: #909399; }
.timeline-text { font-size: 11px; font-family: monospace; text-align: left; margin: 2px 0; }
.review-tips { font-size: 12px; color: #909399; margin-bottom: 10px; text-align: center;}
.receipt-img { width: 100%; max-height: 40vh; border: 1px dashed #d9d9d9; border-radius: 8px; cursor: pointer; object-fit: contain; }
.review-info { margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; text-align: left; }
.review-info p { margin: 5px 0; font-size: 14px; color: #303133; }
</style>