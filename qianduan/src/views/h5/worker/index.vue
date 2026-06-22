<template>
  <div class="mobile-container" v-if="userInfo.id">
    <div class="mobile-header">
      <div class="header-top-row" style="display:flex; justify-content: space-between; align-items:flex-start; width: 100%;">
        <div class="user-greeting">
          <h3 style="display: flex; align-items: center; gap: 8px;">
            {{ userInfo.real_name || '员工' }} 
            <el-tag size="small" type="warning" effect="dark" style="border-radius: 12px; border: none;">
              {{ displayRoleType }}
            </el-tag>
          </h3>
          <p><el-icon><Iphone /></el-icon> 账号: {{ userInfo.username }}</p>
        </div>
        
        <div class="header-actions">
          <div class="msg-bell" @click="openMsgDrawer">
            <el-badge :value="unreadCount" :hidden="unreadCount === 0" :max="99">
              <el-icon :size="24" color="#ffffff"><BellFilled /></el-icon>
            </el-badge>
          </div>
        </div>
      </div>
      <el-button v-if="activeTab === 'orders'" type="danger" link @click="handleLogout" style="color: #ffcccc; margin-top: 10px;">交班退出</el-button>
    </div>

    <div v-show="activeTab === 'orders'" class="h5-content">
      <div v-if="pendingReturns.length > 0 && !isSecurity" class="responsibility-card" style="border: 1px solid #faecd8; background-color: #fdf6ec; margin-top: -25px; margin-bottom: 15px; position: relative; z-index: 10;">
        <div class="resp-title" style="color: #e6a23c; margin-bottom: 12px;">
          <el-icon><Box /></el-icon> 待归还物资提醒 ({{ pendingReturns.length }} 件)
        </div>
        <div v-for="inv in pendingReturns" :key="'ret-'+inv.id" class="quick-bill-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px dashed #faecd8;">
          <div class="qb-info">
            <div class="qb-title" style="margin-bottom: 4px;">
              <span class="qb-amount" style="color: #e6a23c; font-weight: bold; font-size: 15px;">{{ inv.item_name }}</span>
              <span style="font-size: 14px; margin-left: 10px; font-weight: bold; color: #f56c6c;">x{{ inv.quantity }}{{ inv.unit }}</span>
            </div>
            <div class="qb-date text-danger" v-if="inv.expected_return_date" style="font-size: 12px; color: #f56c6c;">
              <el-icon><Timer /></el-icon> 应于 {{ inv.expected_return_date }} 前归还
            </div>
          </div>
        </div>
      </div>

      <div class="responsibility-card" :style="{ marginTop: (pendingReturns.length > 0 && !isSecurity) ? '0' : '-25px' }">
        <div class="resp-title"><el-icon><List /></el-icon> 我的岗位职责</div>
        <div class="resp-content">
          {{ userInfo.responsibility || '请严格按照中控室派发的工单规范作业。' }}
        </div>
      </div>

      <div class="stats-panel">
        <div class="stat-box">
          <div class="stat-num text-danger">{{ pendingOrders.length }}</div>
          <div class="stat-label">中控派单待办</div>
        </div>
        <div class="stat-box">
          <div class="stat-num text-success">{{ completedOrders.length }}</div>
          <div class="stat-label">今日已完工(件)</div>
        </div>
      </div>

      <div class="action-grid">
        <div class="action-btn" @click="scanCode">
          <el-icon class="action-icon" :style="{ color: actionMeta.color }">
            <Aim v-if="actionMeta.iconName === 'Aim'" />
            <Brush v-else-if="actionMeta.iconName === 'Brush'" />
            <Setting v-else-if="actionMeta.iconName === 'Setting'" />
            <FullScreen v-else />
          </el-icon>
          <span>{{ actionMeta.btnText }}</span>
        </div>
        <div class="action-btn" @click="fetchWorkOrders">
          <el-icon class="action-icon" style="color: #409eff;"><Refresh /></el-icon>
          <span>刷新调度指令</span>
        </div>
      </div>

      <div class="task-list">
        <div class="list-title">调度室下发任务列表</div>
        <el-empty v-if="pendingOrders.length === 0" description="暂无派发指令" :image-size="80" />

        <div v-for="order in pendingOrders" :key="order.id" class="task-card">
          <div class="task-header">
            <span class="task-title">{{ order.title }}</span>
            <el-tag size="small" type="danger" effect="dark" v-if="order.priority === 1">调度加急</el-tag>
          </div>
          
          <div class="task-body">
            <p><strong>异常描述：</strong>{{ parseDesc(order.description).text }}</p>
            <div v-if="parseDesc(order.description).image" class="image-preview-box">
              <span style="font-size: 12px; color: #909399; margin-bottom: 5px; display: block;">现场证物照片：</span>
              <el-image 
                style="width: 100px; height: 100px; border-radius: 6px; border: 1px solid #ebeef5;"
                :src="parseDesc(order.description).image"
                :preview-src-list="[parseDesc(order.description).image]"
                fit="cover"
                preview-teleported
              />
            </div>
            <el-divider border-style="dashed" style="margin: 10px 0" />
            <p><strong>指令发起：</strong>{{ order.reporter_name }}</p>
            <p><strong>下发时间：</strong>{{ new Date(order.created_at).toLocaleString() }}</p>
          </div>

          <div class="task-footer">
            <el-button type="success" size="large" class="full-btn" :loading="actionLoading" @click="completeOrder(order.id)">
              确认现场处置完毕，打卡回传
            </el-button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="!isSecurity" v-show="activeTab === 'inventory'" class="h5-content profile-wrapper" style="margin-top: -30px;">
      <div class="responsibility-card title-card" style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
        <span class="resp-title" style="margin:0;"><el-icon><Box /></el-icon> 我的后勤物资库</span>
        <el-tag size="small" type="primary" effect="light">{{ inventoryList.length }} 笔记录</el-tag>
      </div>

      <el-empty v-if="inventoryList.length === 0" description="暂无库房领用或外借记录" :image-size="80" class="responsibility-card" />

      <div v-for="inv in inventoryList" :key="inv.id" class="task-card" style="border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
        <div class="task-header" style="border-bottom: 1px dashed #ebeef5; padding-bottom: 12px;">
          <span class="task-title" style="font-size: 16px;">{{ inv.item_name }}</span>
          <el-tag size="small" :type="inv.action_type === 3 ? 'warning' : (inv.action_type === 2 ? 'danger' : 'success')" effect="dark">
            {{ inv.action_type === 3 ? '外借中' : (inv.action_type === 2 ? '已消耗' : '已归还') }}
          </el-tag>
        </div>
        <div class="task-body" style="padding-top: 10px;">
          <p style="margin-bottom: 6px;"><strong>操作数量：</strong><span style="font-weight: bold; color: #f56c6c; font-family: monospace; font-size: 16px; margin: 0 4px;">{{ inv.quantity }}</span>{{ inv.unit }}</p>
          <p style="margin-bottom: 6px; color: #909399; font-size: 12px;">办理时间：{{ inv.created_at }}</p>
          <p v-if="inv.action_type === 3 && inv.expected_return_date" style="margin-bottom: 6px;">
            <strong style="color: #e6a23c;">规定应还日期：</strong><span style="color: #e6a23c; font-weight: bold;">{{ inv.expected_return_date }}</span>
          </p>
          <div v-if="inv.remark" style="background-color: #f8f9fa; padding: 8px; border-radius: 4px; font-size: 12px; color: #606266; margin-top: 8px;">
            备注：{{ inv.remark }}
          </div>
        </div>
      </div>
    </div>

    <div v-show="activeTab === 'profile'" class="h5-content profile-wrapper">
      <div class="profile-header">
        <div class="avatar"><el-icon><UserFilled /></el-icon></div>
        <div class="info">
          <div class="name">{{ userInfo.real_name }}</div>
          <div class="position"><el-tag size="small" effect="dark" type="warning">{{ displayRoleType }}</el-tag></div>
        </div>
      </div>
      <div class="profile-menu">
        <div class="menu-item" @click="openPwdDialog(false)">
          <el-icon><Lock /></el-icon> <span>安全设置 (修改密码)</span> <el-icon class="arrow"><ArrowRight /></el-icon>
        </div>
        <div class="menu-item text-danger" @click="handleLogout">
          <el-icon><SwitchButton /></el-icon> <span>安全退出登录</span>
        </div>
      </div>
    </div>

    <div class="bottom-tabbar">
      <div :class="['tab-item', { active: activeTab === 'orders' }]" @click="activeTab = 'orders'">
        <el-icon><List /></el-icon><span>工单大厅</span>
      </div>
      <div v-if="!isSecurity" :class="['tab-item', { active: activeTab === 'inventory' }]" @click="activeTab = 'inventory'">
        <el-icon><Box /></el-icon><span>物资领用</span>
      </div>
      <div :class="['tab-item', { active: activeTab === 'profile' }]" @click="activeTab = 'profile'">
        <el-icon><User /></el-icon><span>个人中心</span>
      </div>
    </div>

    <el-drawer v-model="msgDrawerVisible" title="消息与预警中心" direction="btt" size="85%" :with-header="false" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
      <div class="drawer-header">
        <span style="font-size: 16px; font-weight: bold; color: #303133;">实时消息列表</span>
        <el-icon size="20" @click="msgDrawerVisible = false"><Close /></el-icon>
      </div>
      <div class="msg-list" v-loading="msgLoading">
        <el-empty v-if="msgList.length === 0" description="暂无服务通知" :image-size="60" />
        <div v-for="msg in msgList" :key="msg.id" :class="['msg-card', { unread: Number(msg.is_read) === 0 }]" @click="readMsg(msg)">
          <div class="msg-header">
            <span class="msg-title"><span v-if="Number(msg.is_read) === 0" class="red-dot"></span>{{ msg.title }}</span>
            <span class="msg-time">{{ msg.created_at }}</span>
          </div>
          <div class="msg-content">{{ msg.content }}</div>
        </div>
      </div>
    </el-drawer>

    <el-dialog 
      v-model="pwdDialogVisible" 
      :title="isForcedReset ? '安全警告：请重置初始密码' : '修改登录密码'" 
      width="90%" 
      :show-close="!isForcedReset" 
      :close-on-click-modal="!isForcedReset" 
      :close-on-press-escape="!isForcedReset"
      top="20vh"
      @close="handleDialogClose"
    >
      <div v-if="isForcedReset" class="force-tips">
        系统检测到您仍在使用初始密码，为保障数据安全，必须修改后方可继续执行作业。
      </div>
      <el-form ref="pwdFormRef" :model="pwdForm" :rules="pwdRules" label-width="0" size="large">
        <el-form-item prop="old_password">
          <el-input v-model="pwdForm.old_password" type="password" placeholder="请输入原密码" show-password />
        </el-form-item>
        <el-form-item prop="new_password">
          <el-input v-model="pwdForm.new_password" type="password" placeholder="请输入新密码" show-password />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button v-if="!isForcedReset" @click="pwdDialogVisible = false">取消</el-button>
        <el-button type="primary" size="large" :style="{ width: isForcedReset ? '100%' : 'auto', letterSpacing: '2px' }" :loading="submitLoading" @click="submitPwd">
          确认安全升级
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Iphone, List, Aim, Brush, Setting, FullScreen, Refresh, UserFilled, Lock, SwitchButton, ArrowRight, User, Box, BellFilled, Close, Timer } from '@element-plus/icons-vue'
import request from '../../../utils/request'

