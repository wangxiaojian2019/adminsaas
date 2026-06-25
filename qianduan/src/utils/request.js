import axios from 'axios'
import { ElMessage } from 'element-plus'

const service = axios.create({
    baseURL: import.meta.env.VITE_BASE_API, 
    timeout: 10000 
})

// 请求拦截器：安全装载 Token
service.interceptors.request.use(
    config => {
        const currentUrl = window.location.href
        let token = null

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

// 响应拦截器：全局错误处理
service.interceptors.response.use(
    response => {
        const res = response.data
        
        if (res.code !== 200) {
            if (response.config.url.includes('/notification/list')) {
                return Promise.reject(new Error('Silent Polling Reject'))
            }

            // 先弹出后端返回的真实错误信息（例如：手机号或密码错误）
            ElMessage.error(res.msg || '系统繁忙')
            
            // 【核心修复】：遇到401或403时，必须判断当前是不是在请求“登录接口”
            // 如果是登录失败（也是返回401），绝对不能执行强制跳转，否则会导致一闪而过并清空页面！
            if ((res.code === 401 || res.code === 403) && !response.config.url.includes('/login')) {
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