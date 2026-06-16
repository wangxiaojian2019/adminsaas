import axios from 'axios'
import { ElMessage } from 'element-plus'
import router from '../router' // 核心修复：直接引入路由实例进行跳转，避免强制刷新导致的死循环

const service = axios.create({
    // 请确保这里的 IP 是你宝塔的真实后端 IP
    baseURL: 'http://47.120.52.65:8787', 
    timeout: 10000 
})

// 请求拦截器：动态装载 Token
service.interceptors.request.use(
    config => {
        // 核心修复：使用 href.includes 替代 pathname，完美兼容 Hash 模式和二级目录部署
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

// 响应拦截器：全局错误处理与 401 剔除
service.interceptors.response.use(
    response => {
        const res = response.data
        
        // 业务逻辑错误拦截
        if (res.code !== 200) {
            ElMessage.error(res.msg || '系统繁忙')
            
            // 核心修复：401 鉴权失败拦截，彻底清除双重缓存并平滑推送路由
            if (res.code === 401) {
                const currentUrl = window.location.href
                
                if (currentUrl.includes('/h5/tenant')) {
                    localStorage.removeItem('h5_tenant_token')
                    localStorage.removeItem('h5_tenant_user') // 斩断死循环的关键
                    router.push('/h5/tenant/login')
                } 
                else if (currentUrl.includes('/h5/worker') || currentUrl.includes('/h5/login')) {
                    localStorage.removeItem('h5_worker_token')
                    localStorage.removeItem('h5_worker_user')
                    router.push('/h5/login')
                } 
                else {
                    localStorage.removeItem('saas_token')
                    localStorage.removeItem('saas_user')
                    router.push('/login')
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