<?php
namespace app\controller;

use support\Request;
use support\Db;

class FeeConfigController
{
    // 获取全局配置
    public function get(Request $request)
    {
        $config = Db::table('system_configs')->where('config_key', 'fee_config')->first();
        $data = $config ? json_decode($config->config_value, true) : [
            'waterPrice' => 5.5, 'electricityPrice' => 1.2, 
            'billMode' => 'fixed', 'lateFeeRate' => 0.1, 'autoCutoff' => true
        ];
        return json(['code' => 200, 'msg' => 'success', 'data' => $data]);
    }

    // 保存并全网生效
    public function save(Request $request)
    {
        $data = $request->post();
        $jsonValue = json_encode($data, JSON_UNESCAPED_UNICODE);

        $exists = Db::table('system_configs')->where('config_key', 'fee_config')->exists();
        if ($exists) {
            Db::table('system_configs')->where('config_key', 'fee_config')->update([
                'config_value' => $jsonValue,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            Db::table('system_configs')->insert([
                'config_key' => 'fee_config',
                'config_value' => $jsonValue,
                'remark' => '计费策略全局配置',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        return json(['code' => 200, 'msg' => '计费引擎策略已更新！全网物业账单将采用新费率。']);
    }
}