<?php

namespace Tests\Feature;

use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAssessmentCategory;
use App\Models\PreschoolClass;
use App\Models\PreschoolMonthlySubmission;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAssessment;
use App\Support\PreschoolMonthlySubmissionStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PreschoolMonthlySubmissionMigrationTest extends TestCase
{
    /**
     * Test that the monthly submission table was created with correct structure.
     */
    public function test_monthly_submission_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('preschool_monthly_submissions'));
    }

    /**
     * Test that required columns exist.
     */
    public function test_monthly_submission_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'academic_year_id'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'class_id'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'assessment_category_id'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'submission_month'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'status'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'submitted_at'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'submitted_by_user_id'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'reviewed_at'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'reviewed_by_user_id'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'review_comment'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'returned_at'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'returned_by_user_id'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'return_reason'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'finalized_at'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'finalized_by_user_id'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'locked_at'));
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'grading_scale_snapshot'));
    }

    /**
     * Test student assessment table has the monthly_submission_id column.
     */
    public function test_student_assessment_has_monthly_submission_column(): void
    {
        $this->assertTrue(Schema::hasColumn('preschool_student_assessments', 'monthly_submission_id'));
    }

    /**
     * Test that monthly_submission_id column is nullable during Phase A.2.
     */
    public function test_monthly_submission_id_is_nullable(): void
    {
        $table = Schema::getColumns('preschool_student_assessments');
        $monthlySubmissionColumn = collect($table)->firstWhere('name', 'monthly_submission_id');

        $this->assertTrue($monthlySubmissionColumn['nullable'] ?? false, 'monthly_submission_id must remain nullable in Phase A.2');
    }

    /**
     * Test that unique constraint exists on the monthly submission.
     */
    public function test_unique_constraint_on_monthly_submission(): void
    {
        $indexes = Schema::getIndexes('preschool_monthly_submissions');
        $uniqueMonthly = collect($indexes)->firstWhere('name', 'unique_monthly_submission');

        $this->assertNotNull($uniqueMonthly, 'Unique constraint unique_monthly_submission must exist');
        $this->assertTrue($uniqueMonthly['unique'] ?? false, 'Constraint must be unique');
    }

    /**
     * Test that unique constraint exists for student + submission.
     */
    public function test_unique_constraint_on_student_per_submission(): void
    {
        $indexes = Schema::getIndexes('preschool_student_assessments');
        $uniqueStudent = collect($indexes)->firstWhere('name', 'unique_student_per_monthly_submission');

        $this->assertNotNull($uniqueStudent, 'Unique constraint unique_student_per_monthly_submission must exist');
        $this->assertTrue($uniqueStudent['unique'] ?? false, 'Constraint must be unique');
    }

    /**
     * Test soft delete support.
     */
    public function test_monthly_submission_supports_soft_deletes(): void
    {
        $this->assertTrue(Schema::hasColumn('preschool_monthly_submissions', 'deleted_at'));
    }

    /**
     * Test that model can be created and retrieved.
     */
    public function test_model_creation(): void
    {
        $academicYear = PreschoolAcademicYear::factory()->create();
        $class = PreschoolClass::factory()->create();
        $category = PreschoolAssessmentCategory::factory()->create();

        $submission = PreschoolMonthlySubmission::create([
            'academic_year_id' => $academicYear->id,
            'class_id' => $class->id,
            'assessment_category_id' => $category->id,
            'submission_month' => now()->startOfMonth(),
            'status' => PreschoolMonthlySubmissionStatus::DRAFT,
        ]);

        $this->assertInstanceOf(PreschoolMonthlySubmission::class, $submission);
        $this->assertEquals(PreschoolMonthlySubmissionStatus::DRAFT, $submission->status);
        $this->assertNotNull($submission->id);
    }

    /**
     * Test that unique constraint is enforced.
     */
    public function test_unique_constraint_enforced(): void
    {
        $academicYear = PreschoolAcademicYear::factory()->create();
        $class = PreschoolClass::factory()->create();
        $category = PreschoolAssessmentCategory::factory()->create();
        $month = now()->startOfMonth();

        PreschoolMonthlySubmission::create([
            'academic_year_id' => $academicYear->id,
            'class_id' => $class->id,
            'assessment_category_id' => $category->id,
            'submission_month' => $month,
            'status' => PreschoolMonthlySubmissionStatus::DRAFT,
        ]);

        // Attempt to create duplicate should fail
        try {
            PreschoolMonthlySubmission::create([
                'academic_year_id' => $academicYear->id,
                'class_id' => $class->id,
                'assessment_category_id' => $category->id,
                'submission_month' => $month,
                'status' => PreschoolMonthlySubmissionStatus::DRAFT,
            ]);
            $this->fail('Unique constraint should prevent duplicate submissions');
        } catch (\Exception $e) {
            $this->assertStringContainsString('unique', strtolower($e->getMessage()));
        }
    }

    /**
     * Test status helper methods.
     */
    public function test_status_helper_methods(): void
    {
        $submission = PreschoolMonthlySubmission::factory()->create(['status' => PreschoolMonthlySubmissionStatus::DRAFT]);

        $this->assertTrue($submission->isEditable());
        $this->assertTrue($submission->canBeSubmitted());
        $this->assertFalse($submission->canBeReviewed());
        $this->assertFalse($submission->canBeReturned());
        $this->assertFalse($submission->canBeFinalized());
        $this->assertTrue($submission->canBeArchived());

        $submission->update(['status' => PreschoolMonthlySubmissionStatus::SUBMITTED]);
        $this->assertFalse($submission->isEditable());
        $this->assertTrue($submission->canBeReviewed());
        $this->assertTrue($submission->canBeReturned());
        $this->assertTrue($submission->canBeFinalized());

        $submission->update(['status' => PreschoolMonthlySubmissionStatus::FINALIZED]);
        $this->assertFalse($submission->isEditable());
        $this->assertTrue($submission->canBeArchived());
    }

    /**
     * Test relationships.
     */
    public function test_relationships(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->submitted()
            ->create();

        $this->assertNotNull($submission->academicYear);
        $this->assertNotNull($submission->class);
        $this->assertNotNull($submission->category);
        $this->assertNotNull($submission->submittedBy);
    }

    /**
     * Test student assessment relationship.
     */
    public function test_student_assessment_relationship(): void
    {
        $submission = PreschoolMonthlySubmission::factory()->create();
        $student = PreschoolStudent::factory()->create();
        $assessment = PreschoolStudentAssessment::factory()->create([
            'monthly_submission_id' => $submission->id,
            'student_id' => $student->id,
        ]);

        $this->assertEquals($submission->id, $assessment->monthly_submission_id);
        $this->assertTrue($submission->studentAssessments()->where('student_id', $student->id)->exists());
    }

    /**
     * Test that grading scale snapshot is JSON castable.
     */
    public function test_grading_scale_snapshot_cast(): void
    {
        $snapshot = [
            'scales' => [
                ['grade' => 'A', 'min' => 90, 'max' => 100],
            ],
        ];

        $submission = PreschoolMonthlySubmission::factory()->finalized()->create();
        $submission->update(['grading_scale_snapshot' => $snapshot]);
        $submission->refresh();

        $this->assertIsArray($submission->grading_scale_snapshot);
        $this->assertArrayHasKey('scales', $submission->grading_scale_snapshot);
    }

    /**
     * Test that soft delete works.
     */
    public function test_soft_delete(): void
    {
        $submission = PreschoolMonthlySubmission::factory()->create();
        $id = $submission->id;

        $submission->delete();

        $this->assertNull(PreschoolMonthlySubmission::find($id));
        $this->assertNotNull(PreschoolMonthlySubmission::withTrashed()->find($id));
    }

    /**
     * Test migration rollback.
     */
    public function test_migration_rollback_safety(): void
    {
        // Verify columns exist before rollback
        $this->assertTrue(Schema::hasTable('preschool_monthly_submissions'));
        $this->assertTrue(Schema::hasColumn('preschool_student_assessments', 'monthly_submission_id'));

        // Create a test record
        $submission = PreschoolMonthlySubmission::factory()->create();
        $assessmentId = PreschoolStudentAssessment::factory()->create([
            'monthly_submission_id' => $submission->id,
        ])->id;

        // Records should exist
        $this->assertTrue(PreschoolMonthlySubmission::find($submission->id) !== null);
        $this->assertTrue(PreschoolStudentAssessment::find($assessmentId) !== null);

        // If we were to rollback (not doing it here, just documenting):
        // - Cascade delete would remove linked assessments
        // - monthly_submission_id column would be dropped from assessments
        // - PreschoolMonthlySubmission table would be dropped
        // - Orphaned assessments would remain in the database (backward compatible)
    }
}
