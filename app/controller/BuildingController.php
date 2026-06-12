<?php
namespace app\controller;

use support\Request;
use support\Db;

class BuildingController
{
    public function list(Request $request)
    {
        $buildings = Db::table('buildings')->orderBy('id', 'desc')->get();
        
        // 核心修改：聚合统计该大厦下的房间数、计算每层的具体分布
        foreach ($buildings as $building) {
            $spaces = Db::table('spaces')->where('building_name', $building->name)->get();
            
            $building->total_spaces_count = $spaces->count();
            
            // 聚合每层的房间数 (如：{ "1": 4, "2": 5 })
            $floorDistribution = [];
            foreach ($spaces as $space) {
                if (!isset($floorDistribution[$space->floor])) {
                    $floorDistribution[$space->floor] = 0;
                }
                $floorDistribution[$space->floor]++;
            }
            
            // 转换为前端更易读的字符串格式：1层:4间, 2层:5间
            $distStr = [];
            ksort($floorDistribution); // 按楼层排序
            foreach ($floorDistribution as $f => $count) {
                $distStr[] = $f . 'F:' . $count . '间';
            }
            $building->floor_details = empty($distStr) ? '暂无物理房间' : implode(', ', $distStr);
        }

        return json(['code' => 200, 'msg' => 'success', 'data' => $buildings]);
    }

    public function add(Request $request)
    {
        $data = [
            'name' => $request->post('name'),
            'property_type' => $request->post('property_type', 1),
            'total_floors' => $request->post('total_floors', 1),
            'building_area' => $request->post('building_area', 0),
            'manager_name' => $request->post('manager_name', ''),
            'created_at' => date('Y-m-d H:i:s')
        ];
        Db::table('buildings')->insert($data);
        return json(['code' => 200, 'msg' => 'success']);
    }
}