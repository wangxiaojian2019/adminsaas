<template>
  <div class="buildings-container">
    <el-card shadow="never" class="main-card">
      <div class="toolbar">
        <div class="toolbar-left">
          <el-button type="primary" icon="Plus" @click="dialogVisible = true">设立新大厦/项目</el-button>
          <el-button type="success" icon="House" @click="openRoomDialog">录入物理房间</el-button>
          <el-button type="warning" icon="Download" @click="exportData">带水印导出</el-button>
          <el-button icon="Refresh" @click="fetchData">刷新数据</el-button>
        </div>
        <div class="toolbar-right">
          <el-radio-group v-model="viewMode" size="default">
            <el-radio-button label="list"><el-icon><List /></el-icon> 列表视图</el-radio-button>
            <el-radio-button label="heatmap"><el-icon><DataBoard /></el-icon> 资产热力图</el-radio-button>
          </el-radio-group>
        </div>
      </div>

      <div v-if="viewMode === 'list'" v-loading="loading">
        <el-table :data="tableData" border stripe style="width: 100%">
          <el-table-column prop="id" label="项目ID" width="80" align="center" />
          <el-table-column prop="name" label="大厦/项目名称" min-width="150" />
          <el-table-column label="建筑业态" width="130" align="center">
            <template #default="{ row }"><el-tag :type="getTypeColor(row.property_type)">{{ getTypeLabel(row.property_type) }}</el-tag></template>
          </el-table-column>
          <el-table-column prop="total_floors" label="物理总楼层" width="100" align="center">
            <template #default="{ row }">{{ row.total_floors }} 层</template>
          </el-table-column>
          <el-table-column prop="floor_details" label="楼层聚合概览(间)" min-width="200" show-overflow-tooltip />
          <el-table-column prop="building_area" label="总建筑面积(㎡)" width="130" align="right" />
          <el-table-column prop="manager_name" label="项目负责人" width="100" align="center" />
          <el-table-column label="设立时间" width="160" align="center">
            <template #default="{ row }">{{ new Date(row.created_at).toLocaleString() }}</template>
          </el-table-column>
        </el-table>
      </div>

      <div v-else v-loading="loading" class="heatmap-view-wrapper">
        <div class="filter-panel">
          <div class="filter-item">
            <span class="filter-label">状态:</span>
            <el-select v-model="filterStatus" clearable placeholder="全部" style="width: 100px">
              <el-option label="全部" value="" /><el-option label="空置可租" :value="0" /><el-option label="在租运营" :value="1" /><el-option label="设备维修" :value="2" /><el-option label="装修施工" :value="3" />
            </el-select>
          </div>
          <div class="filter-item">
            <span class="filter-label">企业检索:</span>
            <el-input v-model="searchTenant" placeholder="输入企业名称" clearable prefix-icon="Search" style="width: 160px" />
          </div>
          <div class="status-legend" style="margin-left: auto;">
            <span class="legend-item"><span class="status-dot status-0"></span>空置可租</span>
            <span class="legend-item"><span class="status-dot status-1"></span>在租运营</span>
            <span class="legend-item"><span class="status-dot status-2"></span>设备维修</span>
            <span class="legend-item"><span class="status-dot status-3"></span>装修施工</span>
          </div>
        </div>

        <el-empty v-if="Object.keys(processedHeatMapData).length === 0" description="暂无物理空间数据" />
        <el-collapse v-else v-model="activeBuildings" class="building-collapse">
          <el-collapse-item v-for="(floors, bName) in processedHeatMapData" :key="bName" :name="bName" class="building-section">
            <template #title>
              <div class="building-title-wrapper">
                <el-icon class="building-icon"><OfficeBuilding /></el-icon> 
                <span class="b-name">{{ bName }}</span>
              </div>
            </template>
            <div class="building-matrix">
              <div v-for="(rooms, floorNum) in floors" :key="floorNum" class="floor-row">
                <div class="floor-badge">{{ floorNum }}F</div>
                <div class="house-tiles-container">
                  <el-tooltip v-for="room in rooms" :key="room.id" placement="top" effect="light" :show-after="200">
                    <template #content>
                      <div class="tooltip-box">
                        <div class="tt-header">{{ room.building_name }} - {{ room.room_number }}</div>
                        <div class="tt-body">
                          <div><strong>建筑面积：</strong> {{ room.area }} ㎡</div>
                          <div><strong>实际状态：</strong> <el-tag size="small" :type="getStatusType(room.status)">{{ getSpaceStatusLabel(room.status) }}</el-tag></div>
                          <template v-if="room.status === 1 && room.enterprise_name">
                            <el-divider border-style="dashed" style="margin: 8px 0" />
                            <div>
                              <strong>承租企业：</strong> 
                              <span class="click-trigger-tooltip" @click.stop="jumpToDetail(room)">
                                {{ room.enterprise_name }}
                              </span>
                            </div>
                          </template>
                        </div>
                      </div>
                    </template>
                    <div :class="['house-card', `theme-status-${room.display_status}`, { 'not-matched': !room.is_matched }]" :style="{ width: getRoomWidth(room.area) }" @click="openEditRoomDialog(room)">
                      <div class="house-info">
                        <div class="room-num">{{ room.room_number }}</div>
                        <div v-if="room.display_status === 1 && room.enterprise_name" class="room-tenant click-trigger" @click.stop="jumpToDetail(room)">
                          {{ truncateName(room.enterprise_name) }}
                        </div>
                        <div v-else class="room-area">{{ room.area }}㎡</div>
                      </div>
                    </div>
                  </el-tooltip>
                </div>
              </div>
            </div>
          </el-collapse-item>
        </el-collapse>
      </div>
    </el-card>

    <el-dialog v-model="dialogVisible" title="设立新大厦/项目" width="500px" @close="formRef?.resetFields()">
      <el-form ref="formRef" :model="formData" :rules="rules" label-width="110px">
        <el-form-item label="项目名称" prop="name"><el-input v-model="formData.name" /></el-form-item>
        <el-form-item label="建筑业态" prop="property_type"><el-select v-model="formData.property_type" style="width: 100%;"><el-option :value="1" label="商业办公" /><el-option :value="2" label="长租公寓" /></el-select></el-form-item>
        <el-form-item label="总物理楼层" prop="total_floors"><el-input-number v-model="formData.total_floors" :min="1" style="width: 100%;" /></el-form-item>
        <el-form-item label="总建筑面积" prop="building_area"><el-input v-model="formData.building_area"><template #append>㎡</template></el-input></el-form-item>
        <el-form-item label="项目负责人" prop="manager_name"><el-input v-model="formData.manager_name" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取 消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitAdd">保 存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="roomDialogVisible" title="录入物理房间 (资产单元)" width="500px" @close="roomFormRef?.resetFields()">
      <el-form ref="roomFormRef" :model="roomForm" :rules="roomRules" label-width="110px">
        <el-form-item label="所属大厦" prop="building_name"><el-select v-model="roomForm.building_name" style="width: 100%;"><el-option v-for="b in tableData" :key="b.id" :label="b.name" :value="b.name" /></el-select></el-form-item>
        <el-form-item label="所在楼层" prop="floor"><el-input-number v-model="roomForm.floor" :min="1" style="width: 100%;" /></el-form-item>
        <el-form-item label="房间编号" prop="room_number"><el-input v-model="roomForm.room_number" /></el-form-item>
        <el-form-item label="建筑面积" prop="area"><el-input v-model="roomForm.area"><template #append>㎡</template></el-input></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="roomDialogVisible = false">取 消</el-button>
        <el-button type="success" :loading="submitLoading" @click="submitRoom">确 认 录 入</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="editRoomDialogVisible" title="资产维护 - 空间参数" width="500px" @close="editRoomFormRef?.resetFields()">
      <el-form ref="editRoomFormRef" :model="editRoomForm" :rules="roomRules" label-width="110px">
        <el-form-item label="所属大厦" prop="building_name"><el-select v-model="editRoomForm.building_name" style="width: 100%;" disabled><el-option v-for="b in tableData" :key="b.id" :label="b.name" :value="b.name" /></el-select></el-form-item>
        <el-form-item label="所在楼层" prop="floor"><el-input-number v-model="editRoomForm.floor" :min="1" style="width: 100%;" disabled /></el-form-item>
        <el-form-item label="房间编号" prop="room_number"><el-input v-model="editRoomForm.room_number" /></el-form-item>
        <el-form-item label="建筑面积" prop="area"><el-input v-model="editRoomForm.area"><template #append>㎡</template></el-input></el-form-item>
        
        <el-form-item label="物理工况状态" prop="status">
          <el-select v-model="editRoomForm.status" style="width: 100%;" :disabled="editRoomForm.status === 1">
            <el-option label="空置可租" :value="0" />
            <el-option label="设备维修" :value="2" />
            <el-option label="装修施工" :value="3" />
            <el-option v-if="editRoomForm.status === 1" label="在租运营 (受合同保护)" :value="1" />
          </el-select>
          <div v-if="editRoomForm.status === 1" style="font-size: 12px; color: #e6a23c; line-height: 1.4; margin-top: 5px;">
            * 提示：该房源受履约合同强锁死。如需转为空置，请前往【租务合同中心】办理退租结算手续。
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <div class="edit-dialog-footer">
          <el-popconfirm title="确认永久删除此空间节点？" @confirm="submitDeleteRoom" confirm-button-text="强制删除" confirm-button-type="danger">
            <template #reference><el-button type="danger" plain :loading="submitLoading" :disabled="editRoomForm.status === 1">拆除空间</el-button></template>
          </el-popconfirm>
          <div>
            <el-button v-if="editRoomForm.status === 0" type="success" @click="goToQuickLease">招商直入(新建企业)</el-button>
            <el-button v-if="editRoomForm.status === 1" type="warning" @click="jumpToDetailFromEdit">查看企业合同档案</el-button>
            <el-button v-if="editRoomForm.status !== 1" type="primary" :loading="submitLoading" @click="submitEditRoom">更 新 参 数</el-button>
          </div>
        </div>
      </template>
    </el-dialog>

    <el-dialog v-model="isDetailMode" fullscreen :show-close="false" destroy-on-close class="fullscreen-detail">
      <template #header>
        <div class="detail-header">
          <el-button @click="isDetailMode = false" type="info" plain>← 返回资产热力图</el-button>
          <span class="detail-title">《{{ currentDetailRoom.enterprise_name }}》 业财与户籍综合档案</span>
        </div>
      </template>
      
      <div class="detail-body">
        <div class="info-section">
          <div class="section-title">🏢 企业户籍联络网</div>
          <div class="info-grid">
            <div class="info-item"><span class="label">法人/联络人：</span><span class="value">{{ currentDetailRoom.contact_person || '未录入' }}</span></div>
            <div class="info-item"><span class="label">联系电话：</span><span class="value text-primary">{{ currentDetailRoom.phone || '未录入' }}</span></div>
            <div class="info-item"><span class="label">所属产业：</span><span class="value">{{ currentDetailRoom.industry || '未分配' }}</span></div>
            <div class="info-item"><span class="label">承租空间：</span><span class="value font-bold">{{ currentDetailRoom.building_name }} - {{ currentDetailRoom.room_number }}</span></div>
          </div>
        </div>

        <div class="info-section mt-20">
          <div class="section-title">📑 现行契约与资费核算矩阵</div>
          <div class="info-grid">
            <div class="info-item"><span class="label">合同公文号：</span><span class="value text-code">{{ currentDetailRoom.contract_no || '暂无数据' }}</span></div>
            <div class="info-item"><span class="label">履约周期：</span><span class="value">{{ currentDetailRoom.start_date }} 至 {{ currentDetailRoom.end_date }}</span></div>
            <div class="info-item"><span class="label">月度场地租金：</span><span class="value text-danger font-bold">￥{{ currentDetailRoom.monthly_rent || '0.00' }}</span></div>
            <div class="info-item"><span class="label">月度后勤物业：</span><span class="value">￥{{ currentDetailRoom.property_fee || '0.00' }}</span></div>
            <div class="info-item full-width mt-10">
              <div class="price-highlight">
                <span class="label">核心算法：日均摊核算单价</span>
                <span class="value text-warning">￥{{ currentDetailRoom.unit_price || '0.00' }}</span> 
                <span class="unit">(元/㎡/天)</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import request from '../../utils/request'

