<template>
  <div class="inventory-container">
    <el-card shadow="never" class="main-card">
      <div class="toolbar">
        <el-button type="success" icon="Plus" @click="openAddDialog">登记新物品品类</el-button>
        <el-button icon="Refresh" @click="fetchItems">刷新台账</el-button>
      </div>

      <el-alert 
        title="物料控制规则：当前在库量低于或等于安全库存时，系统台账将自动标红激活预警。盘盈与盘亏操作直接关联审计留档，不触发双端外部消息触达。" 
        type="warning" 
        show-icon 
        :closable="false" 
        style="margin-bottom: 20px;" 
      />

      <el-table :data="tableData" v-loading="loading" border stripe style="width: 100%">
        <el-table-column prop="id" label="物资编号" width="90" align="center" />
        <el-table-column prop="name" label="物资名称" min-width="180" />
        <el-table-column label="物资属性" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="row.category === 1 ? 'info' : 'warning'" effect="dark">
              {{ row.category === 1 ? '消耗品' : '固定资产' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="当前在库余量" width="160" align="center">
          <template #default="{ row }">
            <span class="stock-num" :class="Number(row.stock) <= Number(row.safety_stock) ? 'text-danger' : 'text-success'">
              {{ row.stock }}
            </span>
            <span style="color: #909399; margin-left: 5px;">{{ row.unit }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="safety_stock" label="安全库存下限" width="130" align="center">
          <template #default="{ row }">
            <el-tag type="danger" size="small" variant="light">≤ {{ row.safety_stock }} {{ row.unit }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="最后盘点/变动时间" prop="updated_at" width="170" align="center" />
        <el-table-column label="操作" width="280" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link icon="Switch" @click="openActionDialog(row)">调拨/借还登记</el-button>
            <el-button type="info" link icon="List" @click="openRecordsDrawer(row)">查阅流水与周期</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="addDialogVisible" title="登记新物资品类" width="500px" @close="addFormRef?.resetFields()">
      <el-form ref="addFormRef" :model="addForm" :rules="addRules" label-width="110px">
        <el-form-item label="物资名称" prop="name">
          <el-input v-model="addForm.name" placeholder="例如：A4打印纸、大号电钻" />
        </el-form-item>
        <el-form-item label="物资属性" prop="category">
          <el-radio-group v-model="addForm.category">
            <el-radio :label="1">易耗品 (领用即消耗)</el-radio>
            <el-radio :label="2">固定资产 (借出需归还)</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="计量单位" prop="unit">
          <el-select v-model="addForm.unit" placeholder="请选择或输入" filterable allow-create style="width: 100%;">
            <el-option label="个" value="个" />
            <el-option label="把" value="把" />
            <el-option label="箱" value="箱" />
            <el-option label="台" value="台" />
            <el-option label="卷" value="卷" />
            <el-option label="米" value="米" />
          </el-select>
        </el-form-item>
        <el-form-item label="期初库存" prop="initial_stock">
          <el-input-number v-model="addForm.initial_stock" :min="0" :max="99999" controls-position="right" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="安全库存下限" prop="safety_stock">
          <el-input-number v-model="addForm.safety_stock" :min="0" :max="9999" controls-position="right" style="width: 100%;" />
          <div style="font-size: 12px; color: #f56c6c; margin-top: 5px;">当物理库存低于或等于此数值时，系统大盘将自动激活标红报警。</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="addDialogVisible = false">取消</el-button>
        <el-button type="success" :loading="submitLoading" @click="submitAdd">确认建档</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="actionDialogVisible" :title="`物资出入库调度：${currentItem.name}`" width="600px" @close="actionFormRef?.resetFields()">
      <div class="current-stock-panel">
        <span class="text-gray">当前在库余量：</span>
        <span class="text-num">{{ currentItem.stock }}</span>
        <span>{{ currentItem.unit }}</span>
        <span style="margin-left: 15px; color: #f56c6c; font-size: 13px;">(安全下限: {{ currentItem.safety_stock }} {{ currentItem.unit }})</span>
      </div>

      <el-form ref="actionFormRef" :model="actionForm" :rules="actionRules" label-width="120px" style="margin-top: 20px;">
        <el-form-item label="业务动作" prop="action_type">
          <el-radio-group v-model="actionForm.action_type" @change="handleActionTypeChange">
            <el-radio-button :label="1">采购入库</el-radio-button>
            <el-radio-button :label="2" v-if="currentItem.category === 1">领用/消耗</el-radio-button>
            <el-radio-button :label="3" v-if="currentItem.category === 2">外借/出借</el-radio-button>
            <el-radio-button :label="4" v-if="currentItem.category === 2">完好归还</el-radio-button>
            <el-radio-button :label="5">盘盈登记</el-radio-button>
            <el-radio-button :label="6">盘亏调整</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="流转数量" prop="quantity">
          <el-input-number v-model="actionForm.quantity" :min="1" :max="isDeductAction ? currentItem.stock : 9999" controls-position="right" style="width: 100%;" />
          <div v-if="isDeductAction" style="font-size: 12px; color: #f56c6c; margin-top: 5px;">* 出库或盘亏数量不能超过当前总库存</div>
        </el-form-item>

        <el-form-item :label="personLabel" prop="related_person">
          <el-select v-if="[1, 5, 6].includes(actionForm.action_type)" v-model="actionForm.related_person" filterable placeholder="请选择负责核验的系统管理员" style="width: 100%;">
            <el-option 
              v-for="admin in adminList" 
              :key="admin.id" 
              :label="admin.real_name || admin.username || admin.name" 
              :value="(actionForm.action_type === 1 ? '[入库核验员] ' : '[盘点核验员] ') + (admin.real_name || admin.username || admin.name)" 
            />
          </el-select>

          <div v-else style="display: flex; gap: 10px; width: 100%;">
            <el-select v-model="actionForm.related_type" style="width: 130px;" @change="actionForm.related_person = ''">
              <el-option label="内部员工" :value="1" />
              <el-option label="入驻企业" :value="2" />
            </el-select>
            
            <el-select v-if="actionForm.related_type === 1" v-model="actionForm.related_person" filterable placeholder="检索基层外勤员工" style="flex: 1;">
              <el-option v-for="staff in staffList" :key="staff.id" :label="staff.real_name || staff.username || staff.name" :value="'[领用师傅] ' + (staff.real_name || staff.username || staff.name)" />
            </el-select>
            
            <el-select v-if="actionForm.related_type === 2" v-model="actionForm.related_person" filterable placeholder="检索登记的租户" style="flex: 1;">
              <el-option v-for="ent in enterpriseList" :key="ent.id" :label="ent.name" :value="'[企业主体] ' + ent.name" />
            </el-select>
          </div>
        </el-form-item>

        <el-form-item label="预计归还日期" prop="expected_return_date" v-if="actionForm.action_type === 3">
          <el-date-picker v-model="actionForm.expected_return_date" type="date" value-format="YYYY-MM-DD" placeholder="请选择计划归还给仓库的时间" style="width: 100%;" />
        </el-form-item>

        <el-form-item label="补充备注" prop="remark">
          <el-input v-model="actionForm.remark" type="textarea" :rows="3" placeholder="请详细写明资产盘点误差原因或调拨用途，以供后续财务和后勤精确审计。" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="actionDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitAction">确认并生成留档台账</el-button>
      </template>
    </el-dialog>

    <el-drawer v-model="recordsDrawerVisible" :title="`【${currentItem.name}】流水溯源台账`" size="800px">
      <div v-loading="recordsLoading" style="padding: 0 15px;">
        <el-table :data="recordsList" border stripe size="small">
          <el-table-column label="留档时间与动作" width="160" align="center">
            <template #default="{ row }">
              <div style="margin-bottom: 4px;">
                <el-tag :type="getActionTag(row.action_type)" size="small" effect="dark">
                  {{ getActionName(row.action_type) }}
                </el-tag>
              </div>
              <div style="font-size: 11px; color: #909399; font-family: monospace;">{{ row.created_at }}</div>
            </template>
          </el-table-column>
          
          <el-table-column label="数量" width="80" align="center">
            <template #default="{ row }">
              <span :class="[1, 4, 5].includes(row.action_type) ? 'text-success' : 'text-danger'" style="font-weight: bold; font-size: 14px;">
                {{ [1, 4, 5].includes(row.action_type) ? '+' : '-' }}{{ row.quantity }}
              </span>
            </template>
          </el-table-column>
          
          <el-table-column label="结构化经办实体" prop="related_person" width="180" show-overflow-tooltip>
            <template #default="{ row }">
              <span style="font-weight: bold; color: #409eff;">{{ row.related_person }}</span>
            </template>
          </el-table-column>
          
          <el-table-column label="外借周期要求" width="140" align="center">
            <template #default="{ row }">
              <span v-if="row.action_type === 3 && row.expected_return_date" style="color: #e6a23c; font-weight: bold;">
                应于 {{ row.expected_return_date }} 还
              </span>
              <span v-else style="color: #c0c4cc;">-</span>
            </template>
          </el-table-column>
          
          <el-table-column label="详细备注说明" prop="remark" min-width="150" show-overflow-tooltip />
        </el-table>
        <el-empty v-if="!recordsLoading && recordsList.length === 0" description="暂无历史归档记录" />
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Refresh, Switch, List } from '@element-plus/icons-vue'
import request from '../../utils/request'

const tableData = ref([])
const loading = ref(false)

const adminList = ref([])      
const staffList = ref([])      
const enterpriseList = ref([]) 

const addDialogVisible = ref(false)
const addFormRef = ref(null)
const submitLoading = ref(false)
const addForm = reactive({ name: '', category: 1, unit: '个', initial_stock: 0, safety_stock: 5 })
const addRules = {
  name: [{ required: true, message: '请填写物资名称', trigger: 'blur' }],
  category: [{ required: true, message: '请选择属性', trigger: 'change' }],
  unit: [{ required: true, message: '请设定单位', trigger: 'change' }],
  initial_stock: [{ required: true, message: '请填写期初库存', trigger: 'blur' }],
  safety_stock: [{ required: true, message: '请设定安全库存下限值', trigger: 'blur' }]
}

const actionDialogVisible = ref(false)
const actionFormRef = ref(null)
const currentItem = ref({})
const actionForm = reactive({ 
  item_id: null, 
  action_type: 1, 
  quantity: 1, 
  related_type: 1, 
  related_person: '', 
  expected_return_date: '',
  remark: '' 
})

const isDeductAction = computed(() => [2, 3, 6].includes(actionForm.action_type))

const personLabel = computed(() => {
  if (actionForm.action_type === 1) return '入库核验员'
  if (actionForm.action_type === 2) return '领用实体'
  if (actionForm.action_type === 3) return '外借实体'
  if (actionForm.action_type === 4) return '交还实体'
  if ([5, 6].includes(actionForm.action_type)) return '盘点核验员'
  return '关联方'
})

const actionRules = computed(() => ({
  action_type: [{ required: true, message: '请选择业务动作', trigger: 'change' }],
  quantity: [{ required: true, message: '数量必须大于0', trigger: 'blur' }],
  related_person: [{ required: true, message: `请明确选中${personLabel.value}以供系统追溯`, trigger: 'change' }],
  expected_return_date: actionForm.action_type === 3 
    ? [{ required: true, message: '出借固定资产必须约定归还日期', trigger: 'change' }] 
    : []
}))

const recordsDrawerVisible = ref(false)
const recordsLoading = ref(false)
const recordsList = ref([])

const fetchInitData = async () => {
  loading.value = true
  try {
    const [itemRes, adminRes, staffRes, entRes] = await Promise.all([
      request.get('/api/inventory/list'),
      request.get('/api/system/admins/list'),
      request.get('/api/services/staff/list'),
      request.get('/api/enterprises/list')
    ])
    if (itemRes.code === 200) tableData.value = itemRes.data
    if (adminRes.code === 200) adminList.value = adminRes.data || []
    if (staffRes.code === 200) staffList.value = staffRes.data || []
    if (entRes.code === 200) enterpriseList.value = entRes.data || []
  } finally {
    loading.value = false
  }
}

const fetchItems = async () => {
  loading.value = true
  try {
    const res = await request.get('/api/inventory/list')
    if (res.code === 200) tableData.value = res.data
  } finally {
    loading.value = false
  }
}

const openAddDialog = () => {
  addForm.name = ''
  addForm.category = 1
  addForm.unit = '个'
  addForm.initial_stock = 0
  addForm.safety_stock = 5
  addDialogVisible.value = true
}

const submitAdd = () => {
  addFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    try {
      const res = await request.post('/api/inventory/add', addForm)
      if (res.code === 200) {
        ElMessage.success(res.msg)
        addDialogVisible.value = false
        fetchItems()
      } else {
        ElMessage.error(res.msg)
      }
    } finally {
      submitLoading.value = false
    }
  })
}

const openActionDialog = (row) => {
  currentItem.value = row
  actionForm.item_id = row.id
  actionForm.action_type = 1
  actionForm.quantity = 1
  actionForm.related_type = 1
  actionForm.related_person = ''
  actionForm.expected_return_date = ''
  actionForm.remark = ''
  actionDialogVisible.value = true
}

const handleActionTypeChange = (newType) => {
  actionForm.related_person = ''
  if ([1, 5, 6].includes(newType)) {
    actionForm.related_type = null 
  } else {
    actionForm.related_type = 1
  }
}

const submitAction = () => {
  actionFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    try {
      const res = await request.post('/api/inventory/action', actionForm)
      if (res.code === 200) {
        ElMessage.success(res.msg)
        actionDialogVisible.value = false
        fetchItems()
      } else {
        ElMessage.error(res.msg)
      }
    } finally {
      submitLoading.value = false
    }
  })
}

