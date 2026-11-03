<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListMembers
{
    public function execute(): LengthAwarePaginator
    {
        return User::query()->select(['id', 'public_id', 'name', 'email', 'is_active'])->orderBy('name')->paginate(20);
    }
}
