<?php

namespace Database\Factories;

use App\Models\ManifesteSg;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManifesteSg>
 */
class ManifesteSgFactory extends Factory
{
    protected $model = ManifesteSg::class;

    public function definition(): array
    {
        return [
            'instance_id' => $this->faker->unique()->numberBetween(1, 100000),
            'code_bureau' => $this->faker->regexify('[A-Z]{5}'),
            'num_voyage' => $this->faker->bothify('VOY-#####'),
            'date_voyage' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'num_manifeste' => $this->faker->numerify('MAN-#####'),
            'date_arrivee_navire' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'nbre_total_bl' => $this->faker->numberBetween(1, 20),
            'nbre_total_colis' => $this->faker->randomFloat(2, 1, 1000),
            'nbre_total_conteneur' => $this->faker->numberBetween(1, 100),
            'total_poids_brut' => $this->faker->randomFloat(2, 100, 5000),
            'nom_moyen_transport' => $this->faker->words(2, true),
            'nom_transport' => $this->faker->word(),
        ];
    }
}


