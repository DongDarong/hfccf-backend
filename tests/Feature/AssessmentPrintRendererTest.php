<?php

namespace Tests\Feature;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentExportLog;
use App\Models\AssessmentFormSection;
use App\Models\AssessmentFormTemplate;
use App\Models\AssessmentFormVersion;
use App\Models\AssessmentPrintTemplate;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionType;
use App\Models\AssessmentRiskLevel;
use App\Models\AssessmentSubmission;
use App\Models\AssessmentSubmissionScore;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Services\AssessmentPrintRenderService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssessmentPrintRendererTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_print_template_render_service_builds_html_and_pdf_outputs(): void
    {
        $admin = $this->makeAdmin();
        $template = $this->createTemplate($admin->id);
        $version = $this->createVersion($template->id, $admin->id);
        $questionType = $this->createQuestionType();
        $section = $this->createSection($template->id);
        $question = $this->createQuestion($template->id, $section->id, $questionType->id);
        $student = $this->createStudent();
        $riskLevel = $this->createRiskLevel($template->id);
        $submission = $this->createSubmission($template->id, $version->id, $student->id, $admin->id, $riskLevel->id);

        AssessmentAnswer::query()->create([
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'question_code' => $question->code,
            'answer_text' => 'Yes',
            'score_value' => 4,
        ]);

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

        $renderer = app(AssessmentPrintRenderService::class);

        $htmlTemplate = $this->createPrintTemplate($template->id, $admin->id, 'html');
        $html = $renderer->renderHtml($submission->fresh(), $htmlTemplate);
        $this->assertStringContainsString('Sophea Chan', $html);
        $this->assertStringContainsString('Low Risk', $html);
        $this->assertStringContainsString('សំណួរ 1', $html);
        $this->assertStringContainsString('Yes', $html);

        $pdfTemplate = $this->createPrintTemplate($template->id, $admin->id, 'pdf');
        $artifact = $renderer->renderArtifact($submission->fresh(), $pdfTemplate);
        $this->assertSame('application/pdf', $artifact['mime']);
        $this->assertStringStartsWith('%PDF-1.4', $artifact['content']);
    }

    public function test_submission_print_endpoint_generates_print_template_artifact(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $template = $this->createTemplate($admin->id);
        $version = $this->createVersion($template->id, $admin->id);
        $questionType = $this->createQuestionType();
        $section = $this->createSection($template->id);
        $question = $this->createQuestion($template->id, $section->id, $questionType->id);
        $student = $this->createStudent();
        $riskLevel = $this->createRiskLevel($template->id);
        $submission = $this->createSubmission($template->id, $version->id, $student->id, $admin->id, $riskLevel->id);

        AssessmentAnswer::query()->create([
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'question_code' => $question->code,
            'answer_text' => 'Yes',
            'score_value' => 4,
        ]);

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

        $printTemplate = $this->createPrintTemplate($template->id, $admin->id, 'html');

        $response = $this->postJson("/api/assessment/submissions/{$submission->id}/print", [
            'template_id' => $printTemplate->id,
        ]);

        $response->assertOk();
        $exportLog = AssessmentExportLog::query()->latest('id')->first();
        $this->assertNotNull($exportLog);
        $this->assertSame((string) $printTemplate->id, (string) $exportLog->print_template_id);
        $this->assertSame('completed', $exportLog->status);
        Storage::disk('local')->assertExists($exportLog->file_path);

        $html = Storage::disk('local')->get($exportLog->file_path);
        $this->assertStringContainsString('Sophea Chan', $html);
        $this->assertStringContainsString('Low Risk', $html);
        $this->assertStringContainsString('សំណួរ 1', $html);
    }

    public function test_print_template_preview_endpoint_renders_unsaved_template_payload(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/assessment/print-templates/preview', [
            'template' => [
                'name' => 'Preview Template',
                'format' => 'html',
                'page_size' => 'A4',
                'orientation' => 'portrait',
                'margin_top' => 20,
                'margin_right' => 20,
                'margin_bottom' => 20,
                'margin_left' => 20,
                'font_family' => 'Khmer',
                'font_size' => 11,
                'header_html' => '<div>Report for {{student_name}}</div>',
                'footer_html' => '<div>Generated at {{generated_at}}</div>',
                'show_logo' => true,
                'show_qr_code' => false,
                'show_watermark' => false,
                'watermark_text' => '',
                'blocks' => [
                    ['type' => 'header'],
                    ['type' => 'student_info'],
                    ['type' => 'score_summary'],
                    ['type' => 'signature_box'],
                    ['type' => 'footer'],
                ],
                'styles' => '.print-footer { text-align: center; }',
            ],
            'preview_data' => [
                'student_name' => 'Sophea Chan',
                'student_code' => 'PS-001',
                'gender' => 'Female',
                'date_of_birth' => '2020-01-01',
                'guardian_name' => 'Guardian',
                'guardian_phone' => '+855 12 111 111',
                'address' => 'Phnom Penh',
                'assessment_date' => '2026-05-23',
                'total_score' => 82,
                'section_score' => 24,
                'risk_level' => 'Low Risk',
                'assessor' => 'Assessment Officer',
                'reviewer' => 'Supervisor',
                'approver' => 'Director',
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.source', 'template');

        $html = $response->json('data.html');
        $this->assertIsString($html);
        $this->assertStringContainsString('Sophea Chan', $html);
        $this->assertStringContainsString('Low Risk', $html);
        $this->assertStringContainsString('Assessment Officer', $html);
        $this->assertStringContainsString('Generated at', $html);
    }

    private function makeAdmin(): User
    {
        return User::factory()->asAdminPreschool()->create([
            'id' => 'usr_print_1',
            'email' => 'assessment-print-admin@hfccf.test',
            'first_name' => 'Assessment',
            'last_name' => 'Admin',
            'username' => 'assessment-print-admin',
            'phone' => '+855 12 345 679',
        ]);
    }

    private function createTemplate(string $userId): AssessmentFormTemplate
    {
        return AssessmentFormTemplate::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'ASSESS-PRN-001',
            'name' => 'Assessment Print Test Form',
            'name_kh' => 'ទម្រង់តេស្តបោះពុម្ព',
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

    private function createQuestionType(): AssessmentQuestionType
    {
        return AssessmentQuestionType::query()->firstOrCreate([
            'key' => 'short_text',
        ], [
            'key' => 'short_text',
            'label' => 'Short Text',
            'renderer' => 'text',
            'has_options' => false,
            'has_scoring' => false,
            'has_matrix' => false,
            'is_file' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function createSection(int $templateId): AssessmentFormSection
    {
        return AssessmentFormSection::query()->create([
            'template_id' => $templateId,
            'code' => 'SEC-1',
            'title' => 'Section 1',
            'title_kh' => 'ផ្នែក 1',
            'sort_order' => 1,
            'print_visible' => true,
        ]);
    }

    private function createQuestion(int $templateId, int $sectionId, int $questionTypeId): AssessmentQuestion
    {
        return AssessmentQuestion::query()->create([
            'uuid' => (string) Str::uuid(),
            'template_id' => $templateId,
            'section_id' => $sectionId,
            'question_type_id' => $questionTypeId,
            'code' => 'Q1',
            'label' => 'Question 1',
            'label_kh' => 'សំណួរ 1',
            'sort_order' => 1,
            'print_visible' => true,
        ]);
    }

    private function createStudent(): PreschoolStudent
    {
        return PreschoolStudent::query()->create([
            'student_code' => 'PS-PRN-001',
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

    private function createPrintTemplate(int $formTemplateId, string $userId, string $format): AssessmentPrintTemplate
    {
        return AssessmentPrintTemplate::query()->create([
            'uuid' => (string) Str::uuid(),
            'form_template_id' => $formTemplateId,
            'name' => 'Official Print Template',
            'name_kh' => 'គំរូបោះពុម្ពផ្លូវការ',
            'format' => $format,
            'page_size' => 'A4',
            'orientation' => 'portrait',
            'margin_top' => 20,
            'margin_right' => 20,
            'margin_bottom' => 20,
            'margin_left' => 20,
            'font_family' => 'Khmer',
            'font_size' => 11,
            'header_html' => '<div>Report for {{student_name}}</div>',
            'footer_html' => '<div>Generated at {{generated_at}}</div>',
            'blocks' => [
                ['type' => 'header'],
                ['type' => 'student_info'],
                ['type' => 'answers_table'],
                ['type' => 'score_summary'],
                ['type' => 'risk_badge'],
                ['type' => 'signature_box'],
                ['type' => 'footer'],
            ],
            'styles' => '.print-footer { text-align: center; }',
            'is_default' => true,
            'status' => 'published',
            'created_by' => $userId,
        ]);
    }
}
