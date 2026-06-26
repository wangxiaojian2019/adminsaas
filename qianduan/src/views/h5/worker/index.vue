<template>
  <div class="mobile-container" v-if="userInfo.id">
    <div class="mobile-header">
      <div class="header-top-row" style="display:flex; justify-content: space-between; align-items:flex-start; width: 100%;">
        <div class="user-greeting">
          <h3 style="display: flex; align-items: center; gap: 8px;">
            {{ userInfo.real_name || '员工' }} <el-tag size="small" type="warning" effect="dark" style="border-radius: 12px; border: none;">{{ displayRoleType }}</el-tag>
          </h3>
          <p><el-icon><Iphone /></el-icon> 账号: {{ userInfo.username }}</p>
        </div>
        <div class="header-actions">
          <div class="msg-bell" @click="openMsgDrawer">
            <el-badge :value="unreadCount" :hidden="unreadCount === 0" :max="99"><el-icon :size="24" color="#ffffff"><BellFilled /></el-icon></el-badge>
          </div>
        </div>
      </div>
      <el-button v-if="activeTab === 'orders'" type="danger" link @click="handleLogout" style="color: #ffcccc; margin-top: 10px;">交班退出</el-button>
    </div>

    <div v-show="activeTab === 'orders'" class="h5-content">
      <div v-if="pendingReturns.length > 0 && !isSecurity" class="responsibility-card" style="border: 1px solid #faecd8; background-color: #fdf6ec; margin-top: -25px; margin-bottom: 15px; position: relative; z-index: 10;">
        <div class="resp-title" style="color: #e6a23c; margin-bottom: 12px;"><el-icon><Box /></el-icon> 待归还物资提醒 ({{ pendingReturns.length }} 件)</div>
        <div v-for="inv in pendingReturns" :key="'ret-'+inv.id" class="quick-bill-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px dashed #faecd8;">
          <div class="qb-info">
            <div class="qb-title" style="margin-bottom: 4px;"><span style="color:#e6a23c;font-weight:bold;font-size:15px;">{{ inv.item_name }}</span><span style="font-size:14px;margin-left:10px;font-weight:bold;color:#f56c6c;">x{{ inv.quantity }}{{ inv.unit }}</span></div>
            <div class="qb-date text-danger" v-if="inv.expected_return_date" style="font-size: 12px; color: #f56c6c;"><el-icon><Timer /></el-icon> 应于 {{ inv.expected_return_date }} 前归还</div>
          </div>
        </div>
      </div>

      <div class="responsibility-card" :style="{ marginTop: (pendingReturns.length > 0 && !isSecurity) ? '0' : '-25px' }">
        <div class="resp-title"><el-icon><List /></el-icon> 我的岗位职责</div>
        <div class="resp-content">{{ userInfo.responsibility || '请严格按照中控室派发的工单规范作业。' }}</div>
      </div>

      <div style="padding: 0 15px; margin-bottom: 15px;">
        <el-button type="danger" size="large" style="width: 100%; box-shadow: 0 4px 12px rgba(245, 108, 108, 0.3); border-radius: 10px; font-weight: bold; letter-spacing: 1px;" icon="WarningFilled" @click="reportDialogVisible = true">巡查发现隐患？一键拍照上报</el-button>
      </div>

      <div class="stats-panel">
        <div class="stat-box"><div class="stat-num text-danger">{{ pendingOrders.length }}</div><div class="stat-label">中控待办</div></div>
        <div class="stat-box"><div class="stat-num text-warning">{{ auditOrders.length }}</div><div class="stat-label">待审核</div></div>
        <div class="stat-box"><div class="stat-num text-success">{{ completedOrders.length }}</div><div class="stat-label">今日完工</div></div>
      </div>

      <div class="action-grid">
        <div class="action-btn" @click="scanCode">
          <el-icon class="action-icon" :style="{ color: actionMeta.color }">
            <Aim v-if="actionMeta.iconName === 'Aim'" /><Brush v-else-if="actionMeta.iconName === 'Brush'" /><Setting v-else-if="actionMeta.iconName === 'Setting'" /><FullScreen v-else />
          </el-icon>
          <span>{{ actionMeta.btnText }}</span>
        </div>
        <div class="action-btn" @click="fetchWorkOrders"><el-icon class="action-icon" style="color: #409eff;"><Refresh /></el-icon><span>刷新调度指令</span></div>
      </div>

      <div class="task-list">
        <div class="list-title">调度室下发任务列表</div>
        <el-tabs v-model="taskTab" class="h5-custom-tabs">
          <el-tab-pane label="作业中" name="processing">
            <el-empty v-if="pendingOrders.length === 0" description="暂无派发指令" :image-size="80" />
            <div v-for="order in pendingOrders" :key="order.id" class="task-card">
              <div class="task-header">
                <span class="task-title">{{ order.title }}</span>
                <el-tag size="small" type="danger" effect="dark" v-if="order.priority === 1">调度加急</el-tag>
                <el-tag size="small" type="warning" effect="dark" v-else>待处置</el-tag>
              </div>
              <div class="task-body">
                <p><strong>异常描述：</strong>{{ order.description }}</p>
                <div v-if="order.report_image_url" class="image-preview-box" style="margin-top:10px; background-color:#fef0f0; padding:12px; border-radius:8px; border-left:3px solid #f56c6c;">
                  <span style="font-size: 13px; font-weight: bold; color: #f56c6c; margin-bottom: 8px; display: flex; align-items: center; gap: 5px;"><el-icon><Picture /></el-icon> 隐患现场实勘照片：</span>
                  <el-image style="width: 90px; height: 90px; border-radius: 6px; border: 1px solid #fde2e2;" :src="getFullImgUrl(order.report_image_url)" :preview-src-list="[getFullImgUrl(order.report_image_url)]" fit="cover" />
                </div>
                <el-button type="primary" link icon="Timer" size="small" style="margin-top:10px;font-weight:bold;" @click="openTimelineDrawer(order)">查看全链路处理轨迹</el-button>
              </div>
              <div class="task-footer">
                <el-button type="success" size="large" class="full-btn" @click="openResolveDialog(order)">处理完毕，拍照提交验收</el-button>
              </div>
            </div>
          </el-tab-pane>

          <el-tab-pane label="待核实" name="auditing">
            <el-empty v-if="auditOrders.length === 0" description="暂无待审核任务" :image-size="80" />
            <div v-for="order in auditOrders" :key="order.id" class="task-card" style="opacity: 0.85;">
              <div class="task-header"><span class="task-title">{{ order.title }}</span><el-tag size="small" type="primary" effect="dark">中控核实中</el-tag></div>
              <div class="task-body">
                <p style="font-size: 13px; color: #409eff; font-weight: bold;">已提交现场作业凭证，等待中控核实。</p>
                <el-button type="primary" link icon="Timer" size="small" style="margin-top:10px;" @click="openTimelineDrawer(order)">查看全链路处理轨迹</el-button>
              </div>
            </div>
          </el-tab-pane>

          <el-tab-pane label="已闭环" name="completed">
            <el-empty v-if="completedOrders.length === 0" description="暂无完结记录" :image-size="80" />
            <div v-for="order in completedOrders" :key="order.id" class="task-card">
              <div class="task-header"><span class="task-title" style="color: #909399;">{{ order.title }}</span><el-tag size="small" type="info" effect="dark">已归档</el-tag></div>
              <div class="task-body">
                <p style="font-size: 13px; color: #909399;">该工单已彻底完结闭环。</p>
                <el-button type="info" link icon="Timer" size="small" style="margin-top:10px;" @click="openTimelineDrawer(order)">回顾全链路处理轨迹</el-button>
              </div>
            </div>
          </el-tab-pane>
        </el-tabs>
      </div>
    </div>

    <div v-show="activeTab === 'patrol'" class="h5-content profile-wrapper" style="margin-top: -20px;">
      <div class="responsibility-card title-card" style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;"><span class="resp-title" style="margin:0;"><el-icon><Location /></el-icon> 安防网格巡视任务</span></div>
      <el-tabs v-model="patrolTab" class="h5-custom-tabs" @tab-change="handlePatrolTabChange">
        
        <el-tab-pane label="待巡视防区" name="points">
          <div class="task-list" style="padding: 0;">
            <el-empty v-if="patrolPoints.length === 0" description="当前时段暂无巡视任务" :image-size="80" />
            
            <div v-for="point in patrolPoints" :key="point.id" class="task-card" :style="{ opacity: point.is_actionable ? 1 : 0.6 }">
              <div class="task-header">
                <span class="task-title">{{ point.name }}</span>
                <el-tag v-if="point.deadline_str" size="small" :type="point.is_actionable ? 'danger' : 'info'" effect="dark" style="font-weight: bold; border-radius: 12px; border: none;">
                  <el-icon style="margin-right:2px;"><Timer /></el-icon> {{ point.deadline_str }}
                </el-tag>
              </div>
              <div class="task-body"><p><strong>物理位置：</strong>{{ point.location }}</p></div>
              <div class="task-footer">
                <el-button :type="point.is_actionable ? 'primary' : 'info'" size="large" class="full-btn" icon="Aim" @click="openPatrolDialog(point)" :disabled="!point.is_actionable">
                  {{ point.is_actionable ? '现场扫码打卡' : '未到时段 暂禁打卡' }}
                </el-button>
              </div>
            </div>

          </div>
        </el-tab-pane>

        <el-tab-pane label="我的打卡记录" name="records">
          <div class="task-list" style="padding: 0;">
            <el-empty v-if="patrolRecords.length === 0" description="暂无打卡历史" :image-size="80" />
            <div v-for="record in patrolRecords" :key="record.id" class="task-card">
              <div class="task-header" style="padding-bottom: 5px;">
                <span class="task-title">{{ record.point_name || '未知防区' }}</span>
                <el-tag size="small" :type="record.status === 1 ? 'success' : 'danger'" effect="dark">{{ record.status === 1 ? '一切正常' : '发现异常' }}</el-tag>
              </div>
              <div class="task-body">
                <p style="margin: 5px 0;"><strong>打卡时间：</strong>{{ record.created_at }}</p>
                <p v-if="record.remark" style="margin: 5px 0;"><strong>情况备注：</strong>{{ record.remark }}</p>
                <div v-if="record.image_url" class="image-preview-box" style="padding: 5px; margin-top: 5px;">
                  <el-image style="width: 50px; height: 50px; border-radius: 4px;" :src="getFullImgUrl(record.image_url)" :preview-src-list="[getFullImgUrl(record.image_url)]" fit="cover" />
                </div>
              </div>
            </div>
          </div>
        </el-tab-pane>
      </el-tabs>
    </div>

    <div v-if="!isSecurity" v-show="activeTab === 'inventory'" class="h5-content profile-wrapper" style="margin-top: -30px;">
      <div class="responsibility-card title-card" style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;"><span class="resp-title" style="margin:0;"><el-icon><Box /></el-icon> 我的后勤物资库</span></div>
      <el-empty v-if="inventoryList.length === 0" description="暂无库房领用或外借记录" :image-size="80" class="responsibility-card" />
      <div v-for="inv in inventoryList" :key="inv.id" class="task-card" style="border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
        <div class="task-header" style="border-bottom: 1px dashed #ebeef5; padding-bottom: 12px;">
          <span class="task-title" style="font-size: 16px;">{{ inv.item_name }}</span>
          <el-tag size="small" :type="inv.action_type === 3 ? 'warning' : (inv.action_type === 2 ? 'danger' : 'success')" effect="dark">{{ inv.action_type === 3 ? '外借中' : (inv.action_type === 2 ? '已消耗' : '已归还') }}</el-tag>
        </div>
        <div class="task-body" style="padding-top: 10px;">
          <p style="margin-bottom: 6px;"><strong>操作数量：</strong><span style="font-weight: bold; color: #f56c6c; font-family: monospace; font-size: 16px; margin: 0 4px;">{{ inv.quantity }}</span>{{ inv.unit }}</p>
          <p style="margin-bottom: 6px; color: #909399; font-size: 12px;">办理时间：{{ inv.created_at }}</p>
        </div>
      </div>
    </div>

    <div v-show="activeTab === 'profile'" class="h5-content profile-wrapper" style="margin-top: 20px;">
      <div class="profile-header">
        <div class="avatar"><el-icon><UserFilled /></el-icon></div>
        <div class="info"><div class="name">{{ userInfo.real_name }}</div><div class="position"><el-tag size="small" effect="dark" type="warning">{{ displayRoleType }}</el-tag></div></div>
      </div>
      <div class="profile-menu">
        <div class="menu-item" @click="openPwdDialog(false)"><el-icon><Lock /></el-icon> <span>安全设置 (修改密码)</span></div>
        <div class="menu-item text-danger" @click="handleLogout" style="border-top: 5px solid #f5f7fa;"><el-icon><SwitchButton /></el-icon> <span>交班安全退出</span></div>
      </div>
    </div>

    <div class="bottom-tabbar">
      <div :class="['tab-item', { active: activeTab === 'orders' }]" @click="activeTab = 'orders'"><el-icon><List /></el-icon><span>工单大厅</span></div>
      <div :class="['tab-item', { active: activeTab === 'patrol' }]" @click="activeTab = 'patrol'"><el-icon><Location /></el-icon><span>安防巡视</span></div>
      <div v-if="!isSecurity" :class="['tab-item', { active: activeTab === 'inventory' }]" @click="activeTab = 'inventory'"><el-icon><Box /></el-icon><span>物资领用</span></div>
      <div :class="['tab-item', { active: activeTab === 'profile' }]" @click="activeTab = 'profile'"><el-icon><User /></el-icon><span>个人中心</span></div>
    </div>

    <el-drawer v-model="timelineDrawerVisible" title="生命周期流转轨迹" direction="btt" size="85%" :with-header="false" style="border-top-left-radius: 16px; border-top-right-radius: 16px; background-color: #f5f7fa;">
      <div class="drawer-header"><span style="font-size: 16px; font-weight: bold; color: #303133;"><el-icon><Timer /></el-icon> 工单生命周期流转轨迹</span><el-icon size="20" @click="timelineDrawerVisible = false"><Close /></el-icon></div>
      <div class="msg-list" style="padding: 20px;">
        <el-timeline v-if="currentTimeline.length > 0">
          <el-timeline-item v-for="(activity, index) in currentTimeline" :key="index" :timestamp="activity.time" :type="getTimelineType(activity.title)" :hollow="index !== currentTimeline.length - 1" placement="top">
            <el-card shadow="never" style="border-radius: 8px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                <span style="font-weight:bold; font-size:14px; color:#303133;">{{ activity.title }}</span>
                <el-tag size="small" type="info" effect="plain">{{ activity.operator }}</el-tag>
              </div>
              <p style="margin:0; font-size:13px; color:#606266; line-height:1.5;">{{ activity.desc }}</p>
              <div v-if="activity.image" style="margin-top: 10px;">
                <el-image style="width: 60px; height: 60px; border-radius: 4px;" :src="getFullImgUrl(activity.image)" :preview-src-list="[getFullImgUrl(activity.image)]" fit="cover" preview-teleported />
              </div>
            </el-card>
          </el-timeline-item>
        </el-timeline>
        <el-empty v-else description="暂无流转记录" />
      </div>
    </el-drawer>

    <el-drawer v-model="msgDrawerVisible" title="消息与预警中心" direction="btt" size="85%" :with-header="false" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
      <div class="drawer-header"><span style="font-size: 16px; font-weight: bold; color: #303133;">实时消息列表</span><el-icon size="20" @click="msgDrawerVisible = false"><Close /></el-icon></div>
      <div class="msg-list" v-loading="msgLoading">
        <el-empty v-if="msgList.length === 0" description="暂无新指令或通知" :image-size="60" />
        <div v-for="msg in msgList" :key="msg.id" :class="['msg-card', { unread: Number(msg.is_read) === 0 }]" @click="readMsg(msg)">
          <div class="msg-header"><span class="msg-title"><span v-if="Number(msg.is_read) === 0" class="red-dot"></span>{{ msg.title }}</span><span class="msg-time">{{ msg.created_at }}</span></div>
          <div class="msg-content">{{ msg.content }}</div>
        </div>
      </div>
    </el-drawer>

    <el-dialog v-model="reportDialogVisible" title="一键拍照上报隐患" width="90%" top="5vh">
      <el-form label-position="top">
        <el-form-item label="隐患简述"><el-input v-model="reportForm.title" placeholder="如：A区走廊灯不亮" /></el-form-item>
        <el-form-item label="现场实勘照片 (必传)">
          <el-upload class="h5-uploader" :action="uploadUrl" :headers="uploadHeaders" :show-file-list="false" :on-success="(res) => handleUploadSuccess(res, reportForm, 'image_url')">
            <img v-if="reportForm.image_url" :src="getFullImgUrl(reportForm.image_url)" class="uploaded-img" />
            <el-icon v-else class="uploader-icon"><Camera /></el-icon>
          </el-upload>
        </el-form-item>
        <el-form-item label="补充说明"><el-input v-model="reportForm.description" type="textarea" :rows="2" placeholder="具体位置或情况" /></el-form-item>
      </el-form>
      <template #footer><el-button type="danger" size="large" :loading="submitLoading" @click="submitReport" style="width:100%">提 交 上 报</el-button></template>
    </el-dialog>

    <el-dialog v-model="resolveDialogVisible" title="上传完工作业凭证" width="90%" top="10vh">
      <div style="margin-bottom: 10px; color: #909399; font-size: 13px;">请拍摄处理完毕后的现场，提交后中控将进行核实。</div>
      <el-upload class="h5-uploader" :action="uploadUrl" :headers="uploadHeaders" :show-file-list="false" :on-success="(res) => handleUploadSuccess(res, resolveForm, 'resolve_image_url')">
        <img v-if="resolveForm.resolve_image_url" :src="getFullImgUrl(resolveForm.resolve_image_url)" class="uploaded-img" />
        <el-icon v-else class="uploader-icon"><Camera /></el-icon>
      </el-upload>
      <el-input v-model="resolveForm.content" type="textarea" :rows="2" placeholder="（选填）消耗物资或情况备注" style="margin-top: 15px;" />
      <template #footer><el-button type="success" size="large" :loading="submitLoading" @click="submitResolve" style="width:100%">提 交 审 核</el-button></template>
    </el-dialog>

    <el-dialog v-model="patrolDialogVisible" title="防区现场打卡" width="90%" top="10vh">
      <el-form label-position="top">
        <el-form-item label="防区当前状态"><el-radio-group v-model="patrolForm.status"><el-radio :label="1">一切正常</el-radio><el-radio :label="2">发现异常</el-radio></el-radio-group></el-form-item>
        <el-form-item label="现场打卡照片">
          <el-upload class="h5-uploader" :action="uploadUrl" :headers="uploadHeaders" :show-file-list="false" :on-success="(res) => handleUploadSuccess(res, patrolForm, 'image_url')">
            <img v-if="patrolForm.image_url" :src="getFullImgUrl(patrolForm.image_url)" class="uploaded-img" />
            <el-icon v-else class="uploader-icon"><Camera /></el-icon>
          </el-upload>
        </el-form-item>
        <el-form-item label="巡查备注"><el-input v-model="patrolForm.remark" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer><el-button type="primary" size="large" :loading="submitLoading" @click="submitPatrol" style="width:100%">确 认 打 卡</el-button></template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Iphone, List, Aim, Brush, Setting, FullScreen, Refresh, UserFilled, Lock, SwitchButton, User, Box, BellFilled, Close, Timer, Camera, WarningFilled, Location, Picture } from '@element-plus/icons-vue'
