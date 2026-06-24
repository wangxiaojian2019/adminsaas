<template>
  <div class="finance-container">
    <el-tabs v-model="activeName" class="finance-tabs">
      
      <el-tab-pane label="应收账单/核销中心" name="receivables">
        <el-card shadow="never">
          <div class="toolbar" style="margin-bottom: 20px; display: flex; justify-content: space-between;">
            <el-form :inline="true" :model="queryForm" size="default">
              <el-form-item label="企业名称">
                <el-input v-model="queryForm.enterprise_name" placeholder="模糊检索" clearable />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="fetchReceivables">查账</el-button>
              </el-form-item>
            </el-form>
          </div>

          <el-table :data="receivableList" border v-loading="loadingReceivables">
            <el-table-column prop="enterprise_name" label="归属企业主体" min-width="180" />
            <el-table-column label="关联空间资产" width="160">
              <template #default="{ row }">
                <span v-if="row.building_name">{{ row.building_name }} - {{ row.room_number }}</span>
                <span v-else style="color: #909399">全局服务(无具体资产)</span>
              </template>
            </el-table-column>
            <el-table-column label="账单分类" width="110" align="center">
              <template #default="{ row }">
                <el-tag :type="getBillTypeColor(row.bill_type)" effect="dark">{{ getBillTypeLabel(row.bill_type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="账单金额与核销(元)" width="200">
              <template #default="{ row }">
                <div style="font-size: 12px; color: #909399;">总应收: <span style="text-decoration: line-through;">￥{{ row.amount }}</span></div>
                <div style="font-size: 15px; font-weight: bold; color: #F56C6C;">待付: ￥{{ (row.amount - row.paid_amount).toFixed(2) }}</div>
                <div style="font-size: 12px; color: #67C23A;">已安全入账: ￥{{ row.paid_amount }}</div>
              </template>
            </el-table-column>
            <el-table-column prop="due_date" label="最迟缴费日" width="120" align="center" />
            <el-table-column label="核销状态" width="130" align="center">
              <template #default="{ row }">
                <el-tag :type="getBillStatusTag(row.is_paid)">{{ getBillStatusText(row.is_paid) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="财务操作" width="160" align="center" fixed="right">
              <template #default="{ row }">
                <el-button size="small" type="primary" plain @click="openTransactionDrawer(row)">打开对账审计</el-button>
              </template>
            </el-table-column>
          </el-table>

          <div class="pagination-container">
            <el-pagination v-model:current-page="queryForm.page" v-model:page-size="queryForm.limit" :page-sizes="[15, 30, 50]" layout="total, sizes, prev, pager, next, jumper" :total="receivableTotal" @size-change="fetchReceivables" @current-change="fetchReceivables" />
          </div>
        </el-card>
      </el-tab-pane>

      <el-tab-pane label="退租清算/打款台账" name="checkouts">
        <el-card shadow="never">
          <el-table :data="checkoutList" border v-loading="loadingCheckouts">
            <el-table-column prop="enterprise_name" label="退租企业" width="200" />
            <el-table-column prop="contract_no" label="作废合同号" width="180" />
            <el-table-column label="清算明细" min-width="300">
              <template #default="{ row }">
                应退原押金: ￥{{ row.refund_deposit }} | 扣违约/房租: ￥{{ row.deduct_rent }} | 扣物损: ￥{{ row.deduct_damage }}<br>
                <span style="color: #67C23A; font-weight: bold; font-size: 15px;">财务应打款总额: ￥{{ row.actual_refund }}</span>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="120" align="center">
              <template #default="{ row }">
                <el-tag :type="row.status == 1 ? 'success' : 'danger'">{{ row.status == 1 ? '已结清' : '待财务打款' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="120" align="center" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status == 0" size="small" type="success" @click="handlePayCheckout(row)">标记为已打款</el-button>
                <span v-else style="color: #909399; font-size: 12px;">{{ row.paid_time }}</span>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination-container">
            <el-pagination v-model:current-page="checkoutPage.page" v-model:page-size="checkoutPage.limit" :page-sizes="[15, 30]" layout="total, sizes, prev, pager, next" :total="checkoutTotal" @size-change="fetchCheckouts" @current-change="fetchCheckouts" />
          </div>
        </el-card>
      </el-tab-pane>

      <el-tab-pane label="水电能耗/智能抄表" name="meters">
        <el-card shadow="never">
          <el-table :data="meterList" border v-loading="loadingMeters">
            <el-table-column label="入驻资产" width="200">
              <template #default="{ row }">{{ row.building_name }} - {{ row.room_number }}</template>
            </el-table-column>
            <el-table-column prop="enterprise_name" label="承租企业" width="200" />
            <el-table-column label="当前水表底数" width="150" align="center">
              <template #default="{ row }"><strong style="color: #409EFF">{{ row.current_water }}</strong></template>
            </el-table-column>
            <el-table-column label="当前电表底数" width="150" align="center">
              <template #default="{ row }"><strong style="color: #E6A23C">{{ row.current_electric }}</strong></template>
            </el-table-column>
            <el-table-column label="操作" width="250" align="center" fixed="right">
              <template #default="{ row }">
                <el-button size="small" type="primary" plain @click="openMeterDialog(row, 1)">抄水表</el-button>
                <el-button size="small" type="warning" plain @click="openMeterDialog(row, 2)">抄电表</el-button>
                <el-button size="small" type="info" plain @click="openHistoryDialog(row)">历史明细</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination-container">
            <el-pagination v-model:current-page="meterPage.page" v-model:page-size="meterPage.limit" :page-sizes="[15, 30]" layout="total, sizes, prev, pager, next" :total="meterTotal" @size-change="fetchMeters" @current-change="fetchMeters" />
          </div>
        </el-card>
      </el-tab-pane>
    </el-tabs>

    <el-drawer v-model="transDrawerVisible" title="账单打款流水对账台" size="60%">
      <div v-if="currentAuditBill" style="margin-bottom: 20px; padding: 15px; background: #f4f4f5; border-radius: 4px;">
        <span style="font-weight:bold; margin-right: 20px;">主体: {{ currentAuditBill.enterprise_name }}</span>
        <span style="color:#F56C6C; font-weight:bold; margin-right: 20px;">需对账缺口: ￥{{ (currentAuditBill.amount - currentAuditBill.paid_amount).toFixed(2) }}</span>
        <span style="color:#67C23A;">已安全入账: ￥{{ currentAuditBill.paid_amount }}</span>
      </div>

      <el-table :data="transactions" border v-loading="transLoading">
        <el-table-column label="租户提交凭证截图" width="150" align="center">
          <template #default="{ row }">
            <el-image style="width: 80px; height: 80px" :src="getFullImgUrl(row.receipt_url)" :preview-src-list="[getFullImgUrl(row.receipt_url)]" fit="cover" />
          </template>
        </el-table-column>
        <el-table-column prop="pay_amount" label="本笔款项 (元)" width="120" align="center">
          <template #default="{ row }"><span style="font-weight:bold; color:#F56C6C">￥{{ row.pay_amount }}</span></template>
        </el-table-column>
        <el-table-column prop="created_at" label="打款提交时间" width="160" align="center" />
        <el-table-column label="财务核销指令" min-width="200" align="center">
          <template #default="{ row }">
            <template v-if="row.status === 0">
              <el-button size="small" type="success" @click="auditTrans(row, 1)">凭证无误，确认资金入账</el-button>
              <el-button size="small" type="danger" @click="auditTrans(row, 2)">异常，驳回</el-button>
            </template>
            <div v-else-if="row.status === 1">
              <el-tag type="success">已核销通过入账</el-tag>
              <div style="font-size:12px; color:#909399; margin-top:4px;">{{ row.audit_time }}</div>
            </div>
            <div v-else-if="row.status === 2">
              <el-tag type="danger">已驳回打回</el-tag>
              <div style="font-size:12px; color:#F56C6C; margin-top:4px;">原因: {{ row.reject_reason }}</div>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </el-drawer>

    <el-dialog :title="`录入${meterType === 1 ? '水表' : '电表'}读数`" v-model="meterDialogVisible" width="400px" @close="meterForm.reading = 0">
      <el-form :model="meterForm" label-width="100px">
        <el-form-item label="计费月份">
          <el-date-picker v-model="meterForm.month" type="month" value-format="YYYY-MM" style="width: 100%" />
        </el-form-item>
        <el-form-item label="当前表底数">
          <el-input-number v-model="meterForm.reading" :min="0" :precision="2" :step="10" style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="meterDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitMeter">生成本期能耗账单</el-button>
      </template>
    </el-dialog>

    <el-dialog title="能耗表底数抄写历史" v-model="historyDialogVisible" width="600px">
      <el-table :data="historyList" border>
        <el-table-column prop="record_month" label="账单月份" width="100" align="center" />
        <el-table-column label="表计类型" width="80" align="center">
          <template #default="{ row }"><el-tag size="small" :type="row.meter_type == 1 ? 'primary' : 'warning'">{{ row.meter_type == 1 ? '水表' : '电表' }}</el-tag></template>
        </el-table-column>
        <el-table-column prop="last_reading" label="上期底数" width="100" align="center" />
        <el-table-column prop="current_reading" label="本期底数" width="100" align="center" />
        <el-table-column prop="usage_amount" label="核算消耗量" width="100" align="center">
          <template #default="{ row }"><strong style="color:#F56C6C">{{ row.usage_amount }}</strong></template>
        </el-table-column>
      </el-table>
    </el-dialog>

  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import request from '@/utils/request'

const activeName = ref('receivables')

// 应收账单
const loadingReceivables = ref(false)
const receivableList = ref([])
const receivableTotal = ref(0)
const queryForm = ref({ page: 1, limit: 15, enterprise_name: '' })

// 流水核销抽屉
const transDrawerVisible = ref(false)
const transLoading = ref(false)
const transactions = ref([])
const currentAuditBill = ref(null)

// 退租清算
const loadingCheckouts = ref(false)
const checkoutList = ref([])
const checkoutTotal = ref(0)
const checkoutPage = ref({ page: 1, limit: 15 })

// 抄表管理
const loadingMeters = ref(false)
const meterList = ref([])
const meterTotal = ref(0)
const meterPage = ref({ page: 1, limit: 15 })

const meterDialogVisible = ref(false)
const meterType = ref(1)
const meterForm = reactive({ space_id: null, enterprise_id: null, type: 1, reading: 0, month: '' })

const historyDialogVisible = ref(false)
const historyList = ref([])

const getFullImgUrl = (url) => {
  if (!url) return ''
  return url.startsWith('http') ? url : `http://47.120.52.65:8787${url}`
}

const getBillTypeLabel = (type) => ({ 1: '场地租金', 2: '水费', 3: '电费', 4: '物业/车位/共享', 5: '违约金', 6: '合同押金', 7: '会议室费用' }[type] || '其他')
const getBillTypeColor = (type) => ({ 1: 'primary', 2: 'info', 3: 'warning', 4: 'success', 5: 'danger', 6: 'info', 7: 'success' }[type] || 'info')
const getBillStatusText = (status) => ({ 0: '挂账待核销', 1: '已入账结清', 2: '租户凭证待审', 3: '被驳回/异常' }[status])
const getBillStatusTag = (status) => ({ 0: 'danger', 1: 'success', 2: 'warning', 3: 'info' }[status])

const fetchReceivables = async () => {
  loadingReceivables.value = true
  const res = await request.get('/api/finance/receivables/list', { params: queryForm.value })
  if (res.code === 200) { receivableList.value = res.data; receivableTotal.value = res.meta.total }
  loadingReceivables.value = false
}

// 核心：加载针对某一个账单的所有打款流水
const openTransactionDrawer = async (row) => {
  currentAuditBill.value = row
  transDrawerVisible.value = true
  fetchTransactions(row.id)
}

const fetchTransactions = async (billId) => {
  transLoading.value = true
  const res = await request.get('/api/finance/transactions/list', { params: { bill_id: billId } })
  if (res.code === 200) { transactions.value = res.data }
  transLoading.value = false
}

// 核心：流水级审计
const auditTrans = (transRow, status) => {
  if (status === 2) {
    ElMessageBox.prompt('请输入驳回原因，租户将在H5端收到通知', '核销异常驳回', { confirmButtonText: '强制驳回', cancelButtonText: '取消' }).then(async ({ value }) => {
      executeAudit(transRow.id, 2, value)
    }).catch(() => {})
  } else {
    ElMessageBox.confirm(`系统将直接累加 ￥${transRow.pay_amount} 至总实收账单中，并自动研判结清状态。确认凭证无误？`, '核销确认', { type: 'success' }).then(() => {
      executeAudit(transRow.id, 1, '')
    }).catch(() => {})
  }
}

const executeAudit = async (transId, status, reason) => {
  const res = await request.post('/api/finance/transactions/audit', { transaction_id: transId, status, reject_reason: reason })
  if (res.code === 200) {
    ElMessage.success(res.msg)
    fetchTransactions(currentAuditBill.value.id) 
    fetchReceivables() // 同步刷新底层账单状态大盘
  } else {
    ElMessage.error(res.msg)
  }
}

const fetchCheckouts = async () => {
  loadingCheckouts.value = true
  const res = await request.get('/api/finance/checkouts/list', { params: checkoutPage.value })
  if (res.code === 200) { checkoutList.value = res.data; checkoutTotal.value = res.meta.total }
  loadingCheckouts.value = false
}

const handlePayCheckout = (row) => {
  ElMessageBox.confirm('确认财务已通过网银打款完毕？', '打款结清', { type: 'warning' }).then(async () => {
    const res = await request.post('/api/finance/checkouts/pay', { id: row.id })
    if (res.code === 200) { ElMessage.success('操作成功'); fetchCheckouts() }
  }).catch(() => {})
}

const fetchMeters = async () => {
  loadingMeters.value = true
  const res = await request.get('/api/finance/meters/list', { params: meterPage.value })
  if (res.code === 200) { meterList.value = res.data; meterTotal.value = res.meta.total }
  loadingMeters.value = false
}

const openMeterDialog = (row, type) => {
  meterType.value = type
  meterForm.space_id = row.space_id
  meterForm.enterprise_id = row.enterprise_id
  meterForm.type = type
  
  const now = new Date()
  meterForm.month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
  meterDialogVisible.value = true
}

const submitMeter = async () => {
  if (meterForm.reading <= 0) return ElMessage.error('读数必须大于0')
  const res = await request.post('/api/finance/meters/record', meterForm)
  if (res.code === 200) {
    ElMessage.success(res.msg)
    meterDialogVisible.value = false
    fetchMeters()
  } else {
    ElMessage.error(res.msg)
  }
}

const openHistoryDialog = async (row) => {
  historyDialogVisible.value = true
  const res1 = await request.get('/api/finance/meterHistory', { params: { space_id: row.space_id, type: 1 } })
  const res2 = await request.get('/api/finance/meterHistory', { params: { space_id: row.space_id, type: 2 } })
  historyList.value = [...(res1.data || []), ...(res2.data || [])].sort((a, b) => b.id - a.id)
}

onMounted(() => {
  fetchReceivables()
  fetchCheckouts()
  fetchMeters()
})
</script>

<style scoped>
.finance-container { padding: 20px; }
.pagination-container { display: flex; justify-content: flex-end; margin-top: 20px; padding-top: 15px; border-top: 1px dashed #ebeef5; }
</style>