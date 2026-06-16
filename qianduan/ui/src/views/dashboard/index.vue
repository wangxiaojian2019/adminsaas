<template>
  <div class="dashboard-container" v-loading="loading">
    <div style="display: flex; justify-content: flex-end; margin-bottom: 15px;">
      <el-button type="warning" icon="Download" size="small" @click="exportData">带水印导出大盘明细</el-button>
    </div>
    
    <el-row :gutter="20" class="panel-group">
      <el-col :span="4"><el-card shadow="hover" class="data-card"><div class="label">总房源数 (间)</div><div class="value">{{ assetData.total_spaces }}</div></el-card></el-col>
      <el-col :span="4"><el-card shadow="hover" class="data-card"><div class="label">已租房间 (间)</div><div class="value text-danger">{{ assetData.rented_spaces }}</div></el-card></el-col>
      <el-col :span="4"><el-card shadow="hover" class="data-card"><div class="label">综合空置率</div><div class="value text-success">{{ assetData.vacancy_rate }}</div></el-card></el-col>
      <el-col :span="4"><el-card shadow="hover" class="data-card"><div class="label">累计应收 (元)</div><div class="value">¥{{ financeData.total_receivable }}</div></el-card></el-col>
      <el-col :span="4"><el-card shadow="hover" class="data-card"><div class="label">累计实收 (元)</div><div class="value text-success">¥{{ financeData.total_received }}</div></el-card></el-col>
      <el-col :span="4"><el-card shadow="hover" class="data-card"><div class="label">待收欠款 (元)</div><div class="value text-danger">¥{{ financeData.total_unpaid }}</div></el-card></el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px;">
      <el-col :span="16">
        <el-card shadow="never" class="heatmap-card">
          <template #header>
            <div class="heatmap-header">
              <span style="font-weight: bold;">楼宇实时销控热力图</span>
              <div class="legend">
                <span class="legend-item"><span class="box bg-empty"></span>空置可招商</span>
                <span class="legend-item"><span class="box bg-rented"></span>在租运营中</span>
                <span class="legend-item"><span class="box bg-repair"></span>设备维修中</span>
              </div>
            </div>
          </template>
          
          <div class="heatmap-body">
            <el-empty v-if="heatMapTree.length === 0" description="暂无空间资产数据" />
            <div v-else v-for="building in heatMapTree" :key="building.building_name" class="building-block">
              <h4 class="building-title"><el-icon><OfficeBuilding /></el-icon> {{ building.building_name }}</h4>
              <div class="floor-list">
                <div v-for="floor in building.floors" :key="floor.floor" class="floor-row">
                  <div class="floor-label">{{ floor.floor }}F</div>
                  <div class="rooms-container">
                    <el-tooltip v-for="room in floor.rooms" :key="room.room_number" effect="dark" :content="`${room.room_number} - ${getStatusText(room.status)}`" placement="top">
                      <div class="room-box" :class="getStatusClass(room.status)">
                        {{ room.room_number }}
                      </div>
                    </el-tooltip>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </el-card>
      </el-col>

      <el-col :span="8">
        <el-card shadow="never" class="alert-card">
          <template #header><span style="font-weight: bold; color: #f56c6c;"><el-icon><WarnTriangleFilled /></el-icon> 突发安全隐患实时播报</span></template>
          <el-empty v-if="patrolAlerts.length === 0" description="园区安防网格内暂无未处理隐患，健康度100%" :image-size="80" />
          <el-timeline v-else style="padding-top: 10px;">
            <el-timeline-item v-for="(alert, index) in patrolAlerts" :key="index" type="danger" :timestamp="alert.check_time" placement="top">
              <el-card shadow="hover" class="alert-item">
                <div style="font-weight: bold; font-size: 13px; margin-bottom: 5px;">{{ alert.location }}</div>
                <div style="font-size: 12px; color: #606266; margin-bottom: 5px;">{{ alert.remarks }}</div>
                <div style="font-size: 11px; color: #909399;">上报人: {{ alert.worker_name }}</div>
              </el-card>
            </el-timeline-item>
          </el-timeline>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import request from '../../utils/request'
import { ElMessage } from 'element-plus'

