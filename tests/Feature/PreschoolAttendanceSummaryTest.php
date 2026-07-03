<?php

namespace Tests\Feature;

use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\PreschoolStudent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolAttendanceSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_attendance_summary_missing_and_class_summary_remain_compatible_with_legacy_records(): void
    {
        $admin = User::factory()->asAdminPreschool()->create([
            'email' => 'attendance-summary-admin@hfccf.org',
        ]);
        Sanctum::actingAs($admin);

        $class = $this->createClass('PS-ATT-SUM-001', 'Attendance Summary Class');
        $student = $this->createStudent('PS-ATT-STU-001', 'Amy', 'Summary');

        PreschoolAttendanceRecord::query()->create([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'attendance_session_id' => null,
            'recorded_by_user_id' => $admin->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
            'note' => 'Legacy attendance row',
            'academic_year_id' => null,
            'term_id' => null,
        ]);

        PreschoolAttendanceRecord::query()->create([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'attendance_session_id' => null,
            'recorded_by_user_id' => $admin->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'absent',
            'note' => 'Legacy attendance row 2',
            'academic_year_id' => null,
            'term_id' => null,
        ]);

        $summary = $this->getJson('/api/preschool/attendance/summary');
        $summary->assertOk()
            ->assertJsonPath('data.summary.totalRecords', 2)
            ->assertJsonPath('data.summary.present', 1)
            ->assertJsonPath('data.summary.absent', 1)
            ->assertJsonPath('data.summary.linkedSessions', 0)
            ->assertJsonPath('data.summary.legacyUnlinkedRecords', 2);

        $missing = $this->getJson('/api/preschool/attendance/missing');
        $missing->assertOk()
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.pagination.total', 2);

        $classSummary = $this->getJson('/api/preschool/attendance/class-summary');
        $classSummary->assertOk()
            ->assertJsonPath('data.summary.totalRecords', 2)
            ->assertJsonPath('data.summary.classes', 1)
            ->assertJsonPath('data.items.0.classId', $class->id)
            ->assertJsonPath('data.items.0.totalRecords', 2)
            ->assertJsonPath('data.items.0.present', 1)
            ->assertJsonPath('data.items.0.absent', 1);
    }

    private function createClass(string $code, string $name): PreschoolClass
    {
        return PreschoolClass::query()->create([
            'code' => $code,
            'name' => $name,
            'teacher_user_id' => null,
            'teacher_display_name' => null,
            'level' => 'Nursery',
            'schedule' => null,
            'students_count' => 0,
            'tuition_fee' => '0.00',
            'status' => 'active',
            'room' => 'Room A1',
            'notes' => null,
        ]);
    }

    private function createStudent(string $code, string $firstName, string $lastName): PreschoolStudent
    {
        return PreschoolStudent::query()->create([
            'student_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'female',
            'date_of_birth' => '2020-01-01',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '+855 12 000 000',
            'address' => 'Phnom Penh',
            'status' => 'active',
            'student_type' => 'regular',
        ]);
    }
}
