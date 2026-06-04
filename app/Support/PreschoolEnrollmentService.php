<?php

namespace App\Support;

use App\Events\PaymentCreatedOnEnrollment;
use App\Models\PreschoolAcademicTerm;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolClass;
use App\Models\PreschoolEnrollmentApplication;
use App\Models\PreschoolEnrollmentDecisionLog;
use App\Models\PreschoolEnrollmentDocument;
use App\Models\PreschoolGuardian;
use App\Models\PreschoolPayment;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * PreschoolEnrollmentService
 *
 * Encapsulates all business logic for moving a PreschoolEnrollmentApplication
 * through its status lifecycle: draft → submitted → under_review →
 * approved → enrolled (or rejected / waitlisted / cancelled).
 *
 * The enrollAsStudent() method is the core transaction that converts an
 * approved application into a live student record, guardian link, class
 * assignment, and auto-generated tuition payment.
 */
class PreschoolEnrollmentService
{
    /**
     * Injected lifecycle service used to resolve the current academic year and
     * term when auto-generating a payment on enrollment. Injected via constructor
     * so the dependency can be swapped in tests.
     *
     * @var PreschoolAcademicLifecycleService
     */
    private PreschoolAcademicLifecycleService $lifecycleService;

    /**
     * The PreschoolPayment created during the most recent enrollAsStudent() call.
     * Exposed as a public property so the calling controller can include payment
     * data in its response without requiring a second DB query.
     * Null if the last enrollment had no class assignment (no payment is created).
     *
     * @var PreschoolPayment|null
     */
    public ?PreschoolPayment $lastCreatedPayment = null;

    /**
     * @param PreschoolAcademicLifecycleService $lifecycleService
     */
    public function __construct(PreschoolAcademicLifecycleService $lifecycleService)
    {
        $this->lifecycleService = $lifecycleService;
    }

    /**
     * Generate a human-readable application code: ENR-YYYYMMDD-XXXX.
     *
     * @return string
     */
    public function generateApplicationCode(): string
    {
        // Generate a human-readable application code: ENR-YYYYMMDD-XXXX
        $date = now()->format('Ymd');
        $suffix = strtoupper(Str::random(4));

        return "ENR-{$date}-{$suffix}";
    }

    /**
     * Seed the document checklist rows for a new application.
     * Required documents are marked; optional ones default is_required=false.
     *
     * @param  PreschoolEnrollmentApplication  $application
     * @return void
     */
    public function seedDocumentChecklist(PreschoolEnrollmentApplication $application): void
    {
        $required = ['birth_certificate', 'photo', 'consent_form'];
        $optional = ['family_book', 'vaccination_card', 'parent_id'];

        $rows = [];
        foreach ([...$required, ...$optional] as $type) {
            $rows[] = [
                'application_id' => $application->id,
                'document_type' => $type,
                'is_required' => in_array($type, $required, true),
                'is_received' => false,
            ];
        }

        PreschoolEnrollmentDocument::insert($rows);
    }

    /**
     * Record a status transition in the decision log.
     *
     * @param  PreschoolEnrollmentApplication  $application
     * @param  string  $action       Verb describing the transition (e.g. 'approved')
     * @param  string|null  $fromStatus
     * @param  string  $toStatus
     * @param  User|null  $actor
     * @param  string|null  $note
     * @param  array<string, mixed>  $context
     * @return void
     */
    public function logDecision(
        PreschoolEnrollmentApplication $application,
        string $action,
        ?string $fromStatus,
        string $toStatus,
        ?User $actor,
        ?string $note = null,
        array $context = []
    ): void {
        PreschoolEnrollmentDecisionLog::create([
            'application_id' => $application->id,
            'action'         => $action,
            'from_status'    => $fromStatus,
            'to_status'      => $toStatus,
            'actor_user_id'  => $actor?->id,
            'actor_role'     => $actor?->role_code,
            'note'           => $note,
            'context'        => $context ?: null,
            'recorded_at'    => now(),
        ]);
    }

