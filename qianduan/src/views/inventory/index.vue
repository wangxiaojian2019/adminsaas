<template>
  <div class="inventory-container">
    <el-row :gutter="20" class="stat-row">
      <el-col :span="6">
        <div class="stat-card total-value">
          <div class="label">当前库存总货值 (元)</div><div class="value">￥{{ stats.totalValue }}</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card warning">
          <div class="label">库存告警物料 (SKU数)</div><div class="value">{{ stats.warningCount }}</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card cost">
          <div class="label">本月工单核销成本 (元)</div><div class="value">￥{{ stats.monthCost }}</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card inbound">
          <div class="label">本月新增采购入库 (元)</div><div class="value">￥{{ stats.monthInbound }}</div>
        </div>
      </el-col>
    </el-row>

    <el-card class="main-card" shadow="never">
      <el-tabs v-model="activeTab" class="inventory-tabs">
        <el-tab-pane label="实时库存与资产台账" name="stock">
          <div class="toolbar">
            <el-form :inline="true" :model="queryForm" size="default">
              <el-form-item label="物料名称">
                <el-input v-model="queryForm.keyword" placeholder="输入SKU检索" clearable />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="fetchStock">查询</el-button>
              </el-form-item>
            </el-form>
            <div>
              <el-button type="success" @click="openInboundDialog">采购入库 (计价)</el-button>
              <el-button type="warning" @click="openOutboundDialog">工单领料出库</el-button>
            </div>
          </div>
          <el-table :data="stockData" border style="width: 100%" v-loading="loading">
            <el-table-column prop="sku_code" label="物料编码" width="120" />
            <el-table-column prop="name" label="物料名称" width="180" />
            <el-table-column prop="category" label="分类" width="120" />
            <el-table-column label="当前结余库存" width="140" align="center">
              <template #default="{ row }">
                <span :class="{'danger-text': row.qty <= row.min_stock}" style="font-size: 16px; font-weight: bold;">{{ row.qty }} {{ row.unit }}</span>
                <el-tag v-if="row.qty <= row.min_stock" type="danger" size="small" style="margin-left: 5px;">预警</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="加权平均单价" width="160">
              <template #default="{ row }">￥{{ row.avg_price }} / {{ row.unit }}</template>
            </el-table-column>
            <el-table-column label="在库总货值" width="140">
              <template #default="{ row }">￥{{ (row.qty * row.avg_price).toFixed(2) }}</template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane label="工单领料核销流水" name="outbound_log">
          <el-table :data="outboundLogs" border style="width: 100%">
            <el-table-column prop="created_at" label="出库时间" width="170" />
            <el-table-column prop="work_order_no" label="关联工单号" width="160" />
            <el-table-column prop="material_name" label="领用物料" width="180" />
            <el-table-column prop="qty" label="出库数量" width="100">
              <template #default="{ row }">-{{ row.qty }}</template>
            </el-table-column>
            <el-table-column prop="total_cost" label="计入工单成本" width="140">
              <template #default="{ row }"><span style="color: #F56C6C; font-weight: bold;">￥{{ row.total_cost }}</span></template>
            </el-table-column>
            <el-table-column prop="worker" label="领料人" width="140" />
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <el-dialog title="新增采购入库 (影响成本单价)" v-model="inboundVisible" width="550px">
      <el-form :model="inboundForm" label-width="120px">
        <el-form-item label="选择入库物料">
          <el-select v-model="inboundForm.sku_id" style="width: 100%" @change="handleInboundSkuChange">
            <el-option v-for="item in stockData" :key="item.id" :label="`${item.sku_code} - ${item.name}`" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="本次入库数量"><el-input-number v-model="inboundForm.qty" :min="1" /></el-form-item>
        <el-form-item label="本次采购单价"><el-input-number v-model="inboundForm.price" :precision="2" :step="0.5" :min="0.01" /></el-form-item>
        <el-form-item label="系统预演新单价" v-if="inboundForm.sku_id && inboundForm.qty > 0 && inboundForm.price > 0">
          <el-tag type="warning" size="large">预计重新核定单价：￥{{ previewNewAvgPrice }} / {{ selectedSkuUnit }}</el-tag>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="inboundVisible = false">取消</el-button>
        <el-button type="success" @click="submitInbound">确认入库</el-button>
      </template>
    </el-dialog>

    <el-dialog title="外勤工单领料出库" v-model="outboundVisible" width="550px">
      <el-form :model="outboundForm" label-width="120px">
        <el-form-item label="关联工单号"><el-input v-model="outboundForm.work_order_no" placeholder="如：WO20260601-002" /></el-form-item>
        <el-form-item label="选择出库物料">
          <el-select v-model="outboundForm.sku_id" style="width: 100%">
            <el-option v-for="item in stockData" :key="item.id" :label="`${item.name} (库存: ${item.qty} ${item.unit})`" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="出库数量"><el-input-number v-model="outboundForm.qty" :min="1" /></el-form-item>
        <el-form-item label="领料人"><el-input v-model="outboundForm.worker" placeholder="如：李师傅" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="outboundVisible = false">取消</el-button>
        <el-button type="primary" @click="submitOutbound">确认出库扣减</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import request from '@/utils/request' // 接入真实 API

