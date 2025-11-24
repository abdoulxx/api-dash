<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBanqueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ANNEE_DVT' => 'sometimes|integer|min:2000|max:2100',
            'NUM_DVT' => 'sometimes|string|max:128',
            'DATE_DVT' => 'sometimes|date',
            'MONT_AC_XOF' => 'nullable|numeric|min:0',
            'MONT_FACT_XOF' => 'nullable|numeric|min:0',
            'REF_DDU' => 'nullable|string|max:255',
            'CDA_AC' => 'nullable|string|max:255',
        ];
    }
}
