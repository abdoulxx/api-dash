<?php

namespace Database\Factories;

use App\Models\BanqueSad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BanqueSad>
 */
class BanqueSadFactory extends Factory
{
    protected $model = BanqueSad::class;

    public function definition(): array
    {
        return [
            'num_ddu' => $this->faker->unique()->numerify('DDU-#####'),
            'num_man' => $this->faker->numerify('MAN-#####'),
            'ref_ddu' => $this->faker->numerify('DEC-#####'),
            'date_ddu' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'statut_ac' => $this->faker->randomElement(['APPROVED', 'PENDING']),
            'num_dom' => $this->faker->numerify('DOM-#####'),
            'date_dom' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'bank_dom' => $this->faker->company,
        ];
    }
}





