<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdvanceDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app()->environment(['local', 'testing']);
    }

    public function rules(): array
    {
        return [
            'game_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
