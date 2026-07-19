<?php

namespace Database\Factories;

use App\Models\PreschoolStudent;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreschoolStudentFactory extends Factory
{
    protected $model = PreschoolStudent::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'date_of_birth' => $this->faker->dateTimeBetween('-6 years', '-3 years')->format('Y-m-d'),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'status' => 'active',
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
