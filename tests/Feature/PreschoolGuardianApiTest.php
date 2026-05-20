<?php

namespace Tests\Feature;

use App\Models\PreschoolClass;
use App\Models\PreschoolGuardian;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolGuardianApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_create_guardian_and_link_student_relationship(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-g-100', 'preschool.guardian100@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-GUARD-100', 'Lina', 'Chan');
        $guardian = $this->postJson('/api/preschool/guardians', $this->guardianPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->json('data.guardian');

        $this->postJson("/api/preschool/students/{$student->id}/guardians", [
            'guardian_id' => $guardian['id'],
            'relationship_type' => 'mother',
            'is_primary' => true,
            'can_pickup' => true,
            'emergency_priority' => 1,
            'status' => 'active',
            'notes' => 'Primary pickup contact.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.relationship.isPrimary', true)
            ->assertJsonPath('data.relationship.canPickup', true);

        $this->getJson("/api/preschool/students/{$student->id}/guardians")
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_only_one_active_primary_guardian_is_kept_per_student(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-g-110', 'preschool.guardian110@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-GUARD-110', 'Pich', 'Sothea');
        $guardianOne = PreschoolGuardian::query()->create($this->guardianData('Guardian One', '+855 12 111 111'));
        $guardianTwo = PreschoolGuardian::query()->create($this->guardianData('Guardian Two', '+855 12 222 222'));

        $this->postJson("/api/preschool/students/{$student->id}/guardians", [
            'guardian_id' => $guardianOne->id,
            'relationship_type' => 'father',
            'is_primary' => true,
            'can_pickup' => true,
            'emergency_priority' => 1,
            'status' => 'active',
        ])->assertCreated();

        $this->postJson("/api/preschool/students/{$student->id}/guardians", [
            'guardian_id' => $guardianTwo->id,
            'relationship_type' => 'guardian',
            'is_primary' => true,
            'can_pickup' => true,
            'emergency_priority' => 2,
            'status' => 'active',
        ])->assertCreated();

        $this->assertSame(1, PreschoolStudentGuardian::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->where('is_primary', true)
            ->count());
    }

    public function test_duplicate_active_student_guardian_pair_is_blocked(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-g-120', 'preschool.guardian120@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-GUARD-120', 'Nita', 'Ly');
        $guardian = PreschoolGuardian::query()->create($this->guardianData('Guardian', '+855 12 333 333'));

        $this->postJson("/api/preschool/students/{$student->id}/guardians", [
            'guardian_id' => $guardian->id,
            'relationship_type' => 'mother',
            'is_primary' => false,
            'can_pickup' => true,
            'emergency_priority' => 1,
            'status' => 'active',
        ])->assertCreated();

        $this->postJson("/api/preschool/students/{$student->id}/guardians", [
            'guardian_id' => $guardian->id,
            'relationship_type' => 'mother',
            'is_primary' => false,
            'can_pickup' => true,
            'emergency_priority' => 2,
            'status' => 'active',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_emergency_contacts_are_ordered_by_priority(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-g-130', 'preschool.guardian130@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-GUARD-130', 'Dara', 'Kim');
        $guardianOne = PreschoolGuardian::query()->create($this->guardianData('Guardian One', '+855 12 444 444'));
        $guardianTwo = PreschoolGuardian::query()->create($this->guardianData('Guardian Two', '+855 12 555 555'));

        $this->linkRelationship($student, $guardianOne, 3, false, $admin->id);
        $this->linkRelationship($student, $guardianTwo, 1, true, $admin->id);

        $this->getJson("/api/preschool/students/{$student->id}/emergency-contacts")
            ->assertOk()
            ->assertJsonPath('data.items.0.emergencyPriority', 1)
            ->assertJsonPath('data.items.0.isPrimary', true);
    }

    public function test_teacher_can_view_guardians_for_assigned_student_but_not_unrelated_student(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-g-140', 'preschool.guardian140@hfccf.org');
        Sanctum::actingAs($teacher);

        $class = $this->createClass('PS-GUARD-140', 'Assigned Class', $teacher->id);
        $student = $this->createStudent('PS-GUARD-141', 'Assigned', 'Student', [$class->id]);
        $guardian = PreschoolGuardian::query()->create($this->guardianData('Guardian', '+855 12 666 666'));
        $this->linkRelationship($student, $guardian, 1, true, $teacher->id);

        $this->getJson("/api/preschool/students/{$student->id}/guardians")
            ->assertOk()
            ->assertJsonCount(1, 'data.items');

        $otherStudent = $this->createStudent('PS-GUARD-142', 'Other', 'Student');
        $this->getJson("/api/preschool/students/{$otherStudent->id}/guardians")
            ->assertForbidden();
    }

    public function test_teacher_cannot_modify_guardians(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-g-150', 'preschool.guardian150@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->postJson('/api/preschool/guardians', $this->guardianPayload())
            ->assertForbidden();
    }

    public function test_unauthorized_users_are_blocked_from_guardian_management(): void
    {
        $coach = $this->makeUserWithRole('coach', 'psc-g-160', 'preschool.guardian160@hfccf.org');
        Sanctum::actingAs($coach);

        $this->getJson('/api/preschool/guardians')
            ->assertForbidden();
    }

    private function makeUserWithRole(string $roleCode, string $id, string $email): User
    {
        $role = Role::query()->with('permissions')->findOrFail($roleCode);

        $user = User::query()->create([
            'id' => $id,
            'first_name' => ucfirst(str_replace('-', ' ', $roleCode)),
            'last_name' => 'User',
            'username' => $roleCode.'-'.$id,
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

    private function createClass(string $code, string $name, ?string $teacherId = null): PreschoolClass
    {
        return PreschoolClass::query()->create([
            'code' => $code,
            'name' => $name,
            'teacher_user_id' => $teacherId,
            'teacher_display_name' => $teacherId ? 'Assigned Teacher' : null,
            'level' => 'Nursery',
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 0,
            'status' => 'active',
            'room' => 'Room A1',
            'notes' => null,
        ]);
    }

    private function createStudent(string $code, string $firstName, string $lastName, array $classIds = []): PreschoolStudent
    {
        $student = PreschoolStudent::query()->create([
            'student_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'other',
            'date_of_birth' => now()->subYears(4)->toDateString(),
            'guardian_name' => 'Legacy Guardian',
            'guardian_phone' => '+855 12 777 777',
            'address' => 'Phnom Penh',
            'status' => 'active',
        ]);

        if ($classIds !== []) {
            $student->classes()->sync(
                collect($classIds)->mapWithKeys(static fn ($classId) => [
                    $classId => [
                        'enrolled_at' => now(),
                        'status' => 'active',
                    ],
                ])->all(),
            );
        }

        return $student;
    }

    private function guardianPayload(): array
    {
        return [
            'full_name' => 'Guardian One',
            'phone' => '+855 12 111 222',
            'secondary_phone' => '+855 12 111 333',
            'email' => 'guardian@example.com',
            'address' => 'Phnom Penh',
            'occupation' => 'Business',
            'national_id' => 'NID-001',
            'status' => 'active',
            'notes' => 'Created for test coverage.',
        ];
    }

    private function guardianData(string $name, string $phone): array
    {
        return array_merge($this->guardianPayload(), [
            'full_name' => $name,
            'phone' => $phone,
        ]);
    }

    private function linkRelationship(PreschoolStudent $student, PreschoolGuardian $guardian, int $priority, bool $primary, string $userId): void
    {
        PreschoolStudentGuardian::query()->create([
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship_type' => 'guardian',
            'is_primary' => $primary,
            'can_pickup' => true,
            'emergency_priority' => $priority,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'notes' => 'Seeded for test coverage.',
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ]);
    }
}
