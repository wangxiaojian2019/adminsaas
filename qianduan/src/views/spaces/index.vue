<template>
  <div class="spaces-container">
    <el-card shadow="never" class="filter-card">
      <div class="toolbar">
        <el-form :inline="true" :model="queryForm" size="default">
          <el-form-item label="归属大厦">
            <el-input v-model="queryForm.building_name" placeholder="输入大厦名称搜索" clearable />
          </el-form-item>
          <el-form-item label="资产状态">
            <el-select v-model="queryForm.status" placeholder="全部状态" style="width: 150px" clearable>
              <el-option label="空置待租" value="0" />
              <el-option label="已出租" value="1" />
              <el-option label="维修中" value="2" />
              <el-option label="装修中" value="3" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="handleSearch">检索</el-button>
            <el-button @click="resetQuery">重置</el-button>
          </el-form-item>
        </el-form>
        <el-button type="success" :icon="Plus" @click="openAddDialog">录入新资产</el-button>
      </div>
    </el-card>

    <el-card shadow="never" style="margin-top: 15px;">
      <el-table :data="tableData" border stripe style="width: 100%" v-loading="loading">
        <el-table-column prop="building_name" label="所属大厦" width="150" />
        <el-table-column prop="floor" label="楼层" width="80" align="center">
          <template #default="{ row }">{{ row.floor }} F</template>
        </el-table-column>
        <el-table-column prop="room_number" label="房间门牌号" width="120" align="center">
          <template #default="{ row }"><strong style="font-family: monospace; font-size: 16px;">{{ row.room_number }}</strong></template>
        </el-table-column>
        <el-table-column prop="area" label="建筑面积 (㎡)" width="120" align="center" />
        <el-table-column label="当期租务单价" width="150" align="center">
          <template #default="{ row }">
            <span v-if="row.unit_price > 0" style="color:#F56C6C; font-weight:bold;">￥{{ row.unit_price }} <span style="font-size: 12px; color: #909399;">/㎡/天</span></span>
            <span v-else style="color:#909399">-</span>
          </template>
        </el-table-column>
        <el-table-column label="资产状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusTag(row.status)" effect="dark">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="enterprise_name" label="当前承租企业" min-width="200">
          <template #default="{ row }">
            <span v-if="row.enterprise_name" style="font-weight: bold; color: #409EFF;">{{ row.enterprise_name }}</span>
            <span v-else style="color: #C0C4CC;">无承租方</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" align="center" fixed="right">
          <template #default="{ row }">
            <el-button size="small" type="primary" plain @click="openEditDialog(row)">编辑资产</el-button>
            <el-button size="small" type="danger" plain @click="handleDelete(row)">销户归档</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination
          v-model:current-page="queryForm.page"
          v-model:page-size="queryForm.limit"
          :page-sizes="[15, 30, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </el-card>

    <el-dialog :title="dialogType === 'add' ? '录入新资产' : '编辑资产档案'" v-model="dialogVisible" width="500px" @close="resetForm">
      <el-form ref="formRef" :model="form" label-width="100px" :rules="rules">
        <el-form-item label="所属大厦" prop="building_name">
          <el-input v-model="form.building_name" placeholder="例如：京东大厦" />
        </el-form-item>
        <el-form-item label="楼层" prop="floor">
          <el-input-number v-model="form.floor" :min="1" :max="100" />
        </el-form-item>
        <el-form-item label="门牌号" prop="room_number">
          <el-input v-model="form.room_number" placeholder="例如：1203" />
        </el-form-item>
        <el-form-item label="建筑面积" prop="area">
          <el-input-number v-model="form.area" :min="1" :precision="2" :step="10" />
          <span style="margin-left: 10px;">㎡</span>
        </el-form-item>
        <el-form-item label="资产状态" prop="status" v-if="dialogType === 'edit'">
          <el-select v-model="form.status" style="width: 100%">
            <el-option label="空置待租" :value="0" />
            <el-option label="已出租" :value="1" />
            <el-option label="维修中" :value="2" />
            <el-option label="装修中" :value="3" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitForm">保存入库</el-button>
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

// 核心重构：加入分页驱动数据
const total = ref(0)
const queryForm = ref({ page: 1, limit: 15, building_name: '', status: '' })

const dialogVisible = ref(false)
const dialogType = ref('add')
const submitLoading = ref(false)
const formRef = ref(null)

const form = reactive({ id: null, building_name: '', floor: 1, room_number: '', area: 50.00, status: 0 })
const rules = {
  building_name: [{ required: true, message: '请输入所属大厦', trigger: 'blur' }],
  room_number: [{ required: true, message: '请输入门牌号', trigger: 'blur' }]
}

const getStatusText = (status) => ({ 0: '空置', 1: '在租', 2: '维修', 3: '装修' }[status])
const getStatusTag = (status) => ({ 0: 'info', 1: 'success', 2: 'danger', 3: 'warning' }[status])

const fetchList = async () => {
  loading.value = true
  try {
    const res = await request.get('/api/spaces/list', { params: queryForm.value })
    if (res.code === 200) {
      // 核心重构：适配后端最新的标准分页 JSON 结构
      tableData.value = res.data || []
      total.value = res.meta ? res.meta.total : 0
    }
  } finally {
    loading.value = false
  }
}

// 分页器驱动引擎
const handleSearch = () => { queryForm.value.page = 1; fetchList() }
const resetQuery = () => { queryForm.value = { page: 1, limit: 15, building_name: '', status: '' }; fetchList() }
const handleSizeChange = (val) => { queryForm.value.limit = val; fetchList() }
const handleCurrentChange = (val) => { queryForm.value.page = val; fetchList() }

onMounted(() => fetchList())

const openAddDialog = () => {
  dialogType.value = 'add'
  dialogVisible.value = true
}

const openEditDialog = (row) => {
  dialogType.value = 'edit'
  Object.assign(form, row)
  dialogVisible.value = true
}

const resetForm = () => {
  if (formRef.value) formRef.value.resetFields()
  Object.assign(form, { id: null, building_name: '', floor: 1, room_number: '', area: 50.00, status: 0 })
}

const submitForm = () => {
  formRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    const url = dialogType.value === 'add' ? '/api/spaces/add' : '/api/spaces/update'
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
  ElMessageBox.confirm('确定要将该房源档案销户归档吗？（软删除）', '高危操作确认', { type: 'warning' }).then(async () => {
    const res = await request.post('/api/spaces/delete', { id: row.id })
    if (res.code === 200) {
      ElMessage.success(res.msg)
      // 若当前页删空了，自动回到上一页
      if (tableData.value.length === 1 && queryForm.value.page > 1) {
        queryForm.value.page -= 1
      }
      fetchList()
    } else {
      ElMessage.error(res.msg)
    }
  }).catch(() => {})
}
</script>

<style scoped>
.spaces-container { padding: 20px; }
.toolbar { display: flex; justify-content: space-between; align-items: flex-start; }
.pagination-container { display: flex; justify-content: flex-end; margin-top: 20px; padding-top: 15px; border-top: 1px dashed #ebeef5; }
</style>