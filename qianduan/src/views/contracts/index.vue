<template>
  <div class="contracts-container">
    <el-card shadow="never" class="main-card">
      <div class="toolbar">
        <el-button type="success" icon="DocumentAdd" @click="openContractDialog">起草/批量分签合同</el-button>
        <el-button type="warning" icon="Download" @click="exportData">合同库带水印导出</el-button>
        <el-button icon="Refresh" @click="fetchContracts">刷新</el-button>
      </div>

      <div class="filter-panel">
        <div class="filter-item">
          <span class="filter-label">合同状态:</span>
          <el-select v-model="filterStatus" clearable placeholder="全部" style="width: 120px">
            <el-option label="全部" value="" />
            <el-option label="履约中" :value="1" />
            <el-option label="已退租/失效" :value="0" />
          </el-select>
        </div>
      </div>

      <el-table :data="processedTableData" v-loading="loading" border stripe style="width: 100%">
        <el-table-column prop="contract_no" label="单体契约编号" width="170" align="center" />
        <el-table-column prop="enterprise_name" label="承租企业" min-width="150" show-overflow-tooltip />
        <el-table-column label="承租单体空间" width="140" align="center">
          <template #default="{ row }">
            <span style="font-weight: bold; color: #409eff;">{{ row.building_name }} - {{ row.room_number }}</span>
          </template>
        </el-table-column>
        <el-table-column label="起租/退租日期" width="130" align="center">
          <template #default="{ row }">
            <div style="font-size: 12px">{{ row.start_date }}</div>
            <div style="font-size: 12px; color: #909399;">至</div>
            <div style="font-size: 12px">{{ row.end_date }}</div>
          </template>
        </el-table-column>
        <el-table-column label="单体业财基准" width="140" align="right">
          <template #default="{ row }">
            <div style="font-weight: bold; color: #f56c6c;">租: ¥{{ row.monthly_rent }}</div>
            <div style="font-size: 12px; color: #909399;">物: ¥{{ row.property_fee }}</div>
          </template>
        </el-table-column>
        
        <el-table-column label="系统起草/录入时间" width="150" align="center">
          <template #default="{ row }">
            <div style="font-size: 12px; color: #909399;">{{ row.created_at }}</div>
          </template>
        </el-table-column>

        <el-table-column label="当前状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'info'">
              {{ row.status === 1 ? '履约中' : '已失效' }}
            </el-tag>
          </template>
        </el-table-column>
        
        <el-table-column label="操作" width="460" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="success" link icon="Clock" @click="openHistoryDrawer(row)">履约族谱</el-button>
            <el-button type="primary" link icon="Document" @click="openDocsDrawer(row)">电子档案</el-button>
            <el-button type="info" link icon="Monitor" @click="openAuditDrawer(row)">业财核对</el-button>
            
            <el-button v-if="row.status === 1" type="warning" link icon="Switch" @click="openAlterDialog(row)">变更/搬迁</el-button>
            <el-button v-if="row.status === 1" type="danger" link icon="Wallet" @click="openCheckoutDrawer(row)">退租结算</el-button>
            <el-button v-if="row.status === 0" type="danger" plain link icon="RefreshLeft" @click="handleRevoke(row)">撤销退租</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="contractDialogVisible" title="起草/扩签新合同 (支持批量锁房与水电基数建账)" width="680px" @close="contractFormRef?.resetFields()">
      <div class="sandbox-tips" style="margin-top: -10px;">
        <el-icon><InfoFilled /></el-icon> 
        批量勾选多个房间并输入总价，底层资产引擎将按面积自动等比切割为多份独立合同，实现资产精细化解耦。
      </div>

      <el-form ref="contractFormRef" :model="contractForm" :rules="contractRules" label-width="130px">
        <el-form-item label="承租企业" prop="enterprise_id">
          <el-select v-model="contractForm.enterprise_id" filterable placeholder="检索已建档企业 (扩租请直接搜原企业)" style="width: 100%;">
            <el-option v-for="ent in enterprises" :key="ent.id" :label="ent.name" :value="ent.id" />
          </el-select>
        </el-form-item>
        
        <el-form-item label="批量锁定空间" prop="space_ids">
          <el-select v-model="contractForm.space_ids" multiple filterable placeholder="可多选空置空间，系统将自动拆分单体合同" style="width: 100%;">
            <el-option v-for="sp in availableSpaces" :key="sp.id" :label="`${sp.building_name} - ${sp.floor}F - ${sp.room_number} (${sp.area}㎡)`" :value="sp.id" />
          </el-select>
        </el-form-item>

        <div v-if="contractForm.meters.length > 0" class="meters-input-panel">
          <div class="panel-header">
            <el-icon><Monitor /></el-icon> 强制要求：请抄录入驻房间的【期初水电底数】用于后续计费扣算
          </div>
          <el-table :data="contractForm.meters" size="small" border style="width: 100%;">
            <el-table-column prop="room_number" label="物理房间" width="120" />
            <el-table-column label="期初水表 (吨)">
              <template #default="{ row }">
                <el-input-number v-model="row.init_water" :min="0" :precision="2" controls-position="right" style="width: 100%" />
              </template>
            </el-table-column>
            <el-table-column label="期初电表 (度)">
              <template #default="{ row }">
                <el-input-number v-model="row.init_elec" :min="0" :precision="2" controls-position="right" style="width: 100%" />
              </template>
            </el-table-column>
          </el-table>
        </div>

        <el-form-item label="合同周期" prop="dateRange">
          <el-date-picker v-model="contractForm.dateRange" type="daterange" range-separator="至" start-placeholder="起租日" end-placeholder="到期日" value-format="YYYY-MM-DD" style="width: 100%;" />
        </el-form-item>
        
        <div style="display: flex; gap: 10px;">
          <el-form-item label="总月租金(元)" prop="monthly_rent" style="flex: 1;">
            <el-input-number v-model="contractForm.monthly_rent" :min="0" style="width: 100%" controls-position="right" @change="handleRentChange" />
          </el-form-item>
          <el-form-item label="总物业费(元)" prop="property_fee" style="flex: 1;">
            <el-input-number v-model="contractForm.property_fee" :min="0" style="width: 100%" controls-position="right" />
          </el-form-item>
        </div>

        <el-divider content-position="left" style="margin: 15px 0;">业财收缴标准与原件留档</el-divider>

        <el-form-item label="收费标准/周期" prop="payment_cycle">
          <div style="display: flex; gap: 10px; width: 100%;">
            <el-radio-group v-model="contractForm.payment_cycle" @change="handleCycleModeChange">
              <el-radio-button :label="1">月付</el-radio-button>
              <el-radio-button :label="3">季付</el-radio-button>
              <el-radio-button :label="6">半年付</el-radio-button>
              <el-radio-button :label="12">年付</el-radio-button>
              <el-radio-button :label="0">自定义</el-radio-button>
            </el-radio-group>
            <el-input-number v-if="contractForm.payment_cycle === 0 || isCustomCycle" v-model="customCycleValue" :min="1" :max="60" placeholder="月数" controls-position="right" style="width: 100px" />
          </div>
        </el-form-item>
        
        <el-form-item label="总履约押金(元)" prop="deposit">
          <el-input-number v-model="contractForm.deposit" :min="0" style="width: 100%" controls-position="right" />
        </el-form-item>

        <el-form-item label="纸质原件扫描" prop="scanned_file_url">
          <el-upload
            class="contract-uploader"
            action="http://47.120.52.65:8787/api/upload"
            :headers="uploadHeaders"
            :show-file-list="false"
            :on-success="handleUploadScanned"
            :before-upload="beforeUpload"
          >
            <img v-if="contractForm.scanned_file_url && !isPdf(contractForm.scanned_file_url)" :src="getFullImgUrl(contractForm.scanned_file_url)" class="preview-img" />
            <div v-else-if="contractForm.scanned_file_url && isPdf(contractForm.scanned_file_url)" class="upload-trigger text-success">
              <el-icon size="24"><DocumentChecked /></el-icon> PDF已就绪
            </div>
            <div v-else class="upload-trigger">
              <el-icon size="24" style="margin-bottom: 8px;"><Plus /></el-icon> 点击上传高清扫描件(PDF/图片)
            </div>
          </el-upload>
        </el-form-item>

        <div class="bill-preview">
          <div class="preview-title"><el-icon><Money /></el-icon> 首期总应收账单推演 (包含所有选中房间)</div>
          <div class="preview-content">
            <span class="formula">总押金 (¥{{ contractForm.deposit || 0 }}) + [总租金+总物业] × {{ actualPaymentCycle }} 个月</span>
            <span class="total">¥ {{ firstBillTotal }}</span>
          </div>
        </div>

      </el-form>
      <template #footer>
        <el-button @click="contractDialogVisible = false">取消</el-button>
        <el-button type="success" :loading="submitLoading" @click="submitContract">确认自动分单并锁房</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="alterDialogVisible" title="主合同状态流转与重叠期沙盘 (搬迁/缩租/置换)" width="750px" top="5vh" @close="alterFormRef?.resetFields()">
      <div class="sandbox-tips">
        <el-icon><InfoFilled /></el-icon> 
        原合同将进入失效库。调整【物理入驻日】与【计费起始日】的差值，系统将自动计算出免租重叠期。
      </div>

      <el-form ref="alterFormRef" :model="alterForm" label-width="140px">
        <el-form-item label="流转业务类型" prop="alteration_type">
          <el-radio-group v-model="alterForm.alteration_type">
            <el-radio :label="3">园区内空间置换/搬迁</el-radio>
            <el-radio :label="2">原址缩租/降级</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="目标物理空间" v-if="alterForm.alteration_type === 3" prop="new_space_id">
          <el-select v-model="alterForm.new_space_id" placeholder="请选择要搬入的新房间 (原房间将自动释放)" style="width: 100%;">
            <el-option v-for="space in availableSpaces" :key="space.id" :label="space.building_name + '-' + space.room_number" :value="space.id" />
          </el-select>
        </el-form-item>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="拿钥匙(物理入驻)" prop="start_date">
              <el-date-picker v-model="alterForm.start_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="财务计费起始日" class="highlight-label" prop="billing_start_date">
              <el-date-picker v-model="alterForm.billing_start_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>

        <div class="overlap-calc-box" v-if="alterForm.start_date && alterForm.billing_start_date">
          <span>系统推演计算：该企业在本次流转中将获得 </span>
          <span class="overlap-days">{{ calculateOverlapDays }}</span>
          <span> 天的免租/重叠过渡期。</span>
        </div>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="流转后月租金(元)" prop="monthly_rent">
              <el-input-number v-model="alterForm.monthly_rent" :min="0" style="width: 100%" controls-position="right" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="流转后新到期日" prop="end_date">
              <el-date-picker v-model="alterForm.end_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-form-item label="流转后物业费(元)" prop="property_fee">
          <el-input-number v-model="alterForm.property_fee" :min="0" style="width: 100%" controls-position="right" />
        </el-form-item>

        <el-form-item label="补充协议/新合同" prop="scanned_file_url">
          <el-upload
            class="contract-uploader"
            action="http://47.120.52.65:8787/api/upload"
            :headers="uploadHeaders"
            :show-file-list="false"
            :on-success="handleAlterUpload"
          >
            <img v-if="alterForm.scanned_file_url && !isPdf(alterForm.scanned_file_url)" :src="getFullImgUrl(alterForm.scanned_file_url)" class="preview-img" />
            <div v-else-if="alterForm.scanned_file_url && isPdf(alterForm.scanned_file_url)" class="upload-trigger text-success">
              <el-icon size="24"><DocumentChecked /></el-icon> PDF已就绪
            </div>
            <div v-else class="upload-trigger">
              <el-icon size="24" style="margin-bottom: 8px; display: block;"><Plus /></el-icon> 
              必须上传流转后的补充协议(备查)
            </div>
          </el-upload>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="alterDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitAlteration" :loading="submitLoading">确认重组档案并生效</el-button>
      </template>
    </el-dialog>

    <el-drawer v-model="checkoutDrawerVisible" :title="`退租结算核算：${currentContract.contract_no}`" size="500px">
      <div class="checkout-container">
        <el-alert title="合同作废不可逆。退租后，物理房间将立即释放为【空置可租】状态，并自动生成财务打款单。" type="error" show-icon :closable="false" style="margin-bottom: 20px;" />
        
        <el-form ref="checkoutFormRef" :model="checkoutForm" label-position="top">
          <el-row :gutter="20">
            <el-col :span="12">
              <el-form-item label="该合同原始已缴押金(元)">
                <el-input v-model="checkoutForm.refund_deposit" disabled />
              </el-form-item>
            </el-col>
          </el-row>

          <el-divider content-position="left">退租罚没与损耗核算</el-divider>

          <el-row :gutter="20">
            <el-col :span="12">
              <el-form-item label="扣除违约租金/水电(元)">
                <el-input-number v-model="checkoutForm.deduct_rent" :min="0" style="width: 100%;" controls-position="right" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="扣除物损破坏费用(元)">
                <el-input-number v-model="checkoutForm.deduct_damage" :min="0" style="width: 100%;" controls-position="right" />
              </el-form-item>
            </el-col>
          </el-row>
          
          <el-form-item label="清算/扣款原因及备注说明">
            <el-input v-model="checkoutForm.remark" type="textarea" :rows="3" placeholder="请详述扣款原因，例如：提前半个月退租扣除租金、房间墙面破坏等，以便财务备查" />
          </el-form-item>

          <el-divider border-style="dashed" />

          <div class="checkout-result">
            <div class="result-label">最终财务应退款总计：</div>
            <div class="result-amount" :class="{ 'text-danger': actualRefund < 0 }">
              ¥ {{ actualRefund }}
            </div>
            <div v-if="actualRefund < 0" style="font-size: 12px; color: #f56c6c; margin-top: 5px;">
              * 扣除金额已超出原始押金，需向企业追缴欠款。
            </div>
          </div>

          <el-button type="danger" size="large" class="checkout-submit-btn" :loading="submitLoading" @click="submitCheckout">
            确认清算数据，作废合同并释放空间
          </el-button>
        </el-form>
      </div>
    </el-drawer>

    <el-drawer v-model="historyDrawerVisible" :title="`资产生命周期与流转族谱溯源`" size="500px">
      <div v-loading="historyLoading" class="history-container">
        <el-alert title="展示当前合同相关的历史沿革，包括空间切割、置换搬迁与扩缩租补充协议流转全貌。" type="info" show-icon :closable="false" style="margin-bottom: 20px;" />
        
        <el-timeline>
          <el-timeline-item
            v-for="(activity, index) in historyList"
            :key="activity.id"
            :type="index === 0 ? 'success' : 'info'"
            :hollow="index !== 0"
            :timestamp="`生效操作记录时间: ${activity.created_at}`"
            placement="top"
          >
            <el-card :shadow="index === 0 ? 'hover' : 'never'" :class="{'is-active-card': index === 0}">
              <div class="history-card-header">
                <span class="room-title">{{ activity.building_name }} - {{ activity.room_number }}</span>
                <el-tag :type="activity.status === 1 ? 'success' : 'info'" size="small">
                  {{ activity.status === 1 ? '当前履约态' : '历史/已失效' }}
                </el-tag>
              </div>
              <el-descriptions :column="1" size="small" style="margin-top: 10px;">
                <el-descriptions-item label="系统内唯一契约号">
                  <span style="font-family: monospace;">{{ activity.contract_no }}</span>
                </el-descriptions-item>
                <el-descriptions-item label="业务变更定性">
                  <el-tag :type="getAlterationTypeTag(activity.alteration_type)" size="small" effect="dark">
                    {{ getAlterationTypeName(activity.alteration_type) }}
                  </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="财务计费区间">
                  {{ activity.billing_start_date }} ~ <span :class="{'text-danger': activity.status === 0}">{{ activity.end_date }}</span>
                </el-descriptions-item>
                <el-descriptions-item label="核定月租金/物业">
                  ¥{{ activity.monthly_rent }} / ¥{{ activity.property_fee }}
                </el-descriptions-item>
              </el-descriptions>
              <div v-if="activity.scanned_file_url" style="margin-top: 10px; text-align: right;">
                <el-button type="primary" link icon="View" @click="previewDoc(activity.scanned_file_url)">查阅当时留底原件</el-button>
              </div>
            </el-card>
          </el-timeline-item>
        </el-timeline>
        <el-empty v-if="!historyLoading && historyList.length === 0" description="未能追溯到任何家族版本记录" />
      </div>
    </el-drawer>

    <el-drawer v-model="auditDrawerVisible" :title="`业财一致性双屏核对：${currentContract.contract_no}`" size="85%" destroy-on-close>
      <el-row :gutter="20" style="height: 100%;">
        <el-col :span="7">
          <el-card shadow="never" header="系统底层结构化数据" class="audit-card">
            <el-descriptions :column="1" border size="small">
              <el-descriptions-item label="承租企业主体">
                <span class="text-primary" style="font-weight:bold;">{{ currentContract.enterprise_name }}</span>
              </el-descriptions-item>
              <el-descriptions-item label="挂载物理空间">
                {{ currentContract.building_name }} - {{ currentContract.room_number }}
              </el-descriptions-item>
              <el-descriptions-item label="系统设定月租">
                <span class="text-danger" style="font-weight:bold;">¥ {{ currentContract.monthly_rent }}</span>
              </el-descriptions-item>
              <el-descriptions-item label="系统设定物管">
                ¥ {{ currentContract.property_fee }}
              </el-descriptions-item>
              <el-descriptions-item label="系统履约押金">
                ¥ {{ currentContract.deposit }}
              </el-descriptions-item>
              <el-descriptions-item label="物理起租日期">
                {{ currentContract.start_date }}
              </el-descriptions-item>

              <el-descriptions-item label="期初水表底数">
                <span class="text-primary" style="font-weight:bold;">{{ currentContract.water_meter || '0.00' }} 吨</span>
              </el-descriptions-item>
              <el-descriptions-item label="期初电表底数">
                <span class="text-primary" style="font-weight:bold;">{{ currentContract.electric_meter || '0.00' }} 度</span>
              </el-descriptions-item>
              <el-descriptions-item label="财务计费起始日">
                <span class="text-warning" style="font-weight:bold;">{{ currentContract.billing_start_date || currentContract.start_date }}</span>
              </el-descriptions-item>
              <el-descriptions-item label="合同到期日期">
                <span :class="{'text-danger': currentContract.status === 0}">{{ currentContract.end_date }}</span>
              </el-descriptions-item>
              <el-descriptions-item label="当前运作状态">
                <el-tag :type="currentContract.status === 1 ? 'success' : 'info'">{{ currentContract.status === 1 ? '履约生效中' : '已退租失效' }}</el-tag>
              </el-descriptions-item>
            </el-descriptions>
            <div style="margin-top: 20px; font-size: 12px; color: #909399;">
              审计要求：请核对左侧系统生成的财务出账基准（包含初始水电），是否与右侧企业方签章的纸质合同原件条款严格一致。
            </div>
          </el-card>
        </el-col>
        <el-col :span="17" style="height: 100%; border-left: 1px dashed #dcdfe6; padding-left: 20px; overflow: hidden;">
          <div style="font-weight: bold; margin-bottom: 10px; color: #303133;">
            <el-icon><DocumentChecked /></el-icon> 纸质合同/补充协议扫描原件
          </div>
          <div v-if="currentContract.scanned_file_url" style="height: calc(100vh - 120px);">
            <iframe 
              v-if="isPdf(currentContract.scanned_file_url)" 
              :src="getFullImgUrl(currentContract.scanned_file_url)" 
              style="width: 100%; height: 100%; border: 1px solid #ebeef5; border-radius: 4px;"
            ></iframe>
            <el-image 
              v-else
              :src="getFullImgUrl(currentContract.scanned_file_url)" 
              style="width: 100%; height: 100%; background: #f5f7fa; border-radius: 4px;" 
              fit="contain" 
              :preview-src-list="[getFullImgUrl(currentContract.scanned_file_url)]"
            />
          </div>
          <el-empty v-else description="该合同未上传纸质扫描件归档" :image-size="80" />
        </el-col>
      </el-row>
    </el-drawer>

    <el-drawer v-model="docsDrawerVisible" :title="`电子制式合同生成：${currentContract.contract_no}`" size="450px">
      <div class="docs-container" v-loading="docsLoading">
        <el-alert title="系统标准制式文书，仅供参考。" type="info" show-icon :closable="false" style="margin-bottom: 20px;" />
        <div class="doc-actions">
          <el-button type="primary" icon="Printer" @click="generateElecDoc">自动生成标准制式合同PDF</el-button>
        </div>
        <el-divider content-position="left">已生成附件</el-divider>
        <ul class="doc-list">
          <li class="doc-item">
            <div class="doc-info">
              <el-icon class="doc-icon"><Document /></el-icon>
              <div class="doc-name-time">
                <span class="doc-name">电子制式合同草案</span>
                <span v-if="currentDocs.elec_contract_url" class="doc-audit-time">生成于: {{ currentDocs.updated_at }}</span>
              </div>
            </div>
            <div class="doc-ctrl">
              <el-button v-if="currentDocs.elec_contract_url" type="primary" link @click="previewDoc(currentDocs.elec_contract_url)">预览</el-button>
              <span v-else style="font-size: 12px; color: #909399;">尚未演算生成</span>
            </div>
          </li>
        </ul>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue' 
