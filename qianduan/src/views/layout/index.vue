<template>
  <el-container class="app-wrapper">
    <el-aside width="240px" class="sidebar-container">
      <div class="logo-area">
        <h2 class="logo-text">智慧园区 SaaS</h2>
      </div>
      
      <el-scrollbar>
        <el-menu
          :default-active="activeMenu"
          class="el-menu-vertical"
          background-color="#304156"
          text-color="#bfcbd9"
          active-text-color="#409EFF"
          router
        >
          <el-menu-item index="/dashboard">
            <el-icon><Odometer /></el-icon>
            <template #title>数据可视化大屏</template>
          </el-menu-item>

          <el-sub-menu index="assets">
            <template #title>
              <el-icon><OfficeBuilding /></el-icon>
              <span>资产与企业管网</span>
            </template>
            <el-menu-item index="/buildings">大厦与资产大盘</el-menu-item>
            <el-menu-item index="/spaces">房源资产精细库</el-menu-item>
            <el-menu-item index="/enterprises">企业户籍档案</el-menu-item>
            <el-menu-item index="/contracts">租务与合同中心</el-menu-item>
          </el-sub-menu>

          <el-sub-menu index="property-hub">
            <template #title>
              <el-icon><Avatar /></el-icon>
              <span>综合物业与工单</span>
            </template>
            <el-menu-item index="/workOrder">外勤工单大盘</el-menu-item>
            <el-menu-item index="/decoration">装修报备管理</el-menu-item>
            <el-menu-item index="/meeting">
              <el-icon><Calendar /></el-icon>共享会议室管网
            </el-menu-item>
            <el-menu-item index="/services">基层服务人员管理</el-menu-item>
            <el-menu-item index="/inventory">仓库与物料管理</el-menu-item>
          </el-sub-menu>

          <el-sub-menu index="finance-hub">
            <template #title>
              <el-icon><Wallet /></el-icon>
              <span>业财与计费中枢</span>
            </template>
            <el-menu-item index="/finance">业财一体化中心</el-menu-item>
            <el-menu-item index="/fee-config">计费策略配置</el-menu-item>
          </el-sub-menu>

          <el-sub-menu index="iot-hub">
            <template #title>
              <el-icon><Cpu /></el-icon>
              <span>物联与安防中枢</span>
            </template>
            <el-menu-item index="/iot">IoT智能网联中心</el-menu-item>
            <el-menu-item index="/patrol">智能安防巡检</el-menu-item>
            <el-menu-item index="/vehicles">车位月卡与收费</el-menu-item>
          </el-sub-menu>

          <el-menu-item index="/leads">
            <el-icon><Phone /></el-icon>
            <template #title>招商与线索中心</template>
          </el-menu-item>
          <el-menu-item index="/reports">
            <el-icon><DataAnalysis /></el-icon>
            <template #title>报表与 BI 中心</template>
          </el-menu-item>
          <el-menu-item index="/system">
            <el-icon><Setting /></el-icon>
            <template #title>系统与权限控制</template>
          </el-menu-item>
        </el-menu>
      </el-scrollbar>
    </el-aside>

    <el-container class="main-container">
      <el-header class="nav-header">
        <div class="header-left">
          <el-icon class="fold-btn"><Expand /></el-icon>
          <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/dashboard' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>{{ currentRouteTitle }}</el-breadcrumb-item>
          </el-breadcrumb>
        </div>
        <div class="header-right">
          <el-dropdown trigger="click">
            <span class="el-dropdown-link user-profile">
              <el-avatar size="small" src="https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png" />
              <span class="username">超级管理员</span>
              <el-icon class="el-icon--right"><arrow-down /></el-icon>
            </span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item divided @click="handleLogout">退出登录</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>

      <el-main class="app-main">
        <router-view v-slot="{ Component }">
          <transition name="fade-transform" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </el-main>
    </el-container>
  </el-container>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { 
  Odometer, OfficeBuilding, Wallet, Cpu, Calendar, 
  Avatar, Phone, DataAnalysis, Setting, Expand, ArrowDown
} from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()

const activeMenu = computed(() => route.path)
const currentRouteTitle = computed(() => route.meta.title || '工作台')

const handleLogout = () => {
  localStorage.removeItem('saas_token')
  router.push('/login')
}
</script>

<style scoped>
.app-wrapper { height: 100vh; width: 100vw; overflow: hidden; }
.sidebar-container { background-color: #304156; transition: width 0.28s; display: flex; flex-direction: column; }
.logo-area { height: 60px; line-height: 60px; text-align: center; background-color: #2b3643; color: #fff; }
.logo-text { margin: 0; font-size: 18px; font-weight: 600; }
.el-menu-vertical { border-right: none; }
.nav-header { height: 60px; background: #fff; box-shadow: 0 1px 4px rgba(0,21,41,.08); display: flex; align-items: center; justify-content: space-between; padding: 0 20px; }
.header-left { display: flex; align-items: center; }
.fold-btn { font-size: 20px; cursor: pointer; margin-right: 20px; }
.header-right { display: flex; align-items: center; }
.user-profile { display: flex; align-items: center; cursor: pointer; }
.username { margin-left: 8px; font-size: 14px; color: #333; }
.app-main { background-color: #f0f2f5; padding: 20px; overflow-y: auto; }
.fade-transform-leave-active, .fade-transform-enter-active { transition: all .3s; }
.fade-transform-enter-from { opacity: 0; transform: translateX(-30px); }
.fade-transform-leave-to { opacity: 0; transform: translateX(30px); }
</style>