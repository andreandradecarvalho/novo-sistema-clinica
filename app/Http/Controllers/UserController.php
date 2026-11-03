<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Actions\ListMembers;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

final class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('users.index', ['users' => app(ListMembers::class)->execute()]);
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);

        return view('users.show', ['subject' => $user->load('unit')]);
    }
}