const router = useRouter()
const viewMode = ref('heatmap')
const tableData = ref([])
const rawHeatMapData = ref({})
const activeBuildings = ref([])
const loading = ref(false)
const filterStatus = ref(null)
const searchTenant = ref('')

const dialogVisible = ref(false)
const submitLoading = ref(false)
const formRef = ref(null)
const formData = reactive({ name: '', property_type: 1, total_floors: 1, building_area: '', manager_name: '' })
const rules = { name: [{ required: true, message: '请输入名称', trigger: 'blur' }] }

const roomDialogVisible = ref(false)
const roomFormRef = ref(null)
const roomForm = reactive({ building_name: '', floor: 1, room_number: '', area: '' })
const roomRules = { area: [{ required: true, trigger: 'blur' }] }

const editRoomDialogVisible = ref(false)
const editRoomFormRef = ref(null)
const editRoomForm = reactive({ id: '', building_name: '', floor: 1, room_number: '', area: '', status: 0 })

// 穿透视图状态机
const isDetailMode = ref(false)
const currentDetailRoom = ref({})
const currentSelectedRoom = ref(null)

const processedHeatMapData = computed(() => {
  const result = {}
  for (const bName in rawHeatMapData.value) {
    result[bName] = {}
    for (const fNum in rawHeatMapData.value[bName]) {
      const rooms = rawHeatMapData.value[bName][fNum].map(r => {
        let isMatched = true
        if (typeof filterStatus.value === 'number' && r.display_status !== filterStatus.value) isMatched = false
        if (searchTenant.value && r.enterprise_name && !r.enterprise_name.includes(searchTenant.value)) isMatched = false
        return { ...r, is_matched: isMatched }
      })
      result[bName][fNum] = rooms
    }
  }
  return result
})

