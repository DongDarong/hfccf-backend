<?php

namespace Tests\Feature;

use App\Jobs\GenerateAssessmentExportJob;
use App\Models\AssessmentExportLog;
use App\Models\AssessmentFormTemplate;
use App\Models\AssessmentFormVersion;
use App\Models\AssessmentSubmission;
use App\Models\AssessmentSubmissionScore;
use App\Models\AssessmentRiskLevel;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Services\AssessmentExportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssessmentExportPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_export_service_generates_pdf_xlsx_and_zip_artifacts(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin();
        $template = $this->createTemplate($admin->id);
        $version = $this->createVersion($template->id, $admin->id);
        $student = $this->createStudent();
        $riskLevel = $this->createRiskLevel($template->id);
        $submission = $this->createSubmission($template->id, $version->id, $student->id, $admin->id, $riskLevel->id);

        AssessmentSubmissionScore::query()->create([
            'submission_id' => $submission->id,
            'scope' => 'template',
            'scope_id' => $template->id,
            'raw_score' => 82,
            'max_score' => 100,
            'weighted_score' => 82,
            'percentage' => 82,
            'risk_level_id' => $riskLevel->id,
        ]);

        $service = app(AssessmentExportService::class);

        foreach (['pdf', 'xlsx', 'zip'] as $format) {
            $exportLog = AssessmentExportLog::query()->create([
                'uuid' => (string) Str::uuid(),
                'initiated_by' => $admin->id,
                'export_type' => $format === 'xlsx' ? 'excel' : $format,
                'scope' => 'report',
                'status' => 'queued',
                'started_at' => now(),
                'expires_at' => now()->addDay(),
                'meta' => ['test' => true],
            ]);

            $result = $service->generate($exportLog);

            $this->assertSame('completed', $result->status);
            $this->assertNotEmpty($result->file_path);
            Storage::disk('local')->assertExists($result->file_path);

            $content = Storage::disk('local')->get($result->file_path);
            $this->assertIsString($content);

            if ($format === 'pdf') {
                $this->assertStringStartsWith('%PDF-1.4', $content);
            } elseif ($format === 'xlsx' || $format === 'zip') {
                $this->assertStringStartsWith('PK', $content);
            }
        }
    }

    public function test_report_export_queue_dispatches_job_and_returns_accepted_response(): void
    {
        Queue::fake();

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $template = $this->createTemplate($admin->id);
        $version = $this->createVersion($template->id, $admin->id);
        $student = $this->createStudent();
        $riskLevel = $this->createRiskLevel($template->id);
        $submission = $this->createSubmission($template->id, $version->id, $student->id, $admin->id, $riskLevel->id);

        AssessmentSubmissionScore::query()->create([
            'submission_id' => $submission->id,
            'scope' => 'template',
            'scope_id' => $template->id,
            'raw_score' => 75,
            'max_score' => 100,
            'weighted_score' => 75,
            'percentage' => 75,
            'risk_level_id' => $riskLevel->id,
        ]);

        $response = $this->getJson('/api/assessment/reports/export?format=pdf&queue=1');

        $response->assertAccepted()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'queued');

        Queue::assertPushedTimes(GenerateAssessmentExportJob::class, 1);
    }

    private function makeAdmin(): User
    {
        return User::factory()->asAdminPreschool()->create([
            'id' => 'usr_exp_1',
            'email' => 'assessment-export-admin@hfccf.test',
            'first_name' => 'Assessment',
            'last_name' => 'Admin',
            'username' => 'assessment-export-admin',
            'phone' => '+855 12 345 678',
        ]);
    }

    private function createTemplate(string $userId): AssessmentFormTemplate
    {
        return AssessmentFormTemplate::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'ASSESS-EXP-001',
            'name' => 'Assessment Export Test Form',
            'module' => 'preschool',
            'status' => 'published',
            'created_by' => $userId,
        ]);
    }

    private function createVersion(int $templateId, string $userId): AssessmentFormVersion
    {
        return AssessmentFormVersion::query()->create([
            'template_id' => $templateId,
            'version_number' => 1,
            'label' => 'v1',
            'snapshot' => ['template' => ['id' => $templateId]],
            'published_at' => now(),
            'published_by' => $userId,
            'is_current' => true,
        ]);
    }

    private function createRiskLevel(int $templateId): AssessmentRiskLevel
    {
        return AssessmentRiskLevel::query()->create([
            'template_id' => $templateId,
            'label' => 'Low Risk',
            'key' => 'low-risk',
            'min_score' => 0,
            'max_score' => 100,
            'color_code' => '#22c55e',
            'sort_order' => 1,
        ]);
    }

    private function createStudent(): PreschoolStudent
    {
        return PreschoolStudent::query()->create([
            'student_code' => 'PS-EXP-001',
            'first_name' => 'Sophea',
            'last_name' => 'Chan',
            'gender' => 'female',
            'date_of_birth' => '2020-01-01',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '+855 12 111 111',
            'address' => 'Phnom Penh',
            'status' => 'active',
        ]);
    }

    private function createSubmission(int $templateId, int $versionId, int $studentId, string $userId, int $riskLevelId): AssessmentSubmission
    {
        return AssessmentSubmission::query()->create([
            'uuid' => (string) Str::uuid(),
            'template_id' => $templateId,
            'version_id' => $versionId,
            'student_id' => $studentId,
            'assessor_id' => $userId,
            'reviewer_id' => null,
            'approver_id' => null,
            'status' => 'approved',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'approved_at' => now(),
            'risk_level_id' => $riskLevelId,
            'risk_override' => false,
            'meta' => ['source' => 'test'],
        ]);
    }
}
