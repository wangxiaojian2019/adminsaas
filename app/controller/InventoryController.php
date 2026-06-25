<?php
namespace app\controller;

use support\Request;
use support\Db;

class InventoryController
{
    /**
     * 1. 获取库存大盘及统计数据
     */
    public function stockList(Request $request)
    {
        $keyword = $request->get('keyword');
        $category = $request->get('category');

        $query = Db::table('inventory_items');
        if (!empty($keyword)) $query->where('name', 'like', "%{$keyword}%")->orWhere('sku_code', 'like', "%{$keyword}%");
        if (!empty($category)) $query->where('category', $category);

        $list = $query->orderBy('id', 'desc')->get();

        $totalValue = 0;
        $warningCount = 0;
        foreach ($list as $item) {
            $totalValue += ($item->qty * $item->avg_price);
            if ($item->qty <= $item->min_stock) $warningCount++;
        }

        // 核心修复：统一从 inventory_records (员工和后台共用表) 计算月度出入库成本
        $currentMonth = date('Y-m');
        
        $monthCostRecords = Db::table('inventory_records')
            ->join('inventory_items', 'inventory_records.item_id', '=', 'inventory_items.id')
            ->whereIn('inventory_records.action_type', [2, 3]) // 2领用消耗 3借出
            ->where('inventory_records.created_at', 'like', "{$currentMonth}%")
            ->select('inventory_records.quantity', 'inventory_items.avg_price')
            ->get();
            
        $monthCost = 0;
        foreach ($monthCostRecords as $r) {
            $monthCost += ($r->quantity * $r->avg_price);
        }

        // 入库采用纯量流水表计算，确保准确
        $monthInbound = Db::table('inventory_logs')->where('type', 1)->where('created_at', 'like', "{$currentMonth}%")->sum('total_cost');

        return json([
            'code' => 200, 
            'msg' => 'success', 
            'data' => $list,
            'stats' => [
                'totalValue' => round($totalValue, 2),
                'warningCount' => $warningCount,
                'monthCost' => round($monthCost, 2),
                'monthInbound' => $monthInbound ?: 0
            ]
        ]);
    }

    /**
     * 2. 新增物料 SKU (核心修复：补齐缺失的建档功能)
     */
    public function add(Request $request)
    {
        $sku = $request->post('sku_code');
        $name = $request->post('name');
        $category = $request->post('category', '通用耗材');
        $unit = $request->post('unit', '件');
        $minStock = intval($request->post('min_stock', 10));

        if (!$sku || !$name) {
            return json(['code' => 400, 'msg' => '必须填写物料编码和名称']);
        }

        $exists = Db::table('inventory_items')->where('sku_code', $sku)->exists();
        if ($exists) {
            return json(['code' => 400, 'msg' => '该物料编码已被使用，请更换']);
        }

        Db::table('inventory_items')->insert([
            'sku_code' => $sku,
            'name' => $name,
            'category' => $category,
            'unit' => $unit,
            'min_stock' => $minStock,
            'qty' => 0,
            'avg_price' => 0.00,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return json(['code' => 200, 'msg' => '物料档案创建成功']);
    }

    /**
     * 3. 采购入库 (影响加权平均价)
     */
    public function inbound(Request $request)
    {
        $skuId = $request->post('sku_id');
        $qty = intval($request->post('qty'));
        $price = floatval($request->post('price')); 

        if (!$skuId || $qty <= 0 || $price <= 0) {
            return json(['code' => 400, 'msg' => '入库参数错误']);
        }

        Db::beginTransaction();
        try {
            $item = Db::table('inventory_items')->where('id', $skuId)->lockForUpdate()->first();
            if (!$item) throw new \Exception('物料不存在');

            $oldTotal = $item->qty * $item->avg_price;
            $newTotal = $qty * $price;
            $newAvgPrice = ($oldTotal + $newTotal) / ($item->qty + $qty);

            Db::table('inventory_items')->where('id', $skuId)->update([
                'qty' => $item->qty + $qty,
                'avg_price' => round($newAvgPrice, 2),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // 保留 logs 表用于财务核算
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
     * 4. 工单领料出库
     */
    public function outbound(Request $request)
    {
        $skuId = $request->post('sku_id');
        $qty = intval($request->post('qty'));
        $workOrderNo = $request->post('work_order_no', '');
        $worker = $request->post('worker', '');

        if (!$skuId || $qty <= 0) {
            return json(['code' => 400, 'msg' => '出库参数错误']);
        }

        Db::beginTransaction();
        try {
            $item = Db::table('inventory_items')->where('id', $skuId)->lockForUpdate()->first();
            if ($item->qty < $qty) throw new \Exception("可用库存不足，仅剩 {$item->qty}");

            $totalCost = $qty * $item->avg_price;

            Db::table('inventory_items')->where('id', $skuId)->update([
                'qty' => $item->qty - $qty,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // 核心修复：向 inventory_records 写入，保证与员工端 H5 行为一致
            Db::table('inventory_records')->insert([
                'tenant_id' => 1,
                'item_id' => $skuId,
                'action_type' => 2,
                'quantity' => $qty,
                'related_person' => $worker ?: '后台中控分发',
                'remark' => $workOrderNo ?: '内部调拨耗材',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            Db::commit();
            return json(['code' => 200, 'msg' => "领料成功！扣减成本￥" . round($totalCost, 2) . "，已挂载"]);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }
    
    /**
     * 5. 获取流水 logs (核心修复：统一查询 inventory_records 解决空白问题)
     */
    public function logs(Request $request)
    {
        $records = Db::table('inventory_records')
            ->join('inventory_items', 'inventory_records.item_id', '=', 'inventory_items.id')
            ->whereIn('inventory_records.action_type', [2, 3]) // 提取消耗和借出的记录
            ->select(
                'inventory_records.created_at',
                'inventory_records.remark as work_order_no',
                'inventory_items.name as material_name',
                'inventory_records.quantity as qty',
                'inventory_records.related_person as worker',
                'inventory_items.avg_price'
            )
            ->orderBy('inventory_records.id', 'desc')
            ->get();

        // 动态回溯当时的大概成本
        foreach ($records as $log) {
            $log->total_cost = number_format($log->qty * $log->avg_price, 2, '.', '');
            if (!$log->work_order_no) $log->work_order_no = '无单耗材';
        }

        return json(['code' => 200, 'msg' => 'success', 'data' => $records]);
    }
}