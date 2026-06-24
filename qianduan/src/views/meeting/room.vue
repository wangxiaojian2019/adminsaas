<template>
  <div class="meeting-room-container">
    <el-card shadow="never">
      <div class="toolbar" style="margin-bottom: 20px;">
        <span style="font-size: 16px; font-weight: bold;">共享会议室配置矩阵</span>
        <el-button type="primary" :icon="Plus" @click="openDialog('add')">新增会议室</el-button>
      </div>

      <el-table :data="tableData" border v-loading="loading">
        <el-table-column prop="name" label="会议室名称" width="200" />
        <el-table-column prop="capacity" label="容纳人数" width="100" align="center">
          <template #default="{ row }">{{ row.capacity }} 人</template>
        </el-table-column>
        <el-table-column label="计费策略配置" width="250">
          <template #default="{ row }">
            <div>
              <el-tag size="small" type="success" v-if="row.free_hours > 0">前 {{ row.free_hours }} 小时免费</el-tag>
              <el-tag size="small" type="info" v-else>无免费额度</el-tag>
            </div>
            <div style="margin-top: 5px; color: #f56c6c; font-weight: bold;">
              超时收费：¥{{ row.price_per_hour }} / 时
            </div>
          </template>
        </el-table-column>
        <el-table-column label="硬件设施配置" width="150" align="center">
          <template #default="{ row }">
            <el-tag size="small" :type="row.has_projector ? 'primary' : 'info'" style="margin-right: 5px;">投影仪</el-tag>
            <el-tag size="small" :type="row.has_video_conf ? 'primary' : 'info'">视频会议</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="运营状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'danger'">
              {{ row.status === 'active' ? '正常开放' : '已停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" align="center">
          <template #default="{ row }">
            <el-button size="small" type="primary" plain @click="openDialog('edit', row)">配置</el-button>
            <el-button size="small" type="danger" plain @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog :title="dialogType === 'add' ? '新增会议室' : '配置会议室'" v-model="dialogVisible" width="550px" @close="resetForm">
      <el-form ref="formRef" :model="form" label-width="120px" :rules="rules">
        <el-form-item label="会议室名称" prop="name">
          <el-input v-model="form.name" placeholder="例如：A栋101大型董事局" />
        </el-form-item>
        <el-form-item label="最大容纳人数" prop="capacity">
          <el-input-number v-model="form.capacity" :min="1" :max="500" />
        </el-form-item>
        <el-divider content-position="left">智能计费策略</el-divider>
        <el-form-item label="单次免费时长" prop="free_hours">
          <el-input-number v-model="form.free_hours" :min="0" :step="0.5" />
          <span style="margin-left: 10px; color: #909399; font-size: 12px;">单位：小时 (0代表不免费)</span>
        </el-form-item>
        <el-form-item label="超时收费单价" prop="price_per_hour">
          <el-input-number v-model="form.price_per_hour" :min="0" :step="10" />
          <span style="margin-left: 10px; color: #909399; font-size: 12px;">单位：元/小时</span>
        </el-form-item>
        <el-divider content-position="left">资产设施选项</el-divider>
        <el-form-item label="配套设施">
          <el-checkbox v-model="form.has_projector" :true-label="1" :false-label="0">提供投影仪/白板</el-checkbox>
          <el-checkbox v-model="form.has_video_conf" :true-label="1" :false-label="0">提供视频会议系统</el-checkbox>
        </el-form-item>
        <el-form-item label="运营状态">
          <el-radio-group v-model="form.status">
            <el-radio label="active">正常开放预订</el-radio>
            <el-radio label="disabled">锁定维护停用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitForm">保存配置</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import request from '@/utils/request'

const loading = ref(false)
const tableData = ref([])

const dialogVisible = ref(false)
const dialogType = ref('add')
const submitLoading = ref(false)
const formRef = ref(null)

const form = reactive({
  id: null,
  name: '',
  capacity: 10,
  free_hours: 0,
  price_per_hour: 0,
  has_projector: 0,
  has_video_conf: 0,
  status: 'active'
})

const rules = {
  name: [{ required: true, message: '会议室名称不能为空', trigger: 'blur' }]
}

const fetchList = async () => {
  loading.value = true
  try {
    const res = await request.get('/api/v1/meeting/rooms/list')
    if (res.code === 200) tableData.value = res.data
  } finally {
    loading.value = false
  }
}

const openDialog = (type, row = null) => {
  dialogType.value = type
  if (type === 'edit' && row) {
    Object.assign(form, row)
  }
  dialogVisible.value = true
}

const resetForm = () => {
  if (formRef.value) formRef.value.resetFields()
  Object.assign(form, {
    id: null, name: '', capacity: 10, free_hours: 0, price_per_hour: 0, 
    has_projector: 0, has_video_conf: 0, status: 'active'
  })
}

const submitForm = () => {
  formRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    const url = dialogType.value === 'add' ? '/api/v1/meeting/rooms/add' : '/api/v1/meeting/rooms/update'
    try {
      const res = await request.post(url, form)
      if (res.code === 200) {
        ElMessage.success(res.msg)
        dialogVisible.value = false
        fetchList()
      } else {
        ElMessage.error(res.msg)
      }
    } finally {
      submitLoading.value = false
    }
  })
}

const handleDelete = (row) => {
  ElMessageBox.confirm('确定要删除该会议室资产吗？如果存在未完成的历史订单将无法删除。', '高危操作确认', {
    type: 'warning'
  }).then(async () => {
    const res = await request.post('/api/v1/meeting/rooms/delete', { id: row.id })
    if (res.code === 200) {
      ElMessage.success('删除成功')
      fetchList()
    } else {
      ElMessage.error(res.msg)
    }
  }).catch(() => {})
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.meeting-room-container {
  padding: 20px;
}
</style>