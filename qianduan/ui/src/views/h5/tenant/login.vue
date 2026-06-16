<template>
  <div class="tenant-login-container">
    <div class="header-bg"></div>
    
    <div class="login-card">
      <div class="logo-area">
        <div class="icon-circle">
          <el-icon><OfficeBuilding /></el-icon>
        </div>
        <h2>企业综合服务门户</h2>
        <p>高新科技产业园 · 租户专属</p>
      </div>

      <div class="form-area">
        <div class="input-group">
          <el-icon class="input-icon"><Iphone /></el-icon>
          <input v-model="loginForm.username" type="text" placeholder="请输入企业登记联系人手机号" />
        </div>
        
        <div class="input-group">
          <el-icon class="input-icon"><Lock /></el-icon>
          <input v-model="loginForm.password" type="password" placeholder="请输入门户查询密码" @keyup.enter="handleLogin" />
        </div>

        <button class="login-btn" :disabled="loading" @click="handleLogin">
          {{ loading ? '企业身份核验中...' : '授权登入' }}
        </button>
      </div>
    </div>

    <div class="footer-tips">
      如有登录问题，请联系园区招商运营中心重置密码。
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import request from '../../../utils/request'

const router = useRouter()
const loginForm = reactive({ username: '', password: '' }) 
const loading = ref(false)

const handleLogin = () => {
  if (!loginForm.username || !loginForm.password) {
    ElMessage.warning('请输入手机号和密码')
    return
  }
  
  loading.value = true
  request.post('/api/tenant/login', {
    username: loginForm.username,
    password: loginForm.password
  }).then(res => {
    const user = res.data.user_info
    
    // 强制隔离：确认为租户身份
    if (user.role !== 'tenant') {
      ElMessage.error('权限异常：非企业租户账号')
      return
    }

    // 存储到租户专属的 H5 容器中
    localStorage.setItem('h5_tenant_token', res.data.token)
    localStorage.setItem('h5_tenant_user', JSON.stringify(user))
    
    ElMessage.success(`欢迎您，${user.enterprise_name}`)
    router.push('/h5/tenant/index')
  }).finally(() => {
    loading.value = false
  })
}
</script>

<style scoped>
.tenant-login-container {
  width: 100%;
  max-width: 480px;
  margin: 0 auto;
  min-height: 100vh;
  background-color: #f5f7fa;
  position: relative;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  overflow: hidden;
}

.header-bg {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 250px;
  background: linear-gradient(135deg, #182848, #4b6cb7);
  border-bottom-left-radius: 30px;
  border-bottom-right-radius: 30px;
  z-index: 0;
}

.login-card {
  position: relative;
  z-index: 10;
  background: #fff;
  margin: 80px 20px 0;
  border-radius: 16px;
  padding: 30px 20px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.08);
}

.logo-area { text-align: center; margin-bottom: 40px; }
.icon-circle {
  width: 70px;
  height: 70px;
  background: #f0f7ff;
  border-radius: 50%;
  margin: 0 auto 15px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #409eff;
  font-size: 32px;
}
.logo-area h2 { margin: 0 0 8px 0; font-size: 22px; color: #303133; }
.logo-area p { margin: 0; font-size: 13px; color: #909399; }

.input-group {
  display: flex;
  align-items: center;
  background-color: #f5f7fa;
  border-radius: 8px;
  padding: 0 15px;
  margin-bottom: 20px;
  height: 50px;
  border: 1px solid #ebeef5;
  transition: all 0.3s;
}
.input-group:focus-within { border-color: #409eff; background-color: #fff; }
.input-icon { font-size: 18px; color: #909399; margin-right: 10px; }
.input-group input { flex: 1; height: 100%; border: none; background: transparent; outline: none; font-size: 15px; color: #303133; }

.login-btn {
  width: 100%;
  height: 50px;
  background: linear-gradient(135deg, #4b6cb7, #182848);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: bold;
  letter-spacing: 1px;
  margin-top: 10px;
  box-shadow: 0 6px 15px rgba(24, 40, 72, 0.2);
  cursor: pointer;
}
.login-btn:active { transform: scale(0.98); }
.login-btn:disabled { opacity: 0.7; }

.footer-tips { text-align: center; font-size: 12px; color: #c0c4cc; margin-top: 30px; padding: 0 20px; position: relative; z-index: 10; }
</style>