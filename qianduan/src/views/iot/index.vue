<template>
  <div class="iot-container">
    <el-card class="box-card" shadow="hover">
      <template #header>
        <div class="card-header">
          <span><el-icon><Connection /></el-icon> IoT 智能网联中枢与硬件大盘</span>
          <el-button type="success" @click="fetchDevices">实时轮询网联状态</el-button>
        </div>
      </template>

      <el-row :gutter="20" class="iot-dashboard">
        <el-col :span="8">
          <div class="stat-card online">
            <div class="title">硬件在线运行</div><div class="number">{{ stats.online }}</div>
          </div>
        </el-col>
        <el-col :span="8">
          <div class="stat-card offline">
            <div class="title">硬件离线/断联</div><div class="number">{{ stats.offline }}</div>
          </div>
        </el-col>
        <el-col :span="8">
          <div class="stat-card warning">
            <div class="title">故障报警列队</div><div class="number">{{ stats.warning }}</div>
          </div>
        </el-col>
      </el-row>

      <el-table :data="deviceList" border style="width: 100%" v-loading="loading">
        <el-table-column prop="id" label="设备 ID" width="80" align="center" />
        <el-table-column prop="mac_address" label="物理 MAC 地址" width="200" />
        <el-table-column prop="device_type" label="设备类型" width="120">
          <template #default="{ row }">
            <el-tag :type="row.device_type === '智能电表' ? 'warning' : 'primary'">{{ row.device_type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="asset_name" label="强绑定资产/房源" />
        <el-table-column prop="driver_key" label="底层驱动模型" width="150">
          <template #default="{ row }">
            <el-tag type="info" effect="dark">{{ row.driver_key }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="通讯状态" width="100" align="center">
          <template #default="{ row }">
            <el-switch v-model="row.is_online" disabled active-color="#13ce66" inactive-color="#909399" />
          </template>
        </el-table-column>
        <el-table-column label="核心远程管控面板" width="260" align="center">
          <template #default="{ row }">
            <el-button size="small" type="primary" @click="sendCommand(row.id, 'turn_on')">通电 / 开闸</el-button>
            <el-button size="small" type="danger" @click="sendCommand(row.id, 'turn_off')">强制断电拉闸</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Connection } from '@element-plus/icons-vue'
import request from '@/utils/request'

const loading = ref(false)
const deviceList = ref([])
const stats = ref({ online: 0, offline: 0, warning: 0 })

// 真实接口：获取硬件列表
const fetchDevices = async () => {
  loading.value = true
  const res = await request.get('/api/v1/iot/list')
  if (res.code === 200) {
    deviceList.value = res.data
    stats.value = res.stats
  }
  loading.value = false
}

onMounted(() => fetchDevices())

// 真实接口：下发物理指令
const sendCommand = async (deviceId, action) => {
  const actionText = action === 'turn_on' ? '【通电/恢复门禁】' : '【断电拉闸/禁用门禁】'
  const actionType = action === 'turn_on' ? 'success' : 'error'
  
  ElMessageBox.confirm(`即将向设备下发 ${actionText} 指令，该操作会直接穿透驱动层影响现实硬件，是否继续？`, '执行高危物理动作警告', {
    confirmButtonText: '立即执行',
    cancelButtonText: '取消',
    type: 'warning',
  }).then(async () => {
    const res = await request.post('/api/v1/iot/control', { device_id: deviceId, action: action })
    if (res.code === 200) {
      ElMessage({ message: res.msg, type: actionType, duration: 4000 })
      fetchDevices() // 刷新状态
    } else {
      ElMessage.error(res.msg)
    }
  })
}
</script>

<style scoped>
.iot-container { padding: 20px; }
.card-header { display: flex; justify-content: space-between; align-items: center; font-weight: bold; }
.iot-dashboard { margin-bottom: 20px; }
.stat-card { padding: 20px; border-radius: 8px; color: #fff; text-align: center; }
.stat-card.online { background: linear-gradient(135deg, #67C23A 0%, #a0c23a 100%); }
.stat-card.offline { background: linear-gradient(135deg, #909399 0%, #b3b6bd 100%); }
.stat-card.warning { background: linear-gradient(135deg, #F56C6C 0%, #f58888 100%); }
.stat-card .title { font-size: 14px; margin-bottom: 10px; opacity: 0.9; }
.stat-card .number { font-size: 28px; font-weight: bold; }
</style>