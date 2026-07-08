<?php

namespace Tests\Feature\Preschool;

use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolAttendanceSession;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolTeacherAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_teacher_preschool_can_record_scoped_attendance(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_610', 'teacher.preschool610@hfccf.org');
        Sanctum::actingAs($teacher);

        $class = $this->createPreschoolClass('PS-CLASS-610', 'Teacher Scope Class', $teacher->id);
        $student = $this->createPreschoolStudent('PS-STU-610', 'Scoped', 'Student');
        $this->attachStudentToClass($class->id, $student->id);

        $response = $this->postJson('/api/preschool/attendance', [
            'class_id' => $class->id,
            'student_id' => $student->id,
            'attendance_date' => '2026-05-14',
            'status' => 'present',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.attendance.classId', $class->id)
            ->assertJsonPath('data.attendance.studentId', $student->id);

        $attendanceId = $response->json('data.attendance.id');

        $this->putJson('/api/preschool/attendance/'.$attendanceId, [
            'status' => 'late',
        ])->assertOk()
            ->assertJsonPath('data.attendance.status', 'late');
    }

    public function test_teacher_preschool_cannot_spoof_unassigned_class_or_student(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_611', 'teacher.preschool611@hfccf.org');
        Sanctum::actingAs($teacher);

        $ownedClass = $this->createPreschoolClass('PS-CLASS-611', 'Owned Scope Class', $teacher->id);
        $foreignClass = $this->createPreschoolClass('PS-CLASS-612', 'Foreign Scope Class');
        $ownedStudent = $this->createPreschoolStudent('PS-STU-611', 'Owned', 'Student');
        $foreignStudent = $this->createPreschoolStudent('PS-STU-612', 'Foreign', 'Student');
        $this->attachStudentToClass($ownedClass->id, $ownedStudent->id);
        $this->attachStudentToClass($foreignClass->id, $foreignStudent->id);

        $this->postJson('/api/preschool/attendance', [
            'class_id' => $ownedClass->id,
            'student_id' => $foreignStudent->id,
            'attendance_date' => '2026-05-14',
            'status' => 'present',
        ])->assertForbidden();

        $this->postJson('/api/preschool/attendance', [
            'class_id' => $foreignClass->id,
            'student_id' => $foreignStudent->id,
            'attendance_date' => '2026-05-14',
            'status' => 'present',
        ])->assertForbidden();
    }

    public function test_teacher_preschool_only_sees_owned_classes_students_and_attendance(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_612', 'teacher.preschool612@hfccf.org');
        Sanctum::actingAs($teacher);

        $ownedClass = $this->createPreschoolClass('PS-CLASS-613', 'Owned Class', $teacher->id);
        $foreignClass = $this->createPreschoolClass('PS-CLASS-614', 'Foreign Class');
        $ownedStudent = $this->createPreschoolStudent('PS-STU-613', 'Owned', 'Student');
        $foreignStudent = $this->createPreschoolStudent('PS-STU-614', 'Foreign', 'Student');
        $this->attachStudentToClass($ownedClass->id, $ownedStudent->id);
        $this->attachStudentToClass($foreignClass->id, $foreignStudent->id);

        PreschoolAttendanceRecord::query()->create([
            'class_id' => $ownedClass->id,
            'student_id' => $ownedStudent->id,
            'attendance_date' => '2026-05-14',
            'status' => 'present',
            'recorded_by_user_id' => $teacher->id,
        ]);
        PreschoolAttendanceRecord::query()->create([
            'class_id' => $foreignClass->id,
            'student_id' => $foreignStudent->id,
            'attendance_date' => '2026-05-14',
            'status' => 'present',
            'recorded_by_user_id' => $teacher->id,
        ]);

        $this->getJson('/api/preschool/classes')
            ->assertOk()
            ->assertJsonPath('data.items.0.code', 'PS-CLASS-613')
            ->assertJsonMissing(['code' => 'PS-CLASS-614']);

        $this->getJson('/api/preschool/students?class_id='.$ownedClass->id)
            ->assertOk()
            ->assertJsonPath('data.items.0.studentCode', 'PS-STU-613')
            ->assertJsonMissing(['studentCode' => 'PS-STU-614']);

        $this->getJson('/api/preschool/attendance?class_id='.$ownedClass->id.'&attendance_date=2026-05-14')
            ->assertOk()
            ->assertJsonPath('data.items.0.classId', $ownedClass->id)
            ->assertJsonMissing(['classId' => $foreignClass->id]);
    }

    public function test_teacher_preschool_today_sessions_only_returns_assigned_classes_with_progress(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_613', 'teacher.preschool613@hfccf.org');
        Sanctum::actingAs($teacher);

        $ownedClass = $this->createPreschoolClass('PS-CLASS-615', 'Owned Today Class', $teacher->id, 18);
        $foreignClass = $this->createPreschoolClass('PS-CLASS-616', 'Foreign Today Class', null, 9);
        $ownedStudent = $this->createPreschoolStudent('PS-STU-615', 'Owned', 'Student');
        $foreignStudent = $this->createPreschoolStudent('PS-STU-616', 'Foreign', 'Student');
        $this->attachStudentToClass($ownedClass->id, $ownedStudent->id);
        $this->attachStudentToClass($foreignClass->id, $foreignStudent->id);

        $ownedSession = $this->createAttendanceSession($ownedClass->id, 'scheduled', Carbon::now()->toDateString(), 'class:'.$ownedClass->id.':'.Carbon::now()->toDateString().':manual-owned');
        $foreignSession = $this->createAttendanceSession($foreignClass->id, 'open', Carbon::now()->toDateString(), 'class:'.$foreignClass->id.':'.Carbon::now()->toDateString().':manual-foreign');

        $this->createAttendanceRecord($ownedSession->id, $ownedClass->id, $ownedStudent->id, 'present', $teacher->id);

        $response = $this->getJson('/api/preschool/attendance-sessions/today');

        $response->assertOk()
            ->assertJsonPath('data.items.0.classId', $ownedClass->id)
            ->assertJsonPath('data.items.0.studentCount', 18)
            ->assertJsonPath('data.items.0.recordedStudents', 1)
            ->assertJsonPath('data.items.0.missingStudents', 17)
            ->assertJsonPath('data.items.0.canRecord', true)
            ->assertJsonPath('data.items.0.canViewDetails', true)
            ->assertJsonMissing(['classId' => $foreignClass->id]);
    }

    public function test_teacher_preschool_today_sessions_returns_empty_payload_when_none_exist(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_613a', 'teacher.preschool613a@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->createPreschoolClass('PS-CLASS-619', 'Empty Today Class', $teacher->id, 14);

        $this->getJson('/api/preschool/attendance-sessions/today')
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.summary.scheduled', 0)
            ->assertJsonPath('data.summary.open', 0)
            ->assertJsonPath('data.summary.completed', 0)
            ->assertJsonPath('data.summary.locked', 0)
            ->assertJsonPath('data.summary.cancelled', 0)
            ->assertJsonPath('data.summary.missing', 0);
    }

    public function test_teacher_preschool_cannot_view_unassigned_session(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_614', 'teacher.preschool614@hfccf.org');
        Sanctum::actingAs($teacher);

        $foreignClass = $this->createPreschoolClass('PS-CLASS-617', 'Foreign View Class', null, 8);
        $foreignStudent = $this->createPreschoolStudent('PS-STU-617', 'Foreign', 'Student');
        $this->attachStudentToClass($foreignClass->id, $foreignStudent->id);

        $foreignSession = $this->createAttendanceSession($foreignClass->id, 'open', Carbon::now()->toDateString(), 'class:'.$foreignClass->id.':'.Carbon::now()->toDateString().':view-deny');
        $this->createAttendanceRecord($foreignSession->id, $foreignClass->id, $foreignStudent->id, 'present', $teacher->id);

        $this->getJson('/api/preschool/attendance-sessions/'.$foreignSession->id)->assertForbidden();
    }

    public function test_admin_and_superadmin_can_view_unassigned_session(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_615', 'admin.preschool615@hfccf.org');
        $superAdmin = $this->makeUserWithRole('superadmin', 'usr_616', 'superadmin615@hfccf.org');

        $foreignClass = $this->createPreschoolClass('PS-CLASS-618', 'Foreign Admin View Class', null, 6);
        $foreignStudent = $this->createPreschoolStudent('PS-STU-618', 'Foreign', 'Student');
        $this->attachStudentToClass($foreignClass->id, $foreignStudent->id);

        $foreignSession = $this->createAttendanceSession($foreignClass->id, 'completed', Carbon::now()->toDateString(), 'class:'.$foreignClass->id.':'.Carbon::now()->toDateString().':admin-view');
        $this->createAttendanceRecord($foreignSession->id, $foreignClass->id, $foreignStudent->id, 'present', $admin->id);

        Sanctum::actingAs($admin);
        $this->getJson('/api/preschool/attendance-sessions/'.$foreignSession->id)
            ->assertOk()
            ->assertJsonPath('data.attendanceSession.classId', $foreignClass->id);

        Sanctum::actingAs($superAdmin);
        $this->getJson('/api/preschool/attendance-sessions/'.$foreignSession->id)
            ->assertOk()
            ->assertJsonPath('data.attendanceSession.classId', $foreignClass->id);
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

    private function createPreschoolClass(string $code, string $name, ?string $teacherId = null, int $studentsCount = 0): object
    {
        $classId = DB::table('preschool_classes')->insertGetId([
            'code' => $code,
            'name' => $name,
            'teacher_user_id' => $teacherId,
            'teacher_display_name' => $teacherId ? 'Teacher User' : null,
            'level' => 'Nursery',
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => $studentsCount,
            'status' => 'active',
            'room' => 'Room A1',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('preschool_classes')->where('id', $classId)->first();
    }

    private function createPreschoolStudent(string $code, string $firstName, string $lastName): object
    {
        $studentId = DB::table('preschool_students')->insertGetId([
            'student_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'female',
            'date_of_birth' => '2020-01-01',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '+855 12 000 000',
            'address' => 'Phnom Penh',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('preschool_students')->where('id', $studentId)->first();
    }

    private function attachStudentToClass(int $classId, int $studentId): void
    {
        DB::table('preschool_class_students')->insert([
            'class_id' => $classId,
            'student_id' => $studentId,
            'enrolled_at' => now(),
            'status' => 'active',
            'enrollment_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAttendanceSession(int $classId, string $status, string $attendanceDate, string $sessionKey): PreschoolAttendanceSession
    {
        return PreschoolAttendanceSession::query()->create([
            'preschool_class_id' => $classId,
            'attendance_date' => $attendanceDate,
            'status' => $status,
            'generated_from_schedule' => false,
            'session_key' => $sessionKey,
        ]);
    }

    private function createAttendanceRecord(int $sessionId, int $classId, int $studentId, string $status, string $recordedByUserId): void
    {
        PreschoolAttendanceRecord::query()->create([
            'attendance_session_id' => $sessionId,
            'class_id' => $classId,
            'student_id' => $studentId,
            'attendance_date' => Carbon::now()->toDateString(),
            'status' => $status,
            'recorded_by_user_id' => $recordedByUserId,
        ]);
    }
}