import request from '../../../utils/request'

const router = useRouter()
const userInfo = ref({})

const rawOrders = ref([]); const inventoryList = ref([]); const patrolPoints = ref([]); const patrolRecords = ref([]); const msgList = ref([])
const activeTab = ref('orders'); const taskTab = ref('processing'); const patrolTab = ref('points')

const msgDrawerVisible = ref(false); const timelineDrawerVisible = ref(false)
const pwdDialogVisible = ref(false); const reportDialogVisible = ref(false); const resolveDialogVisible = ref(false); const patrolDialogVisible = ref(false)
const submitLoading = ref(false); const msgLoading = ref(false)

const currentTimeline = ref([])
const unreadCount = computed(() => msgList.value.filter(m => Number(m.is_read) === 0).length)

const uploadUrl = import.meta.env.VITE_BASE_API + '/api/upload'
const uploadHeaders = computed(() => ({ Authorization: `Bearer ${localStorage.getItem('h5_worker_token')}` }))

const reportForm = reactive({ title: '', description: '', image_url: '' })
const resolveForm = reactive({ id: '', resolve_image_url: '', content: '' })
const patrolForm = reactive({ point_id: '', status: 1, remark: '', image_url: '' })

const displayRoleType = computed(() => userInfo.value.role_type || userInfo.value.position || '综合外勤')
const isSecurity = computed(() => displayRoleType.value.includes('安保') || displayRoleType.value.includes('巡逻') || displayRoleType.value.includes('保安'))

