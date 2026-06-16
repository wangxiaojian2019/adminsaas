<?php
namespace app\process;

use Workerman\Timer;
use support\Db;
use support\Log;

class BillingTask
{
    public function onWorkerStart()
    {
        // 引擎物理启动时，立即执行一次全盘推演，补齐停机期间可能遗漏的账单
        $this->runBillingEngine();

        // 挂载时间轮：每 60 秒进行一次时钟脉冲嗅探
        Timer::add(60, function() {
            $hour = date('H');
            $minute = date('i');
            
            // 绝对零点触发器
            if ($hour === '00' && $minute === '00') {
                $this->runBillingEngine();
            }
        });
    }

    private function runBillingEngine()
    {
        $today = date('Y-m-d');
        
        // 提取所有处于履约中，且下期出账日小于等于今天的合同
        $contracts = Db::table('contracts')
            ->where('status', 1)
            ->whereNotNull('next_bill_date')
            ->where('next_bill_date', '<=', $today)
            ->get();

        if ($contracts->isEmpty()) {
            return;
        }

        foreach ($contracts as $contract) {
            Db::beginTransaction();
            try {
                // 设定 7 天的财务打款宽限期
                $dueDate = date('Y-m-d', strtotime($today . ' + 7 days')); 
                
                // 1. 独立析出并推算本期租金账单
                if ($contract->monthly_rent > 0) {
                    Db::table('receivables')->insert([
                        'enterprise_id' => $contract->enterprise_id,
                        'space_id' => $contract->space_id,
                        'bill_type' => 1, // 1:租金
                        'amount' => $contract->monthly_rent * $contract->payment_cycle,
                        'due_date' => $dueDate,
                        'is_paid' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }

                // 2. 独立析出并推算本期物业费账单
                if ($contract->property_fee > 0) {
                    Db::table('receivables')->insert([
                        'enterprise_id' => $contract->enterprise_id,
                        'space_id' => $contract->space_id,
                        'bill_type' => 4, // 4:物业费
                        'amount' => $contract->property_fee * $contract->payment_cycle,
                        'due_date' => $dueDate,
                        'is_paid' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }

                // 3. 推进时间轴：计算下一次出账点位
                $nextBillDate = date('Y-m-d', strtotime($contract->next_bill_date . " + {$contract->payment_cycle} months"));
                
                // 物理防呆：如果下期账单日已经超过了合同终止日，则自动抹除锚点，终止账单循环
                if (strtotime($nextBillDate) > strtotime($contract->end_date)) {
                    $nextBillDate = null; 
                }

                Db::table('contracts')->where('id', $contract->id)->update([
                    'next_bill_date' => $nextBillDate
                ]);

                Db::commit();
                Log::info("账单引擎触发成功：底座合同号 {$contract->contract_no} 周期循环账单已入池。");
            } catch (\Exception $e) {
                Db::rollBack();
                Log::error("账单引擎阻断：合同号 {$contract->contract_no} 致命异常 - " . $e->getMessage());
            }
        }
    }
}