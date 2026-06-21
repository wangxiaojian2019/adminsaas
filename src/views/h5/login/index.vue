<template>
  <div class="h5-login-container">
    <div class="login-box">
      <div class="logo-area">
        <div class="icon-circle">
          <el-icon><Iphone /></el-icon>
        </div>
        <h2>员工移动终端</h2>
        <p>智慧园区现场作业系统</p>
      </div>

      <el-form ref="formRef" :model="form" :rules="rules" class="login-form">
        <el-form-item prop="username">
          <el-input v-model="form.username" placeholder="请输入手机号(账号)" size="large" clearable>
            <template #prefix><el-icon><User /></el-icon></template>
          </el-input>
        </el-form-item>
        <el-form-item prop="password">
          <el-input v-model="form.password" type="password" placeholder="请输入登录密码" size="large" show-password @keyup.enter="handleLogin">
            <template #prefix><el-icon><Lock /></el-icon></template>
          </el-input>
        </el-form-item>
        <el-button type="primary" size="large" class="submit-btn" :loading="loading" @click="handleLogin">
          安全登录
        </el-button>
      </el-form>
      <div class="bottom-tips">仅限园区内部基层作业人员登录</div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Iphone, User, Lock } from '@element-plus/icons-vue'
import request from '../../../utils/request'

const router = useRouter()
const formRef = ref(null)
const loading = ref(false)

const form = reactive({ username: '', password: '' })
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
        // 核心修复：宽容度极高的数据抓取，防止后端结构变化导致前端读取空值
        const token = res.data?.token || res.data?.saas_token || ''
        const userInfo = res.data?.user_info || res.data?.admin || res.data?.user || res.data || {}

        localStorage.setItem('h5_worker_token', token)
        localStorage.setItem('h5_worker_user', JSON.stringify(userInfo))

        ElMessage.success('登录成功，正在进入工作台')
        
        setTimeout(() => {
          router.push('/h5/worker')
        }, 300)
      } else {
        ElMessage.error(res.msg || '登录失败')
      }
    } catch (e) {
      ElMessage.error('网络或服务异常')
    } finally {
      loading.value = false
    }
  })
}
</script>

<style scoped>
.h5-login-container { height: 100vh; display: flex; justify-content: center; background-color: #f5f7fa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
.login-box { width: 100%; max-width: 480px; padding: 40px 30px; display: flex; flex-direction: column; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
.logo-area { text-align: center; margin-bottom: 50px; margin-top: 20px; }
.icon-circle { width: 80px; height: 80px; background: linear-gradient(135deg, #409eff, #3498db); border-radius: 50%; display: flex; justify-content: center; align-items: center; margin: 0 auto 15px; color: #fff; font-size: 36px; box-shadow: 0 4px 15px rgba(64, 158, 255, 0.4); }
.logo-area h2 { margin: 0 0 8px 0; color: #303133; font-size: 24px; font-weight: bold; letter-spacing: 1px; }
.logo-area p { margin: 0; color: #909399; font-size: 13px; }
.login-form { width: 100%; }
:deep(.el-input__wrapper) { background-color: #f8f9fa; border-radius: 8px; box-shadow: none !important; border: 1px solid #ebeef5; padding: 4px 15px; }
:deep(.el-input__wrapper.is-focus) { border-color: #409eff; background-color: #fff; }
.submit-btn { width: 100%; border-radius: 8px; font-size: 16px; font-weight: bold; letter-spacing: 2px; margin-top: 15px; height: 48px; background: linear-gradient(135deg, #409eff, #3498db); border: none; box-shadow: 0 4px 15px rgba(64, 158, 255, 0.3); }
.submit-btn:active { transform: scale(0.98); }
.bottom-tips { margin-top: auto; text-align: center; color: #c0c4cc; font-size: 12px; }
</style>