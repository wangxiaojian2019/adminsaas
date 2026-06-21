<template>
  <div class="finance-container">
    <el-tabs v-model="activeTab" type="border-card" class="custom-tabs" @tab-click="handleTabClick">
      
      <el-tab-pane label="常规应收账单中心" name="bills">
        <div class="toolbar">
          <el-button type="warning" icon="Download" @click="exportData">带水印导出财务报表</el-button>
          <el-button icon="Refresh" @click="fetchBills">刷新流水</el-button>
        </div>

        <el-table :data="billData" v-loading="billLoading" border stripe style="width: 100%">
          <el-table-column prop="id" label="单号" width="70" align="center" />
          <el-table-column prop="enterprise_name" label="付款方企业" min-width="160" show-overflow-tooltip />
          <el-table-column label="关联空间" width="140" align="center">
            <template #default="{ row }">
              <span v-if="row.space_id">{{ row.building_name }} - {{ row.room_number }}</span>
              <span v-else class="text-gray">公共/杂项</span>
            </template>
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
              <el-popconfirm v-else-if="row.is_paid === 0 || row.is_paid === 3" title="确认收到线下款项并强制核销？" @confirm="handlePay(row.id, 'approve')">
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
              <div v-if="row.status === 1" class="timeline-text text-success" title="财务确认打款或追缴结清的绝对时间">结: {{ row.updated_at }}</div>
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
        <div class="toolbar" style="margin-bottom: 15px;">
          <el-alert title="系统已自动提取租户入驻时填写的【期初底数】作为初始账本。每月抄表入账后，系统将自动算出差值并向企业推送水电账单。" type="warning" show-icon :closable="false" style="width: 100%" />
          <el-button icon="Refresh" @click="fetchMeters" style="margin-left: 15px;">刷新台账</el-button>
        </div>
        <el-table :data="metersList" v-loading="metersLoading" border stripe style="width: 100%">
          <el-table-column label="关联空间" width="150" align="center">
            <template #default="{ row }">
              <span style="font-weight: bold; color: #409eff;">{{ row.building_name }}-{{ row.room_number }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="enterprise_name" label="承租企业" min-width="180" show-overflow-tooltip />
          
          <el-table-column label="水表系统底数存档" align="center" width="220">
            <template #default="{ row }">
              <div style="font-size: 16px; font-weight: bold; color: #409eff;">{{ row.last_water }} <span style="font-size:12px;color:#909399;">吨</span></div>
              <div style="font-size: 11px; color: #c0c4cc;">最后存档: {{ row.last_water_date }}</div>
            </template>
          </el-table-column>
          <el-table-column label="水费核算操作" align="center" width="130">
            <template #default="{ row }">
              <el-button type="primary" plain size="small" @click="openMeterDialog(row, 1)">抄记水表</el-button>
            </template>
          </el-table-column>
          
          <el-table-column label="电表系统底数存档" align="center" width="220">
            <template #default="{ row }">
              <div style="font-size: 16px; font-weight: bold; color: #e6a23c;">{{ row.last_elec }} <span style="font-size:12px;color:#909399;">度</span></div>
              <div style="font-size: 11px; color: #c0c4cc;">最后存档: {{ row.last_elec_date }}</div>
            </template>
          </el-table-column>
          <el-table-column label="电费核算操作" align="center" width="130">
            <template #default="{ row }">
              <el-button type="warning" plain size="small" @click="openMeterDialog(row, 2)">抄记电表</el-button>
            </template>
          </el-table-column>
        </el-table>
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

    <el-dialog v-model="meterDialogVisible" :title="`${currentMeter.building_name}-${currentMeter.room_number} 月度能耗抄表`" width="450px" @close="meterFormRef?.resetFields()">
      <div style="background-color: #f0f9eb; padding: 15px; border-radius: 6px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #e1f3d8;">
        <span style="color: #67c23a; font-weight: bold;">系统上次存档底数：</span>
        <span style="font-size: 24px; font-weight: bold; color: #67c23a; font-family: monospace;">{{ lastMeterReading }}</span>
      </div>

      <el-form ref="meterFormRef" :model="meterForm" label-width="120px">
        <el-form-item label="本次表盘读数" prop="current_reading" :rules="[{ required: true, message: '不可为空' }]">
          <el-input-number v-model="meterForm.current_reading" :min="lastMeterReading" :precision="2" controls-position="right" style="width: 100%;" />
        </el-form-item>
        
        <div v-if="calculatedUsage > 0" class="calc-preview">
          <div style="display:flex; justify-content: space-between; margin-bottom: 10px;">
            <span>本期消耗量推演：</span>
            <span style="font-weight: bold;">{{ calculatedUsage }} {{ meterForm.meter_type == 1 ? '吨' : '度' }}</span>
          </div>
          <div style="display:flex; justify-content: space-between; align-items: center; color: #f56c6c;">
            <span>系统自动生单账单：</span>
            <span style="font-size: 20px; font-weight: bold;">¥ {{ calculatedCost }}</span>
          </div>
          <div style="font-size: 11px; color: #909399; margin-top: 5px; text-align: right;">(当前计费标准: {{ meterForm.meter_type == 1 ? '水费 5.5元/吨' : '电费 1.2元/度' }})</div>
        </div>
      </el-form>
      <template #footer>
        <el-button @click="meterDialogVisible = false">取消</el-button>
        <el-button type="primary" :disabled="calculatedUsage <= 0" :loading="submitLoading" @click="submitMeter">生成计费账单并结底存档</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Check, Timer, Warning } from '@element-plus/icons-vue'
