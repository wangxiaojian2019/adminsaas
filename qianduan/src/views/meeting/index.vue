<template>
  <div class="meeting-container">
    <div class="header-tools">
      <div class="section-title">园区共享会议室资产大盘</div>
      <el-button type="success" :icon="Setting" @click="goToRoomConfig">⚙️ 资产底库配置 (新增/管理会议室)</el-button>
    </div>

    <el-row :gutter="20" class="room-row">
      <el-col :span="8" v-for="room in roomAssets" :key="room.id">
        <el-card class="room-card" shadow="hover">
          <div class="room-header">
            <span class="room-name">{{ room.name }}</span>
            <el-tag :type="room.status === 'active' ? 'success' : 'info'">{{ room.status === 'active' ? '开放中' : '已停用' }}</el-tag>
          </div>
          <div class="room-details">
            <p><strong>容纳人数：</strong> {{ room.capacity }} 人</p>
            <p><strong>计费策略：</strong> 
              <span v-if="room.free_hours > 0" style="color: #67C23A">前 {{ room.free_hours }}H 免费，</span>
              <span class="price-text">￥{{ room.price_per_hour }}/小时</span>
            </p>
            <p class="equipments">
              <el-tag size="small" type="primary" v-if="room.has_projector">智能投影</el-tag>
              <el-tag size="small" type="primary" v-if="room.has_video_conf">视频会议</el-tag>
            </p>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card class="booking-card" shadow="never">
      <template #header>
        <div class="card-header">
          <span class="title">会议室预订审批工作台</span>
          <el-button type="primary" @click="openBookingDialog">内部代客新建预订</el-button>
        </div>
      </template>

      <el-table :data="bookingList" border style="width: 100%" v-loading="loading">
        <el-table-column prop="booking_no" label="预订单号" width="160" />
        <el-table-column prop="enterprise_name" label="申请企业" width="200" />
        <el-table-column prop="room_name" label="预订会议室" width="140" />
        <el-table-column label="预订时间段" width="280">
          <template #default="{ row }">
            <strong>{{ row.date }}</strong> ( {{ row.start_time }} ~ {{ row.end_time }} )
            <el-tag size="small" style="margin-left:8px">{{ row.duration }}H</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="产生费用 (系统核算)" width="160">
          <template #default="{ row }">
            <span v-if="row.cost <= 0" style="color: #67C23A; font-weight: bold;">免费额度全额抵扣</span>
            <span v-else style="color: #F56C6C; font-weight: bold;">￥{{ row.cost }}</span>
          </template>
        </el-table-column>
        <el-table-column label="审批状态" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusTag(row.status)">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作台" width="200" align="center">
          <template #default="{ row }">
            <template v-if="row.status == 0">
              <el-button size="small" type="success" @click="auditBooking(row, 1)">同意</el-button>
              <el-button size="small" type="danger" @click="auditBooking(row, 2)">驳回</el-button>
            </template>
            <span v-else-if="row.status == 1" style="font-size:12px; color:#909399">已通过审核，待履约</span>
            <span v-else-if="row.status == 2" style="font-size:12px; color:#F56C6C">已驳回预订</span>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog title="新建会议室预订 (内部管理代提)" v-model="dialogVisible" width="550px">
      <el-form :model="form" label-width="110px">
        <el-form-item label="预订企业">
          <el-select v-model="form.enterprise_id" placeholder="搜索并选择关联企业" filterable style="width: 100%">
            <el-option v-for="ent in enterpriseList" :key="ent.id" :label="ent.name" :value="ent.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="选择会议室">
          <el-select v-model="form.room_id" placeholder="选择资产底库" style="width: 100%">
            <el-option v-for="room in roomAssets" :key="room.id" :label="`${room.name} (￥${room.price_per_hour}/h)`" :value="room.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="使用日期">
          <el-date-picker v-model="form.date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
        </el-form-item>
        <el-form-item label="时间段">
          <el-time-select v-model="form.start_time" start="08:00" step="00:30" end="22:00" placeholder="开始" style="width: 48%" /> 至 
          <el-time-select v-model="form.end_time" start="08:30" step="00:30" end="22:30" :min-time="form.start_time" placeholder="结束" style="width: 48%" />
        </el-form-item>
        <el-form-item label="会议主题">
          <el-input v-model="form.topic" placeholder="如：季度业务复盘会" />
        </el-form-item>
      </el-form>
      <div style="background: #fdf6ec; padding: 15px; text-align: center; border-radius: 4px; margin-top: 20px;">
        <span style="font-size: 13px; color: #e6a23c;">代客录单系统预估费用：</span>
        <span style="font-size: 20px; font-weight: bold; color: #f56c6c;">￥{{ previewCost }}</span>
      </div>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitBooking" :loading="submitLoading">确认系统下单</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElNotification, ElMessageBox } from 'element-plus'
