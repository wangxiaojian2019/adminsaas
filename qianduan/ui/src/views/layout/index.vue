<template>
  <el-container class="layout-container">
    <el-aside width="220px" class="aside">
      <div class="logo">高新科技产业园</div>
      
      <el-menu 
        :default-active="$route.path" 
        router 
        background-color="#2c3e50" 
        text-color="#bfcbd9" 
        active-text-color="#409EFF"
      >
        <template v-for="item in menuList" :key="item.id">
          <el-menu-item :index="item.path">
            <el-icon v-if="item.icon">
              <component :is="item.icon" />
            </el-icon>
            <span>{{ item.name }}</span>
          </el-menu-item>
        </template>
      </el-menu>

    </el-aside>
    <el-container>
      <el-header class="header">
        <div class="header-left">
          <span class="page-title">{{ $route.meta.title || '系统级全功能总线工作台' }}</span>
        </div>
        <div class="header-right">
          <el-icon style="margin-right: 8px;"><UserFilled /></el-icon>
          <span class="user-info" style="margin-right: 20px;">{{ userInfo.real_name || '后台管理操作员' }}</span>
          <el-button type="danger" link @click="handleLogout">
            <el-icon><SwitchButton /></el-icon> 退出登录
          </el-button>
        </div>
      </el-header>
      <el-main class="main">
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElNotification } from 'element-plus'
import request from '../../utils/request'

const router = useRouter()
const userInfo = ref({})

// 动态权限菜单容器
const menuList = ref([])

let pollTimer = null
let notifInstance = null
let lastPendingCount = 0

const handleLogout = () => {
  localStorage.removeItem('saas_token')
  localStorage.removeItem('saas_user')
  router.push('/login')
}

// 核心功能：向总线拉取属于该角色的物理菜单节点
const fetchMenus = async () => {
  try {
    const res = await request.get('/api/system/getMyMenus')
    if (res.code === 200) {
      menuList.value = res.data
    }
  } catch (e) {
    console.error('动态权限拉取链路熔断')
  }
}

// 维持原有的高优全局静默探测与穿透引擎
const checkGlobalNotifications = async () => {
  try {
    const res = await request.get('/api/finance/receivables/list', { timeout: 8000 })
    if (res.code === 200) {
      const pendingBills = res.data.filter(item => Number(item.is_paid) === 2)
      const pendingCount = pendingBills.length

      if (pendingCount > 0 && pendingCount !== lastPendingCount) {
        lastPendingCount = pendingCount
        if (notifInstance) notifInstance.close()

        notifInstance = ElNotification({
          title: '资金核销紧急提醒',
          message: `系统探测到 ${pendingCount} 笔待审核的打款凭证。点击此通知立即直达审核室。`,
          type: 'warning',
          duration: 0,
          position: 'bottom-right',
          customClass: 'clickable-notif',
          onClick: () => {
            const targetId = pendingBills[0].id
            router.push({
              path: '/finance',
              query: { review_bill_id: targetId, _t: Date.now() }
            })
            if (notifInstance) {
              notifInstance.close()
              notifInstance = null
            }
          },
          onClose: () => { lastPendingCount = 0 }
        })
      } else if (pendingCount === 0 && notifInstance) {
        notifInstance.close()
        notifInstance = null
        lastPendingCount = 0
      }
    }
  } catch (e) {}
}

onMounted(() => {
  const userStr = localStorage.getItem('saas_user')
  if (userStr) {
    userInfo.value = JSON.parse(userStr)
  }
  
  // 启动权限拉取
  fetchMenus()
  
  // 启动心跳引擎
  checkGlobalNotifications()
  pollTimer = setInterval(checkGlobalNotifications, 15000)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
  if (notifInstance) notifInstance.close()
})
</script>

<style>
/* 强制注入全局交互样式 */
.clickable-notif { cursor: pointer !important; transition: all 0.2s; }
.clickable-notif:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important; opacity: 0.95; }
</style>

<style scoped>
.layout-container { height: 100vh; }
.aside { background-color: #2c3e50; color: #fff; display: flex; flex-direction: column; }
.logo { height: 60px; line-height: 60px; text-align: center; font-size: 18px; font-weight: bold; background-color: #1e2b3c; }
.el-menu { border-right: none; flex: 1; }
.header { background-color: #fff; border-bottom: 1px solid #eef1f6; display: flex; justify-content: space-between; align-items: center; padding: 0 20px; height: 60px; }
.header-left .page-title { font-size: 18px; font-weight: bold; color: #303133; }
.header-right { display: flex; align-items: center; cursor: pointer; }
.user-info { font-weight: bold; color: #409eff; }
.main { background-color: #f0f2f5; padding: 20px; overflow-y: auto; }
</style>