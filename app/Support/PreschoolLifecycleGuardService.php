<?php

namespace App\Support;

use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\PreschoolClassStudent;
use App\Models\PreschoolScheduleEntry;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAssessment;
use App\Models\User;
use App\Support\PreschoolAssessmentStatus;
use App\Support\PreschoolScheduleStatus;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Preschool lifecycle guards keep operational writes aligned with the active
 * academic year/term so closed periods remain audit-stable. Admin overrides
 * are explicit and remain available only to superadmin/adminpreschool.
 */
class PreschoolLifecycleGuardService
{
    public function attendanceWriteLock(
        ?User $actor,
        array $payload,
        ?PreschoolAttendanceRecord $attendance = null,
        ?PreschoolStudent $student = null,
    ): ?JsonResponse {
        $context = $this->resolveContextForDate($payload['attendance_date'] ?? $attendance?->attendance_date);
        if ($response = $this->guardClosedContext($actor, $context, $payload, 'This term is closed.')) {
            return $response;
        }

        $classId = (int) ($payload['class_id'] ?? $attendance?->class_id ?? 0);
        $studentId = (int) ($payload['student_id'] ?? $attendance?->student_id ?? $student?->id ?? 0);

        if ($classId > 0 && $studentId > 0) {
            $isActiveEnrollment = PreschoolClassStudent::query()
                ->where('class_id', $classId)
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->where('enrollment_status', 'active')
                ->exists();

            if (! $isActiveEnrollment && ! $this->canOverride($actor, $payload)) {
                return $this->conflict('This student enrollment is inactive.');
            }
        }

        return null;
    }

    public function assessmentWriteLock(
        ?User $actor,
        array $payload = [],
        ?PreschoolStudentAssessment $assessment = null,
        ?PreschoolStudent $student = null,
    ): ?JsonResponse {
        $context = $this->resolveContextForDate($payload['assessment_date'] ?? $assessment?->assessment_date);
        if ($response = $this->guardClosedContext($actor, $context, $payload, 'This term is closed.')) {
            return $response;
        }

        if ($assessment && in_array((string) $assessment->status, [PreschoolAssessmentStatus::FINALIZED, PreschoolAssessmentStatus::ARCHIVED], true)) {
            if (! $this->canOverride($actor, $payload)) {
                return $this->conflict('Finalized assessments cannot be edited.');
            }
        }

        $student = $student ?: $assessment?->student;
        if ($student && ! $this->isEnrollmentActive($student, $payload['class_id'] ?? $assessment?->class_id ?? null) && ! $this->canOverride($actor, $payload)) {
            return $this->conflict('This student enrollment is inactive.');
        }

        return null;
    }

    public function scheduleWriteLock(
        ?User $actor,
        array $payload = [],
        ?PreschoolScheduleEntry $schedule = null,
    ): ?JsonResponse {
        $context = $this->resolveContextForDate($payload['effective_from'] ?? $schedule?->effective_from);
        if ($response = $this->guardClosedContext($actor, $context, $payload, 'This term is closed.')) {
            return $response;
        }

        if ($schedule && in_array((string) $schedule->status, [PreschoolScheduleStatus::ARCHIVED], true) && ! $this->canOverride($actor, $payload)) {
            return $this->conflict('Archived schedules cannot be edited.');
        }

        return null;
    }

    public function assignmentWriteLock(
        ?User $actor,
        array $payload = [],
    ): ?JsonResponse {
        $context = $this->currentContext();
        if ($response = $this->guardClosedContext($actor, $context, $payload, 'This term is closed.')) {
            return $response;
        }

        return null;
    }

    public function lifecycleStateForDate(mixed $date = null): array
    {
        return $this->resolveContextForDate($date);
    }

    private function resolveContextForDate(mixed $date = null): array
    {
        if ($date === null || $date === '') {
            return $this->currentContext();
        }

        return app(PreschoolAcademicLifecycleService::class)->resolveForDate($date);
    }

    private function currentContext(): array
    {
        return app(PreschoolAcademicLifecycleService::class)->currentContext();
    }

    private function guardClosedContext(?User $actor, array $context, array $payload, string $message): ?JsonResponse
    {
        $yearLocked = in_array((string) ($context['academic_year_status'] ?? ''), ['closed', 'archived'], true);
        $termLocked = in_array((string) ($context['term_status'] ?? ''), ['closed', 'archived'], true);

        if (! $yearLocked && ! $termLocked) {
            return null;
        }

        if (! $this->canOverride($actor, $payload)) {
            return $this->conflict($message, $context);
        }

        return null;
    }

    private function canOverride(?User $actor, array $payload): bool
    {
        if (! ($payload['override_locked_context'] ?? false)) {
            return false;
        }

        if (! $actor || ! in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)) {
            return false;
        }

        return trim((string) ($payload['override_reason'] ?? '')) !== '';
    }

    private function isEnrollmentActive(PreschoolStudent $student, mixed $classId = null): bool
    {
        if (! in_array((string) $student->status, ['active', 'pending'], true)) {
            return false;
        }

        $query = PreschoolClassStudent::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->where('enrollment_status', 'active');

        if ($classId !== null && $classId !== '') {
            $query->where('class_id', $classId);
        }

        return $query->exists();
    }

    private function conflict(string $message, array $context = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => [
                'lockReason' => $message,
                'lockCode' => 'lifecycle_locked',
                'canOverride' => false,
                'context' => $context,
            ],
        ], Response::HTTP_CONFLICT);
    }
}
