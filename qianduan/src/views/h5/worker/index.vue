<template>
  <div class="h5-worker-main">
    <div class="top-nav">
      <div class="user-greet">外勤终端：{{ workerName }}</div>
      <div class="nav-actions">
        <el-button link type="primary" size="small" @click="pwdDialogVisible = true">修改密码</el-button>
        <el-divider direction="vertical" />
        <el-button link type="danger" size="small" @click="logout(false)">安全退出</el-button>
      </div>
    </div>

    <el-tabs v-model="activeTab" class="mobile-tabs" stretch>
      <el-tab-pane label="指派工单作业" name="tasks">
        <div class="pane-content" v-loading="tasksLoading">
          <el-empty v-if="tasks.length === 0" description="暂无指派给您的工单任务" :image-size="80" />
          
          <div v-for="task in tasks" :key="task.id" class="task-card">
            <div class="card-header">
              <span class="task-id">工单 #{{ task.id }}</span>
              <el-tag size="small" :type="getStatusType(task.status)" effect="dark">
                {{ getStatusLabel(task.status) }}
              </el-tag>
            </div>
            <div class="task-body">
              <div class="task-title">{{ task.title }}</div>
              <div class="task-desc">{{ parseDesc(task.description).text }}</div>
              <el-image 
                v-if="parseDesc(task.description).image"
                class="task-img"
                :src="getFullImgUrl(parseDesc(task.description).image)"
                :preview-src-list="[getFullImgUrl(parseDesc(task.description).image)]"
                fit="cover"
              />
              <div class="task-meta">报修人: {{ task.reporter_name }} | 下发于: {{ task.created_at }}</div>
            </div>
            <div class="card-footer" v-if="task.status === 2">
              <el-button type="primary" size="default" style="width: 100%; border-radius: 8px;" @click="openCompleteDialog(task)">
                开始办理完工上报
              </el-button>
            </div>
          </div>
        </div>
      </el-tab-pane>

      <el-tab-pane label="网格巡更打卡" name="patrol">
        <div class="pane-content" v-loading="pointsLoading">
          <el-empty v-if="points.length === 0" description="园区尚未配置任何巡检网格" :image-size="80" />
          
          <div v-for="point in points" :key="point.id" class="patrol-point-card">
            <div class="point-info">
              <el-icon class="point-icon"><Location /></el-icon>
              <div class="point-detail">
                <div class="point-name">{{ point.location }}</div>
                <div class="point-id">网格防区编号: {{ point.id }}</div>
              </div>
            </div>
            <el-button type="success" size="default" @click="openPatrolDialog(point)">
              现场打卡
            </el-button>
          </div>
        </div>
      </el-tab-pane>
    </el-tabs>

    <el-dialog v-model="completeVisible" title="完工情况反馈核验" width="92%" center append-to-body @close="completeForm.image_url = ''">
      <el-form label-position="top">
        <el-form-item label="修复进展情况说明">
          <el-input v-model="completeForm.reply_remarks" type="textarea" :rows="3" placeholder="请详述现场修复手段..." />
        </el-form-item>
        <el-form-item label="修复后现场实况照片">
          <el-upload class="cert-uploader" action="http://47.120.52.65:8787/api/upload" :headers="uploadHeaders" :show-file-list="false" :on-success="handleTaskUpload">
            <img v-if="completeForm.image_url" :src="getFullImgUrl(completeForm.image_url)" class="preview-img" />
            <div v-else class="upload-trigger">
              <el-icon class="plus-icon"><Camera /></el-icon>
              <div>调起手机相机拍照存证</div>
            </div>
          </el-upload>
        </el-form-item>
      </el-form>
      <template #footer>
        <div style="display: flex; gap: 10px;">
          <el-button @click="completeVisible = false" style="flex:1;">取消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="submitComplete" style="flex:2;">确认完工推单</el-button>
        </div>
      </template>
    </el-dialog>

    <el-dialog v-model="patrolVisible" title="防区网格实地环境勘察" width="92%" center append-to-body @close="patrolForm.image_url = ''">
      <el-form label-position="top">
        <el-form-item label="工况安全性评定">
          <el-radio-group v-model="patrolForm.status" style="width: 100%;">
            <el-radio-button :label="1">安全正常</el-radio-button>
            <el-radio-button :label="0">存在风险隐患</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="工况/异常备注备注">
          <el-input v-model="patrolForm.remarks" type="textarea" :rows="2" placeholder="正常无需填写，存在异常请详述..." />
        </el-form-item>
        <el-form-item label="现场实况拍照">
          <el-upload class="cert-uploader" action="http://47.120.52.65:8787/api/upload" :headers="uploadHeaders" :show-file-list="false" :on-success="handlePatrolUpload">
            <img v-if="patrolForm.image_url" :src="getFullImgUrl(patrolForm.image_url)" class="preview-img" />
            <div v-else class="upload-trigger">
              <el-icon class="plus-icon"><Camera /></el-icon>
              <div>调起手机相机拍摄防区实况</div>
            </div>
          </el-upload>
        </el-form-item>
      </el-form>
      <template #footer>
        <div style="display: flex; gap: 10px;">
          <el-button @click="patrolVisible = false" style="flex:1;">取消</el-button>
          <el-button type="success" :loading="submitLoading" @click="submitPatrol" style="flex:2;">物理打卡存证</el-button>
        </div>
      </template>
    </el-dialog>

    <el-dialog v-model="pwdDialogVisible" title="修改外勤安全终端密码" width="92%" center top="15vh" append-to-body @close="pwdFormRef?.resetFields()">
      <el-form ref="pwdFormRef" :model="pwdForm" :rules="pwdRules" label-position="top">
        <el-form-item label="当前密码" prop="old_password">
          <el-input v-model="pwdForm.old_password" type="password" show-password placeholder="请输入当前密码 (默认 123456)" />
        </el-form-item>
        <el-form-item label="设置全新密码" prop="new_password">
          <el-input v-model="pwdForm.new_password" type="password" show-password placeholder="请输入长度不小于6位的新密码" />
        </el-form-item>
      </el-form>
      <template #footer>
        <div style="display: flex; gap: 10px;">
          <el-button @click="pwdDialogVisible = false" style="flex: 1;">取消</el-button>
          <el-button type="primary" :loading="pwdLoading" @click="submitPwd" style="flex: 1;">保存并退出</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import request from '../../../utils/request'

