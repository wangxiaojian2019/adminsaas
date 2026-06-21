<?php
namespace app\controller;

use support\Request;
use support\Db;

class InventoryController
{
    public function list(Request $request)
    {
        $list = Db::table('inventory_items')->orderBy('id', 'desc')->get();
        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function add(Request $request)
    {
        $name = $request->post('name');
        if (!$name) return json(['code' => 400, 'msg' => '物品名称不能为空']);

        $initialStock = intval($request->post('initial_stock', 0));

        Db::beginTransaction();
        try {
            $itemId = Db::table('inventory_items')->insertGetId([
                'name' => $name,
                'category' => $request->post('category', 1),
                'stock' => $initialStock,
                'unit' => $request->post('unit', '个'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            if ($initialStock > 0) {
                Db::table('inventory_records')->insert([
                    'item_id' => $itemId,
                    'action_type' => 1, 
                    'quantity' => $initialStock,
                    'related_person' => '系统初始建账',
                    'expected_return_date' => null,
                    'remark' => '系统期初建账录入',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            Db::commit();
            return json(['code' => 200, 'msg' => '物品建档成功']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '建档异常：' . $e->getMessage()]);
        }
    }

    public function action(Request $request)
    {
        $itemId = $request->post('item_id');
        $actionType = $request->post('action_type'); 
        $quantity = intval($request->post('quantity', 1));
        $relatedType = $request->post('related_type', 1);
        $relatedPerson = $request->post('related_person', '');
        $expectedReturnDate = $request->post('expected_return_date', null);
        $remark = $request->post('remark', '');

        if ($quantity <= 0) return json(['code' => 400, 'msg' => '操作数量必须大于0']);

        Db::beginTransaction();
        try {
            $item = Db::table('inventory_items')->where('id', $itemId)->lockForUpdate()->first();
            if (!$item) {
                return json(['code' => 404, 'msg' => '物品不存在']);
            }

            $newStock = $item->stock;

            if ($actionType == 1 || $actionType == 4) {
                $newStock += $quantity;
            } else if ($actionType == 2 || $actionType == 3) {
                if ($item->stock < $quantity) {
                    Db::rollBack();
                    return json(['code' => 400, 'msg' => "库存不足，当前仅剩 {$item->stock} {$item->unit}"]);
                }
                $newStock -= $quantity;
            } else {
                return json(['code' => 400, 'msg' => '未知的操作指令']);
            }

            Db::table('inventory_items')->where('id', $itemId)->update([
                'stock' => $newStock,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            Db::table('inventory_records')->insert([
                'item_id' => $itemId,
                'action_type' => $actionType,
                'quantity' => $quantity,
                'related_person' => $relatedPerson,
                'expected_return_date' => $actionType == 3 ? $expectedReturnDate : null,
                'remark' => $remark,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // 双端消息分发引擎
            if ($actionType == 2 || $actionType == 3) {
                $actionName = $actionType == 3 ? '出借' : '发放';
                $returnTips = ($actionType == 3 && $expectedReturnDate) ? " 请注意于 {$expectedReturnDate} 前完好交还至库房。" : "";
                
                if ($relatedType == 2) {
                    // 推送给租户企业
                    $entName = str_replace(['[企业] ', '[企业主体] '], '', $relatedPerson);
                    $ent = Db::table('enterprises')->where('name', trim($entName))->first();
                    if ($ent) {
                        Db::table('notifications')->insert([
                            'enterprise_id' => $ent->id,
                            'title' => "仓库物资{$actionName}通知",
                            'content' => "园区后勤向您{$actionName}了 {$quantity} {$item->unit} 【{$item->name}】。{$returnTips}如有疑问请联系物业中心。",
                            'is_read' => 0,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                } else if ($relatedType == 1) {
                    // 推送给内部外勤员工
                    $staffName = str_replace(['[员工] ', '[领用师傅] '], '', $relatedPerson);
                    $staff = Db::table('admins')->where('real_name', trim($staffName))->orWhere('username', trim($staffName))->first();
                    if ($staff) {
                        Db::table('worker_notifications')->insert([
                            'worker_id' => $staff->id,
                            'title' => "物资{$actionName}入账提醒",
                            'content' => "您已从仓库成功登记{$actionName} {$quantity} {$item->unit} 【{$item->name}】。{$returnTips}",
                            'is_read' => 0,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }

            Db::commit();
            return json(['code' => 200, 'msg' => '库存操作成功，台账与消息均已联动']);
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 500, 'msg' => '库存引擎并发流转异常']);
        }
    }

    public function records(Request $request)
    {
        $itemId = $request->get('item_id');
        $records = Db::table('inventory_records')
            ->where('item_id', $itemId)
            ->orderBy('id', 'desc')
            ->get();
            
        return json(['code' => 200, 'msg' => 'success', 'data' => $records]);
    }
}