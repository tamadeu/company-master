<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'department' => ['required', 'string', Rule::in(config('game.hr.departments'))],
            'role' => ['required', 'string', Rule::in(config('game.hr.roles'))],
            'monthly_salary_cents' => ['required', 'integer', 'min:1', 'max:100000000'],
        ];
    }
}