const getRoomWidth = (area) => `${Math.max(80, Math.min(240, 60 + Math.sqrt(area || 0) * 3))}px`

const fetchData = async () => {
  loading.value = true
  try {
    const [buildingsRes, spacesRes] = await Promise.all([request.get('/api/buildings/list'), request.get('/api/spaces/list')])
    if (buildingsRes.code === 200) tableData.value = buildingsRes.data
    if (spacesRes.code === 200) {
      const tree = {}
      buildingsRes.data.forEach(b => { tree[b.name] = {} })
      spacesRes.data.forEach(space => {
        if (!tree[space.building_name]) tree[space.building_name] = {}
        if (!tree[space.building_name][space.floor]) tree[space.building_name][space.floor] = []
        space.display_status = space.status
        tree[space.building_name][space.floor].push(space)
      })
      for (const b in tree) {
        const sorted = {}
        Object.keys(tree[b]).sort((a, b) => b - a).forEach(f => { sorted[f] = tree[b][f].sort((r1, r2) => r1.room_number.localeCompare(r2.room_number)) })
        tree[b] = sorted
      }
      rawHeatMapData.value = tree
      activeBuildings.value = Object.keys(tree)
    }
  } finally { loading.value = false }
}

const openRoomDialog = () => {
  if (roomFormRef.value) roomFormRef.value.resetFields()
  roomForm.building_name = tableData.value.length > 0 ? tableData.value[0].name : ''
  roomForm.floor = 1
  roomForm.room_number = ''
  roomForm.area = ''
  roomDialogVisible.value = true
}

