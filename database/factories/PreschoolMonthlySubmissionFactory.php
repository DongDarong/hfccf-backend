<?php

namespace Database\Factories;

use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAssessmentCategory;
use App\Models\PreschoolClass;
use App\Models\PreschoolMonthlySubmission;
use App\Models\User;
use App\Support\PreschoolMonthlySubmissionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PreschoolMonthlySubmission>
 */
class PreschoolMonthlySubmissionFactory extends Factory
{
    protected $model = PreschoolMonthlySubmission::class;

    public function definition(): array
    {
        $year = now()->year;
        $academicYear = PreschoolAcademicYear::firstOrCreate(
            ['code' => 'AY' . $year],
            ['label' => $year . '-' . ($year + 1), 'start_date' => now()->startOfYear(), 'end_date' => now()->endOfYear(), 'status' => 'active']
        );

        $class = PreschoolClass::inRandomOrder()->first() ?? PreschoolClass::factory()->create();

        $category = PreschoolAssessmentCategory::inRandomOrder()->first()
            ?? PreschoolAssessmentCategory::factory()->create();

        $teacher = User::where('role_code', 'teacher-preschool')->inRandomOrder()->first()
            ?? User::factory()->create();

        return [
            'academic_year_id' => $academicYear->id,
            'class_id' => $class->id,
            'assessment_category_id' => $category->id,
            'submission_month' => now()->startOfMonth(),
            'status' => PreschoolMonthlySubmissionStatus::DRAFT,
            'submitted_by_user_id' => null,
            'submitted_at' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'review_comment' => null,
            'returned_by_user_id' => null,
            'returned_at' => null,
            'return_reason' => null,
            'finalized_by_user_id' => null,
            'finalized_at' => null,
            'locked_at' => null,
            'grading_scale_snapshot' => null,
        ];
    }

    /**
     * Mark submission as submitted.
     */
    public function submitted(): static
    {
        return $this->state(function (array $attributes) {
            $teacher = User::where('role_code', 'teacher-preschool')->inRandomOrder()->first()
                ?? User::factory()->create();

            return [
                'status' => PreschoolMonthlySubmissionStatus::SUBMITTED,
                'submitted_by_user_id' => $teacher->id,
                'submitted_at' => now(),
            ];
        });
    }

    /**
     * Mark submission as returned.
     */
    public function returned(): static
    {
        return $this->submitted()->state(function (array $attributes) {
            $admin = User::where('role_code', 'adminpreschool')->inRandomOrder()->first()
                ?? User::factory()->create();

            return [
                'status' => PreschoolMonthlySubmissionStatus::RETURNED,
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
                'returned_by_user_id' => $admin->id,
                'returned_at' => now(),
                'return_reason' => 'Please revise the scores.',
            ];
        });
    }

    /**
     * Mark submission as finalized (approved).
     */
    public function finalized(): static
    {
        return $this->submitted()->state(function (array $attributes) {
            $admin = User::where('role_code', 'adminpreschool')->inRandomOrder()->first()
                ?? User::factory()->create();

            return [
                'status' => PreschoolMonthlySubmissionStatus::FINALIZED,
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
                'finalized_by_user_id' => $admin->id,
                'finalized_at' => now(),
                'locked_at' => now(),
                'grading_scale_snapshot' => [
                    'captured_at' => now()->toIso8601String(),
                    'scales' => [
                        ['grade' => 'A', 'min' => 90, 'max' => 100, 'is_passing' => true],
                        ['grade' => 'B', 'min' => 80, 'max' => 89, 'is_passing' => true],
                        ['grade' => 'C', 'min' => 70, 'max' => 79, 'is_passing' => true],
                        ['grade' => 'D', 'min' => 60, 'max' => 69, 'is_passing' => true],
                        ['grade' => 'F', 'min' => 0, 'max' => 59, 'is_passing' => false],
                    ],
                ],
            ];
        });
    }

    /**
     * Mark submission as archived.
     */
    public function archived(): static
    {
        return $this->finalized()->state(function (array $attributes) {
            return [
                'status' => PreschoolMonthlySubmissionStatus::ARCHIVED,
                'deleted_at' => now(),
            ];
        });
    }
}
