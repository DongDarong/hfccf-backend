<?php

namespace App\Services;

use App\Models\PreschoolClass;
use App\Models\PreschoolHealthAlert;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAllergy;
use App\Models\PreschoolStudentHealthContact;
use App\Models\PreschoolStudentHealthIncident;
use App\Models\PreschoolStudentMedicationRecord;
use App\Models\PreschoolStudentHealthAuditLog;
use App\Models\PreschoolStudentVaccinationRecord;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class PreschoolHealthAlertService
{
    public function __construct(
        private readonly PreschoolHealthAuditService $auditService,
        private readonly NotificationService $notificationService,
        private readonly PreschoolGuardianCommunicationService $communicationService,
    ) {
    }

    public function listAlerts(?User $viewer, array $filters = []): LengthAwarePaginator
    {
        $query = $this->buildQuery($viewer, $filters)
            ->orderByRaw("CASE status WHEN 'new' THEN 1 WHEN 'acknowledged' THEN 2 WHEN 'in_progress' THEN 3 WHEN 'resolved' THEN 4 WHEN 'closed' THEN 5 ELSE 6 END ASC")
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END ASC")
            ->orderByDesc('updated_at')
            ->with(['student', 'assignedTo', 'acknowledgedBy', 'resolvedBy', 'closedBy']);

        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function showAlert(PreschoolHealthAlert $alert, ?User $viewer = null): array
    {
        $alert->loadMissing(['student', 'assignedTo', 'acknowledgedBy', 'resolvedBy', 'closedBy']);

        return [
            'alert' => $this->formatAlert($alert),
            'auditLogs' => $this->alertAuditLogs($alert, $viewer),
        ];
    }

    public function dashboardSummary(?User $viewer = null, array $filters = []): array
    {
        $alerts = $this->buildQuery($viewer, $filters)
            ->with(['student', 'assignedTo', 'acknowledgedBy', 'resolvedBy', 'closedBy'])
            ->orderByDesc('updated_at')
            ->get();

        $summary = [
            'newAlerts' => $alerts->where('status', 'new')->count(),
            'acknowledgedAlerts' => $alerts->where('status', 'acknowledged')->count(),
            'inProgressAlerts' => $alerts->where('status', 'in_progress')->count(),
            'resolvedAlerts' => $alerts->where('status', 'resolved')->count(),
            'closedAlerts' => $alerts->where('status', 'closed')->count(),
            'criticalAlerts' => $alerts->whereIn('severity', ['high', 'critical'])->whereNotIn('status', ['resolved', 'closed'])->count(),
            'resolvedThisWeek' => $alerts->where('status', 'resolved')->filter(static function (PreschoolHealthAlert $alert): bool {
                return $alert->resolved_at !== null && $alert->resolved_at->greaterThanOrEqualTo(now()->startOfWeek());
            })->count(),
            'unresolvedItems' => $alerts->whereNotIn('status', ['resolved', 'closed'])->count(),
        ];

        return [
            'summary' => $summary,
            'items' => $alerts->take(10)->map(fn (PreschoolHealthAlert $alert): array => $this->formatAlert($alert))->values()->all(),
            'unresolvedCriticalItems' => $alerts
                ->filter(static fn (PreschoolHealthAlert $alert): bool => in_array($alert->severity, ['high', 'critical'], true) && ! in_array($alert->status, ['resolved', 'closed'], true))
                ->take(10)
                ->map(fn (PreschoolHealthAlert $alert): array => $this->formatAlert($alert))
                ->values()
                ->all(),
            'raw' => null,
        ];
    }

    public function acknowledge(PreschoolHealthAlert $alert, User $actor): PreschoolHealthAlert
    {
        $this->ensureWritable($actor, $alert);

        $before = $alert->replicate()->toArray();
        $alert->status = 'acknowledged';
        $alert->acknowledged_by_user_id = $actor->id;
        $alert->acknowledged_at = now();
        $alert->save();

        $this->auditAction($alert, $actor, 'acknowledged', $before, $alert->toArray(), 'teacher', 'Health alert acknowledged.');
        $this->notify($alert, $actor, 'acknowledged', 'Health alert acknowledged', 'A health alert has been acknowledged.', ['status' => 'acknowledged']);
        $this->communicationService->syncHealthAlert($alert, $actor, 'sent');

        return $alert->fresh(['student', 'assignedTo', 'acknowledgedBy', 'resolvedBy', 'closedBy']);
    }

    public function assign(PreschoolHealthAlert $alert, User $actor, ?User $assignee): PreschoolHealthAlert
    {
        $this->ensureAdmin($actor);

        $before = $alert->replicate()->toArray();
        $alert->assigned_to_user_id = $assignee?->id;
        if (! in_array($alert->status, ['resolved', 'closed'], true)) {
            $alert->status = 'in_progress';
        }
        $alert->save();

        $this->auditAction($alert, $actor, 'assigned', $before, $alert->toArray(), 'admin', 'Health alert assigned.');
        $this->notify($alert, $actor, 'assigned', 'Health alert assigned', 'A health alert has been assigned for follow-up.', [
            'assigned_to_user_id' => $assignee?->id,
        ], $assignee);
        $this->communicationService->syncHealthAlert($alert, $actor);

        return $alert->fresh(['student', 'assignedTo', 'acknowledgedBy', 'resolvedBy', 'closedBy']);
    }

    public function changeStatus(PreschoolHealthAlert $alert, User $actor, string $status): PreschoolHealthAlert
    {
        $this->ensureAdmin($actor);

        $this->assertAllowedStatus($status);

        $before = $alert->replicate()->toArray();
        $alert->status = $status;
        $alert->save();

        $this->auditAction($alert, $actor, 'status_changed', $before, $alert->toArray(), 'admin', 'Health alert status changed to '.$status.'.');
        $this->notify($alert, $actor, 'status_changed', 'Health alert status changed', 'A health alert status was updated.', ['status' => $status]);
        $this->communicationService->syncHealthAlert($alert, $actor, $status === 'closed' || $status === 'resolved' ? 'sent' : 'queued');

        return $alert->fresh(['student', 'assignedTo', 'acknowledgedBy', 'resolvedBy', 'closedBy']);
    }

    public function resolve(PreschoolHealthAlert $alert, User $actor, ?string $notes = null): PreschoolHealthAlert
    {
        $this->ensureAdmin($actor);

        $before = $alert->replicate()->toArray();
        $alert->status = 'resolved';
        $alert->resolved_by_user_id = $actor->id;
        $alert->resolved_at = now();
        $alert->resolution_notes = $notes ?: $alert->resolution_notes;
        $alert->save();

        $this->auditAction($alert, $actor, 'resolved', $before, $alert->toArray(), 'admin', 'Health alert resolved.');
        $this->notify($alert, $actor, 'resolved', 'Health alert resolved', 'A health alert has been resolved.', ['resolution_notes' => $notes]);
        $this->communicationService->syncHealthAlert($alert, $actor, 'sent');

        return $alert->fresh(['student', 'assignedTo', 'acknowledgedBy', 'resolvedBy', 'closedBy']);
    }

    public function close(PreschoolHealthAlert $alert, User $actor, ?string $notes = null): PreschoolHealthAlert
    {
        $this->ensureAdmin($actor);

        $before = $alert->replicate()->toArray();
        $alert->status = 'closed';
        $alert->closed_by_user_id = $actor->id;
        $alert->closed_at = now();
        $alert->resolution_notes = $notes ?: $alert->resolution_notes;
        $alert->save();

        $this->auditAction($alert, $actor, 'closed', $before, $alert->toArray(), 'admin', 'Health alert closed.');
        $this->notify($alert, $actor, 'closed', 'Health alert closed', 'A health alert has been closed.', ['resolution_notes' => $notes]);
        $this->communicationService->syncHealthAlert($alert, $actor, 'sent');

        return $alert->fresh(['student', 'assignedTo', 'acknowledgedBy', 'resolvedBy', 'closedBy']);
    }

    public function syncFromModel(Model $model, ?User $actor = null): ?PreschoolHealthAlert
    {
        return match (true) {
            $model instanceof PreschoolStudentAllergy => $this->syncAllergy($model, $actor),
            $model instanceof PreschoolStudentVaccinationRecord => $this->syncVaccination($model, $actor),
            $model instanceof PreschoolStudentMedicationRecord => $this->syncMedication($model, $actor),
            $model instanceof PreschoolStudentHealthIncident => $this->syncIncident($model, $actor),
            $model instanceof PreschoolStudentHealthContact => $this->syncEmergencyContact($model, $actor),
            default => null,
        };
    }

    public function syncStudentCoverage(PreschoolStudent $student, ?User $actor = null): void
    {
        $this->syncMissingContactAlert($student, $actor);
    }

    private function syncAllergy(PreschoolStudentAllergy $allergy, ?User $actor): ?PreschoolHealthAlert
    {
        if (! in_array((string) $allergy->severity, ['high', 'critical'], true) || ! in_array((string) $allergy->status, ['active', 'resolved'], true)) {
            return $this->resolveSourceAlert($allergy, $actor, 'allergy');
        }

        return $this->upsertSourceAlert(
            student: $allergy->student()->withTrashed()->firstOrFail(),
            alertType: 'severe_allergy',
            sourceType: 'allergy',
            sourceId: (string) $allergy->getKey(),
            title: $allergy->allergy_name,
            description: trim((string) ($allergy->reaction ?: $allergy->action_taken ?: $allergy->notes ?: 'Severe allergy requires review.')),
            severity: (string) $allergy->severity,
            actor: $actor,
            entity: $allergy,
            createdMessage: 'Severe allergy alert created.',
            updatedMessage: 'Severe allergy alert updated.',
        );
    }

    private function syncVaccination(PreschoolStudentVaccinationRecord $vaccination, ?User $actor): ?PreschoolHealthAlert
    {
        if ((string) $vaccination->status !== 'overdue') {
            return $this->resolveSourceAlert($vaccination, $actor, 'vaccination');
        }

        return $this->upsertSourceAlert(
            student: $vaccination->student()->withTrashed()->firstOrFail(),
            alertType: 'overdue_vaccination',
            sourceType: 'vaccination',
            sourceId: (string) $vaccination->getKey(),
            title: $vaccination->vaccine_name,
            description: trim((string) ($vaccination->provider ?: $vaccination->notes ?: 'Vaccination is overdue.')),
            severity: 'high',
            actor: $actor,
            entity: $vaccination,
            createdMessage: 'Overdue vaccination alert created.',
            updatedMessage: 'Overdue vaccination alert updated.',
        );
    }

    private function syncMedication(PreschoolStudentMedicationRecord $medication, ?User $actor): ?PreschoolHealthAlert
    {
        $isReminder = (string) $medication->status === 'active' && $medication->end_date !== null && $medication->end_date->lessThanOrEqualTo(now()->addDays(7));

        if (! $isReminder) {
            return $this->resolveSourceAlert($medication, $actor, 'medication');
        }

        return $this->upsertSourceAlert(
            student: $medication->student()->withTrashed()->firstOrFail(),
            alertType: 'medication_reminder',
            sourceType: 'medication',
            sourceId: (string) $medication->getKey(),
            title: $medication->medication_name,
            description: trim((string) ($medication->frequency ?: $medication->notes ?: 'Medication requires follow-up.')),
            severity: 'medium',
            actor: $actor,
            entity: $medication,
            createdMessage: 'Medication reminder alert created.',
            updatedMessage: 'Medication reminder alert updated.',
        );
    }

    private function syncIncident(PreschoolStudentHealthIncident $incident, ?User $actor): ?PreschoolHealthAlert
    {
        if (! in_array((string) $incident->severity, ['high', 'critical'], true) || in_array((string) $incident->status, ['resolved', 'closed'], true)) {
            return $this->resolveSourceAlert($incident, $actor, 'health_incident');
        }

        return $this->upsertSourceAlert(
            student: $incident->student()->withTrashed()->firstOrFail(),
            alertType: 'critical_incident',
            sourceType: 'health_incident',
            sourceId: (string) $incident->getKey(),
            title: $incident->incident_type,
            description: trim((string) ($incident->action_taken ?: $incident->notes ?: 'Critical incident requires action.')),
            severity: (string) $incident->severity,
            actor: $actor,
            entity: $incident,
            createdMessage: 'Critical incident alert created.',
            updatedMessage: 'Critical incident alert updated.',
        );
    }

    private function syncEmergencyContact(PreschoolStudentHealthContact $contact, ?User $actor): ?PreschoolHealthAlert
    {
        return $this->syncMissingContactAlert($contact->student()->withTrashed()->firstOrFail(), $actor);
    }

    private function syncMissingContactAlert(PreschoolStudent $student, ?User $actor = null): ?PreschoolHealthAlert
    {
        $hasActiveContact = $student->emergencyHealthContacts()
            ->where('status', 'active')
            ->exists();

        if ($hasActiveContact) {
            return $this->resolveExistingAlert($student, 'missing_emergency_contact', 'student', (string) $student->id, $actor, 'Emergency contact restored.');
        }

        return $this->upsertSourceAlert(
            student: $student,
            alertType: 'missing_emergency_contact',
            sourceType: 'student',
            sourceId: (string) $student->id,
            title: 'Missing emergency contact',
            description: 'No active emergency contact is available for this student.',
            severity: 'high',
            actor: $actor,
            entity: $student,
            createdMessage: 'Missing emergency contact alert created.',
            updatedMessage: 'Missing emergency contact alert updated.',
        );
    }

    private function resolveSourceAlert(Model $model, ?User $actor, string $sourceType): ?PreschoolHealthAlert
    {
        $student = $model->student()->withTrashed()->first();
        if (! $student) {
            return null;
        }

        $alert = PreschoolHealthAlert::query()
            ->where('student_id', $student->id)
            ->where('source_type', $sourceType)
            ->where('source_id', (string) $model->getKey())
            ->first();

        if (! $alert || in_array($alert->status, ['resolved', 'closed'], true)) {
            return $alert;
        }

        $before = $alert->replicate()->toArray();
        $alert->status = 'resolved';
        $alert->resolved_by_user_id = $actor?->id;
        $alert->resolved_at = now();
        $alert->resolution_notes = $alert->resolution_notes ?: 'Source record no longer requires an active alert.';
        $alert->save();

        $this->auditAction($alert, $actor, 'resolved', $before, $alert->toArray(), 'teacher', 'Health alert resolved from source update.');

        return $alert->fresh(['student', 'assignedTo', 'acknowledgedBy', 'resolvedBy', 'closedBy']);
    }

    private function resolveExistingAlert(PreschoolStudent $student, string $alertType, string $sourceType, string $sourceId, ?User $actor, string $note): ?PreschoolHealthAlert
    {
        $alert = PreschoolHealthAlert::query()
            ->where('student_id', $student->id)
            ->where('alert_type', $alertType)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if (! $alert || in_array($alert->status, ['resolved', 'closed'], true)) {
            return $alert;
        }

        $before = $alert->replicate()->toArray();
        $alert->status = 'resolved';
        $alert->resolved_by_user_id = $actor?->id;
        $alert->resolved_at = now();
        $alert->resolution_notes = $note;
        $alert->save();

        $this->auditAction($alert, $actor, 'resolved', $before, $alert->toArray(), 'teacher', $note);

        return $alert->fresh(['student', 'assignedTo', 'acknowledgedBy', 'resolvedBy', 'closedBy']);
    }

    private function upsertSourceAlert(
        PreschoolStudent $student,
        string $alertType,
        string $sourceType,
        string $sourceId,
        string $title,
        string $description,
        string $severity,
        ?User $actor,
        Model $entity,
        string $createdMessage,
        string $updatedMessage,
    ): PreschoolHealthAlert {
        $alert = PreschoolHealthAlert::query()->firstOrNew([
            'student_id' => $student->id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);

        $before = $alert->exists ? $alert->replicate()->toArray() : null;
        $wasNew = ! $alert->exists;

        $alert->student_id = $student->id;
        $alert->alert_type = $alertType;
        $alert->title = $title;
        $alert->description = $description;
        $alert->severity = $severity;
        $alert->source_type = $sourceType;
        $alert->source_id = $sourceId;

        if ($wasNew || in_array($alert->status, ['resolved', 'closed'], true)) {
            $alert->status = 'new';
            $alert->acknowledged_by_user_id = null;
            $alert->acknowledged_at = null;
            $alert->resolved_by_user_id = null;
            $alert->resolved_at = null;
            $alert->closed_by_user_id = null;
            $alert->closed_at = null;
            $alert->resolution_notes = null;
        }

        $alert->save();

        $this->auditAction(
            $alert,
            $actor,
            $wasNew ? 'created' : 'updated',
            $before,
            $alert->toArray(),
            'admin',
            $wasNew ? $createdMessage : $updatedMessage,
        );

        if (in_array($severity, ['high', 'critical'], true) && $alert->status === 'new') {
            $this->notify($alert, $actor, $wasNew ? 'created' : 'updated', $title, $description, [
                'severity' => $severity,
                'alert_type' => $alertType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);
        }

        $this->communicationService->syncHealthAlert($alert, $actor);

        return $alert->fresh(['student', 'assignedTo', 'acknowledgedBy', 'resolvedBy', 'closedBy']);
    }

    private function auditAction(PreschoolHealthAlert $alert, ?User $actor, string $action, ?array $before, array $after, string $visibility, string $message): void
    {
        $this->auditService->record(
            $alert->student()->withTrashed()->firstOrFail(),
            $actor,
            'alert.'.$action,
            'health_alert',
            $alert,
            $before,
            $after,
            $alert->severity,
            $visibility,
            $message,
        );
    }

    private function notify(PreschoolHealthAlert $alert, ?User $actor, string $action, string $title, string $message, array $metadata = [], ?User $targetUser = null): void
    {
        if ($actor === null) {
            return;
        }

        try {
            $this->notificationService->createNotification($actor, [
                'type' => 'warning',
                'title' => $title,
                'message' => $message,
                'module' => 'preschool',
                'action_url' => '/module/preschool-admin/health/students/'.$alert->student_id,
                'metadata' => array_merge([
                    'student_id' => $alert->student_id,
                    'alert_id' => $alert->id,
                    'status' => $alert->status,
                    'severity' => $alert->severity,
                ], $metadata),
                'target_type' => $targetUser ? 'user' : 'role',
                'target_value' => $targetUser?->id ?? 'adminpreschool',
            ]);
        } catch (\Throwable $e) {
            // Best effort only. Alert auditing remains the source of truth.
        }
    }

    private function buildQuery(?User $viewer, array $filters = []): Builder
    {
        $query = PreschoolHealthAlert::query()
            ->with(['student', 'assignedTo', 'acknowledgedBy', 'resolvedBy', 'closedBy']);

        if ($viewer && ! $this->isAdmin($viewer)) {
            $query->whereHas('student', function (Builder $studentQuery) use ($viewer): void {
                $studentQuery->whereHas('classes', function (Builder $classQuery) use ($viewer): void {
                    $classQuery->where('teacher_user_id', $viewer->id);
                });
            });
        }

        if (($studentId = trim((string) ($filters['student_id'] ?? ''))) !== '' && $studentId !== 'all') {
            $query->where('student_id', $studentId);
        }

        if (($status = trim((string) ($filters['status'] ?? ''))) !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if (($severity = trim((string) ($filters['severity'] ?? ''))) !== '' && $severity !== 'all') {
            $query->where('severity', $severity);
        }

        if (($assignedTo = trim((string) ($filters['assigned_to'] ?? ''))) !== '' && $assignedTo !== 'all') {
            $query->where('assigned_to_user_id', $assignedTo);
        }

        if (($alertType = trim((string) ($filters['alert_type'] ?? ''))) !== '' && $alertType !== 'all') {
            $query->where('alert_type', $alertType);
        }

        if (($search = trim((string) ($filters['search'] ?? ''))) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(static function (Builder $searchQuery) use ($like): void {
                $searchQuery->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('student', static function (Builder $studentQuery) use ($like): void {
                        $studentQuery->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('student_code', 'like', $like)
                            ->orWhere('public_id', 'like', $like);
                    });
            });
        }

        return $query;
    }

    private function alertAuditLogs(PreschoolHealthAlert $alert, ?User $viewer = null): array
    {
        $query = PreschoolStudentHealthAuditLog::query()
            ->with(['actor'])
            ->where('student_id', $alert->student_id)
            ->where('entity_type', 'health_alert')
            ->where('entity_id', (string) $alert->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($viewer && ! $this->isAdmin($viewer)) {
            $query->whereIn('visibility', ['teacher', 'all']);
        }

        return $query->get()->map(static function ($log): array {
            return [
                'id' => $log->id,
                'studentId' => $log->student_id,
                'actor' => $log->actor?->only(['id', 'first_name', 'last_name', 'role_code']),
                'action' => $log->action,
                'entityType' => $log->entity_type,
                'entityId' => $log->entity_id,
                'severity' => $log->severity,
                'visibility' => $log->visibility,
                'message' => $log->message,
                'createdAt' => $log->created_at?->toISOString(),
            ];
        })->all();
    }

    private function formatAlert(PreschoolHealthAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'studentId' => $alert->student_id,
            'studentName' => trim(($alert->student?->first_name ?? '').' '.($alert->student?->last_name ?? '')) ?: $alert->student?->full_name,
            'studentPublicId' => $alert->student?->public_id,
            'studentCode' => $alert->student?->student_code,
            'studentType' => $alert->student?->student_type,
            'alertType' => $alert->alert_type,
            'severity' => $alert->severity,
            'status' => $alert->status,
            'title' => $alert->title,
            'description' => $alert->description,
            'sourceType' => $alert->source_type,
            'sourceId' => $alert->source_id,
            'assignedTo' => $this->formatUser($alert->assignedTo),
            'acknowledgedBy' => $this->formatUser($alert->acknowledgedBy),
            'resolvedBy' => $this->formatUser($alert->resolvedBy),
            'closedBy' => $this->formatUser($alert->closedBy),
            'acknowledgedAt' => $alert->acknowledged_at?->toISOString(),
            'resolvedAt' => $alert->resolved_at?->toISOString(),
            'closedAt' => $alert->closed_at?->toISOString(),
            'resolutionNotes' => $alert->resolution_notes,
            'createdAt' => $alert->created_at?->toISOString(),
            'updatedAt' => $alert->updated_at?->toISOString(),
        ];
    }

    private function formatUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'fullName' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'roleCode' => $user->role_code,
            'username' => $user->username,
        ];
    }

    private function ensureAdmin(User $actor): void
    {
        if (! $this->isAdmin($actor)) {
            throw new AuthorizationException('Only Preschool administrators may perform this action.');
        }
    }

    private function ensureWritable(User $actor, PreschoolHealthAlert $alert): void
    {
        if ($this->isAdmin($actor)) {
            return;
        }

        if ($actor->role_code === 'teacher-preschool' && $this->teacherCanAccessStudent($actor, $alert->student)) {
            return;
        }

        throw new AuthorizationException('You are not allowed to modify this alert.');
    }

    private function teacherCanAccessStudent(User $user, PreschoolStudent $student): bool
    {
        return PreschoolClass::query()
            ->where('teacher_user_id', $user->id)
            ->whereHas('students', static function (Builder $query) use ($student): void {
                $query->where('preschool_students.id', $student->id);
            })
            ->exists();
    }

    private function isAdmin(User $user): bool
    {
        return in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    private function assertAllowedStatus(string $status): void
    {
        if (! in_array($status, ['new', 'acknowledged', 'in_progress', 'resolved', 'closed'], true)) {
            throw new AuthorizationException('Invalid alert status.');
        }
    }
}