const openRecordsDrawer = async (row) => {
  currentItem.value = row
  recordsDrawerVisible.value = true
  recordsLoading.value = true
  try {
    const res = await request.get('/api/inventory/records', { params: { item_id: row.id } })
    if (res.code === 200) {
      recordsList.value = res.data
    }
  } finally {
    recordsLoading.value = false
  }
}

const getActionName = (type) => {
  const map = { 1: '采购入库', 2: '领用消耗', 3: '外借出借', 4: '归还入库', 5: '盘盈入账', 6: '盘亏调整' }
  return map[type] || '期初建账'
}

const getActionTag = (type) => {
  const map = { 1: 'success', 2: 'danger', 3: 'warning', 4: 'success', 5: 'success', 6: 'danger' }
  return map[type] || 'info'
}

onMounted(() => {
  fetchInitData()
})
</script>

<style scoped>
.inventory-container { width: 100%; }
.main-card { border-radius: 4px; box-shadow: none; }
.toolbar { margin-bottom: 20px; display: flex; gap: 10px; }
.stock-num { font-size: 20px; font-weight: bold; font-family: monospace; }
.text-danger { color: #f56c6c; font-weight: bold; }
.text-success { color: #67c23a; }

.current-stock-panel { background-color: #fdf6ec; border: 1px solid #faecd8; border-radius: 4px; padding: 15px; text-align: center; }
.text-gray { color: #909399; font-size: 14px; }
.text-num { font-size: 28px; font-weight: bold; color: #e6a23c; margin: 0 8px; font-family: monospace; }
</style>