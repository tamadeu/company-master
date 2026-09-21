<?php

namespace App\Domain\Sales\Services;

use App\Models\Game;
use App\Models\JobRole;

class SalesCapacityService
{
    /** @return array{total_units: int, commercial_employee_count: int, channels: array<int, array{employee_id: ?int, name: string, capacity_units: int}>} */
    public function calculate(Game $game, int $seed): array
    {
        $employees = $game->company->employees()
            ->where('status', 'active')
            ->where('department', 'Comercial')
            ->orderBy('id')
            ->get()
            ->sortBy(fn ($employee) => $this->number(
                $seed,
                "employee-priority:{$game->current_date->toDateString()}:{$employee->id}",
                1,
                PHP_INT_MAX,
            ));
        $roleCapacities = JobRole::query()
            ->where('department', 'Comercial')
            ->pluck('sales_capacity_units', 'name');
        $minimumFactor = config('game.sales.productivity_min_basis_points');
        $maximumFactor = config('game.sales.productivity_max_basis_points');
        $channels = [];

        foreach ($employees as $employee) {
            $baseCapacity = $roleCapacities[$employee->role] ?? 0;
            $productivityFactor = $this->number(
                $seed,
                "employee-productivity:{$game->current_date->toDateString()}:{$employee->id}",
                $minimumFactor,
                $maximumFactor,
            );
            $channels[] = [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'capacity_units' => max(1, intdiv(($baseCapacity * $productivityFactor) + 5_000, 10_000)),
            ];
        }

        $channels[] = [
            'employee_id' => null,
            'name' => 'Gestor',
            'capacity_units' => config('game.sales.owner_base_capacity_units'),
        ];

        return [
            'total_units' => array_sum(array_column($channels, 'capacity_units')),
            'commercial_employee_count' => $employees->count(),
            'channels' => $channels,
        ];
    }

    public function productPriority(int $seed, string $gameDate, string $sku): int
    {
        return $this->number($seed, "product-priority:{$gameDate}:{$sku}", 1, PHP_INT_MAX);
    }

    private function number(int $seed, string $context, int $minimum, int $maximum): int
    {
        $value = hexdec(substr(hash('sha256', "{$seed}:{$context}"), 0, 8));

        return $minimum + ($value % (($maximum - $minimum) + 1));
    }
}
