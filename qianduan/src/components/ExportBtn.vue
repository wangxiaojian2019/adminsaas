<template>
  <div style="display: inline-block; margin-left: 10px;">
    <el-button type="success" plain @click="handleExport" :loading="loading">
      <el-icon><Download /></el-icon> 安全导出
    </el-button>

    <el-dialog title="系统拦截：数据资产导出申请" v-model="applyVisible" width="500px">
      <el-alert title="您的当前权限层级不足以直接导出此类敏感资产，请向上级提交申请。" type="error" :closable="false" style="margin-bottom: 20px;" />
      <el-form :model="applyForm" label-width="80px">
        <el-form-item label="导出模块"><el-input :value="moduleNameMap[module] || module" disabled /></el-form-item>
        <el-form-item label="申请事由">
          <el-input type="textarea" v-model="applyForm.reason" placeholder="请详细说明导出此数据的业务用途" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="applyVisible = false">取消</el-button>
        <el-button type="primary" @click="submitApply">确认提交审批</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Download } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import axios from 'axios'

const props = defineProps({
  module: { type: String, required: true }
})

// 核心修复：增加文件名的中文语义化字典映射
const moduleNameMap = {
  'buildings': '空间资产台账',
  'leads': '招商线索数据池',
  'enterprises': '企业户籍档案池',
  'contracts': '租务合同台账',
  'finance': '业财流水报表',
  'dashboard': '运营指挥大盘数据',
  'vehicles': '车位资产与月卡台账',
  'spaces': '房源资产精细库',
  'patrol_records': '安防巡检打卡流水',
  'work_orders': '中控调度工单池',
  'inventory': '物资资产期末盘点表',
  'decorations': '装修报备及工程台账'
}

const loading = ref(false)
const applyVisible = ref(false)
const applyForm = ref({ reason: '' })

const handleExport = async () => {
  loading.value = true
  const token = localStorage.getItem('saas_token')
  try {
    const res = await axios.get(`${import.meta.env.VITE_BASE_API || ''}/api/export/download?module=${props.module}`, {
      headers: { Authorization: `Bearer ${token}` },
      responseType: 'blob'
    })

    if (res.data.type === 'application/json') {
      const reader = new FileReader()
      reader.onload = () => {
        const json = JSON.parse(reader.result)
        if (json.code === 403) {
          applyForm.value.reason = ''
          applyVisible.value = true
        } else {
          ElMessage.error(json.msg)
        }
      }
      reader.readAsText(res.data)
    } else {
      const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8;' })
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      
      // 核心修复：使用字典映射获取中文前缀，并精确到秒
      const prefix = moduleNameMap[props.module] || props.module
      const timeStr = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 14) // 格式化为 YYYYMMDDHHmmss
      link.setAttribute('download', `${prefix}_${timeStr}.csv`)
      
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
      ElMessage.success('报表导出成功')
    }
  } catch (error) {
    ElMessage.error('导出网络异常')
  } finally {
    loading.value = false
  }
}

const submitApply = async () => {
  if (!applyForm.value.reason) return ElMessage.error('请填写业务事由')
  const token = localStorage.getItem('saas_token')
  try {
    const res = await axios.post(`${import.meta.env.VITE_BASE_API || ''}/api/export/apply`, 
      { module: props.module, reason: applyForm.value.reason },
      { headers: { Authorization: `Bearer ${token}` } }
    )
    if (res.data.code === 200) {
      ElMessage.success(res.data.msg)
      applyVisible.value = false
    } else {
      ElMessage.error(res.data.msg)
    }
  } catch (error) {
    ElMessage.error('申请提交失败')
  }
}
</script>