    /**
     * Validate that an application is in one of the allowed states before a transition.
     * Returns a 422 JsonResponse if the status is not allowed; null if the check passes.
     *
     * @param  PreschoolEnrollmentApplication  $application
     * @param  list<string>  $allowed
     * @return JsonResponse|null
     */
    public function assertStatus(PreschoolEnrollmentApplication $application, array $allowed): ?JsonResponse
    {
        if (!in_array($application->status, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => "Application is '{$application->status}' and cannot be transitioned from that state.",
                'data'    => null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    /**
     * Enroll an approved applicant as an active PreschoolStudent.
     *
     * All five writes run within a single DB::transaction().
     * If any step fails, the entire operation rolls back — no partial
     * student records, orphaned guardian links, or stray payment rows.
     *
     * Side effects (in order, all within transaction):
     *  1. Creates PreschoolStudent record
     *  2. Creates PreschoolGuardian + PreschoolStudentGuardian pivot
     *  3. Assigns student to class via syncWithoutDetaching()
     *  4. Increments class students_count
     *  5. Creates pending PreschoolPayment for current term tuition
     *
     * Post-commit:
     *  6. Dispatches PaymentCreatedOnEnrollment event (only when a class and
     *     therefore a payment were created)
     *
     * @param  PreschoolEnrollmentApplication  $application
     * @param  User  $actor  Admin performing the enrollment
     * @param  int|null  $classId
     * @param  int|null  $academicYearId
     * @param  int|null  $termId
     * @return PreschoolStudent
     * @throws \Throwable  Rolls back transaction on any failure
     */
    public function enrollAsStudent(
        PreschoolEnrollmentApplication $application,
        User $actor,
        ?int $classId = null,
        ?int $academicYearId = null,
        ?int $termId = null
    ): PreschoolStudent {
        // Rejected applications must never create active students
        if ($application->status === 'rejected') {
            throw new \RuntimeException('Cannot enroll a rejected application.');
        }

        // Resolve academic context (year ID + term ID) before opening the
        // transaction — this involves read-only queries and should not be
        // retried inside the write transaction.
        $context = $this->lifecycleService->currentContext();
        $resolvedAcademicYearId = $academicYearId ?? $context['academic_year_id'] ?? null;
        $resolvedTermId = $termId ?? $context['term_id'] ?? null;

        // Reset the payment property so stale data from a previous call is never
        // accidentally exposed if the new enrollment has no class assignment.
        $this->lastCreatedPayment = null;

        // All enrollment writes are atomic. Any failure rolls back
        // student creation, guardian linking, class assignment,
        // and payment creation together.
        $student = DB::transaction(function () use (
            $application,
            $actor,
            $classId,
            $resolvedAcademicYearId,
            $resolvedTermId
        ): PreschoolStudent {
            // ── Step 1: Create the student record from application data ────────
            $student = PreschoolStudent::create([
                'student_code'   => PreschoolStudent::nextStudentCode(),
                'first_name'     => $application->first_name,
                'last_name'      => $application->last_name,
                'gender'         => $application->gender,
                'date_of_birth'  => $application->date_of_birth,
                'guardian_name'  => $application->guardian_name,
                'guardian_phone' => $application->guardian_phone,
                'address'        => $application->guardian_address,
                'status'         => 'active',
            ]);

            // ── Step 2: Create guardian record and link to student ─────────────
            if ($application->guardian_name || $application->guardian_phone) {
                $guardian = PreschoolGuardian::create([
                    'full_name'          => $application->guardian_name ?? '',
                    'phone'              => $application->guardian_phone ?? '',
                    'email'              => $application->guardian_email,
                    'address'            => $application->guardian_address,
                    'status'             => 'active',
                    'created_by_user_id' => $actor->id,
                ]);

                // Link guardian to student via pivot with relationship metadata
                PreschoolStudentGuardian::create([
                    'student_id'         => $student->id,
                    'guardian_id'        => $guardian->id,
                    'relationship_type'  => $application->guardian_relationship ?? 'parent',
                    'is_primary'         => true,
                    'can_pickup'         => $application->guardian_can_pickup,
                    'emergency_priority' => $application->guardian_is_emergency ? 1 : null,
                    'status'             => 'active',
                    'created_by_user_id' => $actor->id,
                ]);
            }

            // ── Steps 3, 4, 5: Assign class, increment count, create payment ──
            if ($classId) {
                $class = PreschoolClass::find($classId);

                if ($class && $class->status === 'active') {
                    // Attach student to class via pivot with full enrollment context
                    $student->classes()->syncWithoutDetaching([
                        $classId => [
                            'enrollment_status'     => 'active',
                            'enrolled_at'           => now(),
                            'academic_year_id'      => $resolvedAcademicYearId,
                            'term_id'               => $resolvedTermId,
                            'enrollment_started_at' => now(),
                        ],
                    ]);

                    // Keep class student count accurate after the new enrollment
                    $class->increment('students_count');

                    // ── Step 5: Auto-create pending payment for current term ────
                    // Resolve year and term objects for their human-readable labels
                    // used in the description field. IDs were resolved above via
                    // currentContext(); these model lookups reuse the cached query.
                    $year = $this->lifecycleService->currentAcademicYear();
                    $term = $this->lifecycleService->currentTerm($year?->id);

                    // Generate a unique payment reference: PAY-YYYYMMDD-<uuid4-prefix>
                    $paymentRef = 'PAY-' . now()->format('Ymd') . '-' . strtoupper(substr(Str::uuid()->toString(), 0, 8));

                    $payment = PreschoolPayment::create([
                        'student_id'        => $student->id,
                        'class_id'          => $class->id,
                        'academic_year_id'  => $resolvedAcademicYearId,
                        'term_id'           => $resolvedTermId,
                        'payment_reference' => $paymentRef,
                        'amount'            => $class->tuition_fee ?? 0,
                        'currency'          => 'USD',
                        // maps to payment_status — column name preserved to avoid
                        // breaking existing queries and frontend payment references
                        'payment_status'    => 'pending',
                        'due_date'          => $term?->end_date ?? null,
                        'description'       => 'Tuition fee — '
                            . ($term?->name ?? '')
                            . ' '
                            . ($year?->label ?? ''),
                        'created_by'        => $actor->id,
                    ]);

                    // Expose the payment to the calling controller without changing
                    // the method's return type (still PreschoolStudent).
                    $this->lastCreatedPayment = $payment;
                }
            }

            return $student;
        });

        // ── Post-commit: dispatch event ────────────────────────────────────────
        // Only dispatch when a payment was actually created (class assignment
        // was present and the class was active). Event fires after the
        // transaction commits so listeners always see fully-persisted records.
        if ($this->lastCreatedPayment !== null) {
            event(new PaymentCreatedOnEnrollment($this->lastCreatedPayment, $student));
        }

        return $student;
    }

}
