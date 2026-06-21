<?php

namespace Tests\Feature;

use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolSchoolCalendarEvent;
use App\Models\Role;
use App\Models\User;
use App\Support\PreschoolAttendanceConfigurationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolAttendanceConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_superadmin_can_get_default_attendance_settings(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin', 'usr_pas_100', 'superadmin.settings100@hfccf.org');
        Sanctum::actingAs($superadmin);

        $response = $this->getJson('/api/preschool/settings/attendance');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.settings.late_threshold_minutes', 15)
            ->assertJsonPath('data.settings.half_day_threshold_minutes', 180)
            ->assertJsonPath('data.settings.absence_alert_days', 3)
            ->assertJsonPath('data.settings.school_week.monday_enabled', true)
            ->assertJsonPath('data.settings.school_week.friday_enabled', true)
            ->assertJsonPath('data.settings.school_week.saturday_enabled', false)
            ->assertJsonPath('data.settings.school_week.sunday_enabled', false);
    }

    public function test_adminpreschool_can_update_settings(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pas_101', 'adminpreschool.settings101@hfccf.org');
        Sanctum::actingAs($admin);

        $payload = $this->settingsPayload([
            'late_threshold_minutes' => 20,
            'half_day_threshold_minutes' => 200,
            'absence_alert_days' => 4,
            'monday_enabled' => true,
            'tuesday_enabled' => true,
            'wednesday_enabled' => true,
            'thursday_enabled' => true,
            'friday_enabled' => false,
            'saturday_enabled' => false,
            'sunday_enabled' => false,
        ]);

        $response = $this->putJson('/api/preschool/settings/attendance', $payload);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.settings.late_threshold_minutes', 20)
            ->assertJsonPath('data.settings.absence_alert_days', 4)
            ->assertJsonPath('data.settings.school_week.friday_enabled', false);

        $this->assertDatabaseHas('preschool_attendance_settings', [
            'late_threshold_minutes' => 20,
            'half_day_threshold_minutes' => 200,
            'absence_alert_days' => 4,
            'friday_enabled' => 0,
        ]);
    }

    public function test_settings_update_persists(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pas_102', 'adminpreschool.settings102@hfccf.org');
        Sanctum::actingAs($admin);

        $payload = $this->settingsPayload([
            'late_threshold_minutes' => 25,
            'half_day_threshold_minutes' => 240,
            'absence_alert_days' => 6,
            'guardian_alert_enabled' => true,
            'teacher_alert_enabled' => false,
            'admin_alert_enabled' => true,
        ]);

        $this->putJson('/api/preschool/settings/attendance', $payload)
            ->assertOk()
            ->assertJsonPath('data.settings.late_threshold_minutes', 25);

        $this->assertDatabaseHas('preschool_attendance_settings', [
            'late_threshold_minutes' => 25,
            'half_day_threshold_minutes' => 240,
            'absence_alert_days' => 6,
            'teacher_alert_enabled' => 0,
        ]);

        $this->getJson('/api/preschool/settings/attendance')
            ->assertOk()
            ->assertJsonPath('data.settings.late_threshold_minutes', 25)
            ->assertJsonPath('data.settings.half_day_threshold_minutes', 240)
            ->assertJsonPath('data.settings.absence_alert_days', 6);
    }

    public function test_teacher_preschool_forbidden_from_settings(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_pas_103', 'teacher.settings103@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->getJson('/api/preschool/settings/attendance')
            ->assertForbidden();
    }

    public function test_unrelated_admin_forbidden_from_settings(): void
    {
        $admin = $this->makeUserWithRole('adminenglish', 'usr_pas_104', 'adminenglish.settings104@hfccf.org');
        Sanctum::actingAs($admin);

        $this->getJson('/api/preschool/settings/attendance')
            ->assertForbidden();
    }

    public function test_calendar_event_can_be_created(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pas_105', 'adminpreschool.settings105@hfccf.org');
        Sanctum::actingAs($admin);
        $academicYear = $this->createAcademicYear('AY-2026', '2026 - 2027');

        $response = $this->postJson('/api/preschool/settings/attendance/calendar-events', [
            'academic_year_id' => $academicYear->id,
            'title' => 'Independence Day',
            'description' => 'National holiday',
            'type' => 'holiday',
            'start_date' => '2026-09-24',
            'end_date' => '2026-09-24',
            'status' => 'active',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event.title', 'Independence Day')
            ->assertJsonPath('data.event.type', 'holiday');

        $this->assertDatabaseHas('preschool_school_calendar_events', [
            'title' => 'Independence Day',
            'academic_year_id' => $academicYear->id,
            'type' => 'holiday',
            'status' => 'active',
        ]);
    }

    public function test_calendar_event_validates_date_range(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pas_106', 'adminpreschool.settings106@hfccf.org');
        Sanctum::actingAs($admin);
        $academicYear = $this->createAcademicYear('AY-2027', '2027 - 2028', '2027-01-01', '2027-12-31');

        $this->postJson('/api/preschool/settings/attendance/calendar-events', [
            'academic_year_id' => $academicYear->id,
            'title' => 'Out of range holiday',
            'type' => 'holiday',
            'start_date' => '2026-12-31',
            'end_date' => '2027-01-02',
        ])
            ->assertUnprocessable()
            ->assertJsonFragment(['The event dates must fall within the selected academic year.']);
    }

    public function test_calendar_event_can_be_updated(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pas_107', 'adminpreschool.settings107@hfccf.org');
        Sanctum::actingAs($admin);
        $academicYear = $this->createAcademicYear('AY-2028', '2028 - 2029');
        $event = $this->createCalendarEvent($academicYear->id, 'Teacher Training', 'teacher_training', '2028-02-01', '2028-02-01');

        $this->putJson('/api/preschool/settings/attendance/calendar-events/'.$event->id, [
            'title' => 'Updated Teacher Training',
            'status' => 'active',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event.title', 'Updated Teacher Training');

        $this->assertDatabaseHas('preschool_school_calendar_events', [
            'id' => $event->id,
            'title' => 'Updated Teacher Training',
        ]);
    }

    public function test_calendar_event_can_be_archived(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pas_108', 'adminpreschool.settings108@hfccf.org');
        Sanctum::actingAs($admin);
        $academicYear = $this->createAcademicYear('AY-2029', '2029 - 2030');
        $event = $this->createCalendarEvent($academicYear->id, 'Special Event', 'special_event', '2029-05-10', '2029-05-10');

        $this->postJson('/api/preschool/settings/attendance/calendar-events/'.$event->id.'/archive')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event.status', 'archived');

        $this->assertDatabaseHas('preschool_school_calendar_events', [
            'id' => $event->id,
            'status' => 'archived',
        ]);
    }

    public function test_dashboard_attendance_summary_returns_live_values(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pas_109', 'adminpreschool.settings109@hfccf.org');
        Sanctum::actingAs($admin);
        $academicYear = $this->createAcademicYear('AY-2030', '2030 - 2031', '2030-01-01', '2030-12-31');

        $service = app(PreschoolAttendanceConfigurationService::class);
        $service->updateSettings($this->settingsPayload([
            'late_threshold_minutes' => 12,
            'half_day_threshold_minutes' => 210,
            'absence_alert_days' => 7,
            'monday_enabled' => true,
            'tuesday_enabled' => true,
            'wednesday_enabled' => false,
            'thursday_enabled' => false,
            'friday_enabled' => true,
            'saturday_enabled' => false,
            'sunday_enabled' => false,
        ]), $admin);
        $service->createCalendarEvent([
            'academic_year_id' => $academicYear->id,
            'title' => 'Holiday One',
            'type' => 'holiday',
            'start_date' => '2030-02-01',
            'end_date' => '2030-02-01',
            'status' => 'active',
        ], $admin);
        $service->createCalendarEvent([
            'academic_year_id' => $academicYear->id,
            'title' => 'Teacher Training',
            'type' => 'teacher_training',
            'start_date' => '2030-03-01',
            'end_date' => '2030-03-01',
            'status' => 'active',
        ], $admin);

        $this->getJson('/api/preschool/settings/dashboard')
            ->assertOk()
            ->assertJsonPath('data.dashboard.attendance.late_threshold_minutes', 12)
            ->assertJsonPath('data.dashboard.attendance.absence_alert_days', 7)
            ->assertJsonPath('data.dashboard.attendance.school_days_per_week', 3)
            ->assertJsonPath('data.dashboard.attendance.calendar_events_count', 2)
            ->assertJsonPath('data.dashboard.attendance.school_week.0', 'monday')
            ->assertJsonPath('data.dashboard.attendance.school_week.2', 'friday');
    }

    public function test_service_helper_is_school_day_respects_school_week(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pas_110', 'adminpreschool.settings110@hfccf.org');
        $service = app(PreschoolAttendanceConfigurationService::class);
        $service->updateSettings($this->settingsPayload([
            'monday_enabled' => true,
            'tuesday_enabled' => false,
            'wednesday_enabled' => false,
            'thursday_enabled' => false,
            'friday_enabled' => false,
            'saturday_enabled' => false,
            'sunday_enabled' => false,
        ]), $admin);

        $this->assertTrue($service->isSchoolDay('2026-06-22'));
        $this->assertFalse($service->isSchoolDay('2026-06-23'));
    }

    public function test_service_helper_is_holiday_respects_calendar_events(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pas_111', 'adminpreschool.settings111@hfccf.org');
        $service = app(PreschoolAttendanceConfigurationService::class);
        $academicYear = $this->createAcademicYear('AY-2031', '2031 - 2032', '2031-01-01', '2031-12-31');
        $service->createCalendarEvent([
            'academic_year_id' => $academicYear->id,
            'title' => 'National Holiday',
            'type' => 'holiday',
            'start_date' => '2031-06-01',
            'end_date' => '2031-06-01',
            'status' => 'active',
        ], $admin);

        $this->assertTrue($service->isHoliday('2031-06-01'));
        $this->assertFalse($service->isHoliday('2031-06-02'));
    }

    private function makeUserWithRole(string $roleCode, string $id, string $email): User
    {
        $role = Role::query()->with('permissions')->findOrFail($roleCode);

        $user = User::query()->create([
            'id' => $id,
            'first_name' => ucfirst(str_replace('-', ' ', $roleCode)),
            'last_name' => 'User',
            'username' => ucfirst(str_replace('-', ' ', $roleCode)).' User',
            'email' => $email,
            'phone' => '+855 12 555 555',
            'role_code' => $role->code,
            'department_code' => $role->department_code,
            'status' => 'active',
            'password' => 'secret-pass',
        ]);

        $rows = $role->permissions->map(static fn ($permission) => [
            'user_id' => $user->id,
            'permission_code' => $permission->code,
        ])->all();

        if ($rows !== []) {
            DB::table('user_permissions')->insert($rows);
        }

        return $user;
    }

    private function createAcademicYear(string $code, string $label, string $start = '2026-01-01', string $end = '2026-12-31'): PreschoolAcademicYear
    {
        return PreschoolAcademicYear::query()->create([
            'code' => $code,
            'label' => $label,
            'description' => 'Test academic year',
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'active',
            'is_current' => true,
            'notes' => null,
        ]);
    }

    private function createCalendarEvent(int $academicYearId, string $title, string $type, string $startDate, string $endDate): PreschoolSchoolCalendarEvent
    {
        return PreschoolSchoolCalendarEvent::query()->create([
            'academic_year_id' => $academicYearId,
            'title' => $title,
            'description' => null,
            'type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'active',
        ]);
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'late_threshold_minutes' => 15,
            'half_day_threshold_minutes' => 180,
            'absence_alert_days' => 3,
            'guardian_alert_enabled' => true,
            'teacher_alert_enabled' => true,
            'admin_alert_enabled' => true,
            'monday_enabled' => true,
            'tuesday_enabled' => true,
            'wednesday_enabled' => true,
            'thursday_enabled' => true,
            'friday_enabled' => true,
            'saturday_enabled' => false,
            'sunday_enabled' => false,
        ], $overrides);
    }
}
