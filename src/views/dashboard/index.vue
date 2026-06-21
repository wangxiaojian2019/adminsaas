<template>
  <div class="dashboard-container">
    <el-row :gutter="20" class="stat-row">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card" v-loading="loading">
          <div class="stat-title">全盘空间去化率</div>
          <div class="stat-value text-primary">
            {{ formatRate(dashboardData.space?.rented, dashboardData.space?.total) }}%
          </div>
          <div class="stat-meta">已租 {{ dashboardData.space?.rented || 0 }} / 总数 {{ dashboardData.space?.total || 0 }} 间</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card" v-loading="loading">
          <div class="stat-title">历史总应收资金净额</div>
          <div class="stat-value text-danger">¥ {{ formatMoney(dashboardData.finance?.total_receivable) }}</div>
          <div class="stat-meta">
            已核销实收 ¥ {{ formatMoney(dashboardData.finance?.actual_received) }}
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card" v-loading="loading">
          <div class="stat-title">资金整体回笼率</div>
          <div class="stat-value text-success">
            {{ formatRate(dashboardData.finance?.actual_received, dashboardData.finance?.total_receivable) }}%
          </div>
          <div class="stat-meta">基于全生命周期应收/实收比推算</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card" v-loading="loading">
          <div class="stat-title">活跃企业 / 存量契约</div>
          <div class="stat-value text-warning">
            {{ dashboardData.enterprise_count || 0 }} <span style="font-size: 14px; color:#909399">家</span> / 
            {{ dashboardData.contract_count || 0 }} <span style="font-size: 14px; color:#909399">份</span>
          </div>
          <div class="stat-meta">已排除作废合同与历史流失租户</div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px;">
      <el-col :span="12">
        <el-card shadow="never" class="panel-card">
          <template #header>
            <div class="panel-header">
              <span><el-icon><Money /></el-icon> 财务核销预警 (租户已打款待审)</span>
              <el-button type="primary" link @click="router.push('/finance')">前往核销中心 →</el-button>
            </div>
          </template>
          <el-table :data="dashboardData.pending_bills" style="width: 100%" :show-header="false">
            <el-table-column prop="enterprise_name" label="打款企业" min-width="150" show-overflow-tooltip>
              <template #default="{ row }"><strong>{{ row.enterprise_name }}</strong></template>
            </el-table-column>
            <el-table-column label="待核金额" width="120" align="right">
              <template #default="{ row }"><span class="text-danger">¥{{ row.amount }}</span></template>
            </el-table-column>
            <el-table-column prop="created_at" label="上传时间" width="160" align="right">
              <template #default="{ row }"><span style="font-size: 12px; color: #909399;">{{ row.created_at }}</span></template>
            </el-table-column>
          </el-table>
          <el-empty v-if="!dashboardData.pending_bills || dashboardData.pending_bills.length === 0" description="暂无待核销的单据" :image-size="60" />
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="never" class="panel-card">
          <template #header>
            <div class="panel-header">
              <span><el-icon><Position /></el-icon> 后勤调度预警 (外勤工单流转阻断)</span>
              <el-button type="primary" link @click="router.push('/services')">前往中控室派单 →</el-button>
            </div>
          </template>
          <div v-if="dashboardData.urgent_orders && dashboardData.urgent_orders.length > 0">
            <div v-for="order in dashboardData.urgent_orders" :key="order.id" class="order-alert-item">
              <el-tag size="small" :type="order.status === 1 ? 'danger' : 'warning'">{{ order.status === 1 ? '待指派' : '外勤完工待验' }}</el-tag>
              <span class="order-title">{{ order.title }}</span>
              <span class="order-time">{{ order.created_at }}</span>
            </div>
          </div>
          <el-empty v-else description="所有流转调度正常，暂无积压工单" :image-size="60" />
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import request from '../../utils/request'

const router = useRouter()
const loading = ref(false)
const dashboardData = ref({})

const fetchDashboard = async () => {
  loading.value = true
  try {
    const res = await request.get('/api/dashboard')
    if (res.code === 200) {
      dashboardData.value = res.data
    }
  } finally {
    loading.value = false
  }
}

const formatRate = (part, total) => {
  if (!total || total === 0) return '0.00'
  return ((part / total) * 100).toFixed(2)
}

const formatMoney = (val) => {
  if (!val) return '0.00'
  return Number(val).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

onMounted(() => {
  fetchDashboard()
})
</script>

<style scoped>
.dashboard-container { width: 100%; }
.stat-card { text-align: center; padding: 10px 0; border-radius: 8px; box-shadow: none; border: 1px solid #eef1f6; }
.stat-title { font-size: 14px; color: #909399; margin-bottom: 10px; font-weight: bold; }
.stat-value { font-size: 28px; font-weight: bold; font-family: monospace; margin-bottom: 5px; }
.stat-meta { font-size: 12px; color: #a8abb2; }

.text-primary { color: #409eff; }
.text-danger { color: #f56c6c; }
.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }

.panel-card { border-radius: 8px; box-shadow: none; border: 1px solid #eef1f6; min-height: 280px; }
.panel-header { display: flex; justify-content: space-between; align-items: center; font-weight: bold; color: #303133; }

.order-alert-item { display: flex; align-items: center; padding: 12px 10px; border-bottom: 1px dashed #f0f2f5; }
.order-alert-item:last-child { border-bottom: none; }
.order-title { flex: 1; margin-left: 10px; font-size: 14px; color: #303133; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.order-time { font-size: 12px; color: #909399; font-family: monospace; }
</style>