const router = useRouter()
const userInfo = ref({})
const rawOrders = ref([])
const inventoryList = ref([])
const activeTab = ref('orders')
const actionLoading = ref(false)

const msgDrawerVisible = ref(false)
const msgLoading = ref(false)
const msgList = ref([])
const unreadCount = computed(() => msgList.value.filter(m => Number(m.is_read) === 0).length)

const pwdDialogVisible = ref(false)
const isForcedReset = ref(false)
const submitLoading = ref(false)
const pwdFormRef = ref(null)
const pwdForm = reactive({ id: '', old_password: '', new_password: '' })
const pwdRules = {
  old_password: [{ required: true, message: '原密码必填', trigger: 'blur' }],
  new_password: [{ required: true, message: '新密码必填', trigger: 'blur' }, { min: 6, message: '密码长度不能少于6位', trigger: 'blur' }]
}

const displayRoleType = computed(() => {
  return userInfo.value.role_type || userInfo.value.position || '综合外勤'
})

const isSecurity = computed(() => {
  const role = displayRoleType.value
  return role.includes('安保') || role.includes('巡逻') || role.includes('保安')
})

const pendingOrders = computed(() => {
  if (!userInfo.value.id) return []
  return rawOrders.value.filter(o => o.status === 2 && Number(o.handler_id) === Number(userInfo.value.id))
})
const completedOrders = computed(() => {
  if (!userInfo.value.id) return []
  return rawOrders.value.filter(o => o.status >= 3 && Number(o.handler_id) === Number(userInfo.value.id))
})

