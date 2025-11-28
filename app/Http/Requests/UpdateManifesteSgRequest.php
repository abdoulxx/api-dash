<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManifesteSgRequest extends FormRequest
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
        // Récupérer le paramètre de route (peut être 'manifeste' ou 'sg' selon la route)
        $manifesteId = $this->route('manifeste') ?? $this->route('sg') ?? $this->route('id');
        
        // Récupérer l'instance_id du manifeste si disponible
        $instanceId = null;
        if ($manifesteId) {
            // Vérifier le type de l'identifiant pour éviter les erreurs SQL
            if (\Illuminate\Support\Str::isUlid($manifesteId)) {
                $manifeste = \App\Models\ManifesteSg::where('ulid', $manifesteId)->first();
            } elseif (ctype_digit($manifesteId)) {
                $manifeste = \App\Models\ManifesteSg::where('instance_id', (int) $manifesteId)->first();
            } else {
                $manifeste = \App\Models\ManifesteSg::where('num_manifeste', $manifesteId)->first();
            }
            $instanceId = $manifeste?->instance_id;
        }

        return [
            'instance_id' => [
                'nullable', 
                'integer', 
                Rule::unique('manifeste_sg', 'instance_id')->ignore($instanceId, 'instance_id')
            ],
            'code_bureau' => ['nullable', 'string', 'max:20'],
            'libelle_bureau' => ['nullable', 'string', 'max:200'],
            'num_voyage' => ['nullable', 'string', 'max:120'],
            'date_voyage' => ['nullable', 'date'],
            'date_arrivee_navire' => ['nullable', 'date'],
            'annee_manifeste' => ['nullable', 'integer', 'digits:4'],
            'num_man_sydam' => ['nullable', 'integer'],
            'num_manifeste' => [
                'nullable', 
                'string', 
                'max:255',
                Rule::unique('manifeste_sg', 'num_manifeste')->ignore($instanceId, 'instance_id'),
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
            'annee_manifeste.digits' => 'L\'année doit contenir 4 chiffres.',
            'instance_id.unique' => 'Cet identifiant d\'instance est déjà utilisé.',
            'num_manifeste.unique' => 'Ce numéro de manifeste est déjà utilisé.',
        ];
    }
}







