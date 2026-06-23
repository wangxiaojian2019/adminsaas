<?php
namespace app\controller;

use support\Request;
use support\Db;

class InventoryController
{
    /**
     * 获取库存大盘及统计数据
     */
    public function stockList(Request $request)
    {
        $keyword = $request->get('keyword');
        $category = $request->get('category');

        $query = Db::table('inventory_items');
        if (!empty($keyword)) $query->where('name', 'like', "%{$keyword}%")->orWhere('sku_code', 'like', "%{$keyword}%");
        if (!empty($category)) $query->where('category', $category);

        $list = $query->orderBy('id', 'desc')->get();

        // 统计面板：总货值与月度流水
        $totalValue = 0;
        $warningCount = 0;
        foreach ($list as $item) {
            $totalValue += ($item->qty * $item->avg_price);
            if ($item->qty <= $item->min_stock) $warningCount++;
        }

        $currentMonth = date('Y-m');
        $monthCost = Db::table('inventory_logs')->where('type', 2)->where('created_at', 'like', "{$currentMonth}%")->sum('total_cost');
        $monthInbound = Db::table('inventory_logs')->where('type', 1)->where('created_at', 'like', "{$currentMonth}%")->sum('total_cost');

        return json([
            'code' => 200, 
            'msg' => 'success', 
            'data' => $list,
            'stats' => [
                'totalValue' => round($totalValue, 2),
                'warningCount' => $warningCount,
                'monthCost' => $monthCost ?: 0,
                'monthInbound' => $monthInbound ?: 0
            ]
        ]);
    }

    /**
     * 采购入库 (核心：移动加权平均成本算法)
     */
    public function inbound(Request $request)
    {
        $skuId = $request->post('sku_id');
        $qty = intval($request->post('qty'));
        $price = floatval($request->post('price')); // 本次采购单价

        if (!$skuId || $qty <= 0 || $price <= 0) {
            return json(['code' => 400, 'msg' => '入库参数错误']);
        }

        Db::beginTransaction();
        try {
            $item = Db::table('inventory_items')->where('id', $skuId)->lockForUpdate()->first();
            if (!$item) throw new \Exception('物料不存在');

            // 核心算法：新加权平均价 = (旧库存*旧均价 + 新数量*新单价) / (旧库存 + 新数量)
            $oldTotal = $item->qty * $item->avg_price;
            $newTotal = $qty * $price;
            $newAvgPrice = ($oldTotal + $newTotal) / ($item->qty + $qty);

            // 更新库存和均价
            Db::table('inventory_items')->where('id', $skuId)->update([
                'qty' => $item->qty + $qty,
                'avg_price' => round($newAvgPrice, 2),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // 写入流水
            Db::table('inventory_logs')->insert([
                'type' => 1,
                'sku_id' => $skuId,
                'qty' => $qty,
                'price' => $price,
                'total_cost' => round($newTotal, 2),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            Db::commit();
            return json(['code' => 200, 'msg' => "入库成功，已将成本均价重置为￥" . round($newAvgPrice, 2)]);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 工单领料出库 (联动工单系统)
     */
    public function outbound(Request $request)
    {
        $skuId = $request->post('sku_id');
        $qty = intval($request->post('qty'));
        $workOrderNo = $request->post('work_order_no');
        $worker = $request->post('worker');

        if (!$skuId || $qty <= 0 || !$workOrderNo) {
            return json(['code' => 400, 'msg' => '出库参数错误或未关联工单']);
        }

        Db::beginTransaction();
        try {
            $item = Db::table('inventory_items')->where('id', $skuId)->lockForUpdate()->first();
            if ($item->qty < $qty) throw new \Exception("可用库存不足，仅剩 {$item->qty}");

            // 按照当前加权均价核算成本
            $totalCost = $qty * $item->avg_price;

            // 扣减库存
            Db::table('inventory_items')->where('id', $skuId)->update([
                'qty' => $item->qty - $qty,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // 写入领料流水
            Db::table('inventory_logs')->insert([
                'type' => 2,
                'sku_id' => $skuId,
                'qty' => $qty,
                'price' => $item->avg_price,
                'total_cost' => round($totalCost, 2),
                'work_order_no' => $workOrderNo,
                'worker' => $worker,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // TODO: 后续可在此处联动工单表(work_orders)，将 $totalCost 写入工单的 material_cost 字段

            Db::commit();
            return json(['code' => 200, 'msg' => "领料成功！扣减成本￥" . round($totalCost, 2) . "，已挂载至工单{$workOrderNo}"]);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }
    
    /**
     * 获取出库流水
     */
    public function outLogs(Request $request)
    {
        $logs = Db::table('inventory_logs')
            ->join('inventory_items', 'inventory_logs.sku_id', '=', 'inventory_items.id')
            ->where('inventory_logs.type', 2)
            ->select('inventory_logs.*', 'inventory_items.name as material_name')
            ->orderBy('inventory_logs.id', 'desc')
            ->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $logs]);
    }
}