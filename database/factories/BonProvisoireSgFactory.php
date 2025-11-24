<?php

namespace Database\Factories;

use App\Models\BonProvisoireSg;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BonProvisoireSg>
 */
class BonProvisoireSgFactory extends Factory
{
    protected $model = BonProvisoireSg::class;

    public function definition(): array
    {
        return [
            'instance_id' => $this->faker->unique()->numberBetween(1, 100000),
            'annee' => $this->faker->numberBetween(2020, 2025),
            'bureau' => $this->faker->randomElement(['ABJ', 'DKR', 'BKO']),
            'serie_bp' => $this->faker->randomLetter,
            'num_serie_bp' => $this->faker->numerify('BP-#####'),
            'numero_bon_provisoire' => $this->faker->unique()->numerify('BP-########'),
            'date_bp' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'date_expiration' => $this->faker->dateTimeBetween('now', '+3 months'),
            'num_lta' => $this->faker->numerify('LTA-#####'),
            'nom_importateur' => $this->faker->company,
            'nom_fournisseur' => $this->faker->company,
            'type_bon_provisoire' => $this->faker->randomElement(['TEMPORAIRE', 'DEFINITIF']),
        ];
    }
}




