<template>
  <div class="matrix-container">
    <div class="legend-bar">
      <div class="legend-item"><span class="color-box vacant"></span> 空置预租 (vacant)</div>
      <div class="legend-item"><span class="color-box rented"></span> 已出租 (rented)</div>
      <div class="legend-item"><span class="color-box lock"></span> 锁定/保留 (lock)</div>
    </div>

    <div v-if="loading" class="loading">数据加载中...</div>
    <div v-else-if="!projectData" class="empty">暂无资产数据</div>
    
    <div v-else class="project-wrapper">
      <h2 class="project-title">{{ projectData.asset_name }} 销控总览</h2>
      
      <div class="building-tabs">
        <div 
          v-for="(building, index) in projectData.children" 
          :key="building.id"
          class="tab-item"
          :class="{ active: currentBuildingIndex === index }"
          @click="currentBuildingIndex = index"
        >
          {{ building.asset_name }}
        </div>
      </div>

      <div class="building-content" v-if="currentBuilding">
        <div 
          v-for="floor in currentBuilding.children" 
          :key="floor.id" 
          class="floor-row"
        >
          <div class="floor-label">
            {{ floor.asset_name }}
          </div>
          
          <div class="room-grid">
            <div 
              v-for="room in floor.children" 
              :key="room.id"
              class="room-card"
              :class="room.rent_status"
              @click="handleRoomClick(room)"
            >
              <div class="room-name">{{ room.asset_name }}</div>
              <div class="room-area">{{ room.rentable_area }} ㎡</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

// 响应式状态
const loading = ref(true)
const projectData = ref(null)
const currentBuildingIndex = ref(0)

// 注意：这里替换为你宝塔服务器的实际IP
// 如果前端和后端部署在同一个域名下，可使用相对路径 '/api/v1/assets/tree'
const API_URL = 'http://47.120.52.65:8787/api/v1/assets/tree'

// 获取拓扑树数据
const fetchTreeData = async () => {
  try {
    const response = await fetch(API_URL, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'tenant-id': '1' // 强制传入租户ID以通过中间件校验
      }
    })
    
    const res = await response.json()
    if (res.code === 200 && res.data.length > 0) {
      // 默认取第一个项目作为根节点
      projectData.value = res.data[0] 
    }
  } catch (error) {
    console.error('获取资产数据失败:', error)
    alert('网络请求失败，请检查宝塔 8787 端口是否放行，或控制台报错信息。')
  } finally {
    loading.value = false
  }
}

// 计算当前选中的楼栋数据
const currentBuilding = computed(() => {
  if (!projectData.value || !projectData.value.children) return null
  return projectData.value.children[currentBuildingIndex.value]
})

// 房间点击交互（预留给右侧弹窗或抽屉）
const handleRoomClick = (room) => {
  console.log('点击了房间:', room)
  alert(`选中房源：${room.asset_name}\n状态：${room.rent_status}\n面积：${room.rentable_area}㎡\n\n接下来的动作：在这里触发右侧抽屉，载入对应的合同表单或IoT设备控制面板。`)
}

onMounted(() => {
  fetchTreeData()
})
</script>

<style scoped>
.matrix-container {
  padding: 20px;
  background-color: #f5f7fa;
  min-height: 100vh;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

.legend-bar {
  display: flex;
  gap: 20px;
  margin-bottom: 20px;
  padding: 15px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05);
}

.legend-item {
  display: flex;
  align-items: center;
  font-size: 14px;
  color: #606266;
}

.color-box {
  width: 16px;
  height: 16px;
  border-radius: 4px;
  margin-right: 8px;
}

/* 状态颜色定义 */
.color-box.vacant, .room-card.vacant { background-color: #f0f9eb; border: 1px solid #c2e7b0; color: #67c23a; }
.color-box.rented, .room-card.rented { background-color: #fef0f0; border: 1px solid #fbc4c4; color: #f56c6c; }
.color-box.lock, .room-card.lock { background-color: #f4f4f5; border: 1px solid #d3d4d6; color: #909399; }

.project-wrapper {
  background: #fff;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05);
}

.project-title {
  margin: 0 0 20px 0;
  font-size: 20px;
  color: #303133;
}

.building-tabs {
  display: flex;
  border-bottom: 2px solid #e4e7ed;
  margin-bottom: 20px;
}

.tab-item {
  padding: 10px 20px;
  cursor: pointer;
  font-size: 16px;
  color: #909399;
  position: relative;
}

.tab-item:hover {
  color: #409eff;
}

.tab-item.active {
  color: #409eff;
  font-weight: bold;
}

.tab-item.active::after {
  content: '';
  position: absolute;
  bottom: -2px;
  left: 0;
  width: 100%;
  height: 2px;
  background-color: #409eff;
}

.floor-row {
  display: flex;
  margin-bottom: 15px;
  border-bottom: 1px dashed #ebeef5;
  padding-bottom: 15px;
}

.floor-label {
  width: 80px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  color: #606266;
  background: #f4f4f5;
  border-radius: 4px;
  margin-right: 20px;
}

.room-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  flex: 1;
}

.room-card {
  width: 120px;
  height: 80px;
  border-radius: 6px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  cursor: pointer;
  transition: all 0.2s;
}

.room-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 4px 12px 0 rgba(0,0,0,0.1);
}

.room-name {
  font-size: 16px;
  font-weight: bold;
  margin-bottom: 8px;
}

.room-area {
  font-size: 12px;
}

.loading, .empty {
  text-align: center;
  padding: 50px;
  color: #909399;
}
</style>