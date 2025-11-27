<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreManifesteSgRequest extends FormRequest
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
            'instance_id' => ['nullable', 'integer', 'unique:manifeste_sg,instance_id'],
            'code_bureau' => ['required', 'string', 'max:20'],
            'libelle_bureau' => ['nullable', 'string', 'max:200'],
            'num_voyage' => ['required', 'string', 'max:120'],
            'date_voyage' => ['nullable', 'date'],
            'date_arrivee_navire' => ['nullable', 'date'],
            'annee_manifeste' => ['required', 'integer', 'digits:4'],
            'num_man_sydam' => ['nullable', 'integer'],
            'num_manifeste' => [
                'nullable', 
                'string', 
                'max:255', 
                'unique:manifeste_sg,num_manifeste',
                function ($attribute, $value, $fail) {
                    // Si num_manifeste est fourni, il doit correspondre au format attendu
                    if ($value && !preg_match('/^[A-Z0-9]+\s+\d{4}\s+\d+$/', $value)) {
                        $fail('Le numéro de manifeste doit suivre le format : CODE_BUREAU ANNEE NUMERO_SEQUENTIEL (ex: CIAB1 2024 1)');
                    }
                },
            ],
            'date_manifeste' => ['nullable', 'date'],
            'code_port_charg' => ['nullable', 'string', 'max:20'],
            'nom_port_charg' => ['nullable', 'string', 'max:200'],
            'code_port_decharg' => ['nullable', 'string', 'max:20'],
            'nom_port_decharg' => ['nullable', 'string', 'max:200'],
            'code_consignataire' => ['nullable', 'string', 'max:20'],
            'nom_consignataire' => ['nullable', 'string', 'max:200'],
            'adresse_consignataire' => ['nullable', 'string'],
            'nom_moyen_transport' => ['nullable', 'string', 'max:200'],
            'code_transport' => ['nullable', 'string', 'max:10'],
            'nom_transport' => ['nullable', 'string', 'max:100'],
            'code_nationalite_navire' => ['nullable', 'string', 'max:10'],
            'nom_nationalite_navire' => ['nullable', 'string', 'max:100'],
            'nbre_total_bl' => ['nullable', 'integer', 'min:0'],
            'nbre_total_colis' => ['nullable', 'numeric', 'min:0'],
            'nbre_total_conteneur' => ['nullable', 'integer', 'min:0'],
            'total_poids_brut' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code_bureau.required' => 'Le code bureau est obligatoire.',
            'num_voyage.required' => 'Le numéro de voyage est obligatoire.',
            'annee_manifeste.required' => 'L\'année du manifeste est obligatoire.',
            'annee_manifeste.digits' => 'L\'année doit contenir 4 chiffres.',
            'instance_id.unique' => 'Cet identifiant d\'instance est déjà utilisé.',
            'num_manifeste.unique' => 'Ce numéro de manifeste est déjà utilisé.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Si num_manifeste n'est pas fourni mais que les composants le sont, on le générera dans le contrôleur
        // Ici, on s'assure juste que les données sont prêtes
    }
}





