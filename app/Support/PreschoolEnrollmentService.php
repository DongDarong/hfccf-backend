<?php

namespace App\Support;

use App\Models\PreschoolAcademicTerm;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolClass;
use App\Models\PreschoolEnrollmentApplication;
use App\Models\PreschoolEnrollmentDecisionLog;
use App\Models\PreschoolEnrollmentDocument;
use App\Models\PreschoolGuardian;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PreschoolEnrollmentService
{
    // Generate a human-readable application code: ENR-YYYYMMDD-XXXX
    public function generateApplicationCode(): string
    {
        $date = now()->format('Ymd');
        $suffix = strtoupper(Str::random(4));

        return "ENR-{$date}-{$suffix}";
    }

    // Seed the document checklist rows for a new application.
    // Required documents are marked; optional ones default is_required=false.
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

    // Record a status transition in the decision log.
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
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_user_id' => $actor?->id,
            'actor_role' => $actor?->role_code,
            'note' => $note,
            'context' => $context ?: null,
            'recorded_at' => now(),
        ]);
    }

    // Validate that an application is in one of the allowed states before a transition.
    public function assertStatus(PreschoolEnrollmentApplication $application, array $allowed): ?JsonResponse
    {
        if (!in_array($application->status, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => "Application is '{$application->status}' and cannot be transitioned from that state.",
                'data' => null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    // Convert an approved application into an active student record.
    // Creates guardian, links them, assigns to class, and activates the student.
    // Only called when status transitions to 'enrolled'.
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

        $lifecycleService = app(PreschoolAcademicLifecycleService::class);
        $context = $lifecycleService->currentContext();

        $resolvedAcademicYearId = $academicYearId ?? $context['academic_year_id'] ?? null;
        $resolvedTermId = $termId ?? $context['term_id'] ?? null;

        // Create the student record from application data
        $student = PreschoolStudent::create([
            'student_code' => $this->nextStudentCode(),
            'first_name' => $application->first_name,
            'last_name' => $application->last_name,
            'gender' => $application->gender,
            'date_of_birth' => $application->date_of_birth,
            'guardian_name' => $application->guardian_name,
            'guardian_phone' => $application->guardian_phone,
            'address' => $application->guardian_address,
            'status' => 'active',
        ]);

        // Create or match guardian and link to student
        if ($application->guardian_name || $application->guardian_phone) {
            $guardian = PreschoolGuardian::create([
                'full_name' => $application->guardian_name ?? '',
                'phone' => $application->guardian_phone ?? '',
                'email' => $application->guardian_email,
                'address' => $application->guardian_address,
                'status' => 'active',
                'created_by_user_id' => $actor->id,
            ]);

            PreschoolStudentGuardian::create([
                'student_id' => $student->id,
                'guardian_id' => $guardian->id,
                'relationship_type' => $application->guardian_relationship ?? 'parent',
                'is_primary' => true,
                'can_pickup' => $application->guardian_can_pickup,
                'emergency_priority' => $application->guardian_is_emergency ? 1 : null,
                'status' => 'active',
                'created_by_user_id' => $actor->id,
            ]);
        }

        // Assign to class if provided and class is active
        if ($classId) {
            $class = PreschoolClass::find($classId);
            if ($class && $class->status === 'active') {
                $student->classes()->syncWithoutDetaching([
                    $classId => [
                        'enrollment_status' => 'active',
                        'enrolled_at' => now(),
                        'academic_year_id' => $resolvedAcademicYearId,
                        'term_id' => $resolvedTermId,
                        'enrollment_started_at' => now(),
                    ],
                ]);
                // Keep class student count accurate
                $class->increment('students_count');
            }
        }

        return $student;
    }

    private function nextStudentCode(): string
    {
        $last = PreschoolStudent::query()
            ->whereRaw("student_code LIKE 'PS-STU-%'")
            ->orderByRaw("CAST(SUBSTRING(student_code, 8) AS UNSIGNED) DESC")
            ->value('student_code');

        $next = $last ? (int) substr($last, 7) + 1 : 1;

        return sprintf('PS-STU-%03d', $next);
    }
}
