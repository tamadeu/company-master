<?php

namespace App\Domain\Hr\Actions;

use App\Domain\Inventory\Services\InventoryCapacityService;
use App\Models\Employee;
use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TerminateEmployee
{
    public function __construct(private readonly InventoryCapacityService $inventoryCapacity) {}

    public function execute(Game $game, Employee $employee): Employee
    {
        return DB::transaction(function () use ($game, $employee) {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);
            $companyId = $lockedGame->company()->value('id');
            $lockedEmployee = Employee::query()->lockForUpdate()->findOrFail($employee->id);

            if ($lockedEmployee->company_id !== $companyId) {
                throw ValidationException::withMessages(['employee' => 'Funcionário inválido para esta partida.']);
            }

            if ($lockedEmployee->status === 'active') {
                $this->inventoryCapacity->assertCanTerminate($lockedEmployee->company, $lockedEmployee);
                $lockedEmployee->update([
                    'status' => 'terminated',
                    'terminated_on' => $lockedGame->current_date,
                ]);
            }

            return $lockedEmployee;
        });
    }
}
