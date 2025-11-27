<?php

namespace Database\Factories;

use App\Models\DeclarationArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeclarationArticle>
 */
class DeclarationArticleFactory extends Factory
{
    protected $model = DeclarationArticle::class;

    public function definition(): array
    {
        return [
            'instanceid' => $this->faker->unique()->numberBetween(1, 100000),
            'declaration' => $this->faker->numerify('DEC-#####'),
            'numero_article' => $this->faker->numberBetween(1, 20),
            'postar' => $this->faker->bothify('??######'),
            'libelle_postar' => $this->faker->words(3, true),
            'valcaf' => $this->faker->randomFloat(2, 100, 10000),
            'valfob' => $this->faker->randomFloat(2, 100, 8000),
            'droits_taxes' => $this->faker->randomFloat(2, 10, 1000),
        ];
    }
}





