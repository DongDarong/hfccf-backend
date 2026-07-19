<?php

namespace Tests\Unit\Models;

use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAssessmentCategory;
use App\Models\PreschoolClass;
use App\Models\PreschoolMonthlySubmission;
use App\Support\PreschoolMonthlySubmissionStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PreschoolMonthlySubmissionModelTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test model creation without factory.
     */
    public function test_model_creation(): void
    {
        $academicYear = PreschoolAcademicYear::query()->firstOrFail();
        $class = PreschoolClass::query()->firstOrFail();
        $category = PreschoolAssessmentCategory::query()->firstOrFail();

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
     * Test status constants.
     */
    public function test_status_constants(): void
    {
        $this->assertEquals('draft', PreschoolMonthlySubmissionStatus::DRAFT);
        $this->assertEquals('submitted', PreschoolMonthlySubmissionStatus::SUBMITTED);
        $this->assertEquals('returned', PreschoolMonthlySubmissionStatus::RETURNED);
        $this->assertEquals('finalized', PreschoolMonthlySubmissionStatus::FINALIZED);
        $this->assertEquals('archived', PreschoolMonthlySubmissionStatus::ARCHIVED);
    }

    /**
     * Test status values list.
     */
    public function test_status_values(): void
    {
        $values = PreschoolMonthlySubmissionStatus::values();
        $this->assertCount(5, $values);
        $this->assertContains('draft', $values);
        $this->assertContains('submitted', $values);
        $this->assertContains('returned', $values);
        $this->assertContains('finalized', $values);
        $this->assertContains('archived', $values);
    }

    /**
     * Test editable statuses.
     */
    public function test_teacher_editable_statuses(): void
    {
        $editable = PreschoolMonthlySubmissionStatus::teacherEditableStatuses();
        $this->assertContains('draft', $editable);
        $this->assertContains('returned', $editable);
        $this->assertNotContains('submitted', $editable);
        $this->assertNotContains('finalized', $editable);
    }

    /**
     * Test submission helper for DRAFT status.
     */
    public function test_is_editable_draft_status(): void
    {
        $academicYear = PreschoolAcademicYear::query()->firstOrFail();
        $class = PreschoolClass::query()->firstOrFail();
        $category = PreschoolAssessmentCategory::query()->firstOrFail();

        $submission = PreschoolMonthlySubmission::create([
            'academic_year_id' => $academicYear->id,
            'class_id' => $class->id,
            'assessment_category_id' => $category->id,
            'submission_month' => now()->startOfMonth(),
            'status' => PreschoolMonthlySubmissionStatus::DRAFT,
        ]);

        $this->assertTrue($submission->isEditable());
    }

    /**
     * Test is_editable helper for RETURNED status.
     */
    public function test_is_editable_returned_status(): void
    {
        $academicYear = PreschoolAcademicYear::query()->firstOrFail();
        $class = PreschoolClass::query()->firstOrFail();
        $category = PreschoolAssessmentCategory::query()->firstOrFail();

        $submission = PreschoolMonthlySubmission::create([
            'academic_year_id' => $academicYear->id,
            'class_id' => $class->id,
            'assessment_category_id' => $category->id,
            'submission_month' => now()->startOfMonth(),
            'status' => PreschoolMonthlySubmissionStatus::RETURNED,
        ]);

        $this->assertTrue($submission->isEditable());
    }

    /**
     * Test is_editable helper for SUBMITTED status.
     */
    public function test_is_not_editable_submitted_status(): void
    {
        $academicYear = PreschoolAcademicYear::query()->firstOrFail();
        $class = PreschoolClass::query()->firstOrFail();
        $category = PreschoolAssessmentCategory::query()->firstOrFail();

        $submission = PreschoolMonthlySubmission::create([
            'academic_year_id' => $academicYear->id,
            'class_id' => $class->id,
            'assessment_category_id' => $category->id,
            'submission_month' => now()->startOfMonth(),
            'status' => PreschoolMonthlySubmissionStatus::SUBMITTED,
        ]);

        $this->assertFalse($submission->isEditable());
    }

    /**
     * Test can be submitted from DRAFT.
     */
    public function test_can_be_submitted_from_draft(): void
    {
        $academicYear = PreschoolAcademicYear::query()->firstOrFail();
        $class = PreschoolClass::query()->firstOrFail();
        $category = PreschoolAssessmentCategory::query()->firstOrFail();

        $submission = PreschoolMonthlySubmission::create([
            'academic_year_id' => $academicYear->id,
            'class_id' => $class->id,
            'assessment_category_id' => $category->id,
            'submission_month' => now()->startOfMonth(),
            'status' => PreschoolMonthlySubmissionStatus::DRAFT,
        ]);

        $this->assertTrue($submission->canBeSubmitted());
    }

    /**
     * Test can be reviewed when SUBMITTED.
     */
    public function test_can_be_reviewed_when_submitted(): void
    {
        $academicYear = PreschoolAcademicYear::query()->firstOrFail();
        $class = PreschoolClass::query()->firstOrFail();
        $category = PreschoolAssessmentCategory::query()->firstOrFail();

        $submission = PreschoolMonthlySubmission::create([
            'academic_year_id' => $academicYear->id,
            'class_id' => $class->id,
            'assessment_category_id' => $category->id,
            'submission_month' => now()->startOfMonth(),
            'status' => PreschoolMonthlySubmissionStatus::SUBMITTED,
        ]);

        $this->assertTrue($submission->canBeReviewed());
        $this->assertTrue($submission->canBeReturned());
        $this->assertTrue($submission->canBeFinalized());
    }

    /**
     * Test official statuses.
     */
    public function test_official_statuses(): void
    {
        $official = PreschoolMonthlySubmissionStatus::officialStatuses();
        $this->assertContains('finalized', $official);
        $this->assertNotContains('draft', $official);
        $this->assertNotContains('submitted', $official);
        $this->assertNotContains('returned', $official);
    }

    /**
     * Test grading scale snapshot cast to array.
     */
    public function test_grading_scale_snapshot_cast(): void
    {
        $academicYear = PreschoolAcademicYear::query()->firstOrFail();
        $class = PreschoolClass::query()->firstOrFail();
        $category = PreschoolAssessmentCategory::query()->firstOrFail();

        $snapshot = [
            'scales' => [
                ['grade' => 'A', 'min' => 90, 'max' => 100],
                ['grade' => 'B', 'min' => 80, 'max' => 89],
            ],
        ];

        $submission = PreschoolMonthlySubmission::create([
            'academic_year_id' => $academicYear->id,
            'class_id' => $class->id,
            'assessment_category_id' => $category->id,
            'submission_month' => now()->startOfMonth(),
            'status' => PreschoolMonthlySubmissionStatus::FINALIZED,
            'grading_scale_snapshot' => $snapshot,
        ]);

        $submission->refresh();
        $this->assertIsArray($submission->grading_scale_snapshot);
        $this->assertArrayHasKey('scales', $submission->grading_scale_snapshot);
        $this->assertCount(2, $submission->grading_scale_snapshot['scales']);
    }
}