const openEditRoomDialog = (room) => {
  if (!room.is_matched) return
  currentSelectedRoom.value = room
  editRoomForm.id = room.id
  editRoomForm.building_name = room.building_name
  editRoomForm.floor = room.floor
  editRoomForm.room_number = room.room_number
  editRoomForm.area = room.area
  editRoomForm.status = room.display_status
  editRoomDialogVisible.value = true
}

// 执行跳转逻辑引擎
const jumpToDetail = (room) => {
  currentDetailRoom.value = room
  isDetailMode.value = true
}

const jumpToDetailFromEdit = () => {
  editRoomDialogVisible.value = false
  jumpToDetail(currentSelectedRoom.value)
}

const goToQuickLease = () => {
  editRoomDialogVisible.value = false
  router.push({
    path: '/enterprises',
    query: { space_id: editRoomForm.id, b_name: editRoomForm.building_name, r_num: editRoomForm.room_number }
  })
}

const submitAdd = () => {
  formRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    const res = await request.post('/api/buildings/add', formData)
    if (res.code === 200) { ElMessage.success('设立成功'); dialogVisible.value = false; fetchData() }
    submitLoading.value = false
  })
}

const submitRoom = () => {
  roomFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    const res = await request.post('/api/spaces/add', roomForm)
    if (res.code === 200) { ElMessage.success('物理房间录入成功'); roomDialogVisible.value = false; fetchData() }
    submitLoading.value = false
  })
}

