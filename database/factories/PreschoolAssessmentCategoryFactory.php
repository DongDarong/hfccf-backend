<?php

namespace Database\Factories;

use App\Models\PreschoolAssessmentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreschoolAssessmentCategoryFactory extends Factory
{
    protected $model = PreschoolAssessmentCategory::class;

    public function definition(): array
    {
        return [
            'code' => 'CAT' . $this->faker->unique()->numerify('###'),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }

    public function active(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => true,
            ];
        });
    }

    public function inactive(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
            ];
        });
    }
}
