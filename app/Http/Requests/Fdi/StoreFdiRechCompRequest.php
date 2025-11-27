<?php

namespace App\Http\Requests\Fdi;

use Illuminate\Foundation\Http\FormRequest;

class StoreFdiRechCompRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_fdi_comp' => ['nullable', 'numeric'],
            'fdi_primaire' => ['required', 'string', 'exists:fdi_sg,numero_fdi'],
            'date_fdi_primaire' => ['nullable', 'date'],
            'fdi_secondaire' => ['required', 'string', 'different:fdi_primaire', 'exists:fdi_sg,numero_fdi'],
            'date_fdi_secondaire' => ['nullable', 'date'],
            'observation' => ['nullable', 'string'],
        ];
    }
}