import { ElMessage, ElMessageBox } from 'element-plus'
import { DocumentAdd, Download, Refresh, Document, Wallet, RefreshLeft, Monitor, Money, Printer, Plus, DocumentChecked, Switch, InfoFilled, Clock, View } from '@element-plus/icons-vue'
import request from '../../utils/request'

const tableData = ref([])
const loading = ref(false)
const filterStatus = ref(1) 

const enterprises = ref([])
const availableSpaces = ref([])

const contractDialogVisible = ref(false)
const submitLoading = ref(false)
const contractFormRef = ref(null)

const contractForm = reactive({ 
  enterprise_id: '', 
  space_ids: [], 
  meters: [], 
  dateRange: [], 
  monthly_rent: 0, 
  property_fee: 0, 
  payment_cycle: 3, 
  vehicle_info: '', 
  deposit: 0,
  scanned_file_url: '' 
})

watch(() => contractForm.space_ids, (newVal) => {
  const newMeters = []
  if(newVal && newVal.length > 0) {
      newVal.forEach(id => {
        const sp = availableSpaces.value.find(s => s.id === id)
        const existing = contractForm.meters.find(m => m.id === id)
        newMeters.push({
          id: id,
          room_number: sp ? `${sp.building_name}-${sp.room_number}` : '未知',
          init_water: existing ? existing.init_water : 0,
          init_elec: existing ? existing.init_elec : 0
        })
      })
  }
  contractForm.meters = newMeters
}, { deep: true })

