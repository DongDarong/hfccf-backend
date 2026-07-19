<?php

namespace Database\Factories;

use App\Models\PreschoolClassTeacherAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreschoolClassTeacherAssignmentFactory extends Factory
{
    protected $model = PreschoolClassTeacherAssignment::class;

    public function definition(): array
    {
        return [
            'status' => 'active',
            'assigned_at' => now(),
        ];
    }

    public function inactive(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'inactive',
                'ended_at' => now(),
            ];
        });
    }
}
