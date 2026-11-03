<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Concerns;

use App\Domain\Tenancy\Services\TenantBuilder;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait BelongsToTenant
{
    /** @return TenantBuilder<static> */
    public function newEloquentBuilder($query): TenantBuilder
    {
        /** @var TenantBuilder<self> $builder */
        $builder = new TenantBuilder($query);

        return $builder;
    }

    public function newQueryForRestoration($ids)
    {
        return $this->newQuery()->whereKey($ids);
    }

    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $builder->where($builder->getModel()->qualifyColumn('tenant_id'), app(TenantContext::class)->id());
        });
        static::creating(function (Model $model): void {
            $id = app(TenantContext::class)->id();
            if ($model->getAttribute('tenant_id') !== null && (int) $model->getAttribute('tenant_id') !== $id) {
                throw new LogicException('Cross-tenant creation is forbidden.');
            }
            $model->setAttribute('tenant_id', $id);
        });
        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id') || (int) $model->getAttribute('tenant_id') !== app(TenantContext::class)->id()) {
                throw new LogicException('Tenant ownership cannot change.');
            }
        });
        static::deleting(function (Model $model): void {
            if ((int) $model->getAttribute('tenant_id') !== app(TenantContext::class)->id()) {
                throw new LogicException('Cross-tenant deletion is forbidden.');
            }
        });
    }
}