const submitEditRoom = () => {
  editRoomFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    const res = await request.post('/api/spaces/update', editRoomForm)
    if (res.code === 200) { ElMessage.success('空间参数更新成功'); editRoomDialogVisible.value = false; fetchData() }
    submitLoading.value = false
  })
}

const submitDeleteRoom = async () => {
  submitLoading.value = true
  const res = await request.post('/api/spaces/delete', { id: editRoomForm.id })
  if (res.code === 200) { ElMessage.success('空间节点已拆除'); editRoomDialogVisible.value = false; fetchData() }
  submitLoading.value = false
}

const exportData = async () => {
  ElMessage.info('正在拉取空间资产离线档案...')
  const token = localStorage.getItem('saas_token')
  try {
    const res = await fetch('http://47.120.52.65:8787/api/export/download?module=buildings', { headers: { 'Authorization': `Bearer ${token}` } })
    if (res.status === 200) {
      const blob = await res.blob()
      const a = document.createElement('a')
      a.href = window.URL.createObjectURL(blob)
      a.download = `大厦项目资产台账_${new Date().getTime()}.csv`
      a.click()
      ElMessage.success('安全审计账册下载成功')
    }
  } catch (e) { ElMessage.error('导出失败') }
}

const getTypeLabel = (type) => ({ 1: '商业办公', 2: '长租公寓' }[type] || '未知')
const getTypeColor = (type) => ({ 1: 'primary', 2: 'success' }[type] || 'info')
const getSpaceStatusLabel = (status) => ({ 0: '空置', 1: '在租', 2: '维修', 3: '装修' }[status] || '未知')
const getStatusType = (status) => ({ 0: 'success', 1: 'danger', 2: 'warning', 3: 'info' }[status] || 'info')
const truncateName = (name) => name ? (name.length > 5 ? name.substring(0, 5) + '..' : name) : ''

onMounted(() => { fetchData() })
</script>

<style scoped>
.buildings-container { width: 100%; min-height: 500px;}
.main-card { border-radius: 4px; box-shadow: none; }
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.toolbar-left { display: flex; gap: 10px; }

