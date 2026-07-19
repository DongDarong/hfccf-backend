<?php

namespace Database\Factories;

use App\Models\PreschoolAcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreschoolAcademicYearFactory extends Factory
{
    protected $model = PreschoolAcademicYear::class;

    public function definition(): array
    {
        $startDate = now()->startOfYear();
        $endDate = $startDate->copy()->endOfYear();

        return [
            'code' => 'AY' . $this->faker->year,
            'label' => $this->faker->year . '-' . ($this->faker->year + 1),
            'description' => $this->faker->sentence(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'active',
            'is_current' => true,
            'notes' => null,
        ];
    }

    public function inactive(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'inactive',
                'is_current' => false,
            ];
        });
    }
}
