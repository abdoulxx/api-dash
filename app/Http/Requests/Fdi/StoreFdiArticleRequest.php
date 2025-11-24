<?php

namespace App\Http\Requests\Fdi;

use Illuminate\Foundation\Http\FormRequest;

class StoreFdiArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_fdi' => ['required', 'string', 'max:60', 'exists:fdi_sg,numero_fdi'],
            'numart' => ['required', 'integer'],
            'postar' => ['nullable', 'string', 'max:48'],
            'nature_marchandise' => ['nullable', 'string'],
            'description_marchandise' => ['nullable', 'string'],
            'quantite' => ['nullable', 'numeric'],
            'poids_net' => ['nullable', 'numeric'],
            'poids_brut' => ['nullable', 'numeric'],
            'instance_id' => ['nullable', 'integer'],
            'serie_fdi' => ['nullable', 'string', 'max:4'],
            'bureau' => ['nullable', 'string', 'max:20'],
            'annee' => ['nullable', 'integer'],
            'numero_serie' => ['nullable', 'string', 'max:24'],
            'date_fdi' => ['nullable', 'date'],
        ];
    }
}



