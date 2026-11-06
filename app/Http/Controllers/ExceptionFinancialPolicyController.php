<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Orders\Actions\SaveExceptionFinancialPolicy;
use App\Domain\Orders\Enums\ExceptionFinancialMode;
use App\Domain\Orders\Services\ExceptionFinancialPolicy;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Models\Setting;
use App\Http\Requests\SaveExceptionFinancialPolicyRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class ExceptionFinancialPolicyController extends Controller
{
    public function edit(ExceptionFinancialPolicy $reader): View
    {
        Gate::authorize('viewExceptionPolicy', Setting::class);
        $snapshot = $reader->current();
        app(RegistryAudit::class)->record('settings', 'exception_financial_policy_viewed', null, Setting::class);

        return view('settings.exception-financial-policy', ['snapshot' => $snapshot, 'modes' => ExceptionFinancialMode::cases(), 'idempotencyKey' => (string) Str::uuid()]);
    }

    public function update(SaveExceptionFinancialPolicyRequest $request, SaveExceptionFinancialPolicy $action): RedirectResponse
    {
        $action->execute($request->validated('mode'), (int) $request->validated('expected_revision'), $request->validated('idempotency_key'));

        return redirect()->route('settings.exception-financial-policy.edit')->with('status', 'Política financeira registrada. Cobranças existentes foram preservadas.');
    }
}
