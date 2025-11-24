<?php

namespace Database\Factories;

use App\Models\BonProvisoireArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BonProvisoireArticle>
 */
class BonProvisoireArticleFactory extends Factory
{
    protected $model = BonProvisoireArticle::class;

    public function definition(): array
    {
        return [
            'instance_id' => $this->faker->unique()->numberBetween(1, 100000),
            'numero_bon_provisoire' => $this->faker->numerify('BP-########'),
            'date_bp' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'postar' => $this->faker->bothify('??######'),
            'libelle_marchandise' => $this->faker->words(3, true),
            'poids_net_kgs' => $this->faker->randomFloat(2, 10, 5000),
            'code_devise' => $this->faker->randomElement(['USD', 'EUR', 'XOF']),
            'montant_devise' => $this->faker->randomFloat(2, 100, 50000),
        ];
    }
}




