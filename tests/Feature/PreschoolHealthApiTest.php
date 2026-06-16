<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolHealthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_update_medical_profile(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', [
            'first_name' => 'Health',
            'last_name' => 'Admin',
            'username' => 'health-admin',
            'email' => 'preschool.health.admin@hfccf.org',
            'phone' => '+855 12 600 001',
        ]);
        Sanctum::actingAs($admin);

        $student = $this->createStudent('STU-HEALTH-001', 'Ava', 'Chan');

        $response = $this->putJson("/api/preschool/students/{$student->id}/health/medical-profile", [
            'blood_type' => 'A+',
            'chronic_conditions' => ['Asthma'],
            'current_conditions' => ['Recovering from cough'],
            'medical_notes' => 'Needs daily inhaler reminder.',
            'status' => 'active',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.medicalProfile.blood_type', 'A+');

        $this->assertDatabaseHas('preschool_student_medical_profiles', [
            'student_id' => $student->id,
            'blood_type' => 'A+',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_crud_allergy(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', [
            'first_name' => 'Health',
            'last_name' => 'Admin',
            'username' => 'health-admin-2',
            'email' => 'preschool.health.admin2@hfccf.org',
            'phone' => '+855 12 600 002',
        ]);
        Sanctum::actingAs($admin);

        $student = $this->createStudent('STU-HEALTH-002', 'Ben', 'Lim');

        $create = $this->postJson("/api/preschool/students/{$student->id}/health/allergies", [
            'allergy_name' => 'Peanuts',
            'allergy_type' => 'food',
            'severity' => 'critical',
            'reaction' => 'Swelling',
            'action_taken' => 'Called guardian and monitored breathing.',
            'status' => 'active',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.allergy.allergy_name', 'Peanuts');

        $allergyId = $create->json('data.allergy.id');

        $this->putJson("/api/preschool/students/{$student->id}/health/allergies/{$allergyId}", [
            'severity' => 'high',
            'notes' => 'Use caution at snack time.',
        ])
            ->assertOk()
            ->assertJsonPath('data.allergy.severity', 'high');

        $this->deleteJson("/api/preschool/students/{$student->id}/health/allergies/{$allergyId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson("/api/preschool/students/{$student->id}/health/allergies")
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_admin_can_crud_vaccination(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', [
            'first_name' => 'Health',
            'last_name' => 'Admin',
            'username' => 'health-admin-3',
            'email' => 'preschool.health.admin3@hfccf.org',
            'phone' => '+855 12 600 003',
        ]);
        Sanctum::actingAs($admin);

        $student = $this->createStudent('STU-HEALTH-003', 'Cara', 'Long');

        $create = $this->postJson("/api/preschool/students/{$student->id}/health/vaccinations", [
            'vaccine_name' => 'MMR',
            'vaccination_date' => '2026-05-10',
            'status' => 'completed',
            'dose_number' => 1,
            'provider' => 'HFCCF Clinic',
            'notes' => 'First dose recorded.',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.vaccination.vaccine_name', 'MMR');

        $vaccinationId = $create->json('data.vaccination.id');

        $this->putJson("/api/preschool/students/{$student->id}/health/vaccinations/{$vaccinationId}", [
            'status' => 'pending',
            'notes' => 'Verify second dose schedule.',
        ])
            ->assertOk()
            ->assertJsonPath('data.vaccination.status', 'pending');

        $this->deleteJson("/api/preschool/students/{$student->id}/health/vaccinations/{$vaccinationId}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_crud_medication(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', [
            'first_name' => 'Health',
            'last_name' => 'Admin',
            'username' => 'health-admin-4',
            'email' => 'preschool.health.admin4@hfccf.org',
            'phone' => '+855 12 600 004',
        ]);
        Sanctum::actingAs($admin);

        $student = $this->createStudent('STU-HEALTH-004', 'Dara', 'Nim');

        $create = $this->postJson("/api/preschool/students/{$student->id}/health/medications", [
            'medication_name' => 'Amoxicillin',
            'dosage' => '5 ml',
            'frequency' => 'Twice daily',
            'route' => 'Oral',
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-20',
            'status' => 'active',
            'notes' => 'After meals only.',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.medication.medication_name', 'Amoxicillin');

        $medicationId = $create->json('data.medication.id');

        $this->putJson("/api/preschool/students/{$student->id}/health/medications/{$medicationId}", [
            'status' => 'completed',
            'notes' => 'Completed course.',
        ])
            ->assertOk()
            ->assertJsonPath('data.medication.status', 'completed');

        $this->deleteJson("/api/preschool/students/{$student->id}/health/medications/{$medicationId}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_crud_emergency_contact(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', [
            'first_name' => 'Health',
            'last_name' => 'Admin',
            'username' => 'health-admin-5',
            'email' => 'preschool.health.admin5@hfccf.org',
            'phone' => '+855 12 600 005',
        ]);
        Sanctum::actingAs($admin);

        $student = $this->createStudent('STU-HEALTH-005', 'Ema', 'Sok');

        $create = $this->postJson("/api/preschool/students/{$student->id}/health/emergency-contacts", [
            'name' => 'Guardian One',
            'relationship' => 'Mother',
            'phone' => '+855 12 700 001',
            'secondary_phone' => '+855 12 700 002',
            'priority' => 1,
            'is_primary' => true,
            'receive_alerts' => true,
            'status' => 'active',
            'notes' => 'Primary emergency contact.',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.contact.name', 'Guardian One');

        $contactId = $create->json('data.contact.id');

        $this->putJson("/api/preschool/students/{$student->id}/health/emergency-contacts/{$contactId}", [
            'phone' => '+855 12 700 111',
            'notes' => 'Updated phone number.',
        ])
            ->assertOk()
            ->assertJsonPath('data.contact.phone', '+855 12 700 111');

        $this->deleteJson("/api/preschool/students/{$student->id}/health/emergency-contacts/{$contactId}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_log_and_delete_health_check(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', [
            'first_name' => 'Health',
            'last_name' => 'Admin',
            'username' => 'health-admin-6',
            'email' => 'preschool.health.admin6@hfccf.org',
            'phone' => '+855 12 600 006',
        ]);
        Sanctum::actingAs($admin);

        $student = $this->createStudent('STU-HEALTH-006', 'Fia', 'Khim');

        $create = $this->postJson("/api/preschool/students/{$student->id}/health/check-logs", [
            'checked_at' => '2026-05-14',
            'temperature_celsius' => 37.2,
            'weight_kg' => 15.5,
            'height_cm' => 102.4,
            'symptoms' => 'Mild cough',
            'remarks' => 'Sent home after observation.',
            'status' => 'recorded',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.healthCheck.status', 'recorded');

        $checkId = $create->json('data.healthCheck.id');

        $this->deleteJson("/api/preschool/students/{$student->id}/health/check-logs/{$checkId}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_teacher_can_access_assigned_student_health_but_cannot_edit_admin_only_records(): void
    {
        $teacher = $this->createUserWithRole('teacher-preschool', [
            'first_name' => 'Health',
            'last_name' => 'Teacher',
            'username' => 'health-teacher',
            'email' => 'preschool.health.teacher@hfccf.org',
            'phone' => '+855 12 600 007',
        ]);
        Sanctum::actingAs($teacher);

        $class = $this->createClass('PS-CLASS-HEALTH-001', 'Health Class', $teacher->id, trim($teacher->first_name.' '.$teacher->last_name));
        $student = $this->createStudent('STU-HEALTH-007', 'Gia', 'Rin');
        $this->attachStudentToClass($class->id, $student->id);

        $otherClass = $this->createClass('PS-CLASS-HEALTH-002', 'Other Class');
        $otherStudent = $this->createStudent('STU-HEALTH-008', 'Hana', 'Ly');
        $this->attachStudentToClass($otherClass->id, $otherStudent->id);

        $this->getJson("/api/preschool/students/{$student->id}/health/summary")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson("/api/preschool/students/{$student->id}/health/incidents", [
            'incident_date' => '2026-05-15',
            'incident_type' => 'Headache',
            'severity' => 'medium',
            'action_taken' => 'Observed in classroom and notified admin.',
            'follow_up_needed' => true,
            'notes' => 'Teacher logged incident.',
            'status' => 'open',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->postJson("/api/preschool/students/{$student->id}/health/check-logs", [
            'checked_at' => '2026-05-15',
            'temperature_celsius' => 36.9,
            'symptoms' => 'Tired',
            'remarks' => 'Basic wellness check logged by teacher.',
            'status' => 'recorded',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->putJson("/api/preschool/students/{$student->id}/health/medical-profile", [
            'blood_type' => 'B+',
        ])->assertForbidden();

        $this->getJson("/api/preschool/students/{$otherStudent->id}/health/summary")
            ->assertForbidden();
    }

    private function createStudent(string $publicId, string $firstName, string $lastName): object
    {
        $studentId = DB::table('preschool_students')->insertGetId([
            'public_id' => $publicId,
            'student_code' => 'PS-'.strtoupper(substr($publicId, -4)),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'student_type' => 'paying',
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

    private function createClass(string $code, string $name, ?string $teacherId = null, ?string $teacherDisplayName = null): object
    {
        $classId = DB::table('preschool_classes')->insertGetId([
            'code' => $code,
            'name' => $name,
            'teacher_user_id' => $teacherId,
            'teacher_display_name' => $teacherDisplayName,
            'level' => 'Nursery',
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 0,
            'status' => 'active',
            'room' => 'Room A1',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('preschool_classes')->where('id', $classId)->first();
    }

    private function attachStudentToClass(int $classId, int $studentId): void
    {
        DB::table('preschool_class_students')->insert([
            'class_id' => $classId,
            'student_id' => $studentId,
            'enrolled_at' => now(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
