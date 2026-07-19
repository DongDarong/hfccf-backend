<?php

namespace Tests\Feature\Preschool;

use App\Models\PreschoolAttendanceSession;
use App\Models\PreschoolClass;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Support\BusinessTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolAttendanceSessionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_cannot_write_the_legacy_date_based_route(): void
    {
        $this->seedReferenceData();
        $teacher = User::factory()->asTeacherPreschool()->create();
        $class = $this->createClass($teacher);
        $student = $this->createStudent();
        Sanctum::actingAs($teacher);

        $response = $this->postJson('/api/preschool/attendance', [
            'class_id' => $class->id,
            'student_id' => $student->id,
            'attendance_date' => '2026-07-25',
            'status' => 'present',
        ]);

        $response->assertStatus(410)->assertJsonPath('error.code', 'LEGACY_WRITE_DISABLED');
    }

    public function test_teacher_can_write_only_inside_an_assigned_open_window(): void
    {
        $this->seedReferenceData();
        $teacher = User::factory()->asTeacherPreschool()->create();
        $class = $this->createClass($teacher);
        $student = $this->createStudent();
        $this->attachStudent($class->id, $student->id);
        $session = $this->createSession($class, $teacher, '2026-07-25 01:00:00', '2026-07-25 03:00:00');

        Carbon::setTestNow(Carbon::parse('2026-07-25T01:00:00Z'));
        Sanctum::actingAs($teacher);
        $this->putJson('/api/preschool/attendance-sessions/'.$session->id.'/attendance', [
            'records' => [['participantId' => $student->id, 'status' => 'present']],
        ])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-07-25T03:00:00Z'));
        $this->putJson('/api/preschool/attendance-sessions/'.$session->id.'/attendance', [
            'records' => [['participantId' => $student->id, 'status' => 'late']],
        ])->assertStatus(409)->assertJsonPath('error.code', 'SESSION_WINDOW_CLOSED');
        Carbon::setTestNow();
    }

    public function test_bulk_submission_rejects_duplicate_participants_atomically(): void
    {
        $this->seedReferenceData();
        $teacher = User::factory()->asTeacherPreschool()->create();
        $class = $this->createClass($teacher);
        $student = $this->createStudent();
        $this->attachStudent($class->id, $student->id);
        $session = $this->createSession($class, $teacher, '2026-07-25 01:00:00', '2026-07-25 03:00:00');
        Carbon::setTestNow(Carbon::parse('2026-07-25T01:30:00Z'));
        Sanctum::actingAs($teacher);

        $this->putJson('/api/preschool/attendance-sessions/'.$session->id.'/attendance', [
            'records' => [
                ['participantId' => $student->id, 'status' => 'present'],
                ['participantId' => $student->id, 'status' => 'absent'],
            ],
        ])->assertStatus(422)->assertJsonPath('error.code', 'DUPLICATE_PARTICIPANT_IN_PAYLOAD');

        $this->assertDatabaseCount('preschool_attendance_records', 0);
        Carbon::setTestNow();
    }

    private function seedReferenceData(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    private function createClass(User $teacher): PreschoolClass
    {
        return PreschoolClass::query()->create([
            'code' => 'C1-'.fake()->unique()->numerify('###'), 'name' => 'C1 Class',
            'teacher_user_id' => $teacher->id, 'teacher_display_name' => $teacher->name,
            'level' => 'Nursery', 'students_count' => 1, 'status' => 'active', 'room' => 'A1',
        ]);
    }

    private function createStudent(): PreschoolStudent
    {
        return PreschoolStudent::query()->create([
            'student_code' => 'C1-'.fake()->unique()->numerify('###'), 'first_name' => 'Test', 'last_name' => 'Student',
            'gender' => 'female', 'date_of_birth' => '2020-01-01', 'guardian_name' => 'Guardian',
            'guardian_phone' => '+855 12 000 000', 'address' => 'Phnom Penh', 'status' => 'active',
        ]);
    }

    private function attachStudent(int $classId, int $studentId): void
    {
        DB::table('preschool_class_students')->insert([
            'class_id' => $classId, 'student_id' => $studentId, 'status' => 'active', 'enrollment_status' => 'active',
            'enrollment_started_at' => '2026-01-01 00:00:00', 'enrollment_ended_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function createSession(PreschoolClass $class, User $teacher, string $opensAt, string $closesAt): PreschoolAttendanceSession
    {
        return PreschoolAttendanceSession::query()->create([
            'session_code' => 'C1-'.fake()->unique()->numerify('######'), 'preschool_class_id' => $class->id,
            'teacher_user_id' => $teacher->id, 'attendance_date' => '2026-07-25',
            'opens_at' => $opensAt, 'closes_at' => $closesAt, 'start_time' => '08:00:00', 'end_time' => '10:00:00',
            'status' => 'open', 'session_key' => 'c1:'.fake()->unique()->numerify('######'),
        ]);
    }
}
