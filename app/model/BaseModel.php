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
    use SoftDeletes; 

    // 开启自动维护 created_at 和 updated_at
    public $timestamps = true;

    /**
     * 模型的 "booted" 方法
     * 在这里注册全局作用域 (Global Scopes)
     */
    protected static function booted()
    {
        // 核心防御：自动多租户隔离引擎
        static::addGlobalScope('tenant_isolation', function (Builder $builder) {
            $request = request();
            
            // 场景 1：非 HTTP 环境 (如 CLI 定时任务/队列)，不强制隔离，或根据业务逻辑单独注入
            if (!$request) {
                return;
            }

            // 场景 2：HTTP 请求环境，执行严格隔离检查
            $tenantId = $request->tenantId ?? null;

            // 如果上下文中拿不到明确的租户 ID，直接抛出异常，触发系统阻断，严禁降级默认值
            if (!$tenantId) {
                throw new \Exception('核心底层防御触发：当前上下文丢失合法的租户凭证 (Tenant ID Missing)，已阻断数据库查询。');
            }
            
            // 自动追加查询条件
            $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
        });
    }
}