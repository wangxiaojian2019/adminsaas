<template>
  <el-container class="layout-container">
    <el-aside width="220px" class="aside">
      <div class="logo">高新科技产业园</div>
      
      <el-menu :default-active="$route.path" router background-color="#2c3e50" text-color="#bfcbd9" active-text-color="#409EFF">
        <el-menu-item v-for="menu in menus" :key="menu.id" :index="menu.path">
          <el-icon>
            <component :is="iconMap[menu.icon] || 'Menu'" />
          </el-icon>
          <span>{{ menu.name }}</span>
        </el-menu-item>
      </el-menu>
    </el-aside>
    
    <el-container>
      <el-header class="header">
        <div class="header-left">
          <span class="page-title">{{ $route.meta.title || 'SaaS 资产工作台' }}</span>
        </div>
        <div class="header-right">
          <el-icon style="margin-right: 8px;"><UserFilled /></el-icon>
          <span class="user-info" style="margin-right: 20px;">{{ userInfo.real_name || '操作员' }}</span>
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
import { useRouter, useRoute } from 'vue-router'
import { ElMessage, ElNotification } from 'element-plus'
import { 
  Odometer, Setting, OfficeBuilding, School, Van, User, 
  Memo, Document, Money, Aim, Service, DataLine, Box, 
  UserFilled, SwitchButton 
} from '@element-plus/icons-vue'
import request from '../../utils/request'

const router = useRouter()
const route = useRoute()
const userInfo = ref({})

const iconMap = {
  Odometer, Setting, OfficeBuilding, School, Van, User, 
  Memo, Document, Money, Aim, Service, DataLine, Box
}

const menus = ref([])
let pollTimer = null
let notifInstance = null
let lastPendingCount = 0

const handleLogout = () => {
  localStorage.removeItem('saas_token')
  localStorage.removeItem('saas_user')
  router.push('/login')
}

const fetchDynamicMenus = async () => {
  try {
    const res = await request.get('/api/system/getMyMenus')
    if (res.code === 200) {
      menus.value = res.data
      
      // 【核心安全拦截引擎】：防越权访问
      if (menus.value.length > 0) {
        const currentPath = route.path
        // 判断当前用户正在访问的路由，是否在他的授权菜单列表里
        const hasPermission = menus.value.some(m => m.path === currentPath)
        
        if (!hasPermission) {
          // 如果没有权限（比如被默认路由带到了 dashboard 但他没有这个权限）
          // 强制重定向到他权限列表里的第一个页面！
          router.replace(menus.value[0].path)
        }
      } else {
        ElMessage.error('您的账号尚未分配任何系统模块权限，请联系管理员分配！')
      }
    }
  } catch (e) {}
}

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
            router.push({ path: '/finance', query: { review_bill_id: targetId, _t: Date.now() } })
            if (notifInstance) { notifInstance.close(); notifInstance = null }
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
  fetchDynamicMenus()
  checkGlobalNotifications()
  pollTimer = setInterval(checkGlobalNotifications, 15000)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
  if (notifInstance) notifInstance.close()
})
</script>

<style>
.clickable-notif { cursor: pointer !important; transition: all 0.2s; }
.clickable-notif:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important; opacity: 0.95; }
</style>

<style scoped>
.layout-container { height: 100vh; }
.aside { background-color: #2c3e50; color: #fff; display: flex; flex-direction: column; }
.logo { height: 60px; line-height: 60px; text-align: center; font-size: 18px; font-weight: bold; background-color: #1e2b3c; }
.el-menu { border-right: none; flex: 1; overflow-y: auto; }
.header { background-color: #fff; border-bottom: 1px solid #eef1f6; display: flex; justify-content: space-between; align-items: center; padding: 0 20px; height: 60px; }
.header-left .page-title { font-size: 18px; font-weight: bold; color: #303133; }
.header-right { display: flex; align-items: center; cursor: pointer; }
.user-info { font-weight: bold; color: #409eff; }
.main { background-color: #f0f2f5; padding: 20px; overflow-y: auto; }
</style>