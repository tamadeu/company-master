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
            'population_npc_id' => ['required', 'integer', 'exists:population_npcs,id'],
            'department' => ['required', 'string', Rule::exists('job_roles', 'department')->where('active', true)],
            'role' => [
                'required',
                'string',
                Rule::exists('job_roles', 'name')
                    ->where('department', $this->string('department')->toString())
                    ->where('active', true),
            ],
        ];
    }
}