const isCustomCycle = ref(false)
const customCycleValue = ref(1)

const contractRules = {
  enterprise_id: [{ required: true, message: '请选择企业', trigger: 'change' }],
  space_ids: [{ type: 'array', required: true, message: '请至少批量分配一个空间', trigger: 'change' }],
  dateRange: [{ required: true, message: '请设定周期', trigger: 'change' }],
  monthly_rent: [{ required: true, message: '请输入总租金', trigger: 'blur' }],
  scanned_file_url: [{ required: true, message: '必须上传纸质合同扫描原件备查', trigger: 'change' }]
}

const alterDialogVisible = ref(false)
const alterFormRef = ref(null)
const alterForm = reactive({
  old_contract_id: null,
  alteration_type: 3, 
  new_space_id: null,
  start_date: '',
  billing_start_date: '', 
  end_date: '',
  monthly_rent: 0,
  property_fee: 0,
  scanned_file_url: ''
})

const calculateOverlapDays = computed(() => {
  if (!alterForm.start_date || !alterForm.billing_start_date) return 0
  const start = new Date(alterForm.start_date)
  const billing = new Date(alterForm.billing_start_date)
  const diffTime = billing - start
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
  return diffDays > 0 ? diffDays : 0
})

const auditDrawerVisible = ref(false)
const docsDrawerVisible = ref(false)
const docsLoading = ref(false)
const currentContract = ref({})
const currentDocs = ref({ elec_contract_url: null, updated_at: null })
const uploadHeaders = computed(() => ({ 'Authorization': `Bearer ${localStorage.getItem('saas_token')}` }))