const pendingReturns = computed(() => {
  return inventoryList.value.filter(inv => inv.action_type === 3)
})

const actionMeta = computed(() => {
  const role = displayRoleType.value
  if (role.includes('安保') || role.includes('巡逻')) return { btnText: '防区安全巡检打卡', iconName: 'Aim', color: '#f56c6c' }
  else if (role.includes('保洁')) return { btnText: '卫生绿化清理打卡', iconName: 'Brush', color: '#67c23a' }
  else if (role.includes('维修') || role.includes('工程')) return { btnText: '设备维保扫码打卡', iconName: 'Setting', color: '#e6a23c' }
  else return { btnText: '现场作业扫码', iconName: 'FullScreen', color: '#409eff' }
})

const parseDesc = (desc) => {
  if (!desc) return { text: '', image: '' }
  const match = desc.match(/【现场照片证物】:\s*(http.*)/)
  if (match) return { text: desc.replace(match[0], '').trim(), image: match[1] }
  return { text: desc, image: '' }
}

const fetchWorkOrders = async () => {
  try {
    const res = await request.get('/api/work_order/list')
    if (res.code === 200) rawOrders.value = res.data
  } catch (e) {}
}

const fetchInventory = async () => {
  if (isSecurity.value) return 
  try {
    const res = await request.get('/api/worker/inventory')
    if (res.code === 200) inventoryList.value = res.data
  } catch (e) {}
}

