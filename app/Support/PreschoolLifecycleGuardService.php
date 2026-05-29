<?php

namespace App\Support;

use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\PreschoolClassStudent;
use App\Models\PreschoolReportPeriod;
use App\Models\PreschoolScheduleEntry;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAssessment;
use App\Models\User;
use App\Support\PreschoolAssessmentStatus;
use App\Support\PreschoolReportPeriodService;
use App\Support\PreschoolScheduleStatus;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Preschool lifecycle guards keep operational writes aligned with the active
 * academic year/term/report period so closed windows remain audit-stable.
 * Admin overrides are explicit and stay limited to superadmin/adminpreschool.
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
                $this->recordAudit(
                    actor: $actor,
                    actionType: 'write.blocked',
                    entityType: 'attendance',
                    entityId: (string) $attendance?->id,
                    context: $context,
                    previousState: $attendance ? $this->attendanceState($attendance) : null,
                    newState: $this->requestContext($payload),
                    lockCode: 'inactive_enrollment',
                    lockReason: 'This student enrollment is inactive.',
                );

                return $this->conflict('This student enrollment is inactive.', $context, 'inactive_enrollment', $actor, $payload);
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

        if ($response = $this->guardReportPeriodContext($actor, $payload, $assessment?->period_label ?? $payload['period_label'] ?? null, $assessment, $context)) {
            return $response;
        }

        if ($assessment && in_array((string) $assessment->status, [PreschoolAssessmentStatus::FINALIZED, PreschoolAssessmentStatus::ARCHIVED], true)) {
            if (! $this->canOverride($actor, $payload)) {
                $this->recordAudit(
                    actor: $actor,
                    actionType: 'write.blocked',
                    entityType: 'assessment',
                    entityId: (string) $assessment->id,
                    context: $context,
                    previousState: $this->assessmentState($assessment),
                    newState: $this->requestContext($payload),
                    lockCode: $this->lockCodeForAssessmentStatus((string) $assessment->status),
                    lockReason: $assessment->status === PreschoolAssessmentStatus::FINALIZED
                        ? 'Finalized assessments cannot be edited.'
                        : 'Archived assessments cannot be edited.',
                );

                return $this->conflict(
                    $assessment->status === PreschoolAssessmentStatus::FINALIZED
                        ? 'Finalized assessments cannot be edited.'
                        : 'Archived assessments cannot be edited.',
                    $context,
                    $this->lockCodeForAssessmentStatus((string) $assessment->status),
                    $actor,
                    $payload,
                );
            }
        }

        $student = $student ?: $assessment?->student;
        if ($student && ! $this->isEnrollmentActive($student, $payload['class_id'] ?? $assessment?->class_id ?? null) && ! $this->canOverride($actor, $payload)) {
            $this->recordAudit(
                actor: $actor,
                actionType: 'write.blocked',
                entityType: 'assessment',
                entityId: (string) $assessment?->id,
                context: $context,
                previousState: $assessment ? $this->assessmentState($assessment) : null,
                newState: $this->requestContext($payload),
                lockCode: 'inactive_enrollment',
                lockReason: 'This student enrollment is inactive.',
            );

            return $this->conflict('This student enrollment is inactive.', $context, 'inactive_enrollment', $actor, $payload);
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
            $this->recordAudit(
                actor: $actor,
                actionType: 'write.blocked',
                entityType: 'schedule',
                entityId: (string) $schedule->id,
                context: $context,
                previousState: $this->scheduleState($schedule),
                newState: $this->requestContext($payload),
                lockCode: 'archived_schedule',
                lockReason: 'Archived schedules cannot be edited.',
            );

            return $this->conflict('Archived schedules cannot be edited.', $context, 'archived_schedule', $actor, $payload);
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

        if ($this->canOverride($actor, $payload)) {
            $this->recordAudit(
                actor: $actor,
                actionType: 'override.approved',
                entityType: 'academic_term',
                entityId: (string) ($context['term_id'] ?? ''),
                context: $context,
                previousState: $context,
                newState: $context,
                overrideReason: trim((string) ($payload['override_reason'] ?? '')),
            );

            return null;
        }

        if (($payload['override_locked_context'] ?? false) === true) {
            $this->recordAudit(
                actor: $actor,
                actionType: 'override.attempt',
                entityType: 'academic_term',
                entityId: (string) ($context['term_id'] ?? ''),
                context: $context,
                previousState: $context,
                newState: $context,
                overrideReason: trim((string) ($payload['override_reason'] ?? '')),
                lockCode: 'term_closed',
                lockReason: $message,
            );
        }

        $this->recordAudit(
            actor: $actor,
            actionType: 'write.blocked',
            entityType: 'academic_term',
            entityId: (string) ($context['term_id'] ?? ''),
            context: $context,
            previousState: $context,
            newState: $context,
            lockCode: 'term_closed',
            lockReason: $message,
        );

        return $this->conflict($message, $context, 'term_closed', $actor, $payload);
    }

    private function guardReportPeriodContext(
        ?User $actor,
        array $payload,
        mixed $periodLabel,
        mixed $subject = null,
        array $context = [],
    ): ?JsonResponse {
        $label = trim((string) $periodLabel);
        if ($label === '') {
            return null;
        }

        $period = app(PreschoolReportPeriodService::class)->resolveForAssessment([
            'period_label' => $label,
            'assessment_date' => $payload['assessment_date'] ?? null,
        ], $payload['assessment_date'] ?? null);

        if (! $period) {
            return null;
        }

        $status = strtolower((string) $period->status);
        if (! in_array($status, ['finalized', 'locked', 'archived'], true)) {
            return null;
        }

        if ($this->canOverride($actor, $payload, $status)) {
            $this->recordAudit(
                actor: $actor,
                actionType: 'override.approved',
                entityType: 'report_period',
                entityId: (string) $period->id,
                context: $context,
                previousState: $this->reportPeriodState($period),
                newState: $this->reportPeriodState($period),
                overrideReason: trim((string) ($payload['override_reason'] ?? '')),
                reportPeriod: $period,
            );

            return null;
        }

        if (($payload['override_locked_context'] ?? false) === true) {
            $this->recordAudit(
                actor: $actor,
                actionType: 'override.attempt',
                entityType: 'report_period',
                entityId: (string) $period->id,
                context: $context,
                previousState: $this->reportPeriodState($period),
                newState: $this->reportPeriodState($period),
                overrideReason: trim((string) ($payload['override_reason'] ?? '')),
                lockCode: $this->lockCodeForReportPeriodStatus($status),
                lockReason: $this->reportPeriodConflictMessage($status),
                reportPeriod: $period,
            );
        }

        $this->recordAudit(
            actor: $actor,
            actionType: 'write.blocked',
            entityType: 'report_period',
            entityId: (string) $period->id,
            context: $context,
            previousState: $this->reportPeriodState($period),
            newState: $this->reportPeriodState($period),
            lockCode: $this->lockCodeForReportPeriodStatus($status),
            lockReason: $this->reportPeriodConflictMessage($status),
            reportPeriod: $period,
            requestContext: $this->requestContext($payload),
        );

        return $this->conflict(
            $this->reportPeriodConflictMessage($status),
            $context,
            $this->lockCodeForReportPeriodStatus($status),
            $actor,
            $payload,
        );
    }

    private function canOverride(?User $actor, array $payload, string $lockCode = ''): bool
    {
        if (! ($payload['override_locked_context'] ?? false)) {
            return false;
        }

        if (! $actor || ! in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)) {
            return false;
        }

        if (trim((string) ($payload['override_reason'] ?? '')) === '') {
            return false;
        }

        if ($lockCode === 'report_period_archived') {
            return false;
        }

        return true;
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

    private function conflict(
        string $message,
        array $context = [],
        string $lockCode = 'lifecycle_locked',
        ?User $actor = null,
        array $payload = [],
    ): JsonResponse {
        $canOverride = $actor
            && in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)
            && trim((string) ($payload['override_reason'] ?? '')) !== ''
            && $lockCode !== 'report_period_archived';

        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => [
                'lockReason' => $message,
                'lockCode' => $lockCode,
                'canOverride' => $canOverride,
                'context' => $context,
            ],
        ], Response::HTTP_CONFLICT);
    }

    private function reportPeriodConflictMessage(string $status): string
    {
        return match ($status) {
            'finalized' => 'This report period is finalized.',
            'locked' => 'This report period is locked.',
            'archived' => 'This report period is archived.',
            default => 'This report period is locked.',
        };
    }

    private function lockCodeForReportPeriodStatus(string $status): string
    {
        return match ($status) {
            'finalized' => 'report_period_finalized',
            'locked' => 'report_period_locked',
            'archived' => 'report_period_archived',
            default => 'report_period_locked',
        };
    }

    private function lockCodeForAssessmentStatus(string $status): string
    {
        return match ($status) {
            PreschoolAssessmentStatus::FINALIZED => 'assessment_finalized',
            PreschoolAssessmentStatus::ARCHIVED => 'assessment_archived',
            default => 'assessment_locked',
        };
    }

    private function attendanceState(PreschoolAttendanceRecord $attendance): array
    {
        return [
            'id' => $attendance->id,
            'class_id' => $attendance->class_id,
            'student_id' => $attendance->student_id,
            'attendance_date' => $attendance->attendance_date?->toDateString(),
            'status' => $attendance->status,
        ];
    }

    private function assessmentState(PreschoolStudentAssessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'student_id' => $assessment->student_id,
            'class_id' => $assessment->class_id,
            'period_label' => $assessment->period_label,
            'status' => $assessment->status,
            'assessment_date' => $assessment->assessment_date?->toDateString(),
        ];
    }

    private function scheduleState(PreschoolScheduleEntry $schedule): array
    {
        return [
            'id' => $schedule->id,
            'class_id' => $schedule->class_id,
            'teacher_user_id' => $schedule->teacher_user_id,
            'status' => $schedule->status,
            'effective_from' => $schedule->effective_from?->toDateString(),
        ];
    }

    private function reportPeriodState(PreschoolReportPeriod $period): array
    {
        return [
            'id' => $period->id,
            'period_label' => $period->period_label,
            'academic_year_id' => $period->academic_year_id,
            'term_id' => $period->term_id,
            'status' => $period->status,
        ];
    }

    private function requestContext(array $payload = []): array
    {
        return [
            'method' => request()?->method(),
            'path' => request()?->path(),
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'override_requested' => (bool) ($payload['override_locked_context'] ?? false),
        ];
    }

    private function recordAudit(
        ?User $actor,
        string $actionType,
        string $entityType,
        ?string $entityId = null,
        array $context = [],
        ?array $previousState = null,
        ?array $newState = null,
        ?string $overrideReason = null,
        ?string $lockCode = null,
        ?string $lockReason = null,
        ?PreschoolReportPeriod $reportPeriod = null,
        ?array $requestContext = null,
    ): void {
        app(PreschoolLifecycleAuditService::class)->recordSafely([
            'actor_user_id' => $actor?->id,
            'actor_role' => $actor?->role_code,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'academic_year_id' => $context['academic_year_id'] ?? $reportPeriod?->academic_year_id ?? null,
            'term_id' => $context['term_id'] ?? $reportPeriod?->term_id ?? null,
            'report_period_id' => $reportPeriod?->id ?? ($context['report_period_id'] ?? null),
            'previous_state' => $previousState,
            'new_state' => $newState,
            'override_reason' => $overrideReason,
            'lock_code' => $lockCode,
            'lock_reason' => $lockReason,
            'request_context' => $requestContext ?? $this->requestContext(),
        ]);
    }
}
