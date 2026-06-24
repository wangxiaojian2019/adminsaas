<template>
  <div class="mobile-container">
    <div class="mobile-header">
      <div class="user-greeting">
        <div class="label-text">当前登录企业：</div>
        <div class="enterprise-header">
          <span class="enterprise-name">{{ enterpriseName || '数据加载中...' }}</span>
          <el-tag size="small" type="success" effect="dark" class="enterprise-tag">入驻企业</el-tag>
        </div>
        <p><el-icon><OfficeBuilding /></el-icon> 园区专属移动服务门户</p>
      </div>
      
      <div class="header-actions">
        <div class="msg-bell" @click="openMsgDrawer">
          <el-badge :value="unreadCount" :hidden="unreadCount === 0" :max="99">
            <el-icon :size="24" color="#ffffff"><BellFilled /></el-icon>
          </el-badge>
        </div>
      </div>
    </div>

    <div v-show="activeTab === 'home'" class="h5-content floating-content" v-loading="overviewLoading">
      <div v-if="pendingReturns.length > 0" class="responsibility-card" style="border: 1px solid #faecd8; background-color: #fdf6ec; margin-bottom: 15px;">
        <div class="resp-title" style="color: #e6a23c; margin-bottom: 12px;">
          <el-icon><Box /></el-icon> 待归还物资提醒 ({{ pendingReturns.length }} 件)
        </div>
        <div v-for="inv in pendingReturns" :key="'ret-'+inv.id" class="quick-bill-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px dashed #faecd8;">
          <div class="qb-info">
            <div class="qb-title" style="margin-bottom: 4px;">
              <span class="qb-amount" style="color: #e6a23c; font-weight: bold; font-size: 15px;">{{ inv.item_name }}</span>
              <span style="font-size: 14px; margin-left: 10px; font-weight: bold; color: #f56c6c;">x{{ inv.quantity }}{{ inv.unit }}</span>
            </div>
            <div class="qb-date text-danger" v-if="inv.expected_return_date" style="font-size: 12px; color: #f56c6c;">
              <el-icon><Timer /></el-icon> 应于 {{ inv.expected_return_date }} 前归还
            </div>
          </div>
        </div>
      </div>

      <div v-if="overview.contracts && overview.contracts.length > 0">
        <div class="stats-panel" style="margin-top: 0;">
          <div class="stat-box">
            <div class="stat-num text-danger" style="font-size: 18px;">¥{{ totalMonthlyRent }}</div>
            <div class="stat-label">总月租金</div>
          </div>
          <div class="stat-box">
            <div class="stat-num text-success" style="font-size: 18px;">¥{{ totalDeposit }}</div>
            <div class="stat-label">总存管押金</div>
          </div>
          <div class="stat-box">
            <div class="stat-num" style="font-size: 18px;">{{ overview.contracts.length }}份</div>
            <div class="stat-label">生效中合同</div>
          </div>
        </div>

        <div v-for="(contract, index) in overview.contracts" :key="contract.id" class="responsibility-card" style="margin-bottom: 15px;">
          <div class="resp-title">
            <el-icon><House /></el-icon> 承租空间 {{ index + 1 }}
            <el-tag size="small" type="info" style="margin-left:auto" v-if="contract.alteration_type === 1">扩租补充</el-tag>
          </div>
          <div class="space-title text-primary" style="font-size: 18px; margin: 10px 0; font-weight: bold;">
            {{ contract.building_name }} - {{ contract.room_number }}
          </div>
          <el-divider border-style="dashed" style="margin: 12px 0;" />
          <div class="info-line"><span>公文契约号：</span><span class="text-code">{{ contract.contract_no }}</span></div>
          <div class="info-line"><span>履约周期：</span><span style="font-size: 12px;">{{ contract.start_date }} ~ {{ contract.end_date }}</span></div>
          <div class="info-line"><span>空间独立月租：</span><span class="text-danger font-bold">¥{{ contract.monthly_rent }}</span></div>
          
          <div class="space-actions" style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed #ebeef5; text-align: right;">
            <el-button size="small" type="primary" plain @click="openDecoDialog(contract)">
              <el-icon style="margin-right: 4px"><Tools /></el-icon>发起装修报备
            </el-button>
          </div>
        </div>
      </div>

      <div v-if="decoList.length > 0" class="responsibility-card" style="margin-bottom: 15px;">
        <div class="resp-title" style="color: #409eff; margin-bottom: 12px;">
          <el-icon><SetUp /></el-icon> 施工报备审核进度追踪
        </div>
        <div v-for="deco in decoList" :key="'deco-'+deco.id" class="quick-bill-item" style="display: block; padding: 10px 0;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <span style="font-weight: bold; color: #303133;">{{ deco.building_name }}-{{ deco.room_number }}</span>
            <el-tag size="small" :type="getDecoStatusTag(deco.status)" effect="dark">
              {{ getDecoStatusText(deco.status) }}
            </el-tag>
          </div>
          <div style="font-size: 12px; color: #909399; display: flex; justify-content: space-between;">
            <span>工期: {{ deco.start_date }} ~ {{ deco.end_date }}</span>
            <span>共 {{ deco.total_days }} 天</span>
          </div>
        </div>
      </div>

      <div v-if="unpaidBills.length > 0" class="responsibility-card" style="border: 1px solid #fde2e2; background-color: #fffafb;">
        <div class="resp-title text-danger" style="margin-bottom: 12px;">
          <el-icon><Bell /></el-icon> 待处理账单提醒 ({{ unpaidBills.length }} 笔)
        </div>
        <div v-for="bill in unpaidBills" :key="'quick-'+bill.id" class="quick-bill-item">
          <div class="qb-info">
            <div class="qb-title">
              <el-tag size="small" :type="getBillTypeColor(bill.bill_type)" effect="dark" style="margin-right: 6px;">
                {{ getBillTypeLabel(bill.bill_type) }}
              </el-tag>
              <span class="qb-amount">¥ {{ bill.amount }}</span>
            </div>
            <div class="qb-date">最晚需于 {{ bill.due_date }} 前结清</div>
            <div v-if="Number(bill.is_paid) === 3" class="reject-reason-text">
              <el-icon><WarningFilled /></el-icon> 被驳回: {{ bill.reject_reason || '凭证不符合要求' }}
            </div>
          </div>
          <div class="qb-action">
            <el-tag v-if="Number(bill.is_paid) === 2" type="warning" size="small" effect="plain">核销中</el-tag>
            <el-button v-else-if="Number(bill.is_paid) === 3" type="danger" plain size="small" @click="openPayDialog(bill)">重新提交</el-button>
            <el-button v-else type="danger" size="small" @click="openPayDialog(bill)">去支付</el-button>
          </div>
        </div>
      </div>

      <el-empty v-if="!overviewLoading && (!overview.contracts || overview.contracts.length === 0)" description="当前暂无生效中的租赁契约" :image-size="80" class="responsibility-card" />
    </div>

    <div v-show="activeTab === 'bills'" class="h5-content floating-content" v-loading="billsLoading">
      <div class="responsibility-card title-card">
        <span class="resp-title" style="margin:0;"><el-icon><Wallet /></el-icon> 财务账单中心</span>
        <el-tag size="small" type="primary" effect="light">{{ bills.length }} 笔出账</el-tag>
      </div>

      <el-empty v-if="bills.length === 0" description="当前没有任何账单" :image-size="80" />
      
      <div v-for="bill in bills" :key="bill.id" class="bill-card">
        <div class="bill-header">
          <el-tag size="small" :type="getBillTypeColor(bill.bill_type)" effect="dark">
            {{ getBillTypeLabel(bill.bill_type) }}
          </el-tag>
          <span class="amount">¥ {{ bill.amount }}</span>
        </div>
        <div class="bill-body">
          <div class="b-line">系统出账时间：{{ bill.created_at }}</div>
          <div class="b-line text-danger" style="font-weight: bold;">最晚缴费期限：{{ bill.due_date }}</div>
          <div v-if="Number(bill.is_paid) === 3" class="reject-card">
            <div class="reject-title"><el-icon><CircleCloseFilled /></el-icon> 财务核销失败</div>
            <div class="reject-msg">原因：{{ bill.reject_reason || '系统未标注驳回原因，请联系园区物业。' }}</div>
          </div>
        </div>
        <div class="bill-footer">
          <el-tag v-if="Number(bill.is_paid) === 1" type="success" size="default">已结清</el-tag>
          <el-tag v-else-if="Number(bill.is_paid) === 2" type="warning" size="default" effect="plain">
            <el-icon><Timer /></el-icon> 凭证已传，等待核销
          </el-tag>
          <el-button v-else-if="Number(bill.is_paid) === 3" type="danger" plain size="default" @click="openPayDialog(bill)" class="full-btn">重新上传打款凭证</el-button>
          <el-button v-else type="primary" size="default" @click="openPayDialog(bill)" class="full-btn">上传打款回单</el-button>
        </div>
      </div>
    </div>

    <div v-show="activeTab === 'service'" class="h5-content floating-content">
      <div class="responsibility-card" style="padding-bottom: 10px;">
        <div class="segmented-control">
          <div :class="['seg-item', { active: serviceSubTab === 'repair' }]" @click="serviceSubTab = 'repair'">🛠️ 物业维保报修</div>
          <div :class="['seg-item', { active: serviceSubTab === 'meeting' }]" @click="serviceSubTab = 'meeting'">📅 共享会议预订</div>
        </div>
      </div>

      <div v-if="serviceSubTab === 'repair'" class="responsibility-card">
        <el-form ref="repairFormRef" :model="repairForm" :rules="repairRules" label-position="top">
          <el-form-item label="故障简述 (必填)" prop="title">
            <el-input v-model="repairForm.title" placeholder="例如：空调不制冷、网络端口断网" size="large" />
          </el-form-item>
          <el-form-item label="情况详述" prop="description">
            <el-input v-model="repairForm.description" type="textarea" :rows="3" placeholder="请详述具体方位与故障表现..." />
          </el-form-item>
          <el-form-item label="故障现场照片 (推荐)">
            <el-upload class="cert-uploader" action="http://47.120.52.65:8787/api/upload" :headers="uploadHeaders" :show-file-list="false" :on-success="handleRepairUpload" :before-upload="beforeUpload">
              <img v-if="repairForm.image_url" :src="getFullImgUrl(repairForm.image_url)" class="preview-img" />
              <div v-else class="upload-trigger"><el-icon class="plus-icon"><Camera /></el-icon><div>点击拍照或选择照片</div></div>
            </el-upload>
          </el-form-item>
          <el-button type="primary" size="large" class="full-btn" style="margin-top: 10px;" :loading="repairLoading" @click="submitRepair">下发至调度室</el-button>
        </el-form>
      </div>

      <div v-if="serviceSubTab === 'meeting'">
        <el-button type="primary" size="large" class="full-btn" @click="openMeetingDialog" style="margin-bottom: 15px; box-shadow: 0 4px 10px rgba(64,158,255,0.3);">发起新的会议室预订</el-button>
        
        <div class="responsibility-card title-card" style="padding: 12px 15px !important; margin-bottom: 10px;">
          <span class="resp-title" style="margin:0;"><el-icon><Calendar /></el-icon> 我的历史预订记录</span>
          <el-tag size="small" type="info" effect="plain">{{ meetingList.length }} 条追踪</el-tag>
        </div>

        <el-empty v-if="meetingList.length === 0" description="暂无预订记录" :image-size="60" class="responsibility-card" />
        
        <div v-for="mtg in meetingList" :key="mtg.id" class="bill-card" style="padding: 15px;">
          <div class="bill-header" style="margin-bottom: 8px; padding-bottom: 8px;">
            <span class="amount" style="font-size: 16px; color: #409eff;">{{ mtg.room_name }}</span>
            <el-tag size="small" :type="getMeetingStatusTag(mtg.status)" effect="dark">{{ getMeetingStatusText(mtg.status) }}</el-tag>
          </div>
          <div class="bill-body" style="margin-bottom: 0;">
            <div class="b-line" style="margin-bottom: 6px; font-weight: bold;">
              <el-icon><Calendar /></el-icon> {{ mtg.date }} ( {{ mtg.start_time.substring(0,5) }} - {{ mtg.end_time.substring(0,5) }} )
            </div>
            <div class="b-line" style="margin-bottom: 6px; color: #606266; font-size: 13px;">会议主题：{{ mtg.topic }}</div>
            <div class="b-line" style="display: flex; justify-content: space-between; margin-top: 8px; border-top: 1px dashed #ebeef5; padding-top: 8px;">
              <span style="color: #909399; font-size: 12px;">系统核算费用</span>
              <span class="text-danger font-bold">¥{{ mtg.cost }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-show="activeTab === 'inventory'" class="h5-content floating-content">
      <div class="responsibility-card title-card">
        <span class="resp-title" style="margin:0;"><el-icon><Box /></el-icon> 企业物资领用/借阅台账</span>
        <el-tag size="small" type="primary" effect="light">{{ inventoryList.length }} 笔记录</el-tag>
      </div>

      <el-empty v-if="inventoryList.length === 0" description="暂无园区后勤物资领用或外借记录" :image-size="80" class="responsibility-card" />
      
      <div v-for="inv in inventoryList" :key="inv.id" class="bill-card" style="padding: 15px;">
        <div class="bill-header" style="margin-bottom: 8px; padding-bottom: 8px;">
          <span class="amount" style="font-size: 16px;">{{ inv.item_name }}</span>
          <el-tag size="small" :type="inv.action_type === 3 ? 'warning' : (inv.action_type === 2 ? 'danger' : 'success')" effect="dark">
            {{ inv.action_type === 3 ? '外借中' : (inv.action_type === 2 ? '已消耗' : '已归还') }}
          </el-tag>
        </div>
        <div class="bill-body" style="margin-bottom: 0;">
          <div class="b-line" style="margin-bottom: 6px;">
            <strong>流转数量：</strong><span style="font-weight: bold; color: #f56c6c; font-family: monospace; font-size: 15px; margin: 0 4px;">{{ inv.quantity }}</span>{{ inv.unit }}
          </div>
          <div class="b-line" style="margin-bottom: 6px; color: #909399; font-size: 12px;">办理时间：{{ inv.created_at }}</div>
          <div v-if="inv.action_type === 3 && inv.expected_return_date" class="b-line text-danger" style="margin-bottom: 6px;">
            <strong style="color: #e6a23c;">协议应还日期：</strong><span style="color: #e6a23c;">{{ inv.expected_return_date }}</span>
          </div>
          <div v-if="inv.remark" class="b-line" style="background-color: #f8f9fa; padding: 8px; border-radius: 4px; font-size: 12px; color: #606266; margin-top: 8px;">
            登记备注：{{ inv.remark }}
          </div>
        </div>
      </div>
    </div>

    <div v-show="activeTab === 'profile'" class="h5-content profile-wrapper">
      <div class="profile-header">
        <div class="avatar"><el-icon><OfficeBuilding /></el-icon></div>
        <div class="info">
          <div class="position" style="margin-bottom: 5px;"><el-tag size="small" effect="dark" type="success">入驻企业</el-tag></div>
          <div class="name" style="word-break: break-all; font-size: 16px;">{{ enterpriseName || '数据加载中...' }}</div>
        </div>
      </div>
      <div class="profile-menu">
        <div class="menu-item" @click="pwdDialogVisible = true"><el-icon><Lock /></el-icon> <span>安全设置 (修改密码)</span> <el-icon class="arrow"><ArrowRight /></el-icon></div>
        <div class="menu-item text-danger" @click="logout(false)"><el-icon><SwitchButton /></el-icon> <span>安全退出移动门户</span></div>
      </div>
    </div>

    <div class="bottom-tabbar">
      <div :class="['tab-item', { active: activeTab === 'home' }]" @click="activeTab = 'home'"><el-icon><House /></el-icon><span>资产</span></div>
      <div :class="['tab-item', { active: activeTab === 'bills' }]" @click="activeTab = 'bills'"><el-icon><Wallet /></el-icon><span>账单</span></div>
      <div :class="['tab-item', { active: activeTab === 'service' }]" @click="activeTab = 'service'"><el-icon><Service /></el-icon><span>服务</span></div>
      <div :class="['tab-item', { active: activeTab === 'inventory' }]" @click="activeTab = 'inventory'"><el-icon><Box /></el-icon><span>物资</span></div>
      <div :class="['tab-item', { active: activeTab === 'profile' }]" @click="activeTab = 'profile'"><el-icon><User /></el-icon><span>我的</span></div>
    </div>

    <el-drawer v-model="msgDrawerVisible" title="消息与预警中心" direction="btt" size="85%" :with-header="false" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
      <div class="drawer-header">
        <span style="font-size: 16px; font-weight: bold; color: #303133;">实时消息列表</span>
        <el-icon size="20" @click="msgDrawerVisible = false"><Close /></el-icon>
      </div>
      <div class="msg-list" v-loading="msgLoading">
        <el-empty v-if="msgList.length === 0" description="暂无服务通知" :image-size="60" />
        <div v-for="msg in msgList" :key="msg.id" :class="['msg-card', { unread: Number(msg.is_read) === 0 }]" @click="readMsg(msg)">
          <div class="msg-header">
            <span class="msg-title"><span v-if="Number(msg.is_read) === 0" class="red-dot"></span>{{ msg.title }}</span>
            <span class="msg-time">{{ msg.created_at }}</span>
          </div>
          <div class="msg-content">{{ msg.content }}</div>
        </div>
      </div>
    </el-drawer>

    <el-dialog v-model="payDialogVisible" title="提交财务打款凭证" width="90%" center top="10vh" append-to-body>
      <div class="upload-sandbox">
        <div class="pay-target">待核销金额: <span class="text-danger">¥ {{ currentBill.amount }}</span></div>
        <div v-if="Number(currentBill.is_paid) === 3" class="reject-alert"><el-icon><Warning /></el-icon> 上次凭证被驳回，请重新上传。</div>
        <p v-else class="tips">请将款项汇入园区指定账户，并将回单/截图上传至下方。</p>
        <el-upload class="cert-uploader" action="http://47.120.52.65:8787/api/upload" :headers="uploadHeaders" :show-file-list="false" :on-success="handleUploadSuccess" :before-upload="beforeUpload">
          <img v-if="uploadUrl" :src="getFullImgUrl(uploadUrl)" class="preview-img" />
          <div v-else class="upload-trigger"><el-icon class="plus-icon"><Plus /></el-icon><div>调起手机相册重新拍照</div></div>
        </el-upload>
      </div>
      <template #footer>
        <div style="display: flex; gap: 10px;">
          <el-button @click="payDialogVisible = false; uploadUrl = ''" style="flex: 1;">取消</el-button>
          <el-button type="success" :disabled="!uploadUrl" :loading="submitLoading" @click="submitPayment" style="flex: 2;">提交核销</el-button>
        </div>
      </template>
    </el-dialog>

    <el-dialog v-model="pwdDialogVisible" title="修改登录安全密码" width="90%" center top="15vh" append-to-body @close="pwdFormRef?.resetFields()">
      <el-form ref="pwdFormRef" :model="pwdForm" :rules="pwdRules" label-position="top">
        <el-form-item label="当前密码" prop="old_password"><el-input v-model="pwdForm.old_password" type="password" show-password size="large" /></el-form-item>
        <el-form-item label="新安全密码" prop="new_password"><el-input v-model="pwdForm.new_password" type="password" show-password size="large" /></el-form-item>
      </el-form>
      <template #footer>
        <div style="display: flex; gap: 10px;">
          <el-button @click="pwdDialogVisible = false" size="large" style="flex: 1;">取消</el-button>
          <el-button type="primary" size="large" :loading="pwdLoading" @click="submitPwd" style="flex: 1;">保存</el-button>
        </div>
      </template>
    </el-dialog>

    <el-dialog v-model="decoDialogVisible" title="提交装修进场申请" width="90%" center top="10vh" append-to-body>
      <el-form ref="decoFormRef" :model="decoForm" label-position="top">
        <el-form-item label="锁定的施工房源">
          <el-input v-model="decoForm.space_name" disabled size="large" />
        </el-form-item>
        <el-form-item label="计划施工周期 (起 - 止)">
          <el-date-picker v-model="decoForm.dateRange" type="daterange" range-separator="至" start-placeholder="进场" end-placeholder="完工" value-format="YYYY-MM-DD" size="large" style="width: 100%" />
        </el-form-item>
        <el-form-item label="施工负责人">
          <el-input v-model="decoForm.manager" placeholder="如：张工 13800138000" size="large" />
        </el-form-item>
      </el-form>
      <template #footer>
        <div style="display: flex; gap: 10px;">
          <el-button @click="decoDialogVisible = false" size="large" style="flex: 1;">取消</el-button>
          <el-button type="primary" size="large" :loading="decoLoading" @click="submitDeco" style="flex: 2;">提交中控室审核</el-button>
        </div>
      </template>
    </el-dialog>

    <el-dialog v-model="meetingDialogVisible" title="共享会议室预订" width="90%" center top="5vh" append-to-body>
      <el-form :model="meetingForm" label-position="top">
        <el-form-item label="选择共享会议室 (支持阶梯计费)">
          <el-select v-model="meetingForm.room_id" placeholder="查阅空间配置与计费规则" size="large" style="width: 100%">
            <el-option v-for="rm in meetingRooms" :key="rm.id" :label="`${rm.name} (容纳${rm.capacity}人)`" :value="rm.id" style="height: auto; padding: 8px 15px;">
              <div style="display: flex; flex-direction: column;">
                <span style="font-weight: bold; color: #303133; line-height: 1.4;">{{ rm.name }} (容纳{{ rm.capacity }}人)</span>
                <span style="color: #8492a6; font-size: 12px; line-height: 1.4;">
                  <span v-if="rm.free_hours > 0" class="text-success" style="font-weight:bold;">前 {{ rm.free_hours }} 小时免费，</span>
                  超出部分 ¥{{ rm.price_per_hour }}/小时
                </span>
              </div>
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="使用日期">
          <el-date-picker v-model="meetingForm.date" type="date" value-format="YYYY-MM-DD" placeholder="指定日期" size="large" style="width: 100%" />
        </el-form-item>
        <div style="display: flex; gap: 10px;">
          <el-form-item label="开始时间" style="flex: 1">
            <el-time-select v-model="meetingForm.start_time" start="08:00" step="00:30" end="22:00" placeholder="起始" size="large" style="width: 100%" />
          </el-form-item>
          <el-form-item label="结束时间" style="flex: 1">
            <el-time-select v-model="meetingForm.end_time" start="08:30" step="00:30" end="22:30" placeholder="结束" size="large" style="width: 100%" />
          </el-form-item>
        </div>
        <el-form-item label="会议主题与事由">
          <el-input v-model="meetingForm.topic" placeholder="简要说明使用用途" size="large" />
        </el-form-item>
        
        <div class="cost-preview-box">
          <div class="cp-title">系统实时账单预估 (已自动抵扣免费时长)</div>
          <div class="cp-amount">¥ {{ previewMeetingCost }}</div>
        </div>
      </el-form>
      <template #footer>
        <div style="display: flex; gap: 10px;">
          <el-button @click="meetingDialogVisible = false" size="large" style="flex: 1;">取消</el-button>
          <el-button type="primary" size="large" :loading="meetingLoading" @click="submitMeeting" style="flex: 2;">确认档期并预订</el-button>
        </div>
      </template>
    </el-dialog>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { House, Wallet, Tools, User, Timer, Camera, Plus, OfficeBuilding, Lock, SwitchButton, ArrowRight, Bell, WarningFilled, Warning, CircleCloseFilled, BellFilled, Close, Box, SetUp, Service, Calendar } from '@element-plus/icons-vue'
import request from '../../../utils/request'

const router = useRouter()
const activeTab = ref('home')
const serviceSubTab = ref('repair') 
const enterpriseId = ref(0)
const enterpriseName = ref('')

const overview = ref({ contracts: [] }) 
const overviewLoading = ref(false)
const bills = ref([])
const billsLoading = ref(false)
const inventoryList = ref([])
const decoList = ref([]) 

const meetingRooms = ref([])
const meetingList = ref([])
const meetingDialogVisible = ref(false)
const meetingLoading = ref(false)
const meetingForm = reactive({ room_id: null, date: '', start_time: '', end_time: '', topic: '' })

const totalMonthlyRent = computed(() => {
  if (!overview.value.contracts || overview.value.contracts.length === 0) return 0
  return overview.value.contracts.reduce((sum, contract) => sum + Number(contract.monthly_rent || 0), 0).toFixed(2)
})

const totalDeposit = computed(() => {
  if (!overview.value.contracts || overview.value.contracts.length === 0) return 0
  return overview.value.contracts.reduce((sum, contract) => sum + Number(contract.deposit || 0), 0).toFixed(2)
})

const unpaidBills = computed(() => bills.value.filter(bill => Number(bill.is_paid) === 0 || Number(bill.is_paid) === 3))
const pendingReturns = computed(() => inventoryList.value.filter(inv => inv.action_type === 3))

const msgDrawerVisible = ref(false)
const msgLoading = ref(false)
const msgList = ref([])
const unreadCount = computed(() => msgList.value.filter(m => Number(m.is_read) === 0).length)

let socket = null
let reconnectTimer = null

const initSocket = () => {
  if (!enterpriseId.value) return
  if (socket) socket.close()
  socket = new WebSocket('ws://47.120.52.65:8788')
  socket.onopen = () => {
    socket.send(JSON.stringify({ type: 'bind', enterprise_id: enterpriseId.value, token: localStorage.getItem('h5_tenant_token') }))
  }
  socket.onmessage = (event) => {
    try {
      const data = JSON.parse(event.data)
      if (data.type === 'notification' || data.type === 'reject') {
        fetchMessages() 
        if (data.type === 'notification' && data.msg && data.msg.includes('物资')) fetchInventory()
        if (data.type === 'reject') {
          ElMessage.warning(data.msg || '有一笔账单凭证被驳回')
          fetchBills() 
        } else { ElMessage.info(data.msg || '您有新的服务通知') }
      }
    } catch (e) {}
  }
  socket.onclose = () => {
    if (reconnectTimer) clearTimeout(reconnectTimer)
    reconnectTimer = setTimeout(() => { initSocket() }, 5000)
  }
  socket.onerror = () => {}
}

const fetchMessages = async () => {
  if (!enterpriseId.value) return
  try {
    const res = await request.get('/api/notification/list')
    if (res.code === 200) msgList.value = res.data || []
  } catch (e) {}
}

const openMsgDrawer = () => {
  msgDrawerVisible.value = true
  fetchMessages()
}

const readMsg = async (msg) => {
  if (Number(msg.is_read) === 1) return
  msg.is_read = 1
  try { await request.post('/api/notification/read', { id: msg.id }) } catch (e) { msg.is_read = 0 }
}

const payDialogVisible = ref(false)
const submitLoading = ref(false)
const currentBill = ref({})
const uploadUrl = ref('')

const repairFormRef = ref(null)
const repairLoading = ref(false)
const repairForm = reactive({ title: '', description: '', image_url: '' })
const repairRules = { title: [{ required: true, message: '必填', trigger: 'blur' }] }

const pwdDialogVisible = ref(false)
const pwdFormRef = ref(null)
const pwdLoading = ref(false)
const pwdForm = reactive({ old_password: '', new_password: '' })
const pwdRules = {
  old_password: [{ required: true, message: '不可为空', trigger: 'blur' }],
  new_password: [{ required: true, message: '不可为空', trigger: 'blur' }]
}

const decoDialogVisible = ref(false)
const decoLoading = ref(false)
const decoForm = reactive({ space_id: null, space_name: '', dateRange: [], manager: '' })

const getDecoStatusText = (status) => ({ 0: '待审批', 1: '施工中', 2: '延期审核', 3: '已竣工', 4: '已驳回' }[status])
const getDecoStatusTag = (status) => ({ 0: 'primary', 1: 'warning', 2: 'danger', 3: 'success', 4: 'info' }[status])

const openDecoDialog = (contract) => {
  decoForm.space_id = contract.space_id
  decoForm.space_name = `${contract.building_name} - ${contract.room_number}`
  decoForm.dateRange = []
  decoForm.manager = ''
  decoDialogVisible.value = true
}

const fetchDecorations = async () => {
  if (!enterpriseId.value) return
  try {
    const res = await request.get('/api/tenant/decorations')
    if (res.code === 200) decoList.value = res.data
  } catch (e) {}
}

const submitDeco = async () => {
  if (!decoForm.dateRange || decoForm.dateRange.length < 2) {
    return ElMessage.error('请选择完整的施工起止时间')
  }
  decoLoading.value = true
  try {
    const res = await request.post('/api/tenant/decoration/apply', {
      space_id: decoForm.space_id,
      start_date: decoForm.dateRange[0],
      end_date: decoForm.dateRange[1],
      manager: decoForm.manager
    })
    if (res.code === 200) {
      ElMessage.success(res.msg)
      decoDialogVisible.value = false
      fetchDecorations() 
    } else {
      ElMessage.error(res.msg)
    }
  } finally {
    decoLoading.value = false
  }
}

// ---------------- 会议室引擎 ----------------
const getMeetingStatusText = (status) => ({ 0: '待审核', 1: '已通过', 2: '已驳回', 3: '已取消' }[status])
const getMeetingStatusTag = (status) => ({ 0: 'primary', 1: 'success', 2: 'danger', 3: 'info' }[status])

const fetchMeetingRooms = async () => {
  try {
    const res = await request.get('/api/tenant/meeting/rooms')
    if (res.code === 200) meetingRooms.value = res.data
  } catch (e) {}
}

const fetchMeetingList = async () => {
  if (!enterpriseId.value) return
  try {
    const res = await request.get('/api/tenant/meeting/list')
    if (res.code === 200) meetingList.value = res.data
  } catch (e) {}
}

const openMeetingDialog = () => {
  Object.assign(meetingForm, { room_id: null, date: '', start_time: '', end_time: '', topic: '' })
  meetingDialogVisible.value = true
}

// 核心改动：前端同步抵扣免费时段
const previewMeetingCost = computed(() => {
  if (!meetingForm.room_id || !meetingForm.start_time || !meetingForm.end_time) return '0.00'
  const room = meetingRooms.value.find(r => r.id === meetingForm.room_id)
  if (!room) return '0.00'
  
  const start = new Date(`2000-01-01T${meetingForm.start_time}:00`)
  const end = new Date(`2000-01-01T${meetingForm.end_time}:00`)
  
  const hours = (end - start) / 3600000
  if (hours <= 0) return '0.00'
  
  // 提取配置的免费额度
  const freeHours = room.free_hours ? parseFloat(room.free_hours) : 0
  const billableHours = Math.max(0, hours - freeHours)
  
  return (billableHours * parseFloat(room.price_per_hour)).toFixed(2)
})

const submitMeeting = async () => {
  if (!meetingForm.room_id || !meetingForm.date || !meetingForm.start_time || !meetingForm.end_time) {
    return ElMessage.error('请将预订时间填写完整')
  }
  meetingLoading.value = true
  try {
    const res = await request.post('/api/tenant/meeting/apply', meetingForm)
    if (res.code === 200) {
      ElMessage.success(res.msg)
      meetingDialogVisible.value = false
      fetchMeetingList()
    } else {
      ElMessage.error(res.msg)
    }
  } finally {
    meetingLoading.value = false
  }
}

// ---------------- 底层支撑 ----------------
const uploadHeaders = computed(() => ({ 'Authorization': `Bearer ${localStorage.getItem('h5_tenant_token')}` }))

const initUserInfo = () => {
  const infoStr = localStorage.getItem('tenant_info')
  if (!infoStr) { router.replace('/h5/tenant/login'); return }
  const info = JSON.parse(infoStr)
  enterpriseId.value = info.enterprise_id || info.id || 0
  enterpriseName.value = info.enterprise_name || info.name || info.tenant_name || ''
}

const fetchInventory = async () => {
  if (!enterpriseId.value) return
  try {
    const res = await request.get('/api/tenant/inventory')
    if (res.code === 200) inventoryList.value = res.data
  } catch (e) {}
}

const fetchOverview = async () => {
  overviewLoading.value = true
  try {
    const res = await request.get('/api/tenant/overview')
    if (res.code === 200) {
      overview.value = res.data
      if (res.data.enterprise && res.data.enterprise.id) {
        enterpriseName.value = res.data.enterprise.name
        enterpriseId.value = res.data.enterprise.id
        fetchMessages() 
        initSocket()
        fetchInventory()
        fetchDecorations()
        fetchMeetingRooms()
        fetchMeetingList()
        
        const infoStr = localStorage.getItem('tenant_info')
        if (infoStr) {
          try {
            const info = JSON.parse(infoStr)
            info.enterprise_name = res.data.enterprise.name
            info.enterprise_id = res.data.enterprise.id
            localStorage.setItem('tenant_info', JSON.stringify(info))
          } catch (e) {}
        }
      }
    }
  } finally { overviewLoading.value = false }
}

const fetchBills = async () => {
  billsLoading.value = true
  try {
    const res = await request.get('/api/tenant/bills')
    if (res.code === 200) bills.value = res.data
  } finally { billsLoading.value = false }
}

const openPayDialog = (bill) => { currentBill.value = bill; uploadUrl.value = ''; payDialogVisible.value = true }
const getFullImgUrl = (url) => url.startsWith('http') ? url : `http://47.120.52.65:8787${url}`
const beforeUpload = (file) => file.size / 1024 / 1024 < 10
const handleUploadSuccess = (res) => {
  if (res.code === 200) { uploadUrl.value = res.data.url; ElMessage.success('成功') }
  else { ElMessage.error('异常') }
}
const handleRepairUpload = (res) => {
  if (res.code === 200) { repairForm.image_url = res.data.url; ElMessage.success('成功') }
  else { ElMessage.error('失败') }
}

const submitPayment = async () => {
  submitLoading.value = true
  try {
    const res = await request.post('/api/tenant/pay', { bill_id: currentBill.value.id, receipt_url: uploadUrl.value })
    if (res.code === 200) { ElMessage.success(res.msg); payDialogVisible.value = false; fetchBills() } 
    else { ElMessage.error(res.msg) }
  } finally { submitLoading.value = false }
}

const submitRepair = () => {
  repairFormRef.value.validate(async (valid) => {
    if (!valid) return
    repairLoading.value = true
    try {
      const res = await request.post('/api/tenant/order/submit', repairForm)
      if (res.code === 200) { ElMessage.success(res.msg); repairFormRef.value.resetFields(); repairForm.image_url = ''; activeTab.value = 'home' } 
      else { ElMessage.error(res.msg) }
    } finally { repairLoading.value = false }
  })
}

const submitPwd = () => {
  pwdFormRef.value.validate(async (valid) => {
    if (!valid) return
    pwdLoading.value = true
    try {
      const res = await request.post('/api/tenant/password/update', pwdForm)
      if (res.code === 200) { ElMessage.success(res.msg); pwdDialogVisible.value = false; logout(true) } 
      else { ElMessage.error(res.msg) }
    } finally { pwdLoading.value = false }
  })
}

const logout = (silent = false) => {
  const doLogout = () => {
    localStorage.removeItem('h5_tenant_token'); localStorage.removeItem('tenant_info')
    router.replace('/h5/tenant/login')
  }
  if (silent === true) { doLogout() } else { if (window.confirm('确认要退出吗？')) { doLogout() } }
}

const getBillTypeLabel = (type) => ({ 1: '场地租金', 2: '水费账单', 3: '电费账单', 4: '物业/车位', 5: '违约滞纳金', 6: '履约押金' }[type] || '其他')
const getBillTypeColor = (type) => ({ 1: 'primary', 2: 'info', 3: 'warning', 4: 'success', 5: 'danger', 6: 'info' }[type] || 'info')

onMounted(() => { initUserInfo(); fetchOverview(); fetchBills() })
onUnmounted(() => { if (socket) socket.close(); if (reconnectTimer) clearTimeout(reconnectTimer) })
</script>

<style scoped>
.mobile-container { width: 100%; max-width: 480px; margin: 0 auto; min-height: 100vh; background-color: #f5f7fa; padding-bottom: 70px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; position: relative;}
.mobile-header { background: linear-gradient(135deg, #2c3e50, #3498db); color: #fff; padding: 25px 20px 50px 20px; display: flex; justify-content: space-between; align-items: flex-start; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
.user-greeting { flex: 1; min-width: 0; }
.header-actions { flex-shrink: 0; margin-left: 15px; padding-top: 5px; }
.msg-bell { cursor: pointer; padding: 5px; }
.label-text { font-size: 13px; color: rgba(255, 255, 255, 0.85); margin-bottom: 6px; }
.enterprise-header { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.enterprise-name { font-size: 20px; font-weight: bold; line-height: 1.4; word-break: break-all; }
.enterprise-tag { border-radius: 12px; border: none; flex-shrink: 0; }
.user-greeting p { margin: 0; font-size: 13px; opacity: 0.9; display: flex; align-items: center; gap: 4px; }
.floating-content { padding: 0 15px; margin-top: -30px; position: relative; z-index: 10; }
.title-card { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px !important; margin-bottom: 15px; }
.responsibility-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
.resp-title { font-size: 14px; font-weight: bold; color: #409eff; margin-bottom: 8px; display: flex; align-items: center; gap: 5px; }
.info-line { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; font-size: 14px; color: #606266; }
.text-code { font-family: monospace; font-weight: bold; color: #303133; }
.stats-panel { display: flex; margin: 15px 0; background: #fff; border-radius: 10px; padding: 15px 0; box-shadow: 0 2px 12px rgba(0,0,0,0.03); }
.stat-box { flex: 1; text-align: center; border-right: 1px solid #f0f0f0; }
.stat-box:last-child { border-right: none; }
.stat-num { font-size: 20px; font-weight: bold; margin-bottom: 5px; font-family: monospace; }
.stat-label { font-size: 12px; color: #909399; }
.text-danger { color: #f56c6c; }
.text-success { color: #67c23a; }
.font-bold { font-weight: bold; font-size: 16px; }
.quick-bill-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed #fbd9d9; }
.quick-bill-item:last-child { border-bottom: none; padding-bottom: 0; }
.qb-info { flex: 1; }
.qb-title { display: flex; align-items: center; margin-bottom: 6px; }
.qb-amount { font-size: 16px; font-weight: bold; color: #f56c6c; font-family: monospace; }
.qb-date { font-size: 12px; color: #909399; }
.reject-reason-text { font-size: 11px; color: #f56c6c; margin-top: 5px; background: #fef0f0; padding: 4px 6px; border-radius: 4px; display: inline-block;}
.qb-action { margin-left: 10px; }
.bill-card { background: #fff; border-radius: 12px; padding: 18px; margin-bottom: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.04); border: 1px solid #f0f2f5; }
.bill-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #ebeef5; }
.amount { font-size: 20px; font-weight: bold; color: #303133; font-family: monospace; }
.bill-body { font-size: 13px; color: #606266; line-height: 1.8; margin-bottom: 15px; }
.bill-footer { text-align: right; }
.b-line { margin-bottom: 4px; }
.reject-card { background-color: #fef0f0; border-left: 3px solid #f56c6c; padding: 10px; margin-top: 10px; border-radius: 0 4px 4px 0; }
.reject-title { font-weight: bold; color: #f56c6c; margin-bottom: 4px; display: flex; align-items: center; gap: 4px; }
.reject-msg { color: #f56c6c; font-size: 12px; }
.full-btn { width: 100%; border-radius: 8px; font-weight: bold; letter-spacing: 1px; }

/* 胶囊导航样式 */
.segmented-control { display: flex; background: #ebeef5; border-radius: 8px; padding: 4px; }
.seg-item { flex: 1; text-align: center; padding: 10px 0; border-radius: 6px; font-size: 14px; color: #606266; transition: all 0.3s; }
.seg-item.active { background: #fff; color: #409eff; font-weight: bold; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }

/* 会议室预估计费面板 */
.cost-preview-box { margin-top: 15px; background: #fdf6ec; border: 1px solid #faecd8; padding: 15px; border-radius: 8px; text-align: center; }
.cp-title { font-size: 13px; color: #e6a23c; margin-bottom: 5px; }
.cp-amount { font-size: 26px; font-family: monospace; font-weight: bold; color: #f56c6c; }

.profile-wrapper { padding: 0 15px; margin-top: -30px; position: relative; z-index: 10; }
.profile-header { background: #fff; border-radius: 10px; padding: 25px 20px; display: flex; align-items: center; gap: 15px; margin-bottom: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
.profile-header .avatar { width: 60px; height: 60px; background: #e6f1fc; color: #409eff; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 30px; }
.profile-header .name { font-weight: bold; color: #303133; margin-bottom: 5px; }
.profile-menu { background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,0.03); border: 1px solid #f0f2f5; }
.menu-item { display: flex; align-items: center; padding: 18px 20px; font-size: 15px; color: #303133; border-bottom: 1px solid #fafafa; cursor: pointer; }
.menu-item:active { background-color: #f5f7fa; }
.menu-item:last-child { border-bottom: none; }
.menu-item .el-icon { margin-right: 10px; font-size: 18px; color: #909399; }
.menu-item .arrow { margin-left: auto; color: #c0c4cc; margin-right: 0; }

.bottom-tabbar { position: fixed; bottom: 0; left: 0; right: 0; max-width: 480px; margin: 0 auto; height: 55px; background: #fff; display: flex; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); z-index: 100; border-top: 1px solid #ebeef5; }
.tab-item { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; color: #909399; font-size: 11px; cursor: pointer; }
.tab-item .el-icon { font-size: 22px; margin-bottom: 3px; }
.tab-item.active { color: #409eff; }
.drawer-header { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f2f5; }
.msg-list { padding: 15px; height: calc(100% - 55px); overflow-y: auto; background-color: #f5f7fa; }
.msg-card { background: #fff; border-radius: 8px; padding: 15px; margin-bottom: 12px; cursor: pointer; border: 1px solid #ebeef5; }
.msg-card.unread { border-left: 3px solid #f56c6c; box-shadow: 0 2px 8px rgba(245, 108, 108, 0.1); }
.msg-header { display: flex; justify-content: space-between; margin-bottom: 8px; align-items: center;}
.msg-title { font-size: 14px; font-weight: bold; color: #303133; position: relative; }
.red-dot { display: inline-block; width: 6px; height: 6px; background-color: #f56c6c; border-radius: 50%; vertical-align: middle; margin-right: 4px; }
.msg-time { font-size: 11px; color: #909399; }
.msg-content { font-size: 13px; color: #606266; line-height: 1.5; }
.upload-sandbox { text-align: center; }
.pay-target { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
.tips { font-size: 12px; color: #909399; margin-bottom: 20px; line-height: 1.5; }
.reject-alert { background: #fef0f0; color: #f56c6c; padding: 8px; border-radius: 6px; font-size: 12px; margin-bottom: 15px; text-align: left; display: flex; align-items: center; gap: 5px; border: 1px solid #fde2e2; }
.cert-uploader { border: 1px dashed #d9d9d9; border-radius: 8px; cursor: pointer; position: relative; overflow: hidden; display: block; width: 100%; height: 200px; background-color: #fafafa; }
.cert-uploader:hover { border-color: #409EFF; }
.upload-trigger { display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100%; color: #8c939d; font-size: 13px; }
.plus-icon { font-size: 30px; margin-bottom: 10px; }
.preview-img { width: 100%; height: 100%; object-fit: contain; }
</style>