const pendingOrders = computed(() => rawOrders.value.filter(o => o.status === 2 && Number(o.handler_id) === Number(userInfo.value.id)))
const auditOrders = computed(() => rawOrders.value.filter(o => o.status === 3 && Number(o.handler_id) === Number(userInfo.value.id)))
const completedOrders = computed(() => rawOrders.value.filter(o => o.status === 4 && Number(o.handler_id) === Number(userInfo.value.id)))
const pendingReturns = computed(() => inventoryList.value.filter(inv => inv.action_type === 3))

const actionMeta = computed(() => {
  const role = displayRoleType.value
  if (role.includes('安保') || role.includes('巡逻')) return { btnText: '防区安全巡检打卡', iconName: 'Aim', color: '#f56c6c' }
  else if (role.includes('保洁')) return { btnText: '卫生绿化清理打卡', iconName: 'Brush', color: '#67c23a' }
  else return { btnText: '设备维保扫码打卡', iconName: 'Setting', color: '#e6a23c' }
})

const getFullImgUrl = (url) => url ? (url.startsWith('http') ? url : `http://47.120.52.65:8787${url}`) : ''
const handleUploadSuccess = (res, targetForm, field) => { if (res.code === 200) targetForm[field] = res.data.url; else ElMessage.error('图片上传失败') }