const fetchMessages = async () => {
  if (!userInfo.value.id) return
  try {
    const res = await request.get('/api/worker/notifications')
    if (res.code === 200) msgList.value = res.data || []
  } catch (e) {}
}

const openMsgDrawer = () => {
  msgDrawerVisible.value = true
  fetchMessages()
}

const readMsg = async (msg) => {
  if (Number(msg.is_read) === 1) return
  msg.is_read = 1
  try { await request.post('/api/worker/notifications/read', { id: msg.id }) } catch (e) { msg.is_read = 0 }
}

const completeOrder = async (orderId) => {
  actionLoading.value = true
  try {
    const res = await request.post('/api/work_order/action', { 
        id: orderId, 
        action: 'resolve',
        result_remark: 'H5移动端打卡完工'
    })
    if (res.code === 200) {
      ElMessage.success('打卡成功！现场数据已实时回传中控室。')
      fetchWorkOrders()
    }
  } finally { actionLoading.value = false }
}

const scanCode = () => ElMessage.warning('调用摄像头功能需嵌入APP或小程序内运行')

const handleDialogClose = () => {
  if (pwdFormRef.value) pwdFormRef.value.resetFields()
}

const openPwdDialog = (forced = false) => {
  isForcedReset.value = forced
  pwdForm.id = userInfo.value.id || ''
  pwdForm.old_password = ''
  pwdForm.new_password = ''
  pwdDialogVisible.value = true
}

