<template>
  <div class="reports-container" v-loading="loading">
    <el-row :gutter="20">
      <el-col :span="24">
        <el-card shadow="never" class="chart-card">
          <template #header>
            <div class="card-header">
              <span class="title-text"><el-icon><Money /></el-icon> 业财一体化月度营收收缴率趋势大盘</span>
              <el-button type="warning" icon="Download" size="small" @click="exportReport('finance')">导出流水</el-button>
            </div>
          </template>
          <div ref="financeChartRef" class="chart-box"></div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px;">
      <el-col :span="12">
        <el-card shadow="never" class="chart-card">
          <template #header>
            <div class="card-header">
              <span class="title-text"><el-icon><PieChart /></el-icon> 园区空间资产类型去化与面积结构分布</span>
              <el-button type="warning" icon="Download" size="small" @click="exportReport('buildings')">导出资产</el-button>
            </div>
          </template>
          <div ref="assetChartRef" class="chart-box"></div>
        </el-card>
      </el-col>

      <el-col :span="12">
        <el-card shadow="never" class="chart-card">
          <template #header>
            <div class="card-header">
              <span class="title-text"><el-icon><TrendCharts /></el-icon> 招商线索中心核心转化漏斗分析</span>
              <el-button type="warning" icon="Download" size="small" @click="exportReport('leads')">导出线索</el-button>
            </div>
          </template>
          <div ref="leadChartRef" class="chart-box"></div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { ElMessage } from 'element-plus'
import * as echarts from 'echarts'
import request from '../../utils/request'

const loading = ref(false)

// 图表 DOM 节点容器引用
const financeChartRef = ref(null)
const assetChartRef = ref(null)
const leadChartRef = ref(null)

// Echarts 实例引用对象
let financeChart = null
let assetChart = null
let leadChart = null

const initCharts = async () => {
  loading.value = true
  try {
    const [finRes, leadRes, assetRes] = await Promise.all([
      request.get('/api/reports/finance'),
      request.get('/api/reports/leads'),
      request.get('/api/reports/assets')
    ])

    // 1. 渲染财务柱状折线混合图表
    if (finRes.code === 200 && financeChartRef.value) {
      financeChart = echarts.init(financeChartRef.value)
      const months = finRes.data.map(item => item.month)
      const receivables = finRes.data.map(item => item.total_receivable)
      const received = finRes.data.map(item => item.total_received)
      
      financeChart.setOption({
        tooltip: { trigger: 'axis', axisPointer: { type: 'cross' } },
        legend: { data: ['应收总额', '实收总额', '实收完成率'] },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: [{ type: 'category', data: months, axisPointer: { type: 'shadow' } }],
        yAxis: [
          { type: 'value', name: '金额 (元)', axisLabel: { formatter: '¥{value}' } },
          { type: 'value', name: '完成率', min: 0, max: 100, axisLabel: { formatter: '{value}%' } }
        ],
        series: [
          { name: '应收总额', type: 'bar', barWidth: '25%', data: receivables, itemStyle: { color: '#409eff' } },
          { name: '实收总额', type: 'bar', barWidth: '25%', data: received, itemStyle: { color: '#67c23a' } },
          {
            name: '实收完成率',
            type: 'line',
            yAxisIndex: 1,
            data: receivables.map((rec, i) => rec > 0 ? round((received[i] / rec) * 100, 1) : 0),
            itemStyle: { color: '#e6a23c' },
            lineStyle: { width: 3 }
          }
        ]
      })
    }

    // 2. 渲染资产面积结构饼图
    if (assetRes.code === 200 && assetChartRef.value) {
      assetChart = echarts.init(assetChartRef.value)
      const statusMap = { 0: '空置可租', 1: '在租运营', 2: '设备维修', 3: '装修施工' }
      const pieData = assetRes.data.map(item => ({
        name: statusMap[item.status] || '未知',
        value: parseFloat(item.total_area)
      }))

      assetChart.setOption({
        tooltip: { trigger: 'item', formatter: '{b} : {c} ㎡ ({d}%)' },
        legend: { bottom: '0', left: 'center' },
        series: [{
          type: 'pie',
          radius: '65%',
          center: ['50%', '45%'],
          data: pieData,
          emphasis: { itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0, 0, 0, 0.5)' } }
        }]
      })
    }

    // 3. 渲染招商转化分析漏斗图
    if (leadRes.code === 200 && leadChartRef.value) {
      leadChart = echarts.init(leadChartRef.value)
      const statusCounts = { '跟进中': 0, '已成单': 0, '已流失': 0 }
      leadRes.data.status.forEach(item => {
        if (item.status === 1) statusCounts['跟进中'] = item.count
        if (item.status === 2) statusCounts['已成单'] = item.count
        if (item.status === 3) statusCounts['已流失'] = item.count
      })

      // 漏斗金字塔递进逻辑组装
      const funnelData = [
        { value: statusCounts['跟进中'] + statusCounts['已成单'] + statusCounts['已流失'], name: '获客入池总线索' },
        { value: statusCounts['跟进中'] + statusCounts['已成单'], name: '深度建档跟进线索' },
        { value: statusCounts['已成单'], name: '终审签约成单线索' }
      ]

      leadChart.setOption({
        tooltip: { trigger: 'item', formatter: '{b} : {c}个' },
        legend: { bottom: '0', left: 'center' },
        series: [{
          type: 'funnel',
          left: '10%',
          top: 40,
          bottom: 60,
          width: '80%',
          min: 0,
          max: 100,
          minSize: '0%',
          maxSize: '100%',
          sort: 'descending',
          gap: 2,
          label: { show: true, position: 'inside' },
          labelLine: { show: false },
          itemStyle: { borderColor: '#fff', borderWidth: 1 },
          data: funnelData
        }]
      })
    }
  } catch (error) {
    ElMessage.error('BI 大盘图表核心组件加载失败')
  } finally {
    loading.value = false
  }
}

const exportReport = async (moduleName) => {
  ElMessage.info('正在拉取溯源数字加密档案并盖章...')
  const token = localStorage.getItem('saas_token')
  try {
    const res = await fetch(`http://47.120.52.65:8787/api/export/download?module=${moduleName}`, {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    if (res.status === 200) {
      const blob = await res.blob()
      const a = document.createElement('a')
      a.href = window.URL.createObjectURL(blob)
      a.download = `BI审计衍生报表_${moduleName}_${new Date().getTime()}.csv`
      a.click()
      ElMessage.success('报表数据审计外发归档完毕')
    }
  } catch (e) {
    ElMessage.error('导出被底层网关安全拦截')
  }
}

const handleResize = () => {
  financeChart && financeChart.resize()
  assetChart && assetChart.resize()
  leadChart && leadChart.resize()
}

const round = (value, decimals) => {
  return Number(Math.round(value + 'e' + decimals) + 'e-' + decimals)
}

onMounted(() => {
  initCharts()
  window.addEventListener('resize', handleResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize)
  financeChart && financeChart.dispose()
  assetChart && assetChart.dispose()
  leadChart && leadChart.dispose()
})
</script>

<style scoped>
.reports-container { width: 100%; }
.chart-card { border-radius: 4px; box-shadow: none; background-color: #fff; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.title-text { font-weight: bold; font-size: 15px; color: #2c3e50; display: flex; align-items: center; gap: 6px; }
.chart-box { width: 100%; height: 350px; margin-top: 15px; }
</style>