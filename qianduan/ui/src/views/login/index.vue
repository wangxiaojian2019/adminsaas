<template>
  <div class="login-container">
    <el-card class="login-card" shadow="hover">
      <div class="logo-title">
        <h2>高新科技产业园</h2>
        <p>SaaS运营管理中枢</p>
      </div>

      <el-form ref="formRef" :model="form" :rules="rules" label-width="0" size="large">
        <el-form-item prop="username">
          <el-input v-model="form.username" placeholder="请输入系统管理员账号" clearable>
            <template #prefix><el-icon><User /></el-icon></template>
          </el-input>
        </el-form-item>
        
        <el-form-item prop="password">
          <el-input v-model="form.password" type="password" placeholder="请输入管理员密码" show-password @keyup.enter="handleLogin">
            <template #prefix><el-icon><Lock /></el-icon></template>
          </el-input>
        </el-form-item>

        <el-button type="primary" class="login-btn" :loading="loading" @click="handleLogin">
          登录控制台
        </el-button>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import request from '../../utils/request' // 核心修复：更正为向上两层的相对路径

const formRef = ref(null)
const loading = ref(false)

const form = reactive({
  username: '',
  password: ''
})

const rules = {
  username: [{ required: true, message: '账号不能为空', trigger: 'blur' }],
  password: [{ required: true, message: '密码不能为空', trigger: 'blur' }]
}

const handleLogin = () => {
  formRef.value.validate(async (valid) => {
    if (!valid) return
    loading.value = true
    try {
      const res = await request.post('/api/login', form)
      if (res.code === 200) {
        // 提取安全兜底参数，防止后端未传 Token 时拦截器死锁
        const safeToken = res.data.token || 'saas_auth_fallback_token'
        const safeUser = res.data.user || { real_name: '系统管理员', role: '总控' }
        
        localStorage.setItem('saas_token', safeToken)
        localStorage.setItem('saas_user', JSON.stringify(safeUser))
        
        ElMessage.success(`欢迎回来，${safeUser.real_name}`)
        
        // 采用底层硬重定向清洗前端路由状态栈，直达总控大屏
        setTimeout(() => {
          window.location.href = '/'
        }, 300)
      } else {
        ElMessage.error(res.msg || '登录请求遭拒')
      }
    } finally {
      loading.value = false
    }
  })
}
</script>

<style scoped>
.login-container { 
  display: flex; 
  justify-content: center; 
  align-items: center; 
  height: 100vh; 
  background: linear-gradient(135deg, #1e2b3c 0%, #2c3e50 100%); 
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

.login-card { 
  width: 100%; 
  max-width: 420px; 
  padding: 30px 20px; 
  border-radius: 12px; 
  border: none;
  background: #ffffff;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2); 
}

.logo-title { 
  text-align: center; 
  margin-bottom: 35px; 
}

.logo-title h2 {
  margin: 0 0 10px 0;
  font-size: 26px;
  color: #303133;
  letter-spacing: 1px;
}

.logo-title p {
  margin: 0;
  font-size: 14px;
  color: #909399;
  letter-spacing: 2px;
}

:deep(.el-input__wrapper) {
  background-color: #f5f7fa;
  box-shadow: 0 0 0 1px #e4e7ed inset;
}

:deep(.el-input__wrapper.is-focus) {
  box-shadow: 0 0 0 1px #409eff inset;
  background-color: #ffffff;
}

.login-btn { 
  width: 100%; 
  margin-top: 10px;
  font-size: 16px; 
  font-weight: bold;
  letter-spacing: 4px; 
  border-radius: 6px;
  background: linear-gradient(135deg, #409eff, #3498db);
  border: none;
}
.login-btn:hover {
  opacity: 0.9;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(64, 158, 255, 0.3);
}
</style>