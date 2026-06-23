import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  { path: '/login', name: 'Login', component: () => import('../views/login/index.vue'), meta: { title: '系统后台管理登录' } },
  { path: '/h5/login', name: 'H5Login', component: () => import('../views/h5/login/index.vue'), meta: { title: '外勤作业终端登录' } },
  { path: '/h5/worker', name: 'H5Worker', component: () => import('../views/h5/worker/index.vue'), meta: { title: '外勤移动工作台' } },
  { path: '/h5/tenant/login', name: 'H5TenantLogin', component: () => import('../views/h5/tenant/login.vue'), meta: { title: '企业服务门户登录' } },
  { path: '/h5/tenant/index', name: 'H5TenantIndex', component: () => import('../views/h5/tenant/index.vue'), meta: { title: '企业综合服务门户' } },
  {
    path: '/',
    name: 'Layout',
    component: () => import('../views/layout/index.vue'),
    redirect: '/dashboard',
    children: [
      { path: 'dashboard', name: 'Dashboard', component: () => import('../views/dashboard/index.vue'), meta: { title: '数据可视化大屏' } },
      { path: 'system', name: 'System', component: () => import('../views/system/index.vue'), meta: { title: '系统与权限控制' } },
      { path: 'buildings', name: 'Buildings', component: () => import('../views/buildings/index.vue'), meta: { title: '大厦与资产大盘' } },
      { path: 'spaces', name: 'Spaces', component: () => import('../views/spaces/index.vue'), meta: { title: '房源资产精细库' } },
      { path: 'vehicles', name: 'Vehicles', component: () => import('../views/vehicles/index.vue'), meta: { title: '车位月卡与收费' } },
      { path: 'leads', name: 'Leads', component: () => import('../views/leads/index.vue'), meta: { title: '招商与线索中心' } },
      { path: 'enterprises', name: 'Enterprises', component: () => import('../views/enterprises/index.vue'), meta: { title: '企业户籍档案' } },
      { path: 'contracts', name: 'Contracts', component: () => import('../views/contracts/index.vue'), meta: { title: '租务与合同中心' } },
      { path: 'finance', name: 'Finance', component: () => import('../views/finance/index.vue'), meta: { title: '业财一体化中心' } },
      { path: 'patrol', name: 'Patrol', component: () => import('../views/patrol/index.vue'), meta: { title: '智能安防巡检' } },
      { path: 'services', name: 'Services', component: () => import('../views/services/index.vue'), meta: { title: '基层服务人员管理' } },
      { path: 'workOrder', name: 'WorkOrder', component: () => import('../views/workOrder/index.vue'), meta: { title: '外勤工单大盘' } },
      { path: 'reports', name: 'Reports', component: () => import('../views/reports/index.vue'), meta: { title: '报表与 BI 中心' } },
      { path: 'inventory', name: 'Inventory', component: () => import('../views/inventory/index.vue'), meta: { title: '仓库与物料' } },
      { path: 'fee-config', name: 'FeeConfig', component: () => import('../views/fee-config/index.vue'), meta: { title: '计费策略配置' } },
      { path: 'iot', name: 'Iot', component: () => import('../views/iot/index.vue'), meta: { title: 'IoT智能网联中心' } },
      { path: 'decoration', name: 'Decoration', component: () => import('../views/decoration/index.vue'), meta: { title: '装修报备与工期管理' } },
      // 👇 新增的：会议室预订与审批模块
      { path: 'meeting', name: 'Meeting', component: () => import('../views/meeting/index.vue'), meta: { title: '共享会议室预订中心' } }
    ]
  }
]

const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach((to, from) => {
  document.title = to.meta.title ? `${to.meta.title} - 智慧园区SaaS` : '智慧园区SaaS'
  const path = to.path

  if (path.startsWith('/h5/tenant')) {
    const tenantToken = localStorage.getItem('h5_tenant_token')
    if (path !== '/h5/tenant/login' && !tenantToken) return '/h5/tenant/login'
    if (path === '/h5/tenant/login' && tenantToken) return '/h5/tenant/index'
  } 
  else if (path.startsWith('/h5/worker') || path === '/h5/login') {
    const workerToken = localStorage.getItem('h5_worker_token')
    if (path !== '/h5/login' && !workerToken) return '/h5/login'
    if (path === '/h5/login' && workerToken) return '/h5/worker'
  } 
  else {
    const saasToken = localStorage.getItem('saas_token')
    if (path !== '/login' && !saasToken) return '/login'
    if (path === '/login' && saasToken) return '/dashboard'
  }
  
  return true
})

export default router