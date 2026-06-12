<?php
namespace app\model;

use support\Model;

class IotDevice extends Model
{
    protected $table = 'iot_devices';
    protected $primaryKey = 'id';
    public $timestamps = false;
    
    protected $fillable = [
        'tenant_id',
        'asset_id',
        'device_type',
        'driver_key',
        'mac_address',
        'desired_status',
        'reported_status',
        'is_online'
    ];
}