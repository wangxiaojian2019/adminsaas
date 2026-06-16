<template>
  <div class="worker-login-wrapper">
    <div class="login-card">
      <div class="logo-zone">
        <el-icon class="logo-icon"><Tools /></el-icon>
        <h2 class="title">外勤作业终端</h2>
        <p class="subtitle">园区后勤与安保移动协同平台</p>
      </div>

      <el-form ref="formRef" :model="form" :rules="rules" size="large" class="login-form">
        <el-form-item prop="username">
          <el-input v-model="form.username" placeholder="请输入登录账号(手机号)" prefix-icon="User" clearable />
        </el-form-item>
        <el-form-item prop="password">
          <el-input v-model="form.password" type="password" placeholder="请输入安全密码" prefix-icon="Lock" show-password @keyup.enter="handleLogin" />
        </el-form-item>
        
        <el-button type="primary" class="submit-btn" :loading="loading" @click="handleLogin">
          安全登入终端
        </el-button>
      </el-form>
      
      <div class="contact-tips">
        * 基层作业人员首次登录，请使用初始密码 123456
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import request from '../../../utils/request'

const router = useRouter()
const formRef = ref(null)
const loading = ref(false)

const form = reactive({ username: '', password: '' })
const rules = {
  username: [{ required: true, message: '账号必填', trigger: 'blur' }],
  password: [{ required: true, message: '密码必填', trigger: 'blur' }]
}

const handleLogin = () => {
  formRef.value.validate(async (valid) => {
    if (!valid) return
    loading.value = true
    try {
      const res = await request.post('/api/login', form)
      if (res.code === 200) {
        // 核心解法：写入 h5_worker_token 让路由放行，同时写入 saas_token 供 request.js 发送接口调用
        localStorage.setItem('h5_worker_token', res.data.token)
        localStorage.setItem('saas_token', res.data.token) 
        localStorage.setItem('worker_info', JSON.stringify(res.data.user_info || {}))
        
        ElMessage.success('终端连接成功')
        router.push('/h5/worker')
      } else {
        ElMessage.error(res.msg)
      }
    } catch (e) {
      ElMessage.error('网络通讯阻断')
    } finally {
      loading.value = false
    }
  })
}
</script>

<style scoped>
.worker-login-wrapper { min-height: 100vh; background: #f0f2f5; display: flex; align-items: center; justify-content: center; padding: 20px; }
.login-card { width: 100%; max-width: 400px; background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.05); padding: 40px 20px; box-sizing: border-box; }
.logo-zone { text-align: center; margin-bottom: 30px; }
.logo-icon { font-size: 50px; color: #67c23a; margin-bottom: 10px; }
.title { font-size: 22px; color: #303133; margin: 0 0 5px 0; font-weight: bold; }
.subtitle { font-size: 13px; color: #909399; margin: 0; }
.login-form { margin-top: 20px; }
.submit-btn { width: 100%; margin-top: 10px; border-radius: 8px; font-weight: bold; letter-spacing: 2px; }
.contact-tips { margin-top: 20px; font-size: 12px; color: #a8abb2; text-align: center; line-height: 1.5; }
</style>