const loading = ref(false)
const assetData = ref({ total_spaces: 0, rented_spaces: 0, vacancy_rate: '0%' })
const financeData = ref({ total_receivable: '0.00', total_received: '0.00', total_unpaid: '0.00' })
const heatMapTree = ref([])
const patrolAlerts = ref([])

const fetchData = async () => {
  loading.value = true
  try {
    const res = await request.get('/api/dashboard')
    if (res.code === 200 || res.code === 500) {
      if (res.code === 500) ElMessage.error(res.msg) 
      const data = res.data || {}
      assetData.value = data.asset || assetData.value
      financeData.value = data.finance || financeData.value
      patrolAlerts.value = data.patrol_alerts || []
      
      const rawHeatMap = data.heat_map || []
      buildHeatmapTree(rawHeatMap)
    }
  } catch (error) {
    ElMessage.error('网络请求异常，无法拉取指挥舱数据')
  } finally {
    loading.value = false
  }
}

const buildHeatmapTree = (flatData) => {
  if (!Array.isArray(flatData) || flatData.length === 0) {
    heatMapTree.value = []
    return
  }
  const tree = {}
  flatData.forEach(item => {
    const bName = item.building_name || '未命名大厦'
    const fNum = item.floor || 1
    if (!tree[bName]) tree[bName] = {}
    if (!tree[bName][fNum]) tree[bName][fNum] = []
    tree[bName][fNum].push(item)
  })

  const result = []
  for (const building in tree) {
    const floors = []
    for (const floor in tree[building]) {
      floors.push({ floor: parseInt(floor), rooms: tree[building][floor] })
    }
    floors.sort((a, b) => b.floor - a.floor)
    result.push({ building_name: building, floors })
  }
  heatMapTree.value = result
}

const exportData = async () => {
  ElMessage.info('加密导出中...')
  const token = localStorage.getItem('saas_token')
  try {
    const res = await fetch(`http://47.120.52.65:8787/api/export/download?module=dashboard`, {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    const blob = await res.blob()
    const a = document.createElement('a')
    a.href = window.URL.createObjectURL(blob)
    a.download = `运营指挥大盘明细_${new Date().getTime()}.csv`
    a.click()
    ElMessage.success('导出成功，已入库审计')
  } catch (e) {
    ElMessage.error('导出失败')
  }
}

const getStatusText = (status) => ({ 0: '空置招商中', 1: '在租运营中', 2: '设备维修中' }[status] || '未知')
const getStatusClass = (status) => ({ 0: 'bg-empty', 1: 'bg-rented', 2: 'bg-repair' }[status] || 'bg-empty')

onMounted(fetchData)
</script>

<style scoped>
.dashboard-container { padding-bottom: 20px; }
.panel-group { margin-bottom: 20px; }
.data-card { text-align: center; border-radius: 6px; }
.data-card .label { font-size: 13px; color: #909399; margin-bottom: 8px; }
.data-card .value { font-size: 24px; font-weight: bold; color: #303133; font-family: monospace; }
.text-danger { color: #f56c6c !important; }
.text-success { color: #67c23a !important; }

.heatmap-header { display: flex; justify-content: space-between; align-items: center; }
.legend { display: flex; gap: 15px; font-size: 12px; color: #606266; }
.legend-item { display: flex; align-items: center; gap: 5px; }
.box { width: 12px; height: 12px; border-radius: 2px; }

.building-block { margin-bottom: 25px; }
.building-title { margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 1px solid #ebeef5; color: #303133; font-size: 15px; }
.floor-row { display: flex; align-items: center; margin-bottom: 10px; }
.floor-label { width: 40px; font-weight: bold; color: #909399; font-size: 13px; }
.rooms-container { display: flex; flex-wrap: wrap; gap: 8px; flex: 1; }
.room-box { padding: 4px 10px; border-radius: 4px; font-size: 12px; cursor: pointer; color: #fff; font-weight: bold; text-align: center; min-width: 45px; transition: transform 0.1s; }
.room-box:hover { transform: scale(1.05); }

.bg-empty { background-color: #67c23a; } 
.bg-rented { background-color: #f56c6c; } 
.bg-repair { background-color: #e6a23c; } 

.alert-card { height: 100%; border-color: #fde2e2; }
.alert-item { background-color: #fffaf9; border-color: #fde2e2; }
</style>