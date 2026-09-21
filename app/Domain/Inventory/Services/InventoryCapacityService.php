<?php

namespace App\Domain\Inventory\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\JobRole;
use Illuminate\Validation\ValidationException;

class InventoryCapacityService
{
    /** @return array{capacity_units: int, stock_units: int, incoming_units: int, used_units: int, available_units: int, logistics_employees: int} */
    public function calculate(Company $company, ?int $excludingEmployeeId = null): array
    {
        $employees = $company->employees()
            ->where('status', 'active')
            ->where('department', 'Logística')
            ->when($excludingEmployeeId, fn ($query) => $query->whereKeyNot($excludingEmployeeId))
            ->get();
        $roleCapacity = JobRole::query()
            ->where('department', 'Logística')
            ->pluck('inventory_capacity_units', 'name');
        $capacityUnits = config('game.inventory.base_capacity_units') + $employees->sum(
            fn ($employee) => $roleCapacity[$employee->role] ?? 0,
        );
        $stockUnits = (int) $company->inventoryBalances()->sum('quantity');
        $incomingUnits = (int) $company->purchaseOrders()
            ->where('status', 'ordered')
            ->withSum('items', 'quantity')
            ->get()
            ->sum('items_sum_quantity');
        $usedUnits = $stockUnits + $incomingUnits;

        return [
            'capacity_units' => $capacityUnits,
            'stock_units' => $stockUnits,
            'incoming_units' => $incomingUnits,
            'used_units' => $usedUnits,
            'available_units' => max(0, $capacityUnits - $usedUnits),
            'logistics_employees' => $employees->count(),
        ];
    }

    public function assertCanReserve(Company $company, int $quantity): void
    {
        $capacity = $this->calculate($company);
        if ($quantity > $capacity['available_units']) {
            throw ValidationException::withMessages([
                'items' => "Capacidade insuficiente: {$capacity['available_units']} vaga(s) disponível(is) para {$quantity} unidade(s).",
            ]);
        }
    }

    public function assertWithinCapacity(Company $company): void
    {
        $capacity = $this->calculate($company);
        if ($capacity['used_units'] > $capacity['capacity_units']) {
            throw ValidationException::withMessages([
                'inventory' => 'Estoque e pedidos em trânsito excedem a capacidade logística atual.',
            ]);
        }
    }

    public function assertCanTerminate(Company $company, Employee $employee): void
    {
        if ($employee->status !== 'active' || $employee->department !== 'Logística') {
            return;
        }

        $afterTermination = $this->calculate($company, $employee->id);
        if ($afterTermination['used_units'] > $afterTermination['capacity_units']) {
            throw ValidationException::withMessages([
                'employee' => "Desligamento bloqueado: seriam usadas {$afterTermination['used_units']} de {$afterTermination['capacity_units']} vagas.",
            ]);
        }
    }
}
