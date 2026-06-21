<template>
  <div class="leads-container">
    <el-card shadow="never" class="main-card">
      <div class="header-toolbar">
        <h2 class="page-title">招商与线索管控中心</h2>
        <div>
          <el-button type="warning" icon="Download" size="large" @click="exportData">带水印导出线索</el-button>
          <el-button type="primary" icon="Plus" size="large" @click="openAddDialog">录入新线索 (锁定保护期)</el-button>
        </div>
      </div>

      <el-tabs v-model="activeTab" class="leads-tabs" @tab-change="handleTabChange">
        <el-tab-pane name="private">
          <template #label>
            <span class="tab-label">
              <el-icon><Briefcase /></el-icon> 私海 (我的线索/团队线索)
            </span>
          </template>
        </el-tab-pane>
        <el-tab-pane name="public">
          <template #label>
            <span class="tab-label text-danger">
              <el-icon><WarnTriangleFilled /></el-icon> 公海 (沉睡掉落池)
            </span>
          </template>
        </el-tab-pane>
      </el-tabs>

      <el-table :data="tableData" v-loading="loading" border stripe style="width: 100%; margin-top: 15px;">
        <el-table-column prop="id" label="线索编号" width="90" align="center" />
        <el-table-column prop="customer_name" label="企业/客户名称" min-width="180">
          <template #default="{ row }">
            <span style="font-weight: bold; color: #303133;">{{ row.customer_name }}</span>
            <el-tag v-if="activeTab === 'public'" size="small" type="danger" effect="dark" style="margin-left: 8px;">已掉落</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="contact_person" label="对接人" width="120" />
        <el-table-column prop="phone" label="联系电话" width="140">
          <template #default="{ row }">
            <span style="font-family: monospace; font-size: 14px;">{{ row.phone }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="demand_area" label="需求面积 (㎡)" width="120" align="right">
          <template #default="{ row }">
            <span v-if="row.demand_area > 0">{{ row.demand_area }} ㎡</span>
            <span v-else class="text-muted">未明确</span>
          </template>
        </el-table-column>

        <el-table-column label="归属与活跃状态" min-width="200">
          <template #default="{ row }">
            <div class="status-cell">
              <div class="owner">当前归属：<el-tag size="small" type="info">{{ row.owner_name || '无归属(公海)' }}</el-tag></div>
              <div class="time-track" :class="{ 'text-danger': isNearDrop(row.last_track_time) && activeTab === 'private' }">
                最后心跳：{{ row.last_track_time || row.created_at }}
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="首次建档日期" width="150" align="center">
          <template #default="{ row }">
            <span style="font-size: 12px; color: #909399;">{{ row.created_at }}</span>
          </template>
        </el-table-column>

        <el-table-column label="操作引擎" width="220" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="success" link icon="Microphone" @click="openFollowDialog(row)">录入跟进</el-button>
            <el-button type="primary" link icon="Document" @click="openHistoryDialog(row)">审查时间轴</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="addDialogVisible" title="录入全新招商线索" width="550px" @close="addFormRef?.resetFields()">
      <el-form ref="addFormRef" :model="addForm" :rules="addRules" label-width="110px">
        <el-form-item label="客户名称" prop="customer_name">
          <el-input v-model="addForm.customer_name" placeholder="请输入企业名称或客户全称" />
        </el-form-item>
        <el-form-item label="联系人" prop="contact_person">
          <el-input v-model="addForm.contact_person" placeholder="例如：张总" />
        </el-form-item>
        <el-form-item label="联系电话" prop="phone">
          <el-input v-model="addForm.phone" placeholder="手机号或座机" />
        </el-form-item>
        <el-form-item label="需求面积(㎡)" prop="demand_area">
          <el-input-number v-model="addForm.demand_area" :min="0" :step="50" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="线索来源" prop="source">
          <el-select v-model="addForm.source" style="width: 100%;">
            <el-option :value="1" label="400电话入呼" />
            <el-option :value="2" label="官网表单留言" />
            <el-option :value="3" label="中介/渠道转介" />
            <el-option :value="4" label="自主陌拜开发" />
          </el-select>
        </el-form-item>
      </el-form>
      <div class="dialog-notice">
        <el-icon><InfoFilled /></el-icon> 录入成功后，该线索将自动划入您的私海，并开启15天强制跟进倒计时。
      </div>
      <template #footer>
        <el-button @click="addDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitAdd">确认录入</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="followDialogVisible" title="录入业务跟进纪要" width="600px" @close="followFormRef?.resetFields()">
      <el-form ref="followFormRef" :model="followForm" :rules="followRules" label-width="110px">
        <el-form-item label="意向级别判断" prop="intent_level">
          <el-radio-group v-model="followForm.intent_level">
            <el-radio-button label="高">高意向 (随时签约)</el-radio-button>
            <el-radio-button label="中">中意向 (需持续触达)</el-radio-button>
            <el-radio-button label="低">低意向 (保持观望)</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="跟进纪要内容" prop="content">
          <el-input 
            v-model="followForm.content" 
            type="textarea" 
            :rows="4" 
            placeholder="请详细记录本次沟通的核心诉求、抗拒点及下一步动作..." 
          />
        </el-form-item>
        <el-form-item label="下次跟进计划" prop="next_follow_time">
          <el-date-picker
            v-model="followForm.next_follow_time"
            type="datetime"
            placeholder="选择下次需触达的时间节点"
            format="YYYY-MM-DD HH:mm"
            value-format="YYYY-MM-DD HH:mm:ss"
            style="width: 100%;"
          />
        </el-form-item>
      </el-form>
      <div class="dialog-notice text-success" style="background-color: #f0f9eb; border-color: #e1f3d8;">
        <el-icon><CircleCheckFilled /></el-icon> 提交跟进后，此线索的15天私海保护期将被重置。
      </div>
      <template #footer>
        <el-button @click="followDialogVisible = false">取消</el-button>
        <el-button type="success" :loading="submitLoading" @click="submitFollow">写入纪要并重置心跳</el-button>
      </template>
    </el-dialog>

    <el-drawer v-model="historyDrawerVisible" title="线索全生命周期跟进溯源" size="40%">
      <div class="history-container" v-loading="historyLoading">
        <el-empty v-if="historyData.length === 0" description="暂无任何跟进心跳记录" />
        <el-timeline v-else>
          <el-timeline-item
            v-for="(activity, index) in historyData"
            :key="index"
            :timestamp="activity.created_at"
            placement="top"
            :color="activity.intent_level === '高' ? '#f56c6c' : (activity.intent_level === '中' ? '#e6a23c' : '#909399')"
          >
            <el-card shadow="hover" class="timeline-card">
              <div class="timeline-header">
                <span class="operator"><el-icon><User /></el-icon> {{ activity.operator_name }}</span>
                <el-tag size="small" :type="activity.intent_level === '高' ? 'danger' : 'warning'">意向: {{ activity.intent_level }}</el-tag>
              </div>
              <div class="timeline-content">{{ activity.content }}</div>
              <div v-if="activity.next_follow_time" class="timeline-footer">
                <el-icon><Timer /></el-icon> 计划下次跟进: {{ activity.next_follow_time }}
              </div>
            </el-card>
          </el-timeline-item>
        </el-timeline>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import request from '../../utils/request'

const activeTab = ref('private')
const tableData = ref([])
const loading = ref(false)
const submitLoading = ref(false)

const addDialogVisible = ref(false)
const followDialogVisible = ref(false)
const historyDrawerVisible = ref(false)

const addFormRef = ref(null)
const followFormRef = ref(null)
const currentLeadId = ref(null)

const addForm = reactive({ customer_name: '', contact_person: '', phone: '', demand_area: 0, source: 1 })
const addRules = {
  customer_name: [{ required: true, message: '企业/客户名称不可为空', trigger: 'blur' }],
  phone: [{ required: true, message: '联系电话是后续跟进唯一凭证', trigger: 'blur' }]
}

const followForm = reactive({ intent_level: '中', content: '', next_follow_time: '' })
const followRules = {
  content: [{ required: true, message: '请务必填写沟通纪要，防敷衍审计', trigger: 'blur' }]
}

const historyData = ref([])
const historyLoading = ref(false)

const fetchLeads = async () => {
  loading.value = true
  try {
    const res = await request.get(`/api/leads/list?type=${activeTab.value}`)
    if (res.code === 200) tableData.value = res.data
  } finally {
    loading.value = false
  }
}

const handleTabChange = () => {
  fetchLeads()
}

const openAddDialog = () => { addDialogVisible.value = true }
const submitAdd = () => {
  addFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    const res = await request.post('/api/leads/add', addForm)
    if (res.code === 200) {
      ElMessage.success(res.msg)
      addDialogVisible.value = false
      activeTab.value = 'private' 
      fetchLeads()
    }
    submitLoading.value = false
  })
}

