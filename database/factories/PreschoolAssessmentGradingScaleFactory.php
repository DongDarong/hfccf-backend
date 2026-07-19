<?php

namespace Database\Factories;

use App\Models\PreschoolAssessmentGradingScale;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreschoolAssessmentGradingScaleFactory extends Factory
{
    protected $model = PreschoolAssessmentGradingScale::class;

    public function definition(): array
    {
        static $gradeIndex = 0;
        $grades = [
            ['grade' => 'A', 'name' => 'Excellent', 'min' => 90, 'max' => 100, 'passing' => true],
            ['grade' => 'B', 'name' => 'Good', 'min' => 80, 'max' => 89, 'passing' => true],
            ['grade' => 'C', 'name' => 'Fair', 'min' => 70, 'max' => 79, 'passing' => true],
            ['grade' => 'D', 'name' => 'Poor', 'min' => 60, 'max' => 69, 'passing' => false],
            ['grade' => 'F', 'name' => 'Fail', 'min' => 0, 'max' => 59, 'passing' => false],
        ];

        $grade = $grades[$gradeIndex % 5];
        $gradeIndex++;

        return [
            'name' => $grade['name'],
            'grade' => $grade['grade'],
            'minimum_score' => $grade['min'],
            'maximum_score' => $grade['max'],
            'is_passing' => $grade['passing'],
            'sort_order' => $gradeIndex,
            'color' => $this->faker->hexColor(),
        ];
    }
}