const checkoutDrawerVisible = ref(false)
const checkoutFormRef = ref(null)
const checkoutForm = reactive({ refund_deposit: 0, deduct_rent: 0, deduct_damage: 0, remark: '' })

const historyDrawerVisible = ref(false)
const historyLoading = ref(false)
const historyList = ref([])

const getAlterationTypeName = (type) => {
  const map = { 0: '新签/自动分单', 1: '扩租', 2: '缩租', 3: '搬迁/空间置换' }
  return map[type] || '未知变更'
}

const getAlterationTypeTag = (type) => {
  const map = { 0: 'primary', 1: 'success', 2: 'warning', 3: 'danger' }
  return map[type] || 'info'
}

const openHistoryDrawer = async (row) => {
  historyList.value = []
  historyDrawerVisible.value = true
  historyLoading.value = true
  try {
    const res = await request.get('/api/contracts/history', { params: { id: row.id } })
    if (res.code === 200) {
      historyList.value = res.data
    } else {
      ElMessage.error(res.msg || '抓取履历失败')
    }
  } finally { historyLoading.value = false }
}

const isPdf = (url) => {
  if (!url) return false;
  return url.toLowerCase().endsWith('.pdf');
}

const handleUploadScanned = (res) => {
  if (res.code === 200) {
    contractForm.scanned_file_url = res.data.url
    ElMessage.success('扫描原件提取入库成功')
  } else { ElMessage.error('上传解析异常') }
}

