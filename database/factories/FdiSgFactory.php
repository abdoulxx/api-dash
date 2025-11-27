<?php

namespace Database\Factories;

use App\Models\FdiSg;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FdiSg>
 */
class FdiSgFactory extends Factory
{
    protected $model = FdiSg::class;

    public function definition(): array
    {
        return [
            'numero_fdi' => $this->faker->unique()->numerify('FDI-#####'),
            'serie_fdi' => $this->faker->randomElement(['A', 'B', 'C']),
            'bureau' => $this->faker->regexify('[A-Z]{5}'),
            'annee' => (int) date('Y'),
            'numero_serie' => $this->faker->numerify('SER-#####'),
            'date_fdi' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'derniere_operation' => $this->faker->randomElement(['Direct_Validate', 'Pending', 'Rejected']),
            'date_derniere_operation' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'reglement' => $this->faker->sentence(3),
            'banque' => $this->faker->company,
            'ref_domiciliation' => $this->faker->numerify('DOM ####/####'),
            'date_domiciliation' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'montant_domicilie_cfa' => $this->faker->randomFloat(2, 100000, 5000000),
            'cc' => $this->faker->numerify('#########'),
            'importateur' => $this->faker->company,
            'adresse_importateur' => $this->faker->address,
            'telephone_importateur' => $this->faker->phoneNumber,
            'fournisseur' => $this->faker->company,
            'adresse_fournisseur' => $this->faker->address,
            'pays_fournisseur' => $this->faker->country,
            'tel_fournisseur' => $this->faker->phoneNumber,
            'incoterm' => $this->faker->randomElement(['FOB', 'CFR', 'CIF']),
            'libelle_incoterm' => $this->faker->words(3, true),
            'ref_facture' => $this->faker->numerify('INV-######'),
            'date_facture' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'valeur_facture_cfa' => $this->faker->randomFloat(2, 100000, 10000000),
            'valeur_fob_cfa' => $this->faker->randomFloat(2, 50000, 8000000),
            'valeur_caf' => $this->faker->randomFloat(2, 50000, 8000000),
            'valeur_fret_cfa' => $this->faker->randomFloat(2, 10000, 2000000),
            'valeur_assurance_cfa' => $this->faker->randomFloat(2, 5000, 500000),
            'nom_devise' => $this->faker->randomElement(['Euro', 'Dollar', 'Franc CFA']),
            'devise' => $this->faker->randomFloat(2, 1, 1000),
            'declarant' => $this->faker->name,
        ];
    }
}




