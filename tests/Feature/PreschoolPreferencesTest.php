<?php

namespace Tests\Feature;

use App\Models\PreschoolPreferences;
use App\Models\User;
use App\Support\PreschoolPreferencesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_get_default_preferences(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $response = $this->getJson('/api/preschool/settings/preferences');

        $response->assertOk()
            ->assertJsonPath('data.settings.timezone', 'Asia/Phnom_Penh')
            ->assertJsonPath('data.settings.default_language', 'en')
            ->assertJsonPath('data.settings.minimum_enrollment_age_months', 24)
            ->assertJsonPath('data.settings.student_code_prefix', 'PS')
            ->assertJsonPath('data.settings.waitlist_enabled', true);
    }

    public function test_adminpreschool_can_update_preferences(): void
    {
        Sanctum::actingAs($this->makeUser('adminpreschool'));

        $response = $this->putJson('/api/preschool/settings/preferences', [
            'timezone' => 'Asia/Phnom_Penh',
            'default_language' => 'kh',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'minimum_enrollment_age_months' => 30,
            'maximum_enrollment_age_months' => 72,
            'auto_approve_enrollment' => true,
            'student_code_prefix' => 'PRE',
            'student_code_year_format' => 'YYYY',
            'student_code_sequence_length' => 5,
            'default_class_capacity' => 20,
            'teacher_student_ratio' => 12,
            'waitlist_enabled' => false,
            'minimum_guardians' => 1,
            'maximum_guardians' => 3,
            'primary_guardian_required' => true,
            'pickup_authorization_required' => true,
            'attendance_alert_enabled' => true,
            'assessment_alert_enabled' => true,
            'health_alert_enabled' => true,
            'enrollment_notification_enabled' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.settings.default_language', 'kh')
            ->assertJsonPath('data.settings.student_code_prefix', 'PRE')
            ->assertJsonPath('data.settings.teacher_student_ratio', 12);

        $this->assertDatabaseHas('preschool_preferences', [
            'default_language' => 'kh',
            'student_code_prefix' => 'PRE',
            'teacher_student_ratio' => 12,
        ]);
    }

    public function test_teacher_preschool_is_forbidden(): void
    {
        Sanctum::actingAs($this->makeUser('teacher-preschool'));

        $this->getJson('/api/preschool/settings/preferences')->assertForbidden();
        $this->putJson('/api/preschool/settings/preferences', $this->preferencesPayload())->assertForbidden();
    }

    public function test_unrelated_admin_is_forbidden(): void
    {
        Sanctum::actingAs($this->makeUser('adminenglish'));

        $this->getJson('/api/preschool/settings/preferences')->assertForbidden();
    }

    public function test_helper_methods_use_config_values(): void
    {
        $service = app(PreschoolPreferencesService::class);

        $service->updatePreferences([
            'minimum_enrollment_age_months' => 18,
            'maximum_enrollment_age_months' => 54,
            'auto_approve_enrollment' => true,
            'student_code_prefix' => 'PS',
            'student_code_year_format' => 'YY',
            'student_code_sequence_length' => 6,
            'default_class_capacity' => 22,
            'teacher_student_ratio' => 11,
            'waitlist_enabled' => true,
            'minimum_guardians' => 2,
            'maximum_guardians' => 3,
            'primary_guardian_required' => false,
            'pickup_authorization_required' => false,
            'attendance_alert_enabled' => false,
            'assessment_alert_enabled' => true,
            'health_alert_enabled' => false,
            'enrollment_notification_enabled' => true,
            'timezone' => 'Asia/Phnom_Penh',
            'default_language' => 'en',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
        ]);

        $this->assertSame(18, $service->getMinimumEnrollmentAge());
        $this->assertSame(54, $service->getMaximumEnrollmentAge());
        $this->assertTrue($service->shouldAutoApproveEnrollment());
        $this->assertSame('PS', $service->getStudentCodePrefix());
        $this->assertSame('YY', $service->getStudentCodeYearFormat());
        $this->assertSame(6, $service->getStudentCodeSequenceLength());
        $this->assertSame('PS-'.date('y').'-000001', $service->generateStudentCode(1));
        $this->assertSame(22, $service->getDefaultClassCapacity());
        $this->assertSame(11, $service->getTeacherStudentRatio());
        $this->assertTrue($service->isWaitlistEnabled());
        $this->assertSame(2, $service->getMinimumGuardians());
        $this->assertSame(3, $service->getMaximumGuardians());
        $this->assertFalse($service->isPrimaryGuardianRequired());
        $this->assertFalse($service->isPickupAuthorizationRequired());
        $this->assertFalse($service->attendanceAlertsEnabled());
        $this->assertTrue($service->assessmentAlertsEnabled());
        $this->assertFalse($service->healthAlertsEnabled());
        $this->assertTrue($service->enrollmentNotificationsEnabled());
    }

    public function test_dashboard_summary_returns_live_values(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $response = $this->getJson('/api/preschool/settings/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.dashboard.preferences.minimum_enrollment_age_months', 24)
            ->assertJsonPath('data.dashboard.preferences.maximum_enrollment_age_months', 60)
            ->assertJsonPath('data.dashboard.preferences.student_code_prefix', 'PS')
            ->assertJsonPath('data.dashboard.preferences.default_class_capacity', 18)
            ->assertJsonPath('data.dashboard.preferences.waitlist_enabled', true)
            ->assertJsonPath('data.dashboard.preferences.enrollment_notification_enabled', true);
    }

    private function makeUser(string $roleCode): User
    {
        return User::query()->create([
            'name' => ucfirst(str_replace('-', ' ', $roleCode)),
            'email' => uniqid($roleCode.'_', true).'@example.test',
            'password' => 'password',
            'role_code' => $roleCode,
        ]);
    }

    private function preferencesPayload(): array
    {
        return [
            'timezone' => 'Asia/Phnom_Penh',
            'default_language' => 'en',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'minimum_enrollment_age_months' => 24,
            'maximum_enrollment_age_months' => 60,
            'auto_approve_enrollment' => false,
            'student_code_prefix' => 'PS',
            'student_code_year_format' => 'YYYY',
            'student_code_sequence_length' => 4,
            'default_class_capacity' => 18,
            'teacher_student_ratio' => 10,
            'waitlist_enabled' => true,
            'minimum_guardians' => 1,
            'maximum_guardians' => 2,
            'primary_guardian_required' => true,
            'pickup_authorization_required' => true,
            'attendance_alert_enabled' => true,
            'assessment_alert_enabled' => true,
            'health_alert_enabled' => true,
            'enrollment_notification_enabled' => true,
        ];
    }
}
