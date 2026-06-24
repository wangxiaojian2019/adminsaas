<?php
namespace app\model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 核心 ORM 基类
 * 涵盖全局多租户隔离、软删除防雪崩、自动时间戳维护
 */
class BaseModel extends Model
{
    // 启用 Laravel 原生软删除机制 (依赖 deleted_at 字段)
    // 启用后，所有的 Model::find() 或 Model::get() 会自动过滤掉 deleted_at 不为 null 的数据
    use SoftDeletes; 

    // 开启自动维护 created_at 和 updated_at
    public $timestamps = true;

    /**
     * 模型的 "booted" 方法
     * 在这里注册全局作用域 (Global Scopes)
     */
    protected static function booted()
    {
        // 【核心防御】自动多租户隔离引擎
        // 任何继承此类的模型在查询时，都会在 SQL 底层自动带上 WHERE tenant_id = ?
        static::addGlobalScope('tenant_isolation', function (Builder $builder) {
            // 从 Webman 的 request 上下文中安全提取当前租户 ID，默认防底线为 1
            $request = request();
            $tenantId = $request ? ($request->tenantId ?? 1) : 1; 
            
            // 自动追加查询条件，彻底杜绝数据越权穿透
            $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
        });
    }
}