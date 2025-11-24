<?php

namespace Database\Factories;

use App\Models\ManifesteTt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManifesteTt>
 */
class ManifesteTtFactory extends Factory
{
    protected $model = ManifesteTt::class;

    public function definition(): array
    {
        return [
            'instance_id' => $this->faker->unique()->numberBetween(1, 500000),
            'etat_apurement' => $this->faker->randomElement(['AP', 'NA']),
            'code_bureau' => $this->faker->regexify('[A-Z]{5}'),
            'nom_bureau' => $this->faker->city(),
            'num_voy_ds' => $this->faker->bothify('VOY-#####'),
            'date_arrive' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'num_titre_transport' => $this->faker->bothify('BL#####'),
            'annee_manifeste' => (int) $this->faker->year(),
            'num_manifeste' => $this->faker->numerify('MAN-#####'),
            'date_manifeste' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'nom_consignataire' => $this->faker->company(),
            'nom_exportateur' => $this->faker->company(),
            'nom_importateur' => $this->faker->company(),
            'nom_navire' => $this->faker->words(2, true),
            'nombre_conteneur' => $this->faker->numberBetween(0, 50),
            'poids_brut' => $this->faker->randomFloat(2, 100, 5000),
            'poids_restant' => $this->faker->randomFloat(2, 0, 500),
            'status_cns' => $this->faker->word(),
            'nature_cns' => $this->faker->lexify('????'),
            'type_cns' => $this->faker->lexify('????'),
            'libelle_cns' => $this->faker->sentence(3),
            'nbr_colis' => $this->faker->randomFloat(2, 1, 500),
        ];
    }
}