const handleAlterUpload = (res) => {
  if (res.code === 200) {
    alterForm.scanned_file_url = res.data.url
    ElMessage.success('扫描原件提取入库成功')
  } else { ElMessage.error('上传解析异常') }
}

const handleRentChange = (val) => {
  if (contractForm.deposit === 0 || contractForm.deposit === '') {
    contractForm.deposit = val
  }
}

const handleCycleModeChange = (val) => {
  if (val === 0) { isCustomCycle.value = true } else { isCustomCycle.value = false }
}

const actualPaymentCycle = computed(() => {
  return contractForm.payment_cycle === 0 ? customCycleValue.value : contractForm.payment_cycle
})

const firstBillTotal = computed(() => {
  const rent = Number(contractForm.monthly_rent) || 0
  const prop = Number(contractForm.property_fee) || 0
  const dep = Number(contractForm.deposit) || 0
  const cycle = actualPaymentCycle.value
  return (dep + (rent + prop) * cycle).toFixed(2)
})

const actualRefund = computed(() => {
  const deposit = Number(checkoutForm.refund_deposit) || 0
  const rent = Number(checkoutForm.deduct_rent) || 0
  const damage = Number(checkoutForm.deduct_damage) || 0
  return (deposit - rent - damage).toFixed(2)
})

