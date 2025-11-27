<?php

namespace App\Http\Requests\Fdi;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFdiArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_fdi' => ['sometimes', 'string', 'max:60', 'exists:fdi_sg,numero_fdi'],
            'numart' => ['sometimes', 'integer'],
            'postar' => ['sometimes', 'nullable', 'string', 'max:48'],
            'nature_marchandise' => ['sometimes', 'nullable', 'string'],
            'description_marchandise' => ['sometimes', 'nullable', 'string'],
            'quantite' => ['sometimes', 'nullable', 'numeric'],
            'poids_net' => ['sometimes', 'nullable', 'numeric'],
            'poids_brut' => ['sometimes', 'nullable', 'numeric'],
            'instance_id' => ['sometimes', 'nullable', 'integer'],
            'serie_fdi' => ['sometimes', 'nullable', 'string', 'max:4'],
            'bureau' => ['sometimes', 'nullable', 'string', 'max:20'],
            'annee' => ['sometimes', 'nullable', 'integer'],
            'numero_serie' => ['sometimes', 'nullable', 'string', 'max:24'],
            'date_fdi' => ['sometimes', 'nullable', 'date'],
        ];
    }
}