const fetchWorkOrders = async () => { const res = await request.get('/api/work_order/list'); if (res.code === 200) rawOrders.value = res.data }
const fetchInventory = async () => { if (isSecurity.value) return; const res = await request.get('/api/worker/inventory'); if (res.code === 200) inventoryList.value = res.data }
const fetchMessages = async () => { if (!userInfo.value.id) return; const res = await request.get('/api/worker/notifications'); if (res.code === 200) msgList.value = res.data || [] }

const openMsgDrawer = () => { msgDrawerVisible.value = true; fetchMessages() }
const readMsg = async (msg) => { if (Number(msg.is_read) === 1) return; msg.is_read = 1; try { await request.post('/api/worker/notifications/read', { id: msg.id }) } catch (e) { msg.is_read = 0 } }
const scanCode = () => ElMessage.warning('调用摄像头功能需嵌入APP内运行')

const submitReport = async () => {
  if (!reportForm.title || !reportForm.image_url) return ElMessage.warning('简述和实勘照片不可为空')
  submitLoading.value = true
  try {
    const res = await request.post('/api/worker/work_order/report', reportForm)
    if (res.code === 200) { ElMessage.success('上报成功！等待指派'); reportDialogVisible.value = false; Object.assign(reportForm, { title: '', description: '', image_url: '' }); fetchWorkOrders() }
  } finally { submitLoading.value = false }
}