const processedTableData = computed(() => {
  return tableData.value.filter(row => {
    if (typeof filterStatus.value === 'number' && row.status !== filterStatus.value) return false
    return true
  })
})

const fetchContracts = async () => {
  loading.value = true
  try {
    const res = await request.get('/api/contracts/list')
    if (res.code === 200) tableData.value = res.data
  } finally { loading.value = false }
}

const openContractDialog = async () => {
  contractDialogVisible.value = true
  isCustomCycle.value = false
  contractForm.payment_cycle = 3
  contractForm.scanned_file_url = ''
  contractForm.space_ids = [] 
  contractForm.meters = [] 
  
  const entRes = await request.get('/api/enterprises/list')
  if (entRes.code === 200) enterprises.value = entRes.data
  const spRes = await request.get('/api/spaces/list')
  if (spRes.code === 200) availableSpaces.value = spRes.data.filter(s => s.status === 0)
}

const submitContract = () => {
  contractFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    try {
      const payload = { 
        ...contractForm, 
        start_date: contractForm.dateRange[0], 
        end_date: contractForm.dateRange[1],
        payment_cycle: actualPaymentCycle.value 
      }
      const res = await request.post('/api/contracts/add', payload)
      if (res.code === 200) {
        ElMessage.success(res.msg || '批量自动分单签约成功，房间已锁定')
        contractDialogVisible.value = false
        fetchContracts()
      } else { ElMessage.error(res.msg || '操作失败') }
    } finally { submitLoading.value = false }
  })
}

