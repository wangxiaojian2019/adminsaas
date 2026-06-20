import axios from 'axios'
import { ElMessage } from 'element-plus'

const service = axios.create({
    baseURL: 'http://47.120.52.65:8787', 
    timeout: 10000 
})

// 请求拦截器：动态装载独立 Token
service.interceptors.request.use(
    config => {
        const currentUrl = window.location.href
        let token = null

        // 核心修复：根据访问的终端不同，物理隔离提取 Token，防止串号
        if (currentUrl.includes('/h5/tenant')) {
            token = localStorage.getItem('h5_tenant_token')
        } else if (currentUrl.includes('/h5/worker') || currentUrl.includes('/h5/login')) {
            token = localStorage.getItem('h5_worker_token')
        } else {
            token = localStorage.getItem('saas_token')
        }
        
        if (token) {
            config.headers['Authorization'] = 'Bearer ' + token
        }
        return config
    },
    error => Promise.reject(error)
)

// 响应拦截器：全局错误处理与剔除
service.interceptors.response.use(
    response => {
        const res = response.data
        
        if (res.code !== 200) {
            // 静默处理消息轮询的异常，防控制台红字刷屏
            if (response.config.url.includes('/notification/list')) {
                return Promise.reject(new Error('Silent Polling Reject'))
            }

            ElMessage.error(res.msg || '系统繁忙')
            
            // 处理鉴权丢失与越权
            if (res.code === 401 || res.code === 403) {
                const currentUrl = window.location.href
                
                if (currentUrl.includes('/h5/tenant')) {
                    localStorage.removeItem('h5_tenant_token')
                    localStorage.removeItem('tenant_info') 
                    window.location.href = '/h5/tenant/login'
                } 
                else if (currentUrl.includes('/h5/worker') || currentUrl.includes('/h5/login')) {
                    localStorage.removeItem('h5_worker_token')
                    localStorage.removeItem('h5_worker_user')
                    window.location.href = '/h5/login'
                } 
                else {
                    localStorage.removeItem('saas_token')
                    localStorage.removeItem('saas_user')
                    window.location.href = '/login'
                }
            }
            return Promise.reject(new Error(res.msg || 'Error'))
        }
        return res
    },
    error => {
        ElMessage.error('网络连接断开或后端服务未启动')
        return Promise.reject(error)
    }
)

export default service