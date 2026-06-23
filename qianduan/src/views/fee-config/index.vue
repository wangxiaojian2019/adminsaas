<template>
  <div class="fee-config-container">
    <el-card class="box-card" shadow="hover" v-loading="loading">
      <template #header>
        <div class="card-header">
          <span><el-icon><Money /></el-icon> 园区全局计费策略配置</span>
          <el-button type="primary" @click="saveConfig">保存并全网生效</el-button>
        </div>
      </template>

      <el-form :model="configForm" label-width="140px" class="config-form">
        <el-divider content-position="left">基础能耗定价 (深度挂钩业财抄表引擎)</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="园区水费单价 (元/吨)">
              <el-input-number v-model="configForm.waterPrice" :precision="2" :step="0.1" :min="0" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="园区电费单价 (元/度)">
              <el-input-number v-model="configForm.electricityPrice" :precision="2" :step="0.1" :min="0" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-divider content-position="left">高级计费管控</el-divider>
        <el-form-item label="计费模式">
          <el-radio-group v-model="configForm.billMode">
            <el-radio value="fixed">固定单价模式 (当前生效)</el-radio>
            <el-radio value="stepped">阶梯阶位模式 (预留扩展)</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="逾期滞纳金比例">
          <el-input-number v-model="configForm.lateFeeRate" :precision="1" :step="0.1" :min="0" :max="10" />
          <span class="unit">% / 每天</span>
          <div class="form-tip">租户逾期未打款时，财务账单将自动每日累加利息。</div>
        </el-form-item>

        <el-form-item label="欠费联动断电(IoT)">
          <el-switch v-model="configForm.autoCutoff" active-color="#13ce66" />
          <div class="form-tip">高危操作：开启后，超过宽限期的未交费账单，将触发 IoT 引擎自动对该房源执行断电拉闸操作。</div>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Money } from '@element-plus/icons-vue'
import request from '@/utils/request'

const loading = ref(false)
const configForm = ref({ waterPrice: 5.5, electricityPrice: 1.2, billMode: 'fixed', lateFeeRate: 0.1, autoCutoff: true })

// 真实接口：从数据库读取全局配置
const fetchConfig = async () => {
  loading.value = true
  const res = await request.get('/api/v1/fee-config/get')
  if (res.code === 200) configForm.value = res.data
  loading.value = false
}

onMounted(() => fetchConfig())

// 真实接口：写入数据库
const saveConfig = async () => {
  const res = await request.post('/api/v1/fee-config/save', configForm.value)
  if (res.code === 200) {
    ElMessage.success(res.msg)
  }
}
</script>

<style scoped>
.fee-config-container { padding: 20px; }
.card-header { display: flex; justify-content: space-between; align-items: center; font-weight: bold; }
.config-form { max-width: 900px; margin: 20px 0; }
.form-tip { font-size: 12px; color: #F56C6C; line-height: 1.5; margin-top: 5px; font-weight: bold;}
.unit { margin-left: 10px; color: #606266; }
</style>