const openFollowDialog = (row) => {
  currentLeadId.value = row.id
  followDialogVisible.value = true
}

const submitFollow = () => {
  followFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    const payload = { lead_id: currentLeadId.value, ...followForm }
    const res = await request.post('/api/leads/follow/add', payload)
    if (res.code === 200) {
      ElMessage.success(res.msg)
      followDialogVisible.value = false
      fetchLeads() 
    }
    submitLoading.value = false
  })
}

const openHistoryDialog = async (row) => {
  historyDrawerVisible.value = true
  historyLoading.value = true
  try {
    const res = await request.get(`/api/leads/follow/list?lead_id=${row.id}`)
    if (res.code === 200) historyData.value = res.data
  } finally {
    historyLoading.value = false
  }
}

const isNearDrop = (trackTimeStr) => {
  if (!trackTimeStr) return true
  const trackTime = new Date(trackTimeStr).getTime()
  const now = new Date().getTime()
  const diffDays = (now - trackTime) / (1000 * 3600 * 24)
  return diffDays > 12 
}

// 核心新增：招商线索加密导出逻辑
const exportData = async () => {
  ElMessage.info('正在提取线索明细数据...')
  const token = localStorage.getItem('saas_token')
  try {
    const res = await fetch(`http://47.120.52.65:8787/api/export/download?module=leads`, {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    const blob = await res.blob()
    const a = document.createElement('a')
    a.href = window.URL.createObjectURL(blob)
    a.download = `招商线索数据池_${new Date().getTime()}.csv`
    a.click()
    ElMessage.success('线索导出成功，严禁外泄')
  } catch (e) { ElMessage.error('导出通讯失败') }
}

onMounted(() => {
  fetchLeads()
})
</script>

<style scoped>
.leads-container { width: 100%; }
.main-card { border-radius: 6px; border: none; }
.header-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { margin: 0; font-size: 20px; color: #303133; font-weight: 600; }
.leads-tabs { margin-bottom: 10px; }
.tab-label { font-size: 15px; font-weight: bold; display: flex; align-items: center; gap: 5px; }
.text-danger { color: #f56c6c; }
.text-muted { color: #909399; font-style: italic; }

.status-cell { font-size: 13px; line-height: 1.8; }
.status-cell .owner { color: #606266; }
.status-cell .time-track { color: #909399; font-family: monospace; }

.dialog-notice { margin-top: 15px; padding: 10px 15px; background-color: #f4f4f5; border: 1px solid #e9e9eb; border-radius: 4px; color: #909399; font-size: 13px; display: flex; align-items: center; gap: 8px; }

.history-container { padding: 10px 20px; }
.timeline-card { border-radius: 6px; border: 1px solid #ebeef5; box-shadow: none; }
.timeline-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px dashed #ebeef5; padding-bottom: 8px; }
.operator { font-weight: bold; color: #409eff; display: flex; align-items: center; gap: 4px; }
.timeline-content { font-size: 14px; color: #303133; line-height: 1.6; white-space: pre-wrap; }
.timeline-footer { margin-top: 12px; padding-top: 8px; border-top: 1px solid #f4f4f5; font-size: 12px; color: #909399; display: flex; align-items: center; gap: 4px; }
</style>