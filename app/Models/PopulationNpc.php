<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'birth_date', 'gender', 'city', 'state', 'email'])]
class PopulationNpc extends Model
{
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }
}
