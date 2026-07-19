<?php

namespace Tests\Feature\Preschool;

use App\Models\Department;
use App\Models\PreschoolAttendanceSession;
use App\Models\PreschoolClass;
use App\Models\PreschoolStudent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolAttendanceAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
        Carbon::setTestNow(Carbon::parse('2026-07-01 08:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_session_based_absence_records_are_persisted_for_follow_up_processing(): void
    {
        $admin = User::factory()->asAdminPreschool()->create();
        Sanctum::actingAs($admin);

        $class = PreschoolClass::query()->create([
            'code' => 'PS-999',
            'name' => 'Alerts Class',
            'teacher_user_id' => null,
            'teacher_display_name' => null,
            'level' => 'Preschool',
            'schedule' => null,
            'students_count' => 0,
            'tuition_fee' => '0.00',
            'status' => 'active',
            'room' => 'Room A',
            'notes' => null,
        ]);
        $student = PreschoolStudent::query()->create([
            'first_name' => 'Absent',
            'last_name' => 'Student',
            'gender' => 'female',
            'date_of_birth' => Carbon::now()->subYears(4)->toDateString(),
            'guardian_name' => 'Guardian',
            'guardian_phone' => '012345678',
            'address' => 'Phnom Penh',
            'status' => 'active',
            'student_type' => 'regular',
            'avatar' => null,
        ]);
        DB::table('preschool_class_students')->insert([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'status' => 'active',
            'enrollment_status' => 'active',
            'enrollment_started_at' => Carbon::now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $session = PreschoolAttendanceSession::query()->create([
            'preschool_class_id' => $class->id,
            'attendance_date' => Carbon::now()->toDateString(),
            'start_time' => '07:00:00',
            'end_time' => '10:00:00',
            'status' => 'open',
            'generated_from_schedule' => true,
            'session_key' => 'class:'.$class->id.':'.Carbon::now()->toDateString().':manual',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-07-01T01:00:00Z'));

        $response = $this->postJson('/api/preschool/attendance-sessions/'.$session->id.'/records', [
            'records' => [
                [
                    'student_id' => $student->id,
                    'status' => 'absent',
                    'note' => 'Absent for follow-up',
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('preschool_attendance_records', [
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'absent',
        ]);
    }

    private function seedReferenceData(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            DB::table('departments')->updateOrInsert(
                ['code' => 'education'],
                [
                    'name' => 'Education',
                    'display_order' => 1,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            DB::table('roles')->updateOrInsert(
                ['code' => 'adminpreschool'],
                [
                    'name' => 'Admin Preschool',
                    'scope' => 'admin',
                    'domain_code' => 'preschool',
                    'department_code' => 'education',
                    'sort_order' => 10,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            DB::table('roles')->updateOrInsert(
                ['code' => 'teacher-preschool'],
                [
                    'name' => 'Teacher Preschool',
                    'scope' => 'staff',
                    'domain_code' => 'preschool',
                    'department_code' => 'education',
                    'sort_order' => 20,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }
}





