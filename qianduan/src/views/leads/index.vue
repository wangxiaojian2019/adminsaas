<template>
  <div class="leads-container">
    <el-card shadow="never" class="main-card">
      <div class="header-toolbar">
        <h2 class="page-title">招商与线索管控中心</h2>
        <div>
          <ExportBtn module="leads" />
          <el-button type="primary" icon="Plus" size="large" @click="openAddDialog" style="margin-left: 10px;">录入新线索 (锁定保护期)</el-button>
        </div>
      </div>

      <el-alert 
        title="防囤积 SOP 规则执行中：线索超过 15 天未实质跟进将被强制流放至公海池。原拥有者将受到 7 天惩罚期限制不可重复捞回，团队内其他人可自由介入抢单。" 
        type="error" 
        show-icon 
        :closable="false" 
        style="margin-bottom: 20px;" 
      />

      <el-tabs v-model="activeTab" class="leads-tabs" @tab-change="handleTabChange">
        <el-tab-pane name="private">
          <template #label>
            <span class="tab-label">
              <el-icon><Briefcase /></el-icon> 客户私海 (我的线索/团队线索)
            </span>
          </template>
        </el-tab-pane>
        <el-tab-pane name="public">
          <template #label>
            <span class="tab-label text-danger">
              <el-icon><WarnTriangleFilled /></el-icon> 客户公海 (沉睡掉落池)
            </span>
          </template>
        </el-tab-pane>
      </el-tabs>

      <el-table :data="tableData" v-loading="loading" border stripe style="width: 100%; margin-top: 15px;">
        <el-table-column prop="id" label="线索编号" width="90" align="center" />
        <el-table-column prop="customer_name" label="企业/客户名称" min-width="180">
          <template #default="{ row }">
            <span style="font-weight: bold; color: #303133;">{{ row.customer_name }}</span>
            <el-tag v-if="activeTab === 'public'" size="small" type="danger" effect="dark" style="margin-left: 8px;">流放池</el-tag>
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
              <div class="owner">当前归属：
                <el-tag size="small" :type="activeTab === 'public' ? 'danger' : 'info'">{{ row.responsible_person || '系统公海池' }}</el-tag>
              </div>
              <div class="time-track" v-if="activeTab === 'private'" :class="{ 'text-danger': isNearDrop(row.last_follow_time) }">
                最后心跳：{{ row.last_follow_time || row.created_at }}
              </div>
              <div class="time-track text-danger" v-else>
                掉落时间：{{ row.drop_time }}
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
            <el-button type="success" link icon="Microphone" @click="openFollowDialog(row)">
              {{ activeTab === 'public' ? '捞取并跟进' : '录入跟进' }}
            </el-button>
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
        <el-form-item label="更新线索状态" prop="status">
          <el-radio-group v-model="followForm.status">
            <el-radio-button :label="1">持续跟进</el-radio-button>
            <el-radio-button :label="2">意向强烈</el-radio-button>
            <el-radio-button :label="3">已签约</el-radio-button>
            <el-radio-button :label="4">已流失</el-radio-button>
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
      </el-form>
      <div class="dialog-notice text-success" style="background-color: #f0f9eb; border-color: #e1f3d8;">
        <el-icon><CircleCheckFilled /></el-icon> 
        <span v-if="activeTab === 'public'">提交并判定无防囤积拦截后，线索将立即划归至您的私海。</span>
        <span v-else>提交跟进后，此线索的 15 天保护期倒计时将被重置。</span>
      </div>
      <template #footer>
        <el-button @click="followDialogVisible = false">取消</el-button>
        <el-button type="success" :loading="submitLoading" @click="submitFollow">写入纪要并执行流转</el-button>
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
            color="#409eff"
          >
            <el-card shadow="hover" class="timeline-card">
              <div class="timeline-header">
                <span class="operator"><el-icon><User /></el-icon> {{ activity.operator_name }}</span>
              </div>
              <div class="timeline-content">{{ activity.content }}</div>
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
import { Briefcase, WarnTriangleFilled, Plus, Download, InfoFilled, CircleCheckFilled, Microphone, Document, User } from '@element-plus/icons-vue'
import request from '../../utils/request'
import ExportBtn from '../../components/ExportBtn.vue' // 核心织入引入

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

const followForm = reactive({ status: 1, content: '' })
const followRules = {
  content: [{ required: true, message: '请务必填写沟通纪要，防敷衍审计', trigger: 'blur' }]
}

const historyData = ref([])
const historyLoading = ref(false)

const fetchLeads = async () => {
  loading.value = true
  try {
    const res = await request.get(`/api/leads/list`)
    if (res.code === 200) {
      if (activeTab.value === 'private') {
        tableData.value = res.data.filter(item => item.admin_id !== 0)
      } else {
        tableData.value = res.data.filter(item => item.admin_id === 0)
      }
    }
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
    } else {
      ElMessage.error(res.msg)
    }
    submitLoading.value = false
  })
}

const openFollowDialog = (row) => {
  currentLeadId.value = row.id
  followForm.status = row.status || 1
  followForm.content = ''
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
      activeTab.value = 'private'
      fetchLeads() 
    } else {
      ElMessage.error(res.msg || '操作遭到拦截')
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
.text-danger { color: #f56c6c; font-weight: bold; }
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
</style>