const router = useRouter()
const activeTab = ref('tasks')
const workerName = ref('')

const tasks = ref([]); const tasksLoading = ref(false)
const points = ref([]); const pointsLoading = ref(false)
const submitLoading = ref(false)

const completeVisible = ref(false); const currentTask = ref({})
const completeForm = reactive({ reply_remarks: '', image_url: '' })

const patrolVisible = ref(false); const currentPoint = ref({})
const patrolForm = reactive({ status: 1, remarks: '', image_url: '' })

const pwdDialogVisible = ref(false)
const pwdFormRef = ref(null)
const pwdLoading = ref(false)
const pwdForm = reactive({ old_password: '', new_password: '' })
const pwdRules = {
  old_password: [{ required: true, message: '原密码必须填写验证', trigger: 'blur' }],
  new_password: [{ required: true, message: '新安全密码不可为空', trigger: 'blur' }, { min: 6, message: '为了系统审计安全，密码长度不能小于6位', trigger: 'blur' }]
}

// 采用 saas_token 头以确保上传接口防报 401
const uploadHeaders = computed(() => ({ 'Authorization': `Bearer ${localStorage.getItem('saas_token')}` }))

const initWorkerInfo = () => {
  // 核心：彻底移除组件内的重定向拦截，不干预 router/index.js 的底层调度
  const infoStr = localStorage.getItem('worker_info')
  if (infoStr) {
    const info = JSON.parse(infoStr)
    workerName.value = info.real_name || info.username || '园区作业专员'
  } else {
    workerName.value = '园区作业专员'
  }
}

const parseDesc = (desc) => {
  if (!desc) return { text: '', image: '' }
  const match = desc.match(/【现场照片证物】:\s*(http.*)/)
  return match ? { text: desc.replace(match[0], '').trim(), image: match[1] } : { text: desc, image: '' }
}

const getFullImgUrl = (url) => url.startsWith('http') ? url : `http://47.120.52.65:8787${url}`

const fetchTasks = async () => {
  tasksLoading.value = true
  try {
    const res = await request.get('/api/worker/tasks')
    if (res.code === 200) tasks.value = res.data
  } finally { tasksLoading.value = false }
}

const fetchPoints = async () => {
  pointsLoading.value = true
  try {
    const res = await request.get('/api/worker/patrol/points')
    if (res.code === 200) points.value = res.data
  } finally { pointsLoading.value = false }
}

const openCompleteDialog = (task) => {
  currentTask.value = task
  completeForm.reply_remarks = ''
  completeForm.image_url = ''
  completeVisible.value = true
}

