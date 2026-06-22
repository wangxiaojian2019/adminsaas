<template>
  <div class="finance-container">
    <el-tabs v-model="activeTab" type="border-card" class="custom-tabs" @tab-click="handleTabClick">
      
      <!-- ================= 账单模块 ================= -->
      <el-tab-pane label="常规应收账单中心" name="bills">
        <div class="toolbar" style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
          <div class="filter-panel" style="display: flex; gap: 10px; align-items: center;">
            <span class="filter-label">台账检索:</span>
            <el-input v-model="billFilter.enterprise" placeholder="付款方企业" clearable style="width: 160px;" />
            <el-input v-model="billFilter.space" placeholder="关联空间(如:A栋)" clearable style="width: 160px;" />
            <el-select v-model="billFilter.type" placeholder="费用科目" clearable style="width: 140px;">
              <el-option label="场地租金" :value="1" />
              <el-option label="水费出账" :value="2" />
              <el-option label="电费出账" :value="3" />
              <el-option label="物业/车位" :value="4" />
              <el-option label="违约滞纳金" :value="5" />
              <el-option label="履约押金" :value="6" />
            </el-select>
          </div>
          <div>
            <el-button type="warning" icon="Download" @click="exportData">带水印导出</el-button>
            <el-button icon="Refresh" @click="fetchBills">刷新流水</el-button>
          </div>
        </div>

        <el-table :data="processedBillData" v-loading="billLoading" border stripe style="width: 100%">
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
                  <el-button type="primary" link icon="Check">强制核销</el-button>
                </template>
              </el-popconfirm>
              <div v-else class="timeline-text text-success" title="财务执行核销确认的物理时间">核销于: {{ row.paid_time }}</div>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ================= 退租模块 ================= -->
      <el-tab-pane label="退租清算与退款沙盘" name="checkouts">
        <div class="toolbar" style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
          <div class="filter-panel" style="display: flex; gap: 10px; align-items: center;">
            <span class="filter-label">清算检索:</span>
            <el-input v-model="checkoutFilter.enterprise" placeholder="退租企业" clearable style="width: 160px;" />
            <el-input v-model="checkoutFilter.space" placeholder="关联空间" clearable style="width: 160px;" />
            <el-input v-model="checkoutFilter.contract_no" placeholder="底层契约号" clearable style="width: 180px;" />
          </div>
          <el-button icon="Refresh" @click="fetchCheckouts">重新抓取清算数据</el-button>
        </div>
        <el-table :data="processedCheckoutData" v-loading="checkoutLoading" border stripe style="width: 100%">
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
                  <el-button type="success" link icon="Money">
                    {{ row.actual_refund >= 0 ? '打款结清' : '追缴结清' }}
                  </el-button>
                </template>
              </el-popconfirm>
              <el-tag v-else type="info">已终结归档</el-tag>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ================= 抄表模块 ================= -->
      <el-tab-pane label="后勤能耗抄表台账" name="meters">
        <el-alert title="系统已自动提取租户入驻时填写的【期初底数】作为初始账本。每月抄表入账后，系统将自动算出差值并向企业推送水电账单。" type="warning" show-icon :closable="false" style="width: 100%; margin-bottom: 15px;" />
        <div class="toolbar" style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
          <div class="filter-panel" style="display: flex; gap: 10px; align-items: center;">
            <span class="filter-label">能耗检索:</span>
            <el-input v-model="meterFilter.enterprise" placeholder="承租企业" clearable style="width: 160px;" />
            <el-input v-model="meterFilter.space" placeholder="关联空间" clearable style="width: 160px;" />
          </div>
          <el-button icon="Refresh" @click="fetchMeters">刷新台账</el-button>
        </div>
        <el-table :data="processedMetersList" v-loading="metersLoading" border stripe style="width: 100%">
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
          <el-table-column label="水费核算操作" align="center" width="160">
            <template #default="{ row }">
              <el-button type="primary" plain size="small" @click="openMeterDialog(row, 1)">抄记</el-button>
              <el-button type="success" plain size="small" icon="List" @click="openHistoryDrawer(row, 1)">事件记录</el-button>
            </template>
          </el-table-column>
          
          <el-table-column label="电表系统底数存档" align="center" width="220">
            <template #default="{ row }">
              <div style="font-size: 16px; font-weight: bold; color: #e6a23c;">{{ row.last_elec }} <span style="font-size:12px;color:#909399;">度</span></div>
              <div style="font-size: 11px; color: #c0c4cc;">最后存档: {{ row.last_elec_date }}</div>
            </template>
          </el-table-column>
          <el-table-column label="电费核算操作" align="center" width="160">
            <template #default="{ row }">
              <el-button type="warning" plain size="small" @click="openMeterDialog(row, 2)">抄记</el-button>
              <el-button type="success" plain size="small" icon="List" @click="openHistoryDrawer(row, 2)">事件记录</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 打款审核弹窗 -->
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
          <el-input v-model="rejectReasonText" type="textarea" :rows="2" placeholder="例如：凭证截图不完整、金额与应收不符等..." />
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

    <!-- 抄表录入弹窗 -->
    <el-dialog v-model="meterDialogVisible" :title="`${currentMeter.building_name}-${currentMeter.room_number} 月度能耗抄表`" width="450px" @close="meterFormRef?.resetFields()">
      <div style="background-color: #f0f9eb; padding: 15px; border-radius: 6px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #e1f3d8;">
        <div>
           <div style="color: #67c23a; font-weight: bold;">系统上次存档底数</div>
           <div style="font-size: 12px; color: #909399; margin-top: 4px;">抄表时间: {{ lastMeterDate }}</div>
        </div>
        <span style="font-size: 24px; font-weight: bold; color: #67c23a; font-family: monospace;">{{ lastMeterReading }}</span>
      </div>

      <el-form ref="meterFormRef" :model="meterForm" label-width="120px">
        <el-form-item label="本次表盘读数" prop="current_reading" :rules="[{ required: true, message: '不可为空' }]">
          <el-input-number v-model="meterForm.current_reading" :min="Number(lastMeterReading) || 0" :precision="2" controls-position="right" style="width: 100%;" />
        </el-form-item>
        
        <el-form-item label="计费单价(元)" prop="price" :rules="[{ required: true, message: '单价不可为空' }]">
          <el-input-number v-model="meterForm.price" :min="0" :precision="2" :step="0.1" controls-position="right" style="width: 100%;" />
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
          <div style="font-size: 11px; color: #909399; margin-top: 5px; text-align: right;">
            (当前设定单价: {{ meterForm.price }} 元/{{ meterForm.meter_type == 1 ? '吨' : '度' }})
          </div>
        </div>
      </el-form>
      <template #footer>
        <el-button @click="meterDialogVisible = false">取消</el-button>
        <el-button type="primary" :disabled="calculatedUsage <= 0" :loading="submitLoading" @click="submitMeter">生成计费账单并结底存档</el-button>
      </template>
    </el-dialog>

    <!-- 【核心新增】：抄表事件记录与账单推送流向溯源抽屉 -->
    <el-drawer v-model="historyDrawerVisible" :title="`【${currentMeter.building_name}-${currentMeter.room_number}】${currentHistoryType === 1 ? '水表' : '电表'} 抄表事件记录`" size="450px">
      <div v-loading="historyLoading" style="padding: 10px;">
        <el-timeline>
          <el-timeline-item
            v-for="item in processedHistoryList"
            :key="item.id"
            :type="item.is_latest ? 'success' : 'info'"
            :timestamp="`抄表入库时间: ${item.created_at}`"
            placement="top"
          >
            <el-card shadow="hover" :style="{ border: item.is_latest ? '1px solid #67c23a' : '' }">
              <div style="font-size: 13px; color: #606266;">归属计费月份: <span style="font-weight:bold;color:#303133;">{{ item.record_month }}</span></div>
              <div style="margin-top: 8px; font-size: 13px;">上次底数参照: <span style="font-family:monospace;">{{ item.last_reading }}</span></div>
              <div style="margin-top: 4px; font-size: 13px;">本次实际读数: <span style="font-family:monospace;">{{ item.current_reading }}</span></div>
              <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed #ebeef5; color: #e6a23c; font-size: 13px;">
                系统核算用量差值: <strong style="font-size: 16px; color: #f56c6c; font-family: monospace; float: right;">{{ item.usage_amount }}</strong>
              </div>
              
              <!-- 核心展示：这里展示推送给了谁 -->
              <div v-if="item.billed_amount > 0" style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed #ebeef5; font-size: 13px; background-color: #f0f9eb; padding: 10px; border-radius: 4px;">
                <div style="color: #67c23a; font-weight: bold; margin-bottom: 5px;">
                  <el-icon><Check /></el-icon> 已自动生成账单: ¥{{ item.billed_amount }}
                </div>
                <div style="color: #606266;">
                  <el-icon><Position /></el-icon> 账单信使已推送至: <strong style="color:#409eff">{{ item.pushed_to }}</strong>
                </div>
              </div>
              <div v-else style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed #ebeef5; font-size: 12px; color: #909399;">
                <el-icon><InfoFilled /></el-icon> 初始底数建档，未产生费用账单
              </div>
            </el-card>
          </el-timeline-item>
        </el-timeline>
        <el-empty v-if="!historyLoading && processedHistoryList.length === 0" description="暂无抄表事件记录" />
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
// 核心修复：引入 Position, InfoFilled, Check 等真实存在的 ElementPlus 图标
import { Refresh, Warning, View, List, Download, Check, Money, Position, InfoFilled } from '@element-plus/icons-vue'
import request from '../../utils/request'