.filter-panel { display: flex; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; padding: 12px 15px; background-color: #f8f9fa; border-radius: 6px; border: 1px solid #eef1f6; }
.filter-item { display: flex; align-items: center; gap: 8px; }
.filter-label { font-size: 13px; color: #606266; font-weight: bold; }
.status-legend { display: flex; gap: 15px; font-size: 13px; }
.legend-item { display: flex; align-items: center; gap: 6px; color: #606266; }
.status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.status-0 { background-color: #67C23A; } .status-1 { background-color: #F56C6C; } .status-2 { background-color: #E6A23C; } .status-3 { background-color: #909399; }

.building-collapse { border-top: none; border-bottom: none; }
.building-section { margin-bottom: 20px; background: #fff; border: 1px solid #eef1f6; border-radius: 6px; overflow: hidden; }
:deep(.el-collapse-item__header) { background-color: #fbfdff; padding: 0 15px; font-size: 16px; border-bottom: 1px solid #eef1f6; }
.building-title-wrapper { display: flex; align-items: center; gap: 10px; width: 100%; }
.building-icon { font-size: 18px; color: #409eff; }
.b-name { font-weight: bold; color: #2c3e50; }

.building-matrix { display: flex; flex-direction: column; gap: 12px; padding: 15px; }
.floor-row { display: flex; align-items: center; gap: 15px; border-bottom: 1px dashed #f1f3f7; padding-bottom: 12px; }
.floor-row:last-child { border-bottom: none; padding-bottom: 0; }
.floor-badge { width: 36px; height: 36px; background-color: #ebf5ff; color: #409eff; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; }
.house-tiles-container { display: flex; flex-wrap: wrap; gap: 12px; flex: 1; }

.house-card { height: 52px; border-radius: 6px; display: flex; align-items: center; justify-content: center; padding: 0 8px; color: #fff; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 2px 5px rgba(0,0,0,0.08); position: relative; overflow: hidden; }
.house-card:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.15); filter: contrast(1.1); }
.not-matched { opacity: 0.15; filter: grayscale(100%); pointer-events: none; }

.house-info { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; }
.room-num { font-size: 14px; font-weight: bold; line-height: 1.2; letter-spacing: 0.5px; }
.room-area { font-size: 11px; opacity: 0.8; margin-top: 2px; white-space: nowrap; }

.room-tenant { font-size: 11px; margin-top: 2px; white-space: nowrap; overflow: hidden; opacity: 0.95; font-weight: bold; width: 100%; text-align: center; }
.click-trigger { cursor: pointer; text-decoration: underline; text-underline-offset: 3px; color: #ffffff; transition: all 0.2s; padding: 2px 0; border-radius: 2px;}
.click-trigger:hover { transform: scale(1.08); background-color: rgba(0,0,0,0.15); }

/* Tooltip 内专属穿透超链接样式 */
.click-trigger-tooltip { 
  cursor: pointer; 
  text-decoration: underline; 
  text-underline-offset: 3px; 
  color: #409eff; 
  transition: all 0.2s; 
  font-weight: bold; 
}
.click-trigger-tooltip:hover { 
  color: #66b1ff; 
  transform: scale(1.02); 
  display: inline-block; 
}

.theme-status-0 { background: linear-gradient(135deg, #67c23a, #52a82b); }
.theme-status-1 { background: linear-gradient(135deg, #f56c6c, #e04c4c); }
.theme-status-2 { background: linear-gradient(135deg, #e6a23c, #cc8623); }
.theme-status-3 { background: linear-gradient(135deg, #909399, #606266); }

.tooltip-box { font-size: 13px; line-height: 1.6; color: #303133; min-width: 200px; }
.tt-header { font-weight: bold; font-size: 15px; margin-bottom: 8px; color: #409eff; }

.edit-dialog-footer { display: flex; justify-content: space-between; width: 100%; }

/* 全屏沉浸式详情 UI */
:deep(.fullscreen-detail .el-dialog__header) { margin-right: 0; border-bottom: 1px solid #ebeef5; padding-bottom: 20px; background-color: #f8f9fa; }
.detail-header { display: flex; align-items: center; gap: 20px; }
.detail-title { font-size: 18px; font-weight: bold; color: #303133; }
.detail-body { padding: 10px 20px 30px; max-width: 1000px; margin: 0 auto; }

.section-title { font-size: 16px; font-weight: bold; color: #303133; margin-bottom: 15px; border-bottom: 1px solid #ebeef5; padding-bottom: 10px; }
.mt-20 { margin-top: 30px; }
.mt-10 { margin-top: 15px; }

.info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.info-item { display: flex; align-items: center; font-size: 14px; }
.info-item.full-width { grid-column: span 2; }
.info-item .label { color: #909399; width: 130px; flex-shrink: 0; }
.info-item .value { color: #303133; }

.font-bold { font-weight: bold; }
.text-primary { color: #409eff; }
.text-danger { color: #f56c6c; }
.text-warning { color: #e6a23c; }
.text-code { font-family: monospace; font-weight: bold; background: #f4f4f5; padding: 2px 6px; border-radius: 4px; }

.price-highlight { background: #fdf6ec; padding: 15px 20px; border-radius: 6px; display: flex; align-items: baseline; border: 1px dashed #f3d19e; }
.price-highlight .label { color: #e6a23c; margin-right: 15px; width: auto; font-weight: bold;}
.price-highlight .value { font-size: 24px; font-weight: bold; margin-right: 5px; }
.price-highlight .unit { font-size: 13px; color: #909399; }
</style>