const openResolveDialog = (order) => { resolveForm.id = order.id; resolveForm.resolve_image_url = ''; resolveForm.content = ''; resolveDialogVisible.value = true }
const submitResolve = async () => {
  if (!resolveForm.resolve_image_url) return ElMessage.warning('必须拍摄现场完工照片才能结单！')
  submitLoading.value = true
  try {
    const res = await request.post('/api/work_order/action', { ...resolveForm, action: 'resolve' })
    if (res.code === 200) { ElMessage.success('提交成功！已流转至中控室。'); resolveDialogVisible.value = false; fetchWorkOrders(); taskTab.value = 'auditing' } 
  } finally { submitLoading.value = false }
}

const openTimelineDrawer = (order) => {
  currentTimeline.value = []
  if (order.process_log) { try { currentTimeline.value = JSON.parse(order.process_log) } catch (e) {} }
  timelineDrawerVisible.value = true
}
const getTimelineType = (title) => {
  if (title.includes('驳回') || title.includes('上报')) return 'danger'
  if (title.includes('通过') || title.includes('闭环')) return 'success'
  if (title.includes('验收')) return 'primary'
  return 'info'
}

const fetchPatrolPoints = async () => { const res = await request.get('/api/worker/patrol/points'); if (res.code === 200) patrolPoints.value = res.data }
const fetchPatrolRecords = async () => { const res = await request.get('/api/worker/patrol/records'); if (res.code === 200) patrolRecords.value = res.data }
const handlePatrolTabChange = (tabName) => { if (tabName === 'points') fetchPatrolPoints(); if (tabName === 'records') fetchPatrolRecords() }
const openPatrolDialog = (point) => { patrolForm.point_id = point.id; patrolForm.status = 1; patrolForm.remark = ''; patrolForm.image_url = ''; patrolDialogVisible.value = true }
const submitPatrol = async () => {
  submitLoading.value = true
  try {
    const res = await request.post('/api/worker/patrol/submit', patrolForm)
    if (res.code === 200) { 
        ElMessage.success('防区打卡成功'); 
        patrolDialogVisible.value = false;
        fetchPatrolPoints(); // 打卡成功后立马刷新点位列表让该点位消失
    } else {
        ElMessage.error(res.msg || '打卡异常');
    }
  } finally { submitLoading.value = false }
}
const handleLogout = () => { localStorage.removeItem('h5_worker_token'); localStorage.removeItem('h5_worker_user'); router.push('/h5/login') }

