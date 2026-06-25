<template>
  <div class="tenant-login-wrapper">
    <div class="login-card">
      <div class="logo-zone">
        <el-icon class="logo-icon"><OfficeBuilding /></el-icon>
        <h2 class="title">企业租户服务门户</h2>
        <p class="subtitle">园区业务办理与自助缴费通道</p>
      </div>

      <el-form ref="formRef" :model="form" :rules="rules" size="large" class="login-form" @submit.prevent>
        <el-form-item prop="phone">
          <el-input v-model="form.phone" placeholder="请输入联系人手机号" :prefix-icon="User" clearable @keyup.enter="handleLogin" />
        </el-form-item>
        <el-form-item prop="password">
          <el-input v-model="form.password" type="password" placeholder="请输入登录密码(默认123456)" :prefix-icon="Lock" show-password @keyup.enter="handleLogin" />
        </el-form-item>
        
        <el-button type="primary" class="submit-btn" :loading="loading" @click="handleLogin">
          安全登入
        </el-button>
      </el-form>
      
      <div class="contact-tips">
        * 如遗忘密码或无法登录，请联系园区招商运营部重置。
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { OfficeBuilding, User, Lock } from '@element-plus/icons-vue'
import request from '../../../utils/request'

const router = useRouter()
const formRef = ref(null)
const loading = ref(false)

const form = reactive({ phone: '', password: '' })
const rules = {
  phone: [{ required: true, message: '手机号必填', trigger: 'blur' }],
  password: [{ required: true, message: '密码必填', trigger: 'blur' }]
}

const handleLogin = () => {
  if (!formRef.value) return

  formRef.value.validate(async (valid) => {
    if (!valid) {
      ElMessage.warning('请检查手机号或密码是否已填写')
      return
    }
    
    loading.value = true
    try {
      const res = await request.post('/api/tenant/login', form)
      // 只要能走到这里，说明 res.code 必定是 200（因为非 200 都在 request.js 被拦截了）
      localStorage.setItem('h5_tenant_token', res.data.token)
      localStorage.setItem('tenant_info', JSON.stringify(res.data.tenant_info))
      ElMessage.success('欢迎进入企业租户服务门户')
      router.push('/h5/tenant/index')
      
    } catch (e) {
      // 【终极修复】：request.js 已经负责弹出了具体的错误提示（如“手机号或密码错误”）
      // 这里只需要静默捕获，终止后续逻辑即可。
      // 不再弹出“系统异常”去覆盖真实的业务报错，也不再往控制台抛红字。
      console.log('登录拦截:', e.message)
    } finally {
      // 无论成功还是失败，最后都要关掉按钮的加载圈圈
      loading.value = false
    }
  })
}
</script>

<style scoped>
.tenant-login-wrapper { min-height: 100vh; background: #f4f6f9; display: flex; align-items: center; justify-content: center; padding: 20px; }
.login-card { width: 100%; max-width: 400px; background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.05); padding: 40px 20px; box-sizing: border-box; }
.logo-zone { text-align: center; margin-bottom: 30px; }
.logo-icon { font-size: 50px; color: #409eff; margin-bottom: 10px; }
.title { font-size: 22px; color: #303133; margin: 0 0 5px 0; font-weight: bold; }
.subtitle { font-size: 13px; color: #909399; margin: 0; }
.login-form { margin-top: 20px; }
.submit-btn { width: 100%; margin-top: 10px; border-radius: 8px; font-weight: bold; letter-spacing: 2px; }
.contact-tips { margin-top: 20px; font-size: 12px; color: #a8abb2; text-align: center; line-height: 1.5; }
</style>