const router = useRouter()
const activeTab = ref('bills')
const submitLoading = ref(false)

const billData = ref([]); const billLoading = ref(false)
const checkoutData = ref([]); const checkoutLoading = ref(false)
const metersList = ref([]); const metersLoading = ref(false)

// ==========================
// 全局检索/过滤逻辑
// ==========================
const billFilter = reactive({ enterprise: '', space: '', type: '' })
const checkoutFilter = reactive({ enterprise: '', space: '', contract_no: '' })
const meterFilter = reactive({ enterprise: '', space: '' })

const processedBillData = computed(() => {
  return billData.value.filter(row => {
    if (billFilter.enterprise && !(row.enterprise_name || '').includes(billFilter.enterprise)) return false;
    const spaceStr = (row.building_name || '') + (row.room_number || '');
    if (billFilter.space && !spaceStr.includes(billFilter.space)) return false;
    if (billFilter.type && row.bill_type !== billFilter.type) return false;
    return true;
  })
})

const processedCheckoutData = computed(() => {
  return checkoutData.value.filter(row => {
    if (checkoutFilter.enterprise && !(row.enterprise_name || '').includes(checkoutFilter.enterprise)) return false;
    if (checkoutFilter.contract_no && !(row.contract_no || '').includes(checkoutFilter.contract_no)) return false;
    const spaceStr = (row.building_name || '') + (row.room_number || '');
    if (checkoutFilter.space && !spaceStr.includes(checkoutFilter.space)) return false;
    return true;
  })
})