onMounted(() => {
  const storedUser = localStorage.getItem('h5_worker_user')
  if (storedUser) {
    try {
      const parsed = JSON.parse(storedUser)
      if (parsed && parsed.id) {
        userInfo.value = parsed; fetchWorkOrders(); fetchInventory(); fetchMessages(); fetchPatrolPoints()
        setInterval(fetchMessages, 15000)
      } else router.push('/h5/login')
    } catch (e) { router.push('/h5/login') }
  } else router.push('/h5/login')
})
</script>

<style scoped>
.mobile-container { width: 100%; max-width: 480px; margin: 0 auto; min-height: 100vh; background-color: #f5f7fa; padding-bottom: 70px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; position: relative;}
.mobile-header { background: linear-gradient(135deg, #2c3e50, #3498db); color: #fff; padding: 25px 20px 40px 20px; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
.user-greeting { flex: 1; min-width: 0; }
.header-actions { flex-shrink: 0; margin-left: 15px; padding-top: 5px; }
.msg-bell { cursor: pointer; padding: 5px; }
.user-greeting h3 { margin: 0 0 8px 0; font-size: 22px; font-weight: bold; }
.user-greeting p { margin: 0; font-size: 13px; opacity: 0.9; display: flex; align-items: center; gap: 4px; }
.responsibility-card { margin: 0 15px 15px 15px; background: #fff; border-radius: 10px; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); position: relative; z-index: 10; }
.resp-title { font-size: 14px; font-weight: bold; color: #409eff; margin-bottom: 8px; display: flex; align-items: center; gap: 5px; }
.resp-content { font-size: 13px; color: #606266; line-height: 1.6; }
.stats-panel { display: flex; margin: 0 15px 20px 15px; background: #fff; border-radius: 10px; padding: 15px 0; box-shadow: 0 2px 12px rgba(0,0,0,0.03); }
.stat-box { flex: 1; text-align: center; border-right: 1px solid #f0f0f0; }
.stat-box:last-child { border-right: none; }
.stat-num { font-size: 24px; font-weight: bold; margin-bottom: 5px; font-family: monospace; }
.stat-label { font-size: 12px; color: #909399; }
.text-danger { color: #f56c6c; }
.text-warning { color: #e6a23c; }
.text-success { color: #67c23a; }
.action-grid { display: flex; gap: 15px; padding: 0 15px; margin-bottom: 20px; }
.action-btn { flex: 1; background: #fff; border-radius: 12px; padding: 15px 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); cursor: pointer; color: #303133; font-size: 14px; border: 1px solid #ebeef5; font-weight: bold;}
.action-btn:active { background: #f0f2f5; }
.action-icon { font-size: 28px; }
.task-list { padding: 0 15px; }
.list-title { font-size: 15px; font-weight: bold; color: #303133; margin-bottom: 15px; border-left: 4px solid #409eff; padding-left: 8px; }
.task-card { background: #fff; border-radius: 10px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #f0f2f5; transition: opacity 0.3s;}
.task-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #f0f2f5; padding-bottom: 10px; }
.task-title { font-weight: bold; font-size: 15px; color: #303133; }
.task-body p { margin: 0 0 8px 0; font-size: 13px; color: #606266; line-height: 1.5; }
.task-body strong { color: #303133; }
.image-preview-box { margin-top: 10px; background-color: #f8f9fa; padding: 10px; border-radius: 8px; }
.task-footer { margin-top: 15px; }
.full-btn { width: 100%; border-radius: 8px; font-weight: bold; letter-spacing: 1px; }
.profile-wrapper { padding: 0 15px; margin-top: -20px; position: relative; z-index: 10; }
.profile-header { background: #fff; border-radius: 10px; padding: 25px 20px; display: flex; align-items: center; gap: 15px; margin-bottom: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
.profile-header .avatar { width: 60px; height: 60px; background: #e6f1fc; color: #409eff; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 30px; }
.profile-header .name { font-size: 18px; font-weight: bold; color: #303133; margin-bottom: 5px; }
.profile-menu { background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,0.03); border: 1px solid #f0f2f5; }
.menu-item { display: flex; align-items: center; padding: 18px 20px; font-size: 15px; color: #303133; border-bottom: 1px solid #fafafa; cursor: pointer; }
.menu-item:active { background-color: #f5f7fa; }
.menu-item:last-child { border-bottom: none; }
.menu-item .el-icon { margin-right: 10px; font-size: 18px; color: #909399; }
.bottom-tabbar { position: fixed; bottom: 0; left: 0; right: 0; max-width: 480px; margin: 0 auto; height: 55px; background: #fff; display: flex; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); z-index: 100; border-top: 1px solid #ebeef5; }
.tab-item { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; color: #909399; font-size: 11px; cursor: pointer; }
.tab-item .el-icon { font-size: 22px; margin-bottom: 3px; }
.tab-item.active { color: #409eff; }
.drawer-header { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f2f5; }
.msg-list { padding: 15px; height: calc(100% - 55px); overflow-y: auto; background-color: #f5f7fa; }
.msg-card { background: #fff; border-radius: 8px; padding: 15px; margin-bottom: 12px; cursor: pointer; border: 1px solid #ebeef5; }
.msg-card.unread { border-left: 3px solid #f56c6c; box-shadow: 0 2px 8px rgba(245, 108, 108, 0.1); }
.msg-header { display: flex; justify-content: space-between; margin-bottom: 8px; align-items: center;}
.msg-title { font-size: 14px; font-weight: bold; color: #303133; position: relative; }
.red-dot { display: inline-block; width: 6px; height: 6px; background-color: #f56c6c; border-radius: 50%; vertical-align: middle; margin-right: 4px; }
.msg-time { font-size: 11px; color: #909399; }
.msg-content { font-size: 13px; color: #606266; line-height: 1.5; }
.h5-uploader { border: 1px dashed #d9d9d9; border-radius: 6px; cursor: pointer; position: relative; overflow: hidden; width: 100%; height: 120px; display: flex; align-items: center; justify-content: center; background-color: #fbfdff;}
.uploader-icon { font-size: 28px; color: #8c939d; }
.uploaded-img { width: 100%; height: 100%; object-fit: cover; }
:deep(.el-tabs__nav-wrap::after) { height: 1px; }
</style>