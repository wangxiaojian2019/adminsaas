<template>
  <div class="reports-container">
    <el-row :gutter="20">
      <el-col :span="24" style="margin-bottom: 20px;">
        <el-card shadow="never" class="chart-card">
          <template #header><div class="card-header">近半年业财流转趋势图 (系统出账总额 vs 实际结清打款)</div></template>
          <div ref="financeChartRef" class="chart-box"></div>
        </el-card>
      </el-col>
    </el-row>
    <el-row :gutter="20">
      <el-col :span="12">
        <el-card shadow="never" class="chart-card">
          <template #header><div class="card-header">项目群空间去化率模型</div></template>
          <div ref="assetChartRef" class="chart-box-small"></div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="never" class="chart-card">
          <template #header><div class="card-header">招商线索库转化漏斗诊断</div></template>
          <div ref="leadChartRef" class="chart-box-small"></div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import * as echarts from 'echarts'
import request from '../../utils/request'
import { ElMessage } from 'element-plus'

const financeChartRef = ref(null)
const assetChartRef = ref(null)
const leadChartRef = ref(null)

let financeChart = null
let assetChart = null
let leadChart = null

const initCharts = () => {
  if (financeChartRef.value) financeChart = echarts.init(financeChartRef.value)
  if (assetChartRef.value) assetChart = echarts.init(assetChartRef.value)
  if (leadChartRef.value) leadChart = echarts.init(leadChartRef.value)
}

const loadFinanceData = async () => {
  try {
    const res = await request.get('/api/reports/finance')
    if (res.code === 200) {
      const months = res.data.map(item => item.month)
      const totals = res.data.map(item => item.total)
      const paids = res.data.map(item => item.paid)

      financeChart.setOption({
        tooltip: { trigger: 'axis', axisPointer: { type: 'cross' } },
        legend: { data: ['系统下发账单总额 (应收)', '已财务核销金额 (实收)'] },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'category', boundaryGap: false, data: months },
        yAxis: { type: 'value', name: '流转金额(元)' },
        series: [
          { name: '系统下发账单总额 (应收)', type: 'line', smooth: true, areaStyle: { opacity: 0.1 }, data: totals, itemStyle: { color: '#f56c6c' } },
          { name: '已财务核销金额 (实收)', type: 'line', smooth: true, areaStyle: { opacity: 0.3 }, data: paids, itemStyle: { color: '#67c23a' } }
        ]
      })
    }
  } catch (e) { ElMessage.error('报表数据解析失败') }
}

const loadAssetData = async () => {
  try {
    const res = await request.get('/api/reports/assets')
    if (res.code === 200) {
      const pieData = res.data.map(item => ({
        name: `${item.building_name} (已租:${item.rented}/总:${item.total})`,
        value: item.rented
      }))

      assetChart.setOption({
        tooltip: { trigger: 'item', formatter: '{a} <br/>{b}: {c} 间 ({d}%)' },
        legend: { orient: 'vertical', left: 'left' },
        series: [
          {
            name: '去化规模比重',
            type: 'pie',
            radius: ['40%', '70%'],
            avoidLabelOverlap: false,
            itemStyle: { borderRadius: 10, borderColor: '#fff', borderWidth: 2 },
            label: { show: false, position: 'center' },
            emphasis: { label: { show: true, fontSize: 14, fontWeight: 'bold' } },
            labelLine: { show: false },
            data: pieData
          }
        ]
      })
    }
  } catch (e) { console.error(e) }
}

const loadLeadData = async () => {
  try {
    const res = await request.get('/api/reports/leads')
    if (res.code === 200) {
      leadChart.setOption({
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'value' },
        yAxis: { type: 'category', data: ['总量基数', '跟进流转中', '落单成约', '沉睡流失'] },
        series: [
          {
            name: '线索存量',
            type: 'bar',
            barWidth: '50%',
            data: [
              { value: res.data.total, itemStyle: { color: '#409eff' } },
              { value: res.data.following, itemStyle: { color: '#e6a23c' } },
              { value: res.data.won, itemStyle: { color: '#67c23a' } },
              { value: res.data.lost, itemStyle: { color: '#909399' } }
            ]
          }
        ]
      })
    }
  } catch (e) { console.error(e) }
}

const handleResize = () => {
  if (financeChart) financeChart.resize()
  if (assetChart) assetChart.resize()
  if (leadChart) leadChart.resize()
}

onMounted(() => {
  nextTick(() => {
    initCharts()
    loadFinanceData()
    loadAssetData()
    loadLeadData()
    window.addEventListener('resize', handleResize)
  })
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
  if (financeChart) financeChart.dispose()
  if (assetChart) assetChart.dispose()
  if (leadChart) leadChart.dispose()
})
</script>

<style scoped>
.reports-container { width: 100%; }
.chart-card { border-radius: 8px; box-shadow: none; border: 1px solid #eef1f6; }
.card-header { font-weight: bold; color: #303133; font-size: 15px; }
.chart-box { height: 350px; width: 100%; }
.chart-box-small { height: 300px; width: 100%; }
</style>