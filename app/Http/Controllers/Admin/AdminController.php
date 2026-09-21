<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Game;
use App\Models\JobRole;
use App\Models\PopulationNpc;
use App\Models\ProductTemplate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function overview(): Response
    {
        return $this->render('overview', [
            'stats' => [
                'users' => User::count(),
                'games' => Game::count(),
                'activeGames' => Game::where('status', 'active')->count(),
                'population' => PopulationNpc::count(),
                'products' => ProductTemplate::where('active', true)->count(),
                'jobRoles' => JobRole::where('active', true)->count(),
            ],
            'recentUsers' => User::query()->latest()->limit(6)->get(['id', 'name', 'email', 'is_admin', 'created_at']),
        ]);
    }

    public function users(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return $this->render('users', [
            'filters' => ['search' => $search],
            'records' => User::query()
                ->withCount('games')
                ->when($search, fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_admin' => ['required', 'boolean'],
        ]);

        User::create($data);

        return back()->with('success', 'Usuário criado.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_admin' => ['required', 'boolean'],
        ]);

        if ($request->user()->is($user) && ! $data['is_admin']) {
            throw ValidationException::withMessages(['is_admin' => 'Você não pode remover seu próprio acesso administrativo.']);
        }

        if (blank($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('success', 'Usuário atualizado.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages(['user' => 'Você não pode excluir sua própria conta.']);
        }

        $user->delete();

        return back()->with('success', 'Usuário e seus dados foram excluídos.');
    }

    public function games(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return $this->render('games', [
            'filters' => ['search' => $search],
            'players' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'records' => Game::query()
                ->with(['user:id,name,email', 'company' => fn ($query) => $query
                    ->select(['id', 'game_id', 'name', 'cash_balance_cents'])
                    ->withCount(['customers', 'employees', 'products'])])
                ->when($search, fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhereHas('user', fn ($query) => $query
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%"))
                    ->orWhereHas('company', fn ($query) => $query->where('name', 'ilike', "%{$search}%"))))
                ->latest()
                ->paginate(20)
                ->through(fn (Game $game) => [
                    'id' => $game->id,
                    'user_id' => $game->user_id,
                    'name' => $game->name,
                    'status' => $game->status,
                    'current_date' => $game->current_date->toDateString(),
                    'created_at' => $game->created_at,
                    'user' => $game->user,
                    'company' => $game->company,
                ])
                ->withQueryString(),
        ]);
    }

    public function updateGame(Request $request, Game $game): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'won', 'bankrupt'])],
            'current_date' => ['required', 'date'],
        ]);

        $game->update([
            'user_id' => $data['user_id'],
            'name' => $data['name'],
            'status' => $data['status'],
            'current_date' => $data['current_date'],
            'ended_at' => $data['status'] === 'active' ? null : ($game->ended_at ?? now()),
        ]);
        $game->company()->update(['name' => $data['company_name']]);

        return back()->with('success', 'Partida atualizada.');
    }

    public function destroyGame(Game $game): RedirectResponse
    {
        $game->delete();

        return back()->with('success', 'Partida e seus dados foram excluídos.');
    }

    public function population(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return $this->render('population', [
            'filters' => ['search' => $search],
            'records' => PopulationNpc::query()
                ->withCount(['customers', 'employees'])
                ->when($search, fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")))
                ->orderBy('name')
                ->paginate(20)
                ->through(fn (PopulationNpc $person) => [
                    'id' => $person->id,
                    'code' => $person->code,
                    'name' => $person->name,
                    'birth_date' => $person->birth_date->toDateString(),
                    'gender' => $person->gender,
                    'city' => $person->city,
                    'state' => $person->state,
                    'email' => $person->email,
                    'customers_count' => $person->customers_count,
                    'employees_count' => $person->employees_count,
                ])
                ->withQueryString(),
        ]);
    }

    public function storePopulation(Request $request): RedirectResponse
    {
        PopulationNpc::create($this->validatePopulation($request));

        return back()->with('success', 'Pessoa adicionada à população.');
    }

    public function updatePopulation(Request $request, PopulationNpc $populationNpc): RedirectResponse
    {
        $populationNpc->update($this->validatePopulation($request, $populationNpc));

        return back()->with('success', 'Pessoa atualizada.');
    }

    public function destroyPopulation(PopulationNpc $populationNpc): RedirectResponse
    {
        if ($populationNpc->customers()->exists() || $populationNpc->employees()->exists()) {
            throw ValidationException::withMessages(['population' => 'Esta pessoa já é cliente ou funcionária e não pode ser excluída.']);
        }

        $populationNpc->delete();

        return back()->with('success', 'Pessoa removida da população.');
    }

    public function products(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return $this->render('products', [
            'filters' => ['search' => $search],
            'records' => ProductTemplate::query()
                ->when($search, fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%")))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        ProductTemplate::create($this->validateProduct($request));

        return back()->with('success', 'Produto adicionado ao catálogo.');
    }

    public function updateProduct(Request $request, ProductTemplate $productTemplate): RedirectResponse
    {
        $productTemplate->update($this->validateProduct($request, $productTemplate));

        return back()->with('success', 'Produto atualizado.');
    }

    public function destroyProduct(ProductTemplate $productTemplate): RedirectResponse
    {
        $productTemplate->delete();

        return back()->with('success', 'Produto removido do catálogo futuro.');
    }

    public function jobRoles(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return $this->render('job-roles', [
            'filters' => ['search' => $search],
            'records' => JobRole::query()
                ->when($search, fn ($query) => $query->where(fn ($query) => $query
                    ->where('department', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%")))
                ->orderBy('department')
                ->orderBy('salary_cents')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function storeJobRole(Request $request): RedirectResponse
    {
        JobRole::create($this->validateJobRole($request));

        return back()->with('success', 'Cargo criado.');
    }

    public function updateJobRole(Request $request, JobRole $jobRole): RedirectResponse
    {
        $jobRole->update($this->validateJobRole($request, $jobRole));

        return back()->with('success', 'Cargo atualizado.');
    }

    public function destroyJobRole(JobRole $jobRole): RedirectResponse
    {
        $inUse = Employee::query()
            ->where('department', $jobRole->department)
            ->where('role', $jobRole->name)
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages(['job_role' => 'Este cargo já foi usado em contratações e não pode ser excluído. Desative-o.']);
        }

        $jobRole->delete();

        return back()->with('success', 'Cargo excluído.');
    }

    private function render(string $section, array $data): Response
    {
        return Inertia::render('Admin/Index', ['section' => $section, ...$data]);
    }

    private function validatePopulation(Request $request, ?PopulationNpc $populationNpc = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('population_npcs')->ignore($populationNpc)],
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
    }

    private function validateProduct(Request $request, ?ProductTemplate $productTemplate = null): array
    {
        return $request->validate([
            'sku' => ['required', 'string', 'max:255', Rule::unique(ProductTemplate::class)->ignore($productTemplate)],
            'name' => ['required', 'string', 'max:255'],
            'reference_price_cents' => ['required', 'integer', 'min:1'],
            'base_daily_demand' => ['required', 'integer', 'min:1'],
            'base_cost_cents' => ['required', 'integer', 'min:1'],
            'active' => ['required', 'boolean'],
        ]);
    }

    private function validateJobRole(Request $request, ?JobRole $jobRole = null): array
    {
        return $request->validate([
            'department' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100', Rule::unique('job_roles')->where('department', $request->string('department'))->ignore($jobRole)],
            'salary_cents' => ['required', 'integer', 'min:1'],
            'sales_capacity_units' => ['required', 'integer', 'min:0'],
            'inventory_capacity_units' => ['required', 'integer', 'min:0'],
            'purchasing_capacity_units' => ['required', 'integer', 'min:0'],
            'active' => ['required', 'boolean'],
        ]);
    }
}
