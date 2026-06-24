<?php
namespace app\model;

class MeetingRoom extends BaseModel
{
    protected $table = 'meeting_rooms';

    protected $fillable = [
        'tenant_id',
        'name',
        'capacity',
        'free_hours',
        'price_per_hour',
        'has_projector',
        'has_video_conf',
        'status'
    ];
}