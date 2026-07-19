<?php

namespace Database\Factories;

use App\Models\PreschoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreschoolClassFactory extends Factory
{
    protected $model = PreschoolClass::class;

    public function definition(): array
    {
        return [
            'code' => 'CL' . $this->faker->unique()->numerify('###'),
            'name' => $this->faker->words(3, true),
            'level' => $this->faker->randomElement(['Nursery', 'K1', 'K2']),
            'status' => 'active',
            'students_count' => 0,
            'tuition_fee' => '150.00',
            'notes' => null,
        ];
    }

    public function inactive(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'inactive',
            ];
        });
    }
}