const activeTab = ref('stock')
const loading = ref(false)
const inboundVisible = ref(false)
const outboundVisible = ref(false)
const queryForm = ref({ keyword: '', category: '' })

const stockData = ref([])
const outboundLogs = ref([])
const stats = ref({ totalValue: 0, warningCount: 0, monthCost: 0, monthInbound: 0 })

// 真实接口：拉取大盘库存与面板统计
const fetchStock = async () => {
  loading.value = true
  const res = await request.get('/api/v1/inventory/list', { params: queryForm.value })
  if (res.code === 200) {
    stockData.value = res.data
    stats.value = res.stats
  }
  loading.value = false
}

// 真实接口：拉取出库流水
const fetchLogs = async () => {
  const res = await request.get('/api/v1/inventory/logs')
  if (res.code === 200) outboundLogs.value = res.data
}

onMounted(() => {
  fetchStock()
  fetchLogs()
})

const inboundForm = ref({ sku_id: null, qty: 10, price: 0 })
const selectedSkuUnit = ref('')

const handleInboundSkuChange = (val) => {
  const item = stockData.value.find(s => s.id === val)
  if (item) {
    selectedSkuUnit.value = item.unit
    inboundForm.value.price = item.avg_price
  }
}

const previewNewAvgPrice = computed(() => {
  const item = stockData.value.find(s => s.id === inboundForm.value.sku_id)
  if (!item) return 0
  const oldTotal = item.qty * item.avg_price
  const newTotal = inboundForm.value.qty * inboundForm.value.price
  return ((oldTotal + newTotal) / (item.qty + inboundForm.value.qty)).toFixed(2)
})

const openInboundDialog = () => { inboundForm.value = { sku_id: null, qty: 10, price: 0 }; inboundVisible.value = true }

// 真实接口：提交入库计算
const submitInbound = async () => {
  if (!inboundForm.value.sku_id || inboundForm.value.qty <= 0) return ElMessage.error('入库参数错误')
  const res = await request.post('/api/v1/inventory/inbound', inboundForm.value)
  if (res.code === 200) {
    ElMessage.success(res.msg)
    inboundVisible.value = false
    fetchStock() // 刷新大盘
  } else {
    ElMessage.error(res.msg)
  }
}

const outboundForm = ref({ sku_id: null, qty: 1, work_order_no: '', worker: '' })
const openOutboundDialog = () => { outboundForm.value = { sku_id: null, qty: 1, work_order_no: '', worker: '' }; outboundVisible.value = true }

// 真实接口：提交领料出库
const submitOutbound = async () => {
  if (!outboundForm.value.sku_id || !outboundForm.value.work_order_no) return ElMessage.error('参数不完整')
  const res = await request.post('/api/v1/inventory/outbound', outboundForm.value)
  if (res.code === 200) {
    ElMessageBox.alert(res.msg, '业财联动执行结果', { type: 'success' })
    outboundVisible.value = false
    fetchStock() // 刷新库存
    fetchLogs()  // 刷新流水
  } else {
    ElMessage.error(res.msg)
  }
}
</script>

<style scoped>
.inventory-container { padding: 20px; }
.stat-row { margin-bottom: 20px; }
.stat-card { padding: 20px; border-radius: 6px; color: #fff; box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05); }
.stat-card.total-value { background: linear-gradient(135deg, #409EFF 0%, #66b1ff 100%); }
.stat-card.warning { background: linear-gradient(135deg, #F56C6C 0%, #f78989 100%); }
.stat-card.cost { background: linear-gradient(135deg, #E6A23C 0%, #f3d19e 100%); }
.stat-card.inbound { background: linear-gradient(135deg, #67C23A 0%, #85ce61 100%); }
.stat-card .label { font-size: 14px; opacity: 0.9; margin-bottom: 8px; }
.stat-card .value { font-size: 26px; font-weight: bold; }
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.danger-text { color: #F56C6C; }
</style>