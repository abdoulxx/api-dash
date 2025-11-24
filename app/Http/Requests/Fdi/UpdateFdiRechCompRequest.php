<?php

namespace App\Http\Requests\Fdi;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFdiRechCompRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fdi_primaire' => ['sometimes', 'string', 'exists:fdi_sg,numero_fdi'],
            'date_fdi_primaire' => ['sometimes', 'nullable', 'date'],
            'fdi_secondaire' => ['sometimes', 'string', 'different:fdi_primaire', 'exists:fdi_sg,numero_fdi'],
            'date_fdi_secondaire' => ['sometimes', 'nullable', 'date'],
            'observation' => ['sometimes', 'nullable', 'string'],
        ];
    }
}



