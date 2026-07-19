<?php

namespace Tests\Feature;

use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAssessmentCategory;
use App\Models\PreschoolAssessmentGradingScale;
use App\Models\PreschoolClass;
use App\Models\PreschoolClassStudent;
use App\Models\PreschoolClassTeacherAssignment;
use App\Models\PreschoolMonthlySubmission;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Support\PreschoolMonthlySubmissionStatus;
use Database\Seeders\HfccfAuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Phase A.4 — API Endpoint Feature Tests
 *
 * Tests the REST API for monthly assessment submissions.
 */
class PreschoolMonthlySubmissionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $admin;
    private PreschoolAcademicYear $academicYear;
    private PreschoolClass $class;
    private PreschoolAssessmentCategory $category;
    private PreschoolStudent $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HfccfAuthSeeder::class);

        $this->academicYear = PreschoolAcademicYear::factory()->create();
        $this->class = PreschoolClass::factory()->create();
        $this->category = PreschoolAssessmentCategory::factory()->active()->create();
        $this->student = PreschoolStudent::factory()->create();

        $this->teacher = $this->createUserWithRole('teacher-preschool');
        PreschoolClassTeacherAssignment::create([
            'class_id' => $this->class->id,
            'teacher_user_id' => $this->teacher->id,
            'status' => 'active',
            'academic_year_id' => $this->academicYear->id,
        ]);

        $this->admin = $this->createUserWithRole('adminpreschool');

        PreschoolClassStudent::create([
            'class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'status' => 'active',
            'enrollment_status' => 'active',
        ]);
    }

    // ── List Endpoint ────────────────────────────────────────────────────────

    public function test_list_requires_authentication(): void
    {
        $response = $this->getJson('/api/preschool/monthly-submissions');
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_teacher_can_list_own_submissions(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->create();

        Sanctum::actingAs($this->teacher);
        $response = $this->getJson('/api/preschool/monthly-submissions');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.id', $submission->id);
    }

    public function test_teacher_cannot_list_unrelated_submissions(): void
    {
        $otherClass = PreschoolClass::factory()->create();
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($otherClass, 'class')
            ->for($this->category, 'category')
            ->create();

        Sanctum::actingAs($this->teacher);
        $response = $this->getJson('/api/preschool/monthly-submissions');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.items', []);
    }

    public function test_admin_can_list_all_submissions(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->create();

        Sanctum::actingAs($this->admin);
        $response = $this->getJson('/api/preschool/monthly-submissions');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.items.0.id', $submission->id);
    }

    public function test_list_supports_pagination(): void
    {
        foreach (range(0, 2) as $i) {
            PreschoolMonthlySubmission::factory()
                ->for($this->academicYear, 'academicYear')
                ->for($this->class, 'class')
                ->for($this->category, 'category')
                ->state(['submission_month' => now()->subMonths($i)->startOfMonth()])
                ->create();
        }

        Sanctum::actingAs($this->admin);
        $response = $this->getJson('/api/preschool/monthly-submissions?per_page=2&page=1');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.pagination.perPage', 2)
            ->assertJsonPath('data.pagination.total', 3);
    }

    public function test_list_supports_status_filter(): void
    {
        PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->state(['status' => PreschoolMonthlySubmissionStatus::DRAFT, 'submission_month' => now()->startOfMonth()])
            ->create();

        PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->state(['status' => PreschoolMonthlySubmissionStatus::SUBMITTED, 'submission_month' => now()->subMonths(1)->startOfMonth()])
            ->create();

        Sanctum::actingAs($this->admin);
        $response = $this->getJson('/api/preschool/monthly-submissions?status=draft');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data.items');
    }

    // ── Create Endpoint ──────────────────────────────────────────────────────

    public function test_create_requires_authentication(): void
    {
        $response = $this->postJson('/api/preschool/monthly-submissions', [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class->id,
            'assessment_category_id' => $this->category->id,
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_create_requires_valid_ids(): void
    {
        Sanctum::actingAs($this->teacher);
        $response = $this->postJson('/api/preschool/monthly-submissions', [
            'academic_year_id' => 99999,
            'class_id' => 99999,
            'assessment_category_id' => 99999,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_create_returns_201_for_new_draft(): void
    {
        Sanctum::actingAs($this->teacher);
        $response = $this->postJson('/api/preschool/monthly-submissions', [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class->id,
            'assessment_category_id' => $this->category->id,
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.submission.status', PreschoolMonthlySubmissionStatus::DRAFT);
    }

    public function test_create_returns_200_for_existing_editable(): void
    {
        Sanctum::actingAs($this->teacher);

        // First create
        $response1 = $this->postJson('/api/preschool/monthly-submissions', [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class->id,
            'assessment_category_id' => $this->category->id,
        ]);

        // Second create should return 200
        $response2 = $this->postJson('/api/preschool/monthly-submissions', [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class->id,
            'assessment_category_id' => $this->category->id,
        ]);

        $response2->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.submission.id', $response1->json('data.submission.id'));
    }

    public function test_create_returns_409_for_locked_submission(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->state(['status' => PreschoolMonthlySubmissionStatus::SUBMITTED])
            ->create();

        Sanctum::actingAs($this->teacher);
        $response = $this->postJson('/api/preschool/monthly-submissions', [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class->id,
            'assessment_category_id' => $this->category->id,
        ]);

        $response->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonPath('success', false);
    }

    // ── Show Endpoint ────────────────────────────────────────────────────────

    public function test_show_returns_404_for_missing(): void
    {
        Sanctum::actingAs($this->teacher);
        $response = $this->getJson('/api/preschool/monthly-submissions/99999');

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_show_teacher_cannot_access_unrelated(): void
    {
        $otherClass = PreschoolClass::factory()->create();
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($otherClass, 'class')
            ->for($this->category, 'category')
            ->create();

        Sanctum::actingAs($this->teacher);
        $response = $this->getJson("/api/preschool/monthly-submissions/{$submission->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_show_teacher_can_access_own(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->create();

        Sanctum::actingAs($this->teacher);
        $response = $this->getJson("/api/preschool/monthly-submissions/{$submission->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.submission.id', $submission->id);
    }

    public function test_show_admin_can_access_any(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->create();

        Sanctum::actingAs($this->admin);
        $response = $this->getJson("/api/preschool/monthly-submissions/{$submission->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.submission.id', $submission->id);
    }

    public function test_teacher_score_is_mapped_to_the_admin_configured_grade(): void
    {
        PreschoolAssessmentGradingScale::query()->delete();
        PreschoolAssessmentGradingScale::factory()->create([
            'grade' => 'B', 'name' => 'Good', 'minimum_score' => 80, 'maximum_score' => 100,
        ]);
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')->for($this->class, 'class')->for($this->category, 'category')->create();

        Sanctum::actingAs($this->teacher);
        $this->patchJson("/api/preschool/monthly-submissions/{$submission->id}/scores/{$this->student->id}", [
            'score' => 80,
            'rating' => 'FORGED',
        ])->assertOk()->assertJsonPath('data.assessment.rating', 'B');
    }

    public function test_zero_and_decimal_scores_are_valid_and_derived(): void
    {
        PreschoolAssessmentGradingScale::query()->delete();
        PreschoolAssessmentGradingScale::factory()->create([
            'grade' => 'F', 'name' => 'Fail', 'minimum_score' => 0, 'maximum_score' => 79.99,
        ]);
        PreschoolAssessmentGradingScale::factory()->create([
            'grade' => 'B', 'name' => 'Good', 'minimum_score' => 80, 'maximum_score' => 100,
        ]);
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')->for($this->class, 'class')->for($this->category, 'category')->create();

        Sanctum::actingAs($this->teacher);
        $this->patchJson("/api/preschool/monthly-submissions/{$submission->id}/scores/{$this->student->id}", ['score' => 0])
            ->assertOk()->assertJsonPath('data.assessment.rating', 'F');
        $this->patchJson("/api/preschool/monthly-submissions/{$submission->id}/scores/{$this->student->id}", ['score' => 80.5])
            ->assertOk()->assertJsonPath('data.assessment.rating', 'B');
    }

    public function test_score_requires_a_matching_configured_grade_band(): void
    {
        PreschoolAssessmentGradingScale::query()->delete();
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')->for($this->class, 'class')->for($this->category, 'category')->create();

        Sanctum::actingAs($this->teacher);
        $this->patchJson("/api/preschool/monthly-submissions/{$submission->id}/scores/{$this->student->id}", ['score' => 80])
            ->assertUnprocessable()->assertJsonPath('data.error_code', 'GRADE_SCALE_NOT_CONFIGURED');
    }

    public function test_out_of_range_score_returns_validation_error(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')->for($this->class, 'class')->for($this->category, 'category')->create();

        Sanctum::actingAs($this->teacher);
        $this->patchJson("/api/preschool/monthly-submissions/{$submission->id}/scores/{$this->student->id}", ['score' => 100.01])
            ->assertUnprocessable();
    }

    // ── Submit Endpoint ──────────────────────────────────────────────────────

    public function test_submit_requires_auth(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->state(['status' => PreschoolMonthlySubmissionStatus::DRAFT])
            ->create();

        $response = $this->postJson("/api/preschool/monthly-submissions/{$submission->id}/submit");
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_submit_returns_409_for_empty(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->state(['status' => PreschoolMonthlySubmissionStatus::DRAFT])
            ->create();

        Sanctum::actingAs($this->teacher);
        $response = $this->postJson("/api/preschool/monthly-submissions/{$submission->id}/submit");

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('data.error_code', 'EMPTY_SUBMISSION');
    }

    public function test_submit_succeeds_with_assessments(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->state(['status' => PreschoolMonthlySubmissionStatus::DRAFT])
            ->create();

        $submission->studentAssessments()->create([
            'student_id' => $this->student->id,
            'class_id' => $this->class->id,
            'category_id' => $this->category->id,
            'assessed_by_user_id' => $this->teacher->id,
            'period_label' => $submission->submission_month->format('F Y'),
            'academic_year_id' => $submission->academic_year_id,
            'assessment_date' => now()->toDate(),
            'score' => 85,
        ]);

        Sanctum::actingAs($this->teacher);
        $response = $this->postJson("/api/preschool/monthly-submissions/{$submission->id}/submit");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.submission.status', PreschoolMonthlySubmissionStatus::SUBMITTED);
    }

    // ── Return Endpoint ──────────────────────────────────────────────────────

    public function test_return_requires_admin(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->state(['status' => PreschoolMonthlySubmissionStatus::SUBMITTED])
            ->create();

        Sanctum::actingAs($this->teacher);
        $response = $this->postJson(
            "/api/preschool/monthly-submissions/{$submission->id}/return",
            ['return_reason' => 'Please revise']
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_return_requires_reason(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->state(['status' => PreschoolMonthlySubmissionStatus::SUBMITTED])
            ->create();

        Sanctum::actingAs($this->admin);
        $response = $this->postJson(
            "/api/preschool/monthly-submissions/{$submission->id}/return",
            []
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_return_succeeds(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->state(['status' => PreschoolMonthlySubmissionStatus::SUBMITTED])
            ->create();

        Sanctum::actingAs($this->admin);
        $response = $this->postJson(
            "/api/preschool/monthly-submissions/{$submission->id}/return",
            ['return_reason' => 'Please revise scores']
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.submission.status', PreschoolMonthlySubmissionStatus::RETURNED);
    }

    // ── Finalize Endpoint ────────────────────────────────────────────────────

    public function test_finalize_requires_admin(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->state(['status' => PreschoolMonthlySubmissionStatus::SUBMITTED])
            ->create();

        Sanctum::actingAs($this->teacher);
        $response = $this->postJson(
            "/api/preschool/monthly-submissions/{$submission->id}/finalize",
            []
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_finalize_succeeds(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->state(['status' => PreschoolMonthlySubmissionStatus::SUBMITTED])
            ->create();

        $submission->studentAssessments()->create([
            'student_id' => $this->student->id,
            'class_id' => $this->class->id,
            'category_id' => $this->category->id,
            'assessed_by_user_id' => $this->teacher->id,
            'period_label' => $submission->submission_month->format('F Y'),
            'academic_year_id' => $submission->academic_year_id,
            'assessment_date' => now()->toDate(),
            'score' => 85,
        ]);

        Sanctum::actingAs($this->admin);
        $response = $this->postJson(
            "/api/preschool/monthly-submissions/{$submission->id}/finalize",
            []
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.submission.status', PreschoolMonthlySubmissionStatus::FINALIZED)
            ->assertJsonPath('data.submission.grading_scale_snapshot', [
                'captured_at' => $response->json('data.submission.grading_scale_snapshot.captured_at'),
                'scales' => $response->json('data.submission.grading_scale_snapshot.scales'),
            ]);
    }

    // ── Archive Endpoint ─────────────────────────────────────────────────────

    public function test_archive_requires_admin(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->state(['status' => PreschoolMonthlySubmissionStatus::FINALIZED])
            ->create();

        Sanctum::actingAs($this->teacher);
        $response = $this->postJson("/api/preschool/monthly-submissions/{$submission->id}/archive");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_archive_succeeds(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->state(['status' => PreschoolMonthlySubmissionStatus::FINALIZED])
            ->create();

        Sanctum::actingAs($this->admin);
        $response = $this->postJson("/api/preschool/monthly-submissions/{$submission->id}/archive");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.submission.status', PreschoolMonthlySubmissionStatus::ARCHIVED);

        // Verify archived record is still queryable (not soft-deleted)
        $archived = PreschoolMonthlySubmission::find($submission->id);
        $this->assertNotNull($archived);
        $this->assertEquals(PreschoolMonthlySubmissionStatus::ARCHIVED, $archived->status);
    }

    // ── Delete Endpoint ──────────────────────────────────────────────────────

    public function test_delete_succeeds_for_draft(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->state(['status' => PreschoolMonthlySubmissionStatus::DRAFT])
            ->create();

        Sanctum::actingAs($this->teacher);
        $response = $this->deleteJson("/api/preschool/monthly-submissions/{$submission->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('success', true);
    }

    public function test_delete_fails_for_submitted(): void
    {
        $submission = PreschoolMonthlySubmission::factory()
            ->for($this->academicYear, 'academicYear')
            ->for($this->class, 'class')
            ->for($this->category, 'category')
            ->state(['status' => PreschoolMonthlySubmissionStatus::SUBMITTED])
            ->create();

        Sanctum::actingAs($this->teacher);
        $response = $this->deleteJson("/api/preschool/monthly-submissions/{$submission->id}");

        $response->assertStatus(Response::HTTP_CONFLICT);
    }
}
