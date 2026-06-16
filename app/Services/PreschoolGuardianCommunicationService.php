<?php

namespace App\Services;

use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolEnrollmentApplication;
use App\Models\PreschoolGuardian;
use App\Models\PreschoolGuardianCommunication;
use App\Models\PreschoolGuardianGovernanceIssue;
use App\Models\PreschoolHealthAlert;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAssessment;
use App\Models\User;
use App\Http\Resources\Preschool\PreschoolGuardianCommunicationResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PreschoolGuardianCommunicationService
{
    public function listCommunications(?User $viewer, array $filters = []): LengthAwarePaginator
    {
        $query = PreschoolGuardianCommunication::query()
            ->with(['student', 'guardian', 'creator'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $this->applyFilters($query, $filters);
        $this->applyViewerScope($query, $viewer);

        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function studentTimeline(PreschoolStudent $student, ?User $viewer = null, array $filters = []): array
    {
        $this->ensureCanViewStudent($viewer, $student);

        $filters['student_id'] = $student->id;
        $communications = $this->listCommunications($viewer, $filters);

        return [
            'student' => $student->only(['id', 'student_code', 'public_id', 'first_name', 'last_name']),
            'summary' => $this->timelineSummary($communications->getCollection()),
            'items' => PreschoolGuardianCommunicationResource::collection($communications->getCollection())->resolve(request()),
            'pagination' => [
                'currentPage' => $communications->currentPage(),
                'lastPage' => $communications->lastPage(),
                'perPage' => $communications->perPage(),
                'total' => $communications->total(),
            ],
        ];
    }

    public function guardianTimeline(PreschoolGuardian $guardian, ?User $viewer = null, array $filters = []): array
    {
        $this->ensureAdminOrTeacher($viewer);

        $filters['guardian_id'] = $guardian->id;
        $communications = $this->listCommunications($viewer, $filters);

        return [
            'guardian' => $guardian->only(['id', 'full_name', 'phone', 'email', 'status']),
            'summary' => $this->timelineSummary($communications->getCollection()),
            'items' => PreschoolGuardianCommunicationResource::collection($communications->getCollection())->resolve(request()),
            'pagination' => [
                'currentPage' => $communications->currentPage(),
                'lastPage' => $communications->lastPage(),
                'perPage' => $communications->perPage(),
                'total' => $communications->total(),
            ],
        ];
    }

    public function createManualNote(PreschoolStudent $student, ?PreschoolGuardian $guardian, User $actor, array $data): PreschoolGuardianCommunication
    {
        $this->ensureActorCanWrite($actor, $student);

        return $this->upsertCommunication([
            'student_id' => $student->id,
            'guardian_id' => $guardian?->id,
            'source_type' => 'manual_note',
            'source_id' => 'manual-note-'.Str::uuid()->toString(),
            'communication_type' => 'manual_note',
            'channel' => $this->normalizeChannel($data['channel'] ?? 'manual_note'),
            'subject' => trim((string) ($data['subject'] ?? 'Manual note')),
            'message' => trim((string) ($data['message'] ?? '')),
            'severity' => $this->normalizeSeverity($data['severity'] ?? 'medium'),
            'status' => $this->normalizeStatus($data['status'] ?? 'draft'),
            'created_by' => $actor->id,
        ]);
    }

    public function markSent(PreschoolGuardianCommunication $communication, User $actor): PreschoolGuardianCommunication
    {
        $this->ensureCanManage($actor, $communication);

        $communication->status = 'sent';
        $communication->sent_at = now();
        $communication->failed_at = null;
        $communication->save();

        return $communication->fresh(['student', 'guardian', 'creator']);
    }

    public function acknowledge(PreschoolGuardianCommunication $communication, User $actor): PreschoolGuardianCommunication
    {
        $this->ensureCanManage($actor, $communication);

        $communication->status = 'acknowledged';
        $communication->acknowledged_at = now();
        if ($communication->sent_at === null) {
            $communication->sent_at = now();
        }
        $communication->save();

        return $communication->fresh(['student', 'guardian', 'creator']);
    }

    public function cancel(PreschoolGuardianCommunication $communication, User $actor): PreschoolGuardianCommunication
    {
        $this->ensureCanManage($actor, $communication);

        $communication->status = 'cancelled';
        $communication->save();

        return $communication->fresh(['student', 'guardian', 'creator']);
    }

    public function fail(PreschoolGuardianCommunication $communication, User $actor): PreschoolGuardianCommunication
    {
        $this->ensureCanManage($actor, $communication);

        $communication->status = 'failed';
        $communication->failed_at = now();
        $communication->save();

        return $communication->fresh(['student', 'guardian', 'creator']);
    }

    public function syncHealthAlert(PreschoolHealthAlert $alert, ?User $actor = null, string $mode = 'queued'): ?PreschoolGuardianCommunication
    {
        $student = $alert->student()->withTrashed()->first();
        if (! $student) {
            return null;
        }

        $guardian = $this->resolvePrimaryGuardian($student);
        $type = match ($alert->alert_type) {
            'severe_allergy' => 'health_severe_allergy',
            'critical_incident' => 'health_critical_incident',
            'missing_emergency_contact' => 'health_missing_emergency_contact',
            'medication_reminder' => 'health_medication_concern',
            'overdue_vaccination' => 'health_overdue_vaccination',
            default => 'health_alert',
        };

        return $this->upsertCommunication([
            'student_id' => $student->id,
            'guardian_id' => $guardian?->id,
            'source_type' => 'health_alert',
            'source_id' => (string) $alert->id,
            'communication_type' => $type,
            'channel' => 'in_app',
            'subject' => $this->healthSubject($alert),
            'message' => $this->healthMessage($alert),
            'severity' => $this->normalizeSeverity($alert->severity),
            'status' => $mode,
            'created_by' => $actor?->id,
        ]);
    }

    public function syncAttendanceFollowUp(PreschoolAttendanceRecord $attendance, ?User $actor = null): ?PreschoolGuardianCommunication
    {
        $student = $attendance->student()->withTrashed()->first();
        if (! $student) {
            return null;
        }

        $guardian = $this->resolvePrimaryGuardian($student);
        $streaks = $this->recentAttendanceStreaks($student->id);

        if ($attendance->status === 'excused') {
            return $this->upsertCommunication([
                'student_id' => $student->id,
                'guardian_id' => $guardian?->id,
                'source_type' => 'attendance',
                'source_id' => (string) $attendance->id,
                'communication_type' => 'attendance_exception',
                'channel' => 'in_app',
                'subject' => 'Attendance exception recorded',
                'message' => 'An attendance exception was recorded for this student and may require guardian follow-up.',
                'severity' => 'low',
                'status' => 'queued',
                'created_by' => $actor?->id,
            ]);
        }

        if (($attendance->status === 'absent' && $streaks['absent'] >= 3) || $streaks['absent'] >= 5) {
            return $this->upsertCommunication([
                'student_id' => $student->id,
                'guardian_id' => $guardian?->id,
                'source_type' => 'attendance',
                'source_id' => 'absence-streak-'.$student->id,
                'communication_type' => 'repeated_absence',
                'channel' => 'in_app',
                'subject' => 'Repeated absence follow-up',
                'message' => 'This student has repeated absences and guardian follow-up is recommended.',
                'severity' => 'high',
                'status' => 'queued',
                'created_by' => $actor?->id,
            ]);
        }

        if ($attendance->status === 'late' && $streaks['late'] >= 3) {
            return $this->upsertCommunication([
                'student_id' => $student->id,
                'guardian_id' => $guardian?->id,
                'source_type' => 'attendance',
                'source_id' => 'late-streak-'.$student->id,
                'communication_type' => 'late_pattern',
                'channel' => 'in_app',
                'subject' => 'Late arrival pattern detected',
                'message' => 'This student has a repeated late-arrival pattern that should be reviewed with the guardian.',
                'severity' => 'medium',
                'status' => 'queued',
                'created_by' => $actor?->id,
            ]);
        }

        return null;
    }

    public function syncAssessmentRisk(PreschoolStudentAssessment $assessment, ?User $actor = null): ?PreschoolGuardianCommunication
    {
        $student = $assessment->student()->withTrashed()->first();
        if (! $student) {
            return null;
        }

        $guardian = $this->resolvePrimaryGuardian($student);
        $score = (float) ($assessment->score ?? 0);
        $recentLowCount = $this->recentLowAssessmentCount($student->id);
        $riskLevel = $this->assessmentRiskLabel($assessment, $score, $recentLowCount);

        if ($riskLevel === null) {
            return null;
        }

        return $this->upsertCommunication([
            'student_id' => $student->id,
            'guardian_id' => $guardian?->id,
            'source_type' => 'assessment',
            'source_id' => (string) $assessment->id,
            'communication_type' => $riskLevel,
            'channel' => 'in_app',
            'subject' => 'Assessment follow-up required',
            'message' => 'A recent assessment indicates the student may need guardian follow-up and support.',
            'severity' => $recentLowCount >= 3 ? 'high' : 'medium',
            'status' => 'queued',
            'created_by' => $actor?->id,
        ]);
    }

    public function syncEnrollmentDecision(PreschoolEnrollmentApplication $application, User $actor, string $decision): ?PreschoolGuardianCommunication
    {
        $guardian = null;
        if (($application->guardian_name ?? '') !== '' || ($application->guardian_phone ?? '') !== '') {
            $guardian = PreschoolGuardian::query()
                ->where('full_name', trim((string) ($application->guardian_name ?? '')))
                ->when(($application->guardian_phone ?? '') !== '', static function (Builder $query) use ($application): void {
                    $query->orWhere('phone', $application->guardian_phone);
                })
                ->first();
        }

        $communicationType = match ($decision) {
            'approved' => 'approved_enrollment',
            'rejected' => 'rejected_enrollment',
            'waitlisted' => 'waitlisted_application',
            default => 'enrollment_update',
        };

        $missingDocuments = $this->missingDocumentsForApplication($application);

        $log = $this->upsertCommunication([
            'student_id' => $application->enrolled_student_id ? (int) $application->enrolled_student_id : null,
            'guardian_id' => $guardian?->id,
            'source_type' => 'enrollment',
            'source_id' => (string) $application->id,
            'communication_type' => $communicationType,
            'channel' => 'in_app',
            'subject' => 'Enrollment decision update',
            'message' => $this->enrollmentMessage($decision, $missingDocuments),
            'severity' => $decision === 'rejected' ? 'high' : 'medium',
            'status' => 'queued',
            'created_by' => $actor->id,
        ]);

        if ($missingDocuments !== []) {
            $this->upsertCommunication([
                'student_id' => $application->enrolled_student_id ? (int) $application->enrolled_student_id : null,
                'guardian_id' => $guardian?->id,
                'source_type' => 'enrollment',
                'source_id' => (string) $application->id.'-documents',
                'communication_type' => 'missing_documents',
                'channel' => 'in_app',
                'subject' => 'Missing enrollment documents',
                'message' => 'The enrollment file is missing required documents: '.implode(', ', $missingDocuments),
                'severity' => 'medium',
                'status' => 'queued',
                'created_by' => $actor->id,
            ]);
        }

        return $log;
    }

    public function syncGovernanceIssue(PreschoolGuardianGovernanceIssue $issue, ?User $actor = null): ?PreschoolGuardianCommunication
    {
        $student = $issue->student()->withTrashed()->first();
        $guardian = $issue->guardian()->first();

        return $this->upsertCommunication([
            'student_id' => $student?->id,
            'guardian_id' => $guardian?->id,
            'source_type' => 'governance_issue',
            'source_id' => (string) $issue->id,
            'communication_type' => $issue->issue_type ?: 'governance_issue',
            'channel' => 'in_app',
            'subject' => 'Guardian governance follow-up',
            'message' => 'A guardian governance issue requires follow-up: '.($issue->resolution_notes ?: $issue->issue_type),
            'severity' => $this->normalizeSeverity($issue->severity ?? 'medium'),
            'status' => 'queued',
            'created_by' => $actor?->id,
        ]);
    }

    public function findById(int|string $id): PreschoolGuardianCommunication
    {
        return PreschoolGuardianCommunication::query()
            ->with(['student', 'guardian', 'creator'])
            ->findOrFail($id);
    }

    public function upsertCommunication(array $data): PreschoolGuardianCommunication
    {
        $studentId = Arr::get($data, 'student_id');
        $guardianId = Arr::get($data, 'guardian_id');
        $sourceType = Arr::get($data, 'source_type');
        $sourceId = Arr::get($data, 'source_id');
        $communicationType = Arr::get($data, 'communication_type');
        $channel = Arr::get($data, 'channel');

        $query = PreschoolGuardianCommunication::query()->where([
            ['student_id', '=', $studentId],
            ['guardian_id', '=', $guardianId],
            ['source_type', '=', $sourceType],
            ['source_id', '=', $sourceId],
            ['communication_type', '=', $communicationType],
            ['channel', '=', $channel],
        ]);

        $communication = $query->first() ?? new PreschoolGuardianCommunication();
        $communication->fill([
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'communication_type' => $communicationType,
            'channel' => $channel,
            'subject' => Arr::get($data, 'subject'),
            'message' => Arr::get($data, 'message'),
            'severity' => Arr::get($data, 'severity', 'medium'),
            'status' => Arr::get($data, 'status', 'draft'),
            'created_by' => Arr::get($data, 'created_by'),
        ]);

        if (! $communication->exists && $communication->status === 'sent') {
            $communication->sent_at = now();
        }

        $communication->save();

        return $communication->fresh(['student', 'guardian', 'creator']);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['student_id', 'guardian_id', 'source_type', 'communication_type', 'channel', 'status'] as $key) {
            if (($value = trim((string) ($filters[$key] ?? ''))) !== '' && $value !== 'all') {
                $query->where($key, $value);
            }
        }

        if (($search = trim((string) ($filters['search'] ?? ''))) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(static function (Builder $builder) use ($like): void {
                $builder->where('subject', 'like', $like)
                    ->orWhere('message', 'like', $like)
                    ->orWhereHas('student', static function (Builder $studentQuery) use ($like): void {
                        $studentQuery->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('student_code', 'like', $like)
                            ->orWhere('public_id', 'like', $like);
                    })
                    ->orWhereHas('guardian', static function (Builder $guardianQuery) use ($like): void {
                        $guardianQuery->where('full_name', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
            });
        }
    }

    private function applyViewerScope(Builder $query, ?User $viewer): void
    {
        if (! $viewer || in_array($viewer->role_code, ['superadmin', 'adminpreschool'], true)) {
            return;
        }

        if ($viewer->role_code !== 'teacher-preschool') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('student', static function (Builder $studentQuery) use ($viewer): void {
            $studentQuery->whereHas('classes', static function (Builder $classQuery) use ($viewer): void {
                $classQuery->where('teacher_user_id', $viewer->id);
            });
        });
    }

    private function ensureAdminOrTeacher(?User $viewer): void
    {
        if (! $viewer || ! in_array($viewer->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true)) {
            throw ValidationException::withMessages([
                'authorization' => 'Forbidden.',
            ]);
        }
    }

    private function ensureActorCanWrite(User $actor, PreschoolStudent $student): void
    {
        if (in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)) {
            return;
        }

        if ($actor->role_code === 'teacher-preschool' && $this->teacherCanAccessStudent($actor, $student)) {
            return;
        }

        throw ValidationException::withMessages([
            'authorization' => 'Forbidden.',
        ]);
    }

    private function ensureCanViewStudent(?User $viewer, PreschoolStudent $student): void
    {
        if (! $viewer) {
            throw ValidationException::withMessages(['authorization' => 'Unauthenticated.']);
        }

        if (in_array($viewer->role_code, ['superadmin', 'adminpreschool'], true)) {
            return;
        }

        if ($viewer->role_code === 'teacher-preschool' && $this->teacherCanAccessStudent($viewer, $student)) {
            return;
        }

        throw ValidationException::withMessages(['authorization' => 'Forbidden.']);
    }

    private function ensureCanManage(User $actor, PreschoolGuardianCommunication $communication): void
    {
        if (in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)) {
            return;
        }

        if ($actor->role_code === 'teacher-preschool' && $communication->student_id !== null) {
            $student = PreschoolStudent::query()->find($communication->student_id);
            if ($student && $this->teacherCanAccessStudent($actor, $student)) {
                return;
            }
        }

        throw ValidationException::withMessages(['authorization' => 'Forbidden.']);
    }

    private function teacherCanAccessStudent(User $user, PreschoolStudent $student): bool
    {
        return $student->classes()
            ->where('teacher_user_id', $user->id)
            ->exists();
    }

    private function resolvePrimaryGuardian(PreschoolStudent $student): ?PreschoolGuardian
    {
        return $student->guardians()
            ->where('preschool_student_guardians.status', 'active')
            ->orderByDesc('preschool_student_guardians.is_primary')
            ->orderByRaw('COALESCE(preschool_student_guardians.emergency_priority, 999999) ASC')
            ->first();
    }

    private function recentAttendanceStreaks(int $studentId): array
    {
        $records = PreschoolAttendanceRecord::query()
            ->where('student_id', $studentId)
            ->orderByDesc('attendance_date')
            ->limit(10)
            ->get();

        return [
            'absent' => $records->takeWhile(static fn (PreschoolAttendanceRecord $record): bool => $record->status === 'absent')->count(),
            'late' => $records->takeWhile(static fn (PreschoolAttendanceRecord $record): bool => $record->status === 'late')->count(),
        ];
    }

    private function recentLowAssessmentCount(int $studentId): int
    {
        return PreschoolStudentAssessment::query()
            ->where('student_id', $studentId)
            ->where('status', 'finalized')
            ->orderByDesc('assessment_date')
            ->limit(3)
            ->get()
            ->filter(static function (PreschoolStudentAssessment $assessment): bool {
                return (float) ($assessment->score ?? 0) <= 60 || in_array(strtolower((string) $assessment->rating), ['at-risk', 'needs_improvement'], true);
            })
            ->count();
    }

    private function assessmentRiskLabel(PreschoolStudentAssessment $assessment, float $score, int $recentLowCount): ?string
    {
        if ($recentLowCount >= 3) {
            return 'repeated_low_performance';
        }

        if ($score <= 60) {
            return 'low_score_threshold';
        }

        if (in_array(strtolower((string) $assessment->rating), ['at-risk', 'needs_improvement'], true)) {
            return 'at_risk_assessment_result';
        }

        if ($recentLowCount >= 2) {
            return 'assessment_follow_up_required';
        }

        return null;
    }

    private function missingDocumentsForApplication(PreschoolEnrollmentApplication $application): array
    {
        return $application->documents()
            ->where('is_required', true)
            ->where('is_received', false)
            ->pluck('document_type')
            ->map(static fn ($value) => str_replace('_', ' ', (string) $value))
            ->values()
            ->all();
    }

    private function healthSubject(PreschoolHealthAlert $alert): string
    {
        return match ($alert->alert_type) {
            'severe_allergy' => 'Severe allergy alert',
            'critical_incident' => 'Critical health incident',
            'missing_emergency_contact' => 'Missing emergency contact',
            'medication_reminder' => 'Medication concern',
            'overdue_vaccination' => 'Overdue vaccination',
            default => 'Health alert',
        };
    }

    private function healthMessage(PreschoolHealthAlert $alert): string
    {
        return trim((string) ($alert->description ?: $alert->resolution_notes ?: 'A health alert requires guardian follow-up.'));
    }

    private function enrollmentMessage(string $decision, array $missingDocuments): string
    {
        return match ($decision) {
            'approved' => 'Enrollment approved. Guardian follow-up may be required to complete onboarding.',
            'rejected' => 'Enrollment was rejected and the guardian should be informed of the decision.',
            'waitlisted' => 'Enrollment has been waitlisted pending capacity or review.',
            default => 'Enrollment status changed.',
        } . ($missingDocuments !== [] ? ' Missing documents: '.implode(', ', $missingDocuments).'.' : '');
    }

    private function normalizeSeverity(string $value): string
    {
        return in_array($value, ['low', 'medium', 'high', 'critical'], true) ? $value : 'medium';
    }

    private function normalizeStatus(string $value): string
    {
        return in_array($value, ['draft', 'queued', 'sent', 'acknowledged', 'failed', 'cancelled'], true) ? $value : 'draft';
    }

    private function normalizeChannel(string $value): string
    {
        return in_array($value, ['in_app', 'phone', 'sms', 'email', 'manual_note'], true) ? $value : 'manual_note';
    }

    private function timelineSummary(Collection $items): array
    {
        return [
            'total' => $items->count(),
            'queued' => $items->where('status', 'queued')->count(),
            'sent' => $items->where('status', 'sent')->count(),
            'acknowledged' => $items->where('status', 'acknowledged')->count(),
            'failed' => $items->where('status', 'failed')->count(),
            'cancelled' => $items->where('status', 'cancelled')->count(),
        ];
    }
}
