<template>
  <div class="mobile-container" v-if="userInfo.id">
    <div class="mobile-header">
      <div class="user-greeting">
        <h3 style="display: flex; align-items: center; gap: 8px;">
          {{ userInfo.real_name || '员工' }} 
          <el-tag size="small" type="warning" effect="dark" style="border-radius: 12px; border: none;">
            {{ userInfo.position || '外勤人员' }}
          </el-tag>
        </h3>
        <p><el-icon><Iphone /></el-icon> 账号: {{ userInfo.username }}</p>
      </div>
      <el-button v-if="activeTab === 'orders'" type="danger" link @click="handleLogout" style="color: #ffcccc;">交班退出</el-button>
    </div>

    <div v-show="activeTab === 'orders'" class="h5-content">
      <div class="responsibility-card">
        <div class="resp-title"><el-icon><List /></el-icon> 我的岗位职责</div>
        <div class="resp-content">
          {{ userInfo.responsibility || '系统暂未配置您的详细岗位职责。' }}
        </div>
      </div>

      <div class="stats-panel">
        <div class="stat-box">
          <div class="stat-num text-danger">{{ pendingOrders.length }}</div>
          <div class="stat-label">中控室派单待办</div>
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
            <el-tag size="small" type="danger" effect="dark">调度加急</el-tag>
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

    <div v-show="activeTab === 'profile'" class="h5-content profile-wrapper">
      <div class="profile-header">
        <div class="avatar"><el-icon><UserFilled /></el-icon></div>
        <div class="info">
          <div class="name">{{ userInfo.real_name }}</div>
          <div class="position"><el-tag size="small" effect="dark" type="warning">{{ userInfo.position }}</el-tag></div>
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
      <div :class="['tab-item', { active: activeTab === 'profile' }]" @click="activeTab = 'profile'">
        <el-icon><User /></el-icon><span>个人中心</span>
      </div>
    </div>

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
        <el-button type="primary" size="large" style="width: isForcedReset ? '100%' : 'auto'; letter-spacing: 2px;" :loading="submitLoading" @click="submitPwd">
          确认安全升级
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Iphone, List, Aim, Brush, Setting, FullScreen, Refresh, UserFilled, Lock, SwitchButton, ArrowRight, User } from '@element-plus/icons-vue'
import request from '../../../utils/request'

const router = useRouter()
const userInfo = ref({})
const rawOrders = ref([])
const activeTab = ref('orders')
const actionLoading = ref(false)

const pwdDialogVisible = ref(false)
const isForcedReset = ref(false)
const submitLoading = ref(false)
const pwdFormRef = ref(null)
const pwdForm = reactive({ id: '', old_password: '', new_password: '' })
const pwdRules = {
  old_password: [{ required: true, message: '原密码必填', trigger: 'blur' }],
  new_password: [{ required: true, message: '新密码必填', trigger: 'blur' }, { min: 6, message: '密码长度不能少于6位', trigger: 'blur' }]
}

const pendingOrders = computed(() => {
  if (!userInfo.value.id) return []
  return rawOrders.value.filter(o => o.status === 2 && o.handler_id === userInfo.value.id)
})
const completedOrders = computed(() => {
  if (!userInfo.value.id) return []
  return rawOrders.value.filter(o => o.status > 2 && o.handler_id === userInfo.value.id)
})

const actionMeta = computed(() => {
  const pos = userInfo.value.position || ''
  if (pos.includes('安保')) return { btnText: '防区安全巡检打卡', iconName: 'Aim', color: '#f56c6c' }
  else if (pos.includes('保洁')) return { btnText: '卫生绿化清理打卡', iconName: 'Brush', color: '#67c23a' }
  else if (pos.includes('维修') || pos.includes('工程')) return { btnText: '设备维保扫码打卡', iconName: 'Setting', color: '#e6a23c' }
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
    const res = await request.get('/api/services/work-orders/list')
    if (res.code === 200) rawOrders.value = res.data
  } catch (e) {
    // 拦截器内已处理网络异常提示
  }
}

const completeOrder = async (orderId) => {
  actionLoading.value = true
  try {
    const res = await request.post('/api/services/work-orders/verify', { id: orderId })
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
      const res = await request.post('/api/services/staff/update_pwd', pwdForm)
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
        if (userInfo.value.need_reset_pwd) {
          openPwdDialog(true)
        }
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
.mobile-header { background: linear-gradient(135deg, #2c3e50, #3498db); color: #fff; padding: 30px 20px 40px 20px; display: flex; justify-content: space-between; align-items: flex-start; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
.user-greeting h3 { margin: 0 0 8px 0; font-size: 22px; font-weight: bold; }
.user-greeting p { margin: 0; font-size: 13px; opacity: 0.9; display: flex; align-items: center; gap: 4px; }
.responsibility-card { margin: -25px 15px 15px 15px; background: #fff; border-radius: 10px; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); position: relative; z-index: 10; }
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
.text-danger { color: #f56c6c !important; font-weight: bold; }
.text-danger .el-icon { color: #f56c6c !important; }
.force-tips { font-size: 13px; color: #f56c6c; background-color: #fef0f0; padding: 12px; border-radius: 6px; margin-bottom: 20px; line-height: 1.6; border: 1px solid #fde2e2; }
.bottom-tabbar { position: fixed; bottom: 0; left: 0; right: 0; max-width: 480px; margin: 0 auto; height: 55px; background: #fff; display: flex; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); z-index: 100; border-top: 1px solid #ebeef5; }
.tab-item { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; color: #909399; font-size: 11px; cursor: pointer; }
.tab-item .el-icon { font-size: 22px; margin-bottom: 3px; }
.tab-item.active { color: #409eff; }
</style>