import request from '../../utils/request'

const route = useRoute()
const router = useRouter()

const activeTab = ref('bills')
const submitLoading = ref(false)

const billData = ref([]); const billLoading = ref(false)
const checkoutData = ref([]); const checkoutLoading = ref(false)
const metersList = ref([]); const metersLoading = ref(false)

const fetchBills = async () => {
  billLoading.value = true
  try {
    const res = await request.get('/api/finance/receivables/list')
    if (res.code === 200) {
      billData.value = res.data
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

const fetchMeters = async () => {
  metersLoading.value = true
  try {
    const res = await request.get('/api/finance/meters/list')
    if (res.code === 200) metersList.value = res.data
  } finally { metersLoading.value = false }
}

const handleTabClick = (tab) => {
  if (tab.paneName === 'checkouts') fetchCheckouts()
  if (tab.paneName === 'meters') fetchMeters()
}

const reviewDialogVisible = ref(false)
const currentReviewBill = ref({})
const rejectReasonText = ref('') 

const openReviewDialog = (row) => {
  currentReviewBill.value = row
  rejectReasonText.value = '' 
  reviewDialogVisible.value = true
}

const handlePay = async (id, action) => {
  if (action === 'reject') {
    if (!rejectReasonText.value.trim()) {
      return ElMessage.warning('执行驳回必须填写驳回原因，以便通知租户。')
    }
    try {
      await ElMessageBox.confirm('确认将此凭证驳回，并通知租户重新打款吗？', '驳回风险提示', {
        type: 'warning', confirmButtonText: '坚决驳回', cancelButtonText: '再看看'
      })
    } catch (e) { return }
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

// 抄表模块变量与动态算费
const meterDialogVisible = ref(false)
const meterFormRef = ref(null)
const currentMeter = ref({})
const meterForm = reactive({ space_id: '', enterprise_id: '', meter_type: 1, current_reading: 0 })

const lastMeterReading = computed(() => {
  return meterForm.meter_type === 1 ? currentMeter.value.last_water : currentMeter.value.last_elec
})

const calculatedUsage = computed(() => {
  const diff = Number(meterForm.current_reading) - Number(lastMeterReading.value)
  return diff > 0 ? Number(diff.toFixed(2)) : 0
})

const calculatedCost = computed(() => {
  const rate = meterForm.meter_type === 1 ? 5.5 : 1.2
  return (calculatedUsage.value * rate).toFixed(2)
})

const openMeterDialog = (row, type) => {
  currentMeter.value = row
  meterForm.space_id = row.space_id
  meterForm.enterprise_id = row.enterprise_id
  meterForm.meter_type = type
  meterForm.current_reading = type === 1 ? row.last_water : row.last_elec
  meterDialogVisible.value = true
}

const submitMeter = () => {
  meterFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    try {
      const res = await request.post('/api/finance/meters/record', meterForm)
      if (res.code === 200) {
        ElMessage.success(res.msg)
        meterDialogVisible.value = false
        fetchMeters() 
      } else { ElMessage.error(res.msg) }
    } finally { submitLoading.value = false }
  })
}

const getBillTypeLabel = (type) => ({ 1: '场地租金', 2: '水费出账', 3: '电费出账', 4: '物业/车位', 5: '违约滞纳金', 6: '履约押金' }[type] || '其他')
const getBillTypeColor = (type) => ({ 1: 'primary', 2: 'info', 3: 'warning', 4: 'success', 5: 'danger', 6: 'info' }[type] || 'info')
const getFullImgUrl = (url) => url.startsWith('http') ? url : `http://47.120.52.65:8787${url}`

onMounted(() => {
  fetchBills()
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

.calc-preview { background-color: #fdf6ec; border: 1px solid #faecd8; padding: 15px; border-radius: 6px; margin-top: 15px; font-size: 14px; color: #e6a23c; }
</style>