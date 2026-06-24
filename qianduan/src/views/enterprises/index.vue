<template>
  <div class="enterprise-container">
    <el-card shadow="never" class="toolbar-card">
      <div class="header-tools">
        <span class="title">园区企业户籍档案库</span>
        <el-button type="primary" :icon="Plus" @click="dialogVisible = true">办理企业入驻建档</el-button>
      </div>
    </el-card>

    <el-card shadow="never" style="margin-top: 15px;">
      <el-table :data="tableData" border stripe style="width: 100%" v-loading="loading">
        <el-table-column prop="id" label="户籍编号" width="100" align="center" />
        <el-table-column prop="name" label="企业主体名称" min-width="220">
          <template #default="{ row }">
            <span style="font-weight: bold; color: #303133; font-size: 15px;">{{ row.name }}</span>
            <el-tag size="small" type="info" style="margin-left: 10px">{{ row.industry || '未登记行业' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="关键决策人" width="200">
          <template #default="{ row }">
            <div style="font-weight: bold;">{{ row.contact_person }}</div>
            <div style="color: #606266; font-size: 13px;"><el-icon><Phone /></el-icon> {{ row.phone }}</div>
          </template>
        </el-table-column>
        <el-table-column label="履约财务数据 (元)" width="280">
          <template #default="{ row }">
            <div class="finance-block">月总租金: <span class="text-danger font-bold">￥{{ row.monthly_rent || '0.00' }}</span></div>
            <div class="finance-block">系统沉淀押金: <span class="text-success font-bold">￥{{ row.deposit || '0.00' }}</span></div>
          </template>
        </el-table-column>
        <el-table-column label="最远到期日" width="140" align="center">
          <template #default="{ row }">
            <span v-if="row.end_date" style="color: #E6A23C; font-weight: bold;">{{ row.end_date }}</span>
            <span v-else style="color: #909399">暂无履约合同</span>
          </template>
        </el-table-column>
        <el-table-column label="门户网关操作" width="180" align="center" fixed="right">
          <template #default="{ row }">
            <el-button size="small" type="warning" plain @click="handleResetPwd(row)">重置H5密码</el-button>
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

    <el-dialog title="办理企业入驻与财务建档" v-model="dialogVisible" width="600px" @close="resetForm">
      <el-form ref="formRef" :model="form" label-width="120px" :rules="rules">
        <el-divider content-position="left">企业户籍基础信息</el-divider>
        <el-form-item label="企业全称" prop="name">
          <el-input v-model="form.name" placeholder="营业执照上的全称" />
        </el-form-item>
        <div style="display:flex; gap:10px">
          <el-form-item label="决策人姓名" prop="contact_person" style="flex:1">
            <el-input v-model="form.contact_person" placeholder="法人或负责人" />
          </el-form-item>
          <el-form-item label="所属行业" style="flex:1">
            <el-input v-model="form.industry" placeholder="例如：人工智能" />
          </el-form-item>
        </div>
        <el-form-item label="授权登录手机号" prop="phone">
          <el-input v-model="form.phone" placeholder="该手机号将作为租户 H5 门户的唯一登录账号" />
        </el-form-item>
        
        <el-divider content-position="left">同步锁定房源 (可选)</el-divider>
        <el-form-item label="首发承租空间">
          <el-select v-model="form.space_id" placeholder="选填，如不选则仅创建纯户籍档案" filterable style="width: 100%">
            <el-option v-for="space in emptySpaces" :key="space.id" :label="`${space.building_name} - ${space.room_number}`" :value="space.id" />
          </el-select>
        </el-form-item>
        <template v-if="form.space_id">
          <el-form-item label="首签履约期限">
            <el-date-picker v-model="form.dateRange" type="daterange" range-separator="至" start-placeholder="起租日" end-placeholder="到期日" value-format="YYYY-MM-DD" style="width: 100%" />
          </el-form-item>
          <div style="display:flex; gap:10px">
            <el-form-item label="月总租金(元)" style="flex:1">
              <el-input-number v-model="form.monthly_rent" :min="0" :step="1000" style="width: 100%" />
            </el-form-item>
            <el-form-item label="月物业费(元)" style="flex:1">
              <el-input-number v-model="form.property_fee" :min="0" :step="100" style="width: 100%" />
            </el-form-item>
          </div>
          <el-form-item label="一次性收取押金">
            <el-input-number v-model="form.deposit" :min="0" :step="5000" style="width: 100%" />
          </el-form-item>
        </template>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitForm">确认建档落盘</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Phone } from '@element-plus/icons-vue'
import request from '@/utils/request'

const loading = ref(false)
const tableData = ref([])
const emptySpaces = ref([])

// 分页引擎状态
const total = ref(0)
const queryForm = ref({ page: 1, limit: 15 })

const dialogVisible = ref(false)
const submitLoading = ref(false)
const formRef = ref(null)

const form = reactive({
  name: '', contact_person: '', phone: '', industry: '', 
  space_id: null, dateRange: [], monthly_rent: 0, property_fee: 0, deposit: 0
})

const rules = {
  name: [{ required: true, message: '企业全称必须与营业执照一致', trigger: 'blur' }],
  contact_person: [{ required: true, message: '必须登记一位负责人', trigger: 'blur' }],
  phone: [
    { required: true, message: '登录授权手机号必填', trigger: 'blur' },
    { pattern: /^1[3-9]\d{9}$/, message: '请输入合法的11位手机号', trigger: 'blur' }
  ]
}

const fetchList = async () => {
  loading.value = true
  try {
    // 拉取带分页的企业户籍列表
    const res = await request.get('/api/enterprises/list', { params: queryForm.value })
    if (res.code === 200) {
      tableData.value = res.data || []
      total.value = res.meta ? res.meta.total : 0
    }
  } finally {
    loading.value = false
  }
}

const fetchEmptySpaces = async () => {
  const res = await request.get('/api/spaces/list', { params: { limit: 1000 } })
  if (res.code === 200) {
    // 如果后台接口未做单独的状态过滤，前端过滤出 status == 0 的空置房源供选择
    const allSpaces = res.data.data || res.data || [] 
    emptySpaces.value = allSpaces.filter(item => item.status == 0)
  }
}

// 分页控制方法
const handleSizeChange = (val) => { queryForm.value.limit = val; fetchList() }
const handleCurrentChange = (val) => { queryForm.value.page = val; fetchList() }

onMounted(() => {
  fetchList()
  fetchEmptySpaces()
})

const resetForm = () => {
  if (formRef.value) formRef.value.resetFields()
  Object.assign(form, { space_id: null, dateRange: [], monthly_rent: 0, property_fee: 0, deposit: 0 })
}

const submitForm = () => {
  formRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    try {
      const res = await request.post('/api/enterprises/add', form)
      if (res.code === 200) {
        ElMessage.success(res.msg)
        dialogVisible.value = false
        fetchList()
        fetchEmptySpaces()
      } else {
        ElMessage.error(res.msg)
      }
    } finally {
      submitLoading.value = false
    }
  })
}

const handleResetPwd = (row) => {
  ElMessageBox.confirm(`系统将重置企业【${row.name}】的租户H5登录密码为默认密码：123456，是否继续？`, '重置安全密码', {
    type: 'warning', confirmButtonText: '强制重置', cancelButtonText: '取消'
  }).then(async () => {
    const res = await request.post('/api/enterprises/reset_pwd', { id: row.id })
    if (res.code === 200) {
      ElMessage.success(res.msg)
    } else {
      ElMessage.error(res.msg)
    }
  }).catch(() => {})
}
</script>

<style scoped>
.enterprise-container { padding: 20px; }
.toolbar-card .header-tools { display: flex; justify-content: space-between; align-items: center; }
.toolbar-card .title { font-size: 16px; font-weight: bold; border-left: 4px solid #409EFF; padding-left: 10px; }
.finance-block { font-size: 13px; margin: 4px 0; color: #606266; }
.text-danger { color: #F56C6C; }
.text-success { color: #67C23A; }
.font-bold { font-weight: bold; font-family: monospace; font-size: 15px; }
.pagination-container { display: flex; justify-content: flex-end; margin-top: 20px; padding-top: 15px; border-top: 1px dashed #ebeef5; }
</style>