<?php

namespace Tests\Feature;

use App\Events\PaymentCreatedOnEnrollment;
use App\Models\PreschoolAcademicTerm;
use App\Models\PreschoolAcademicYear;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * EnrollStudentCreatesPaymentTest
 *
 * Verifies that enrolling an approved application via the enroll API endpoint:
 *  1. Persists a PreschoolStudent record
 *  2. Auto-creates a pending PreschoolPayment with the correct amount, status,
 *     student linkage, and term linkage
 *  3. Dispatches the PaymentCreatedOnEnrollment event after the transaction
 *
 * All writes run through the real HTTP stack so the full
 * controller → service → event chain is exercised.
 */
class EnrollStudentCreatesPaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the database (roles, permissions) before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Enrolling an approved application creates a student record, a pending
     * payment with the class tuition fee, and dispatches the enrollment event.
     *
     * @return void
     */
    public function test_enrolling_approved_application_creates_pending_payment(): void
    {
        // ── Arrange: fake event bus before any action so dispatches are captured
        Event::fake([PaymentCreatedOnEnrollment::class]);

        // Create the admin actor who will perform the enrollment
        $admin = $this->makeAdminUser('usr_enr_001', 'enr.admin001@hfccf.org');
        Sanctum::actingAs($admin);

        // Create an active academic year with a label for the payment description
        $year = PreschoolAcademicYear::create([
            'code'       => '2025-2026',
            'label'      => '2025–2026',
            'start_date' => '2025-09-01',
            'end_date'   => '2026-06-30',
            'status'     => 'active',
            'is_current' => true,
        ]);

        // Create an active term with a known end_date used as the payment due_date
        $term = PreschoolAcademicTerm::create([
            'academic_year_id' => $year->id,
            'code'             => 'T1-2025',
            'name'             => 'Term 1',
            'start_date'       => '2025-09-01',
            'end_date'         => '2025-12-31',
            'status'           => 'active',
            'is_current'       => true,
            'sort_order'       => 1,
        ]);

        // Create an active class with a known tuition_fee — this amount must
        // appear on the auto-generated payment row
        $classId = DB::table('preschool_classes')->insertGetId([
            'code'           => 'PS-ENR-TEST-001',
            'name'           => 'Test Enrollment Class',
            'level'          => 'Nursery',
            'schedule'       => 'Mon-Fri',
            'students_count' => 0,
            'tuition_fee'    => 150.00,
            'status'         => 'active',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Create an approved enrollment application — only approved applications
        // are eligible to be enrolled; the service guards against other statuses
        $applicationId = DB::table('preschool_enrollment_applications')->insertGetId([
            'application_code'        => 'ENR-TEST-0001',
            'first_name'              => 'Sophea',
            'last_name'               => 'Chan',
            'gender'                  => 'female',
            'date_of_birth'           => '2021-03-15',
            'guardian_name'           => 'Chan Makara',
            'guardian_phone'          => '+855 12 111 222',
            'guardian_address'        => 'Phnom Penh',
            'guardian_relationship'   => 'parent',
            'guardian_can_pickup'     => 1,
            'guardian_is_emergency'   => 1,
            'status'                  => 'approved',
            'application_date'        => now()->toDateString(),
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        // ── Act: POST to the enroll endpoint with class, year, and term context
        $response = $this->postJson("/api/preschool/enrollments/{$applicationId}/enroll", [
            'class_id'        => $classId,
            'academic_year_id'=> $year->id,
            'term_id'         => $term->id,
        ]);

        // ── Assert ────────────────────────────────────────────────────────────

        // The HTTP response must indicate success
        $response->assertOk()->assertJsonPath('success', true);

        // The student record must exist — confirms Step 1 of the transaction
        $this->assertDatabaseHas('preschool_students', [
            'first_name' => 'Sophea',
            'last_name'  => 'Chan',
            'status'     => 'active',
        ]);

        // Retrieve the created student so its ID can be used in payment assertions
        $student = DB::table('preschool_students')->where('first_name', 'Sophea')->first();

        // A pending payment must exist with the correct student linkage — confirms
        // the auto-generated payment was created inside the same transaction
        $this->assertDatabaseHas('preschool_payments', [
            'student_id'     => $student->id,
            'payment_status' => 'pending',
            'term_id'        => $term->id,
        ]);

        // The payment amount must equal the class tuition_fee — confirms the service
        // reads tuition_fee from the class and stores it on the payment row
        $payment = DB::table('preschool_payments')->where('student_id', $student->id)->first();
        $this->assertEquals('150.00', number_format((float) $payment->amount, 2));

        // The payment API response must expose the payment summary so the frontend
        // can display the confirmation message without a second request
        $response->assertJsonPath('data.payment.paymentStatus', 'pending');
        $response->assertJsonPath('data.payment.amount', '150.00');

        // The PaymentCreatedOnEnrollment event must have been dispatched exactly
        // once — confirms the post-commit event fires after the transaction closes
        Event::assertDispatched(PaymentCreatedOnEnrollment::class, function ($event) use ($student, $payment) {
            return $event->student->id === $student->id
                && $event->payment->id === $payment->id;
        });
    }

    /**
     * Enrolling without a class assignment creates a student but no payment,
     * and does NOT dispatch the PaymentCreatedOnEnrollment event.
     * This ensures the payment creation is guarded by class presence.
     *
     * @return void
     */
    public function test_enrolling_without_class_creates_no_payment_and_no_event(): void
    {
        // Capture all events before acting
        Event::fake([PaymentCreatedOnEnrollment::class]);

        $admin = $this->makeAdminUser('usr_enr_002', 'enr.admin002@hfccf.org');
        Sanctum::actingAs($admin);

        // Create an approved application with no preferred class
        $applicationId = DB::table('preschool_enrollment_applications')->insertGetId([
            'application_code'  => 'ENR-TEST-0002',
            'first_name'        => 'Dara',
            'last_name'         => 'Sok',
            'gender'            => 'male',
            'date_of_birth'     => '2021-06-10',
            'guardian_name'     => 'Sok Vanna',
            'guardian_phone'    => '+855 12 333 444',
            'guardian_address'  => 'Siem Reap',
            'status'            => 'approved',
            'application_date'  => now()->toDateString(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // ── Act: enroll with no class_id
        $response = $this->postJson("/api/preschool/enrollments/{$applicationId}/enroll", []);

        $response->assertOk()->assertJsonPath('success', true);

        // Student must be created despite the missing class
        $this->assertDatabaseHas('preschool_students', [
            'first_name' => 'Dara',
            'last_name'  => 'Sok',
        ]);

        $student = DB::table('preschool_students')->where('first_name', 'Dara')->first();

        // No payment row must exist — no class means no tuition fee can be resolved
        $this->assertDatabaseMissing('preschool_payments', [
            'student_id' => $student->id,
        ]);

        // The response payment field must be null — frontend handles this gracefully
        $response->assertJsonPath('data.payment', null);

        // Event must not be dispatched when there is no payment to attach to it
        Event::assertNotDispatched(PaymentCreatedOnEnrollment::class);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Create an adminpreschool user and sync their role permissions.
     *
     * @param  string  $id     Must be unique across the test suite (16 chars max)
     * @param  string  $email  Must be unique across the test suite
     * @return User
     */
    private function makeAdminUser(string $id, string $email): User
    {
        $role = Role::query()->with('permissions')->findOrFail('adminpreschool');

        $user = User::create([
            'id'              => $id,
            'first_name'      => 'Admin',
            'last_name'       => 'Test',
            'username'        => "admin_{$id}",
            'email'           => $email,
            'phone'           => '+855 12 000 001',
            'role_code'       => $role->code,
            'department_code' => $role->department_code,
            'status'          => 'active',
            'password'        => 'secret-pass',
        ]);

        // Sync the role's permissions to the user_permissions pivot table
        $rows = $role->permissions->map(static fn ($p) => [
            'user_id'          => $user->id,
            'permission_code'  => $p->code,
        ])->all();

        if ($rows !== []) {
            DB::table('user_permissions')->insert($rows);
        }

        return $user;
    }
}