import { Setting } from '@element-plus/icons-vue'
import request from '@/utils/request'

const router = useRouter()
const loading = ref(false)
const dialogVisible = ref(false)
const submitLoading = ref(false)

const roomAssets = ref([])
const bookingList = ref([])
const enterpriseList = ref([])

const form = reactive({ enterprise_id: null, room_id: null, date: '', start_time: '', end_time: '', topic: '' })

const getStatusText = (status) => ({ 0: '待审批', 1: '审批通过', 2: '已驳回', 3: '已结束' }[status])
const getStatusTag = (status) => ({ 0: 'warning', 1: 'success', 2: 'danger', 3: 'info' }[status])

// 路由穿透：前往会议室资产配置中心
const goToRoomConfig = () => {
  router.push('/meeting/room')
}

const fetchDictData = async () => {
  const res = await request.get('/api/enterprises/list')
  if (res.code === 200) enterpriseList.value = res.data
}

const fetchRooms = async () => {
  const res = await request.get('/api/v1/meeting/rooms/list') 
  if (res.code === 200) roomAssets.value = res.data
}

const fetchBookings = async () => {
  loading.value = true
  try {
    const res = await request.get('/api/v1/meeting/list')
    if (res.code === 200) bookingList.value = res.data
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchDictData()
  fetchRooms()
  fetchBookings()
})

const openBookingDialog = () => {
  Object.assign(form, { enterprise_id: null, room_id: null, date: '', start_time: '', end_time: '', topic: '' })
  dialogVisible.value = true
}

const previewCost = computed(() => {
  if (!form.room_id || !form.start_time || !form.end_time) return '0.00'
  const room = roomAssets.value.find(r => r.id === form.room_id)
  if (!room) return '0.00'
  
  const start = new Date(`2000-01-01T${form.start_time}:00`)
  const end = new Date(`2000-01-01T${form.end_time}:00`)
  const hours = (end - start) / 3600000
  if (hours <= 0) return '0.00'
  
  const freeHours = room.free_hours ? parseFloat(room.free_hours) : 0
  const billableHours = Math.max(0, hours - freeHours)
  
  return (billableHours * parseFloat(room.price_per_hour)).toFixed(2)
})

const submitBooking = async () => {
  if (!form.enterprise_id || !form.room_id || !form.date || !form.start_time || !form.end_time) {
    return ElMessage.error('请填写完整的强关联预订信息')
  }
  submitLoading.value = true
  try {
    const res = await request.post('/api/v1/meeting/apply', form)
    if (res.code === 200) {
      ElMessage.success(res.msg)
      dialogVisible.value = false
      fetchBookings() 
    } else if (res.code === 409) {
      ElMessageBox.alert(res.msg, '时间防重叠安全拦截', { type: 'error' })
    } else {
      ElMessage.error(res.msg)
    }
  } finally {
    submitLoading.value = false
  }
}

const auditBooking = async (row, actionType) => {
  const res = await request.post('/api/v1/meeting/audit', { id: row.id, status: actionType })
  if (res.code === 200) {
    if (actionType === 1 && row.cost > 0) {
      ElNotification({ title: '业财联动执行成功', message: res.msg, type: 'success', duration: 4000 })
    } else {
      ElMessage.success(res.msg)
    }
    fetchBookings()
  } else {
    ElMessage.error(res.msg)
  }
}
</script>

<style scoped>
.meeting-container { padding: 20px; }
.header-tools { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px dashed #ebeef5; }
.section-title { font-size: 16px; font-weight: bold; margin-bottom: 0; color: #303133; border-left: 4px solid #409EFF; padding-left: 10px; }
.room-row { margin-bottom: 25px; }
.room-card { border-radius: 8px; }
.room-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ebeef5; padding-bottom: 10px; margin-bottom: 10px; }
.room-name { font-size: 16px; font-weight: bold; color: #303133; }
.room-details p { margin: 8px 0; font-size: 14px; color: #606266; }
.price-text { color: #E6A23C; font-weight: bold; font-size: 16px; }
.equipments .el-tag { margin-right: 5px; margin-top: 5px; }
.booking-card { border-radius: 8px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.card-header .title { font-size: 16px; font-weight: bold; }
</style>