const submitPwd = () => {
  pwdFormRef.value.validate(async (valid) => {
    if (!valid) return
    if (pwdForm.old_password === pwdForm.new_password) {
      return ElMessage.warning('新密码不能与旧密码相同')
    }
    submitLoading.value = true
    try {
      const res = await request.post('/api/worker/password/update', pwdForm)
      if (res.code === 200) {
        ElMessage.success('密码修改成功，请牢记')
        pwdDialogVisible.value = false
        if (isForcedReset.value) {
          userInfo.value.need_reset_pwd = false
          localStorage.setItem('h5_worker_user', JSON.stringify(userInfo.value))
        }
      } else {
        ElMessage.error(res.msg || '修改失败')
      }
    } finally {
      submitLoading.value = false
    }
  })
}

const handleLogout = () => {
  localStorage.removeItem('h5_worker_token')
  localStorage.removeItem('h5_worker_user')
  router.push('/h5/login')
}

onMounted(() => {
  const storedUser = localStorage.getItem('h5_worker_user')
  if (storedUser) {
    try {
      const parsed = JSON.parse(storedUser)
      if (parsed && parsed.id) {
        userInfo.value = parsed
        fetchWorkOrders()
        fetchInventory()
        fetchMessages()
        if (userInfo.value.need_reset_pwd) {
          openPwdDialog(true)
        }
        setInterval(fetchMessages, 20000)
      } else {
        router.push('/h5/login')
      }
    } catch (e) {
      localStorage.removeItem('h5_worker_token')
      localStorage.removeItem('h5_worker_user')
      router.push('/h5/login')
    }
  } else {
    router.push('/h5/login')
  }
})
</script>