const openAlterDialog = async (row) => {
  alterForm.old_contract_id = row.id
  alterForm.alteration_type = 3
  alterForm.new_space_id = null
  alterForm.start_date = row.start_date
  alterForm.billing_start_date = row.billing_start_date || row.start_date
  alterForm.end_date = row.end_date
  alterForm.monthly_rent = row.monthly_rent
  alterForm.property_fee = row.property_fee
  alterForm.scanned_file_url = ''
  
  alterDialogVisible.value = true
  
  const spRes = await request.get('/api/spaces/list')
  if (spRes.code === 200) availableSpaces.value = spRes.data.filter(s => s.status === 0)
}

const submitAlteration = () => {
  if (!alterForm.scanned_file_url) {
    return ElMessage.warning('审计要求：必须上传变更后的补充协议或新合同纸质扫描件')
  }
  submitLoading.value = true
  request.post('/api/contracts/alter', alterForm).then(res => {
    if (res.code === 200) {
      ElMessage.success(res.msg)
      alterDialogVisible.value = false
      fetchContracts()
    } else { ElMessage.error(res.msg) }
  }).finally(() => { submitLoading.value = false })
}

const openAuditDrawer = (row) => {
  currentContract.value = row
  auditDrawerVisible.value = true
}

const openCheckoutDrawer = (row) => {
  currentContract.value = row
  checkoutForm.refund_deposit = row.deposit || 0
  checkoutForm.deduct_rent = 0
  checkoutForm.deduct_damage = 0
  checkoutForm.remark = ''
  checkoutDrawerVisible.value = true
}

const submitCheckout = () => {
  ElMessageBox.confirm(
    `清算后，实体空间 ${currentContract.value.building_name}-${currentContract.value.room_number} 将被释放供租赁，是否确认？`,
    '最后警告',
    { confirmButtonText: '确认清算', cancelButtonText: '取消', type: 'warning' }
  ).then(async () => {
    submitLoading.value = true
    try {
      const payload = {
        id: currentContract.value.id,
        refund_deposit: checkoutForm.refund_deposit,
        deduct_rent: checkoutForm.deduct_rent,
        deduct_damage: checkoutForm.deduct_damage,
        actual_refund: actualRefund.value,
        remark: checkoutForm.remark
      }
      const res = await request.post('/api/contracts/terminate', payload)
      if (res.code === 200) {
        ElMessage.success('退租完毕！清算单据已流转。')
        checkoutDrawerVisible.value = false
        fetchContracts()
      } else { ElMessage.error(res.msg || '退租处理异常') }
    } finally { submitLoading.value = false }
  }).catch(() => {})
}

const handleRevoke = (row) => {
  ElMessageBox.confirm(
    `确认要撤销【${row.contract_no}】的退租吗？系统将强制恢复至“履约中”状态。`,
    '状态机回滚',
    { confirmButtonText: '确认撤销回滚', cancelButtonText: '取消', type: 'error' }
  ).then(async () => {
    try {
      const res = await request.post('/api/contracts/revoke_terminate', { contract_id: row.id })
      if (res.code === 200) {
        ElMessage.success(res.msg)
        fetchContracts()
      } else { ElMessage.error(res.msg) }
    } catch (e) { ElMessage.error('回滚通讯失败') }
  }).catch(() => {})
}

const exportData = async () => {
  const token = localStorage.getItem('saas_token')
  const res = await fetch(`http://47.120.52.65:8787/api/export/download?module=contracts`, { headers: { 'Authorization': `Bearer ${token}` } })
  const blob = await res.blob()
  const a = document.createElement('a'); a.href = window.URL.createObjectURL(blob); a.download = `租务合同台账.csv`; a.click()
}

