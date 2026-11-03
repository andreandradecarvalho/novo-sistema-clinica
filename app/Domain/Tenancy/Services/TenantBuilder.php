<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Services;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
final class TenantBuilder extends Builder
{
    public function update(array $values)
    {
        if (array_key_exists('tenant_id', $values)) {
            throw new LogicException('Tenant ownership cannot change through bulk updates.');
        }

        return parent::update($values);
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new LogicException('Use tenant-scoped model actions instead of bulk upsert.');
    }

    private function owned(array $values): array
    {
        $id = app(TenantContext::class)->id();
        if (isset($values['tenant_id']) && (int) $values['tenant_id'] !== $id) {
            throw new LogicException('Cross-tenant insertion is forbidden.');
        }

        return ['tenant_id' => $id] + $values;
    }

    public function insertGetId(array $values, $sequence = null)
    {
        return $this->getQuery()->insertGetId($this->owned($values), $sequence);
    }

    public function insert(array $values)
    {
        $rows = array_is_list($values) ? array_map($this->owned(...), $values) : $this->owned($values);

        return $this->getQuery()->insert($rows);
    }

    public function __call($method, $parameters)
    {
        if (in_array(strtolower($method), ['insert', 'insertgetid', 'insertorignore', 'insertusing', 'insertorignoreusing', 'upsert'], true)) {
            throw new LogicException('Use tenant-scoped model actions instead of bulk inserts.');
        }

        return parent::__call($method, $parameters);
    }
}
