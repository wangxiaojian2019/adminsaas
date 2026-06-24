<?php
namespace app\model;

class Space extends BaseModel
{
    // 指定绑定的数据表
    protected $table = 'spaces';

    // 允许批量赋值的白名单
    protected $fillable = [
        'tenant_id',
        'building_name',
        'floor',
        'room_number',
        'area',
        'water_meter',
        'electric_meter',
        'status',
        'enterprise_name'
    ];
}