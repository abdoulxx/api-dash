<?php

namespace Database\Factories;

use App\Models\FdiArticle;
use App\Models\FdiSg;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FdiArticle>
 */
class FdiArticleFactory extends Factory
{
    protected $model = FdiArticle::class;

    public function definition(): array
    {
        $numeroFdi = FdiSg::factory()->create()->numero_fdi;

        return [
            'numero_fdi' => $numeroFdi,
            'numart' => $this->faker->numberBetween(1, 9999),
            'postar' => $this->faker->regexify('[0-9]{10}'),
            'nature_marchandise' => $this->faker->words(3, true),
            'description_marchandise' => $this->faker->sentence(8),
            'quantite' => $this->faker->randomFloat(2, 1, 1000),
            'poids_net' => $this->faker->randomFloat(2, 1, 500),
            'poids_brut' => $this->faker->randomFloat(2, 1, 500),
            'instance_id' => $this->faker->randomNumber(6),
            'serie_fdi' => $this->faker->randomElement(['A', 'B', 'C']),
            'bureau' => $this->faker->regexify('[A-Z]{5}'),
            'annee' => (int) date('Y'),
            'numero_serie' => $this->faker->numerify('SER-#####'),
            'date_fdi' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}