const openDocsDrawer = (row) => { currentContract.value = row; docsDrawerVisible.value = true; fetchDocs(row.id) }
const fetchDocs = async (id) => {
  docsLoading.value = true
  const res = await request.get('/api/contracts/docs', { params: { contract_id: id } })
  if (res.code === 200) currentDocs.value = res.data || { elec_contract_url: null, updated_at: null }
  docsLoading.value = false
}

const generateElecDoc = async () => {
  docsLoading.value = true
  const res = await request.post('/api/contracts/generate_elec', { contract_id: currentContract.value.id })
  if (res.code === 200) { ElMessage.success('系统文书生成成功'); fetchDocs(currentContract.value.id) }
  docsLoading.value = false
}

const beforeUpload = (file) => file.size / 1024 / 1024 < 15
const previewDoc = (url) => window.open(`http://47.120.52.65:8787${url}`, '_blank')
const getFullImgUrl = (url) => url.startsWith('http') ? url : `http://47.120.52.65:8787${url}`

onMounted(() => { fetchContracts() })
</script>

<style scoped>
.contracts-container { width: 100%; }
.main-card { border-radius: 4px; box-shadow: none; }
.toolbar { margin-bottom: 20px; display: flex; gap: 10px; }
.filter-panel { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; padding: 12px 15px; background-color: #f8f9fa; border-radius: 6px; border: 1px solid #eef1f6; }
.filter-item { display: flex; align-items: center; gap: 8px; }
.filter-label { font-size: 13px; color: #606266; font-weight: bold; }

.meters-input-panel { border-left: 3px solid #409eff; }
.panel-header { font-size: 13px; color: #409eff; font-weight: bold; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;}

.contract-uploader { border: 1px dashed #d9d9d9; border-radius: 6px; cursor: pointer; position: relative; overflow: hidden; width: 100%; height: 160px; background-color: #fafafa; display: flex; justify-content: center; align-items: center; }
.contract-uploader:hover { border-color: #409EFF; }
.upload-trigger { color: #8c939d; font-size: 13px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.preview-img { width: 100%; height: 100%; object-fit: contain; }

.sandbox-tips { background-color: #e8f3ff; color: #409eff; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; font-size: 13px; display: flex; align-items: center; gap: 8px; }
.highlight-label :deep(.el-form-item__label) { color: #f56c6c; font-weight: bold; }
.overlap-calc-box { background-color: #fdf6ec; border: 1px solid #faecd8; padding: 10px; border-radius: 4px; margin: 10px 0 20px 0; font-size: 13px; color: #e6a23c; text-align: center; }
.overlap-days { font-size: 18px; font-weight: bold; color: #f56c6c; margin: 0 4px; }

.audit-card { margin-top: 10px; border-radius: 6px; }

.checkout-container { padding: 0 10px 20px 10px; }
.checkout-result { background-color: #f4f4f5; padding: 20px; border-radius: 8px; margin-bottom: 30px; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 1px dashed #dcdfe6; }
.result-label { font-size: 14px; color: #606266; margin-bottom: 10px; }
.result-amount { font-size: 32px; font-weight: bold; color: #67c23a; font-family: monospace; }
.text-danger { color: #f56c6c; }
.text-primary { color: #409eff; }
.text-warning { color: #e6a23c; }
.checkout-submit-btn { width: 100%; letter-spacing: 1px; font-weight: bold; }

.history-container { padding: 10px; }
.history-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
.room-title { font-weight: bold; font-size: 15px; color: #303133; }
.is-active-card { border: 1px solid #67c23a; background-color: #f0f9eb; }

.docs-container { padding: 0 10px; }
.doc-actions { display: flex; gap: 10px; margin-bottom: 20px; }
.doc-list { list-style: none; padding: 0; margin: 0; }
.doc-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 1px solid #ebeef5; border-radius: 4px; margin-bottom: 10px; background-color: #fafafa; transition: all 0.3s; }
.doc-item:hover { background-color: #f0f7ff; border-color: #c6e2ff; }
.doc-info { display: flex; align-items: center; gap: 10px; }
.doc-icon { font-size: 24px; color: #909399; }
.doc-name-time { display: flex; flex-direction: column; }
.doc-name { font-size: 14px; color: #303133; }
.doc-audit-time { font-size: 11px; color: #a8abb2; margin-top: 2px; font-family: monospace; }

.bill-preview { background-color: #fdf6ec; border: 1px solid #faecd8; padding: 12px 20px; border-radius: 4px; margin-bottom: 20px; }
.preview-title { font-size: 13px; color: #e6a23c; font-weight: bold; margin-bottom: 8px; display: flex; align-items: center; gap: 5px; }
.preview-content { display: flex; justify-content: space-between; align-items: center; }
.formula { font-size: 12px; color: #909399; font-family: monospace; }
.total { font-size: 24px; font-weight: bold; color: #f56c6c; font-family: monospace; }
</style>