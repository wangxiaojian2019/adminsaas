<template>
  <div class="meeting-container">
    <div class="section-title">园区共享会议室资产大盘</div>
    <el-row :gutter="20" class="room-row">
      <el-col :span="8" v-for="room in roomAssets" :key="room.id">
        <el-card class="room-card" shadow="hover">
          <div class="room-header">
            <span class="room-name">{{ room.name }}</span>
            <el-tag :type="room.status === 'idle' ? 'success' : 'warning'">{{ room.status === 'idle' ? '当前空闲' : '使用中' }}</el-tag>
          </div>
          <div class="room-details">
            <p><strong>容纳人数：</strong> {{ room.capacity }} 人</p>
            <p><strong>基础定价：</strong> <span class="price-text">￥{{ room.price_per_hour }}/小时</span></p>
            <p class="equipments">
              <el-tag size="small" type="info" v-if="room.has_projector">4K投影仪</el-tag>
              <el-tag size="small" type="info" v-if="room.has_whiteboard">智能白板</el-tag>
              <el-tag size="small" type="info" v-if="room.has_video_conf">视频会议</el-tag>
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

      <el-table :data="bookingList" border style="width: 100%">
        <el-table-column prop="booking_no" label="预订单号" width="160" />
        <el-table-column prop="enterprise_name" label="申请企业" width="200" />
        <el-table-column prop="room_name" label="预订会议室" width="140" />
        <el-table-column label="预订时间段" width="280">
          <template #default="{ row }">
            <strong>{{ row.date }}</strong> ( {{ row.start_time }} ~ {{ row.end_time }} )
            <el-tag size="small" style="margin-left:8px">{{ row.duration }}H</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="产生费用 (免首2小时)" width="160">
          <template #default="{ row }">
            <span v-if="row.cost <= 0" style="color: #67C23A; font-weight: bold;">免费额度抵扣</span>
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
            <span v-else-if="row.status == 1" style="font-size:12px; color:#909399">已通过, 待使用</span>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog title="新建会议室预订 (内部/租户代提)" v-model="dialogVisible" width="550px">
      <el-form :model="form" label-width="110px">
        <el-form-item label="预订企业"><el-input v-model="form.enterprise_name" placeholder="输入入驻企业全称" /></el-form-item>
        <el-form-item label="选择会议室">
          <el-select v-model="form.room_id" style="width: 100%">
            <el-option v-for="room in roomAssets" :key="room.id" :label="`${room.name} (￥${room.price_per_hour}/h)`" :value="room.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="使用日期"><el-date-picker v-model="form.date" type="date" value-format="YYYY-MM-DD" style="width: 100%" /></el-form-item>
        <el-form-item label="时间段">
          <el-time-select v-model="form.start_time" start="08:00" step="00:30" end="22:00" placeholder="开始" style="width: 48%" /> 至 
          <el-time-select v-model="form.end_time" start="08:00" step="00:30" end="22:00" :min-time="form.start_time" placeholder="结束" style="width: 48%" />
        </el-form-item>
        <el-form-item label="会议主题"><el-input v-model="form.topic" placeholder="如：季度业务复盘会" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitBooking">提交预订申请</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElNotification, ElMessageBox } from 'element-plus'
import request from '@/utils/request' // 接入真实 API

const dialogVisible = ref(false)
const roomAssets = ref([])
const bookingList = ref([])
const form = ref({ enterprise_name: '', room_id: null, date: '', start_time: '', end_time: '', topic: '' })

const getStatusText = (status) => ({ 0: '待审批', 1: '审批通过', 2: '已驳回', 3: '已结束' }[status])
const getStatusTag = (status) => ({ 0: 'warning', 1: 'success', 2: 'danger', 3: 'info' }[status])

// 真实接口：拉取所有会议室资产
const fetchRooms = async () => {
  const res = await request.get('/api/v1/meeting/rooms')
  if (res.code === 200) roomAssets.value = res.data
}

// 真实接口：拉取预订记录列表
const fetchBookings = async () => {
  const res = await request.get('/api/v1/meeting/list')
  if (res.code === 200) bookingList.value = res.data
}

onMounted(() => {
  fetchRooms()
  fetchBookings()
})

const openBookingDialog = () => {
  form.value = { enterprise_name: '', room_id: null, date: '', start_time: '', end_time: '', topic: '' }
  dialogVisible.value = true
}

// 真实接口：提交预订 (后端进行防冲突拦截和计费计算)
const submitBooking = async () => {
  if (!form.value.enterprise_name || !form.value.room_id || !form.value.date || !form.value.end_time) {
    return ElMessage.error('请填写完整的预订信息')
  }
  const res = await request.post('/api/v1/meeting/apply', form.value)
  if (res.code === 200) {
    ElMessage.success(res.msg)
    dialogVisible.value = false
    fetchBookings() // 刷新列表
  } else if (res.code === 409) {
    // 触发了后端的防冲突拦截
    ElMessageBox.alert(res.msg, '时间防重叠安全拦截', { type: 'error' })
  } else {
    ElMessage.error(res.msg)
  }
}

// 真实接口：审批流程 (后端自动向财务生单)
const auditBooking = async (row, actionType) => {
  const res = await request.post('/api/v1/meeting/audit', { id: row.id, status: actionType })
  if (res.code === 200) {
    if (actionType === 1 && row.cost > 0) {
      ElNotification({ title: '业财联动执行成功', message: res.msg, type: 'success', duration: 4000 })
    } else {
      ElMessage.success(res.msg)
    }
    fetchBookings() // 刷新列表状态
  } else {
    ElMessage.error(res.msg)
  }
}
</script>

<style scoped>
.meeting-container { padding: 20px; }
.section-title { font-size: 16px; font-weight: bold; margin-bottom: 15px; color: #303133; border-left: 4px solid #409EFF; padding-left: 10px; }
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