<style scoped>
.mobile-container { width: 100%; max-width: 480px; margin: 0 auto; min-height: 100vh; background-color: #f5f7fa; padding-bottom: 70px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; position: relative;}
.mobile-header { background: linear-gradient(135deg, #2c3e50, #3498db); color: #fff; padding: 25px 20px 40px 20px; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
.user-greeting { flex: 1; min-width: 0; }
.header-actions { flex-shrink: 0; margin-left: 15px; padding-top: 5px; }
.msg-bell { cursor: pointer; padding: 5px; }
.user-greeting h3 { margin: 0 0 8px 0; font-size: 22px; font-weight: bold; }
.user-greeting p { margin: 0; font-size: 13px; opacity: 0.9; display: flex; align-items: center; gap: 4px; }
.responsibility-card { margin: 0 15px 15px 15px; background: #fff; border-radius: 10px; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); position: relative; z-index: 10; }
.resp-title { font-size: 14px; font-weight: bold; color: #409eff; margin-bottom: 8px; display: flex; align-items: center; gap: 5px; }
.resp-content { font-size: 13px; color: #606266; line-height: 1.6; }
.stats-panel { display: flex; margin: 0 15px 20px 15px; background: #fff; border-radius: 10px; padding: 15px 0; box-shadow: 0 2px 12px rgba(0,0,0,0.03); }
.stat-box { flex: 1; text-align: center; border-right: 1px solid #f0f0f0; }
.stat-box:last-child { border-right: none; }
.stat-num { font-size: 24px; font-weight: bold; margin-bottom: 5px; font-family: monospace; }
.stat-label { font-size: 12px; color: #909399; }
.text-danger { color: #f56c6c; }
.text-success { color: #67c23a; }
.action-grid { display: flex; gap: 15px; padding: 0 15px; margin-bottom: 20px; }
.action-btn { flex: 1; background: #fff; border-radius: 12px; padding: 15px 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); cursor: pointer; color: #303133; font-size: 14px; border: 1px solid #ebeef5; font-weight: bold;}
.action-btn:active { background: #f0f2f5; }
.action-icon { font-size: 28px; }
.task-list { padding: 0 15px; }
.list-title { font-size: 15px; font-weight: bold; color: #303133; margin-bottom: 15px; border-left: 4px solid #409eff; padding-left: 8px; }
.task-card { background: #fff; border-radius: 10px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #f0f2f5; }
.task-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #f0f2f5; padding-bottom: 10px; }
.task-title { font-weight: bold; font-size: 15px; color: #303133; }
.task-body p { margin: 0 0 8px 0; font-size: 13px; color: #606266; line-height: 1.5; }
.task-body strong { color: #303133; }
.image-preview-box { margin-top: 10px; background-color: #f8f9fa; padding: 10px; border-radius: 8px; }
.task-footer { margin-top: 15px; }
.full-btn { width: 100%; border-radius: 8px; font-weight: bold; letter-spacing: 1px; }
.profile-wrapper { padding: 0 15px; margin-top: -20px; position: relative; z-index: 10; }
.profile-header { background: #fff; border-radius: 10px; padding: 25px 20px; display: flex; align-items: center; gap: 15px; margin-bottom: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
.profile-header .avatar { width: 60px; height: 60px; background: #e6f1fc; color: #409eff; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 30px; }
.profile-header .name { font-size: 18px; font-weight: bold; color: #303133; margin-bottom: 5px; }
.profile-menu { background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,0.03); border: 1px solid #f0f2f5; }
.menu-item { display: flex; align-items: center; padding: 18px 20px; font-size: 15px; color: #303133; border-bottom: 1px solid #fafafa; cursor: pointer; }
.menu-item:active { background-color: #f5f7fa; }
.menu-item:last-child { border-bottom: none; }
.menu-item .el-icon { margin-right: 10px; font-size: 18px; color: #909399; }
.menu-item .arrow { margin-left: auto; color: #c0c4cc; margin-right: 0; }
.force-tips { font-size: 13px; color: #f56c6c; background-color: #fef0f0; padding: 12px; border-radius: 6px; margin-bottom: 20px; line-height: 1.6; border: 1px solid #fde2e2; }
.bottom-tabbar { position: fixed; bottom: 0; left: 0; right: 0; max-width: 480px; margin: 0 auto; height: 55px; background: #fff; display: flex; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); z-index: 100; border-top: 1px solid #ebeef5; }
.tab-item { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; color: #909399; font-size: 11px; cursor: pointer; }
.tab-item .el-icon { font-size: 22px; margin-bottom: 3px; }
.tab-item.active { color: #409eff; }
.drawer-header { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f2f5; }
.msg-list { padding: 15px; height: calc(100% - 55px); overflow-y: auto; background-color: #f5f7fa; }
.msg-card { background: #fff; border-radius: 8px; padding: 15px; margin-bottom: 12px; cursor: pointer; border: 1px solid #ebeef5; }
.msg-card.unread { border-left: 3px solid #f56c6c; box-shadow: 0 2px 8px rgba(245, 108, 108, 0.1); }
.msg-header { display: flex; justify-content: space-between; margin-bottom: 8px; align-items: center;}
.msg-title { font-size: 14px; font-weight: bold; color: #303133; position: relative; }
.red-dot { display: inline-block; width: 6px; height: 6px; background-color: #f56c6c; border-radius: 50%; vertical-align: middle; margin-right: 4px; }
.msg-time { font-size: 11px; color: #909399; }
.msg-content { font-size: 13px; color: #606266; line-height: 1.5; }
</style>