const handleTaskUpload = (res) => {
  if (res.code === 200) { completeForm.image_url = res.data.url; ElMessage.success('完工照片解析成功') }
}

const submitComplete = async () => {
  submitLoading.value = true
  try {
    const res = await request.post('/api/worker/tasks/complete', {
      id: currentTask.value.id,
      reply_remarks: completeForm.reply_remarks,
      image_url: completeForm.image_url
    })
    if (res.code === 200) { ElMessage.success(res.msg); completeVisible.value = false; fetchTasks() }
  } finally { submitLoading.value = false }
}

const openPatrolDialog = (point) => {
  currentPoint.value = point
  patrolForm.status = 1
  patrolForm.remarks = ''
  patrolForm.image_url = ''
  patrolVisible.value = true
}

const handlePatrolUpload = (res) => {
  if (res.code === 200) { patrolForm.image_url = res.data.url; ElMessage.success('防区照片拍照完成') }
}

const submitPatrol = async () => {
  submitLoading.value = true
  try {
    const res = await request.post('/api/worker/patrol/submit', {
      point_id: currentPoint.value.id,
      status: patrolForm.status,
      remarks: patrolForm.remarks,
      image_url: patrolForm.image_url
    })
    if (res.code === 200) { ElMessage.success(res.msg); patrolVisible.value = false; }
  } finally { submitLoading.value = false }
}

const submitPwd = () => {
  pwdFormRef.value.validate(async (valid) => {
    if (!valid) return
    pwdLoading.value = true
    try {
      const res = await request.post('/api/worker/password/update', pwdForm)
      if (res.code === 200) {
        ElMessage.success(res.msg)
        pwdDialogVisible.value = false
        logout(true) 
      } else {
        ElMessage.error(res.msg)
      }
    } finally { pwdLoading.value = false }
  })
}

const logout = (silent = false) => {
  const clearAuth = () => {
    // 核心：一并清理双活键值，使用纯净 router 推送遣返
    localStorage.removeItem('h5_worker_token')
    localStorage.removeItem('saas_token')
    localStorage.removeItem('worker_info')
    router.push('/h5/login')
  }
  if (silent === true) {
    clearAuth()
  } else {
    if (window.confirm('确认安全退出作业终端吗？')) {
      clearAuth()
    }
  }
}

const getStatusLabel = (status) => ({ 1: '待指派', 2: '处理中', 3: '待指派验收', 4: '已完工结案' }[status] || '未知')
const getStatusType = (status) => ({ 1: 'danger', 2: 'warning', 3: 'primary', 4: 'success' }[status] || 'info')

onMounted(() => {
  initWorkerInfo()
  fetchTasks()
  fetchPoints()
})
</script>

<style scoped>
.h5-worker-main { min-height: 100vh; background-color: #f4f6f9; display: flex; flex-direction: column; }
.top-nav { background: #fff; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.user-greet { font-size: 14px; font-weight: bold; color: #2c3e50; }
.nav-actions { display: flex; align-items: center; }

:deep(.mobile-tabs .el-tabs__header) { margin: 0; background: #fff; }
.pane-content { padding: 15px; }

.task-card, .patrol-point-card { background: #fff; border-radius: 12px; padding: 18px; margin-bottom: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.task-id { font-family: monospace; font-weight: bold; color: #909399; }
.task-title { font-size: 16px; font-weight: bold; color: #303133; margin-bottom: 6px; }
.task-desc { font-size: 13px; color: #606266; line-height: 1.5; margin-bottom: 10px; }
.task-img { width: 80px; height: 80px; border-radius: 6px; margin-bottom: 10px; }
.task-meta { font-size: 11px; color: #a8abb2; }

.patrol-point-card { display: flex; justify-content: space-between; align-items: center; }
.point-info { display: flex; align-items: center; gap: 10px; }
.point-icon { font-size: 24px; color: #409eff; }
.point-name { font-size: 14px; font-weight: bold; color: #303133; }
.point-id { font-size: 11px; color: #909399; margin-top: 2px; }

.cert-uploader { border: 1px dashed #d9d9d9; border-radius: 8px; cursor: pointer; position: relative; overflow: hidden; display: block; width: 100%; height: 160px; background-color: #fafafa; }
.upload-trigger { display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100%; color: #8c939d; font-size: 13px; }
.plus-icon { font-size: 28px; margin-bottom: 8px; }
.preview-img { width: 100%; height: 100%; object-fit: contain; }
</style>