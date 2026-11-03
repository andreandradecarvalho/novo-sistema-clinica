<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Companies\Actions\GrantCompanyAccess;
use App\Domain\Companies\Actions\RevokeCompanyAccess;
use App\Domain\Companies\Enums\CompanyAccessScope;
use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyAccess;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Services\RegistryAudit;
use App\Http\Requests\GrantCompanyAccessRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class CompanyAccessController extends Controller
{
    public function index(Company $company): View
    {
        Gate::authorize('viewAny', [CompanyAccess::class, $company]);
        app(RegistryAudit::class)->record('company_access', 'company_access_listed', $company, Company::class);

        return view('companies.accesses', ['company' => $company, 'accesses' => $company->accesses()->select(['id', 'tenant_id', 'company_id', 'user_id', 'scope', 'valid_from', 'valid_until', 'revoked_at'])->with('user:id,tenant_id,name,email,is_active,deleted_at')->orderByDesc('id')->paginate(20)]);
    }

    public function create(Company $company): View
    {
        Gate::authorize('create', [CompanyAccess::class, $company]);
        app(RegistryAudit::class)->record('company_access', 'company_access_candidates_listed', $company, Company::class);

        return view('companies.access-form', ['company' => $company, 'users' => User::query()->select(['id', 'name', 'email'])->where('is_active', true)->orderBy('name')->paginate(20), 'scopes' => CompanyAccessScope::cases()]);
    }

    public function store(GrantCompanyAccessRequest $request, Company $company, GrantCompanyAccess $action): RedirectResponse
    {
        $action->execute($company, $request->validated());

        return redirect()->route('companies.accesses.index', $company->public_id)->with('status', 'Concessão registrada. Permissão da categoria também será exigida no portal.');
    }

    public function revoke(Company $company, CompanyAccess $access, RevokeCompanyAccess $action): RedirectResponse
    {
        Gate::authorize('revoke', [$access, $company]);
        $action->execute($company, $access);

        return redirect()->route('companies.accesses.index', $company->public_id)->with('status', 'Concessão revogada. Outras concessões permanecem independentes.');
    }
}