const processedMetersList = computed(() => {
  return metersList.value.filter(row => {
    if (meterFilter.enterprise && !(row.enterprise_name || '').includes(meterFilter.enterprise)) return false;
    const spaceStr = (row.building_name || '') + (row.room_number || '');
    if (meterFilter.space && !spaceStr.includes(meterFilter.space)) return false;
    return true;
  })
})

// ==========================
// 数据拉取与路由匹配
// ==========================
const fetchBills = async () => {
  billLoading.value = true
  try {
    const res = await request.get('/api/finance/receivables/list')
    if (res.code === 200) billData.value = res.data
  } finally { billLoading.value = false }
}

const fetchCheckouts = async () => {
  checkoutLoading.value = true
  try {
    const res = await request.get('/api/finance/checkouts/list')
    if (res.code === 200) checkoutData.value = res.data
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

// ==========================
// 账单与打款逻辑
// ==========================
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

// ==========================
// 抄表模块变量与动态算费
// ==========================
const meterDialogVisible = ref(false)
const meterFormRef = ref(null)
const currentMeter = ref({})
const meterForm = reactive({ space_id: '', enterprise_id: '', meter_type: 1, current_reading: 0, price: 0 })

const lastMeterReading = computed(() => meterForm.meter_type === 1 ? currentMeter.value.last_water : currentMeter.value.last_elec)
const lastMeterDate = computed(() => meterForm.meter_type === 1 ? currentMeter.value.last_water_date : currentMeter.value.last_elec_date)

const calculatedUsage = computed(() => {
  const diff = Number(meterForm.current_reading) - Number(lastMeterReading.value)
  return diff > 0 ? Number(diff.toFixed(2)) : 0
})

const calculatedCost = computed(() => (calculatedUsage.value * meterForm.price).toFixed(2))

const openMeterDialog = (row, type) => {
  currentMeter.value = row
  meterForm.space_id = row.space_id
  meterForm.enterprise_id = row.enterprise_id
  meterForm.meter_type = type
  meterForm.current_reading = type === 1 ? row.last_water : row.last_elec
  meterForm.price = type === 1 ? 5.5 : 1.2
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

// ==========================
// 抄表事件记录与账单追溯
// ==========================
const historyDrawerVisible = ref(false)
const historyList = ref([])
const historyLoading = ref(false)
const currentHistoryType = ref(1)

const processedHistoryList = computed(() => {
  return historyList.value.map((item, index) => {
    const prev = historyList.value[index + 1]
    if (prev) {
      const usage = (item.current_reading - prev.current_reading).toFixed(2)
      return { ...item, is_latest: index === 0, last_reading: prev.current_reading, usage_amount: usage }
    } else {
      return { ...item, is_latest: index === 0, last_reading: '--', usage_amount: '初始底数建档' }
    }
  })
})

const openHistoryDrawer = async (row, type) => {
  currentMeter.value = row
  currentHistoryType.value = type
  historyDrawerVisible.value = true
  historyLoading.value = true
  try {
    const res = await request.get('/api/finance/meterHistory', { 
        params: { space_id: row.space_id, meter_type: type } 
    })
    if (res.code === 200) {
        historyList.value = res.data
    }
  } finally {
    historyLoading.value = false
  }
}

const getBillTypeLabel = (type) => ({ 1: '场地租金', 2: '水费出账', 3: '电费出账', 4: '物业/车位', 5: '违约滞纳金', 6: '履约押金' }[type] || '其他')
const getBillTypeColor = (type) => ({ 1: 'primary', 2: 'info', 3: 'warning', 4: 'success', 5: 'danger', 6: 'info' }[type] || 'info')
const getFullImgUrl = (url) => url.startsWith('http') ? url : `http://47.120.52.65:8787${url}`

onMounted(() => { fetchBills() })
</script>

<style scoped>
.finance-container { width: 100%; }
.custom-tabs { box-shadow: none; border-radius: 4px; }
.filter-label { font-size: 14px; font-weight: bold; color: #606266; margin-right: 5px;}
.toolbar { margin-bottom: 20px; display: flex; gap: 10px; align-items: center;}
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