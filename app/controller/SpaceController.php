<?php
namespace app\controller;

use support\Request;
use support\Db;

class SpaceController
{
    public function list(Request $request)
    {
        $list = Db::table('spaces')
            ->leftJoin('contracts', function($join) {
                $join->on('spaces.id', '=', 'contracts.space_id')
                     ->where('contracts.status', '=', 1);
            })
            ->leftJoin('enterprises', 'contracts.enterprise_id', '=', 'enterprises.id')
            ->select(
                'spaces.*', 
                'enterprises.id as enterprise_id', // 核心补全：输出所属企业外键ID供前端级联过滤
                'enterprises.name as real_enterprise_name', 
                'enterprises.contact_person', 
                'enterprises.phone',
                'enterprises.industry',
                'contracts.contract_no',
                'contracts.start_date',
                'contracts.end_date',
                'contracts.monthly_rent',
                'contracts.property_fee'
            )
            ->orderBy('spaces.building_name')
            ->orderBy('spaces.floor')
            ->orderBy('spaces.room_number')
            ->get();
            
        foreach ($list as $key => $item) {
            if ($item->real_enterprise_name) {
                $list[$key]->enterprise_name = $item->real_enterprise_name;
                if ($item->area > 0 && $item->monthly_rent > 0) {
                    $list[$key]->unit_price = number_format(($item->monthly_rent / $item->area) / 30, 2);
                } else {
                    $list[$key]->unit_price = '0.00';
                }
            }
        }

        return json(['code' => 200, 'msg' => 'success', 'data' => $list]);
    }

    public function add(Request $request)
    {
        Db::table('spaces')->insert([
            'building_name' => $request->post('building_name'),
            'floor' => $request->post('floor'),
            'room_number' => $request->post('room_number'),
            'area' => $request->post('area'),
            'status' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function update(Request $request)
    {
        $id = $request->post('id');

        // 核心重构：绿灯区 - 企业档案的无感快捷更新分支
        if ($request->post('is_enterprise_update') == 1) {
            $contract = Db::table('contracts')->where('space_id', $id)->where('status', 1)->first();
            if ($contract) {
                Db::table('enterprises')->where('id', $contract->enterprise_id)->update([
                    'contact_person' => $request->post('contact_person'),
                    'phone' => $request->post('phone'),
                    'industry' => $request->post('industry')
                ]);
            }
            return json(['code' => 200, 'msg' => '企业联络档案已安全更新']);
        }

        // 常规的物理空间参数更新分支
        $data = [
            'room_number' => $request->post('room_number'),
            'area' => $request->post('area')
        ];
        if ($request->post('status') !== null) {
            $data['status'] = $request->post('status');
        }

        Db::table('spaces')->where('id', $id)->update($data);
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function delete(Request $request)
    {
        Db::table('spaces')->where('id', $request->post('id'))->delete();
        return json(['code' => 200, 'msg' => 'success']);
    }

    public function updateStatus(Request $request)
    {
        $id = $request->post('id');
        $status = $request->post('status');
        Db::table('spaces')->where('id', $id)->update(['status' => $status]);
        return json(['code' => 200, 'msg' => 'success']);
    }
    
    public function tree(Request $request) {
        return json(['code' => 200, 'msg' => 'success', 'data' => []]);
    }
}