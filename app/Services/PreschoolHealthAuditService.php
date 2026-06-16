<?php

namespace App\Services;

use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAllergy;
use App\Models\PreschoolStudentHealthAuditLog;
use App\Models\PreschoolStudentHealthCheckLog;
use App\Models\PreschoolStudentHealthContact;
use App\Models\PreschoolStudentHealthIncident;
use App\Models\PreschoolStudentMedicationRecord;
use App\Models\PreschoolStudentVaccinationRecord;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use App\Services\NotificationService;

class PreschoolHealthAuditService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function record(
        PreschoolStudent $student,
        ?User $actor,
        string $action,
        string $entityType,
        Model|string|int|null $entity = null,
        ?array $beforeState = null,
        ?array $afterState = null,
        ?string $severity = null,
        string $visibility = 'admin',
        ?string $message = null,
    ): PreschoolStudentHealthAuditLog {
        $entityId = null;

        if ($entity instanceof Model) {
            $entityId = $entity->getKey();
            $beforeState ??= $this->normalizeState($entity->getOriginal());
            $afterState ??= $this->normalizeState($entity->getAttributes());
        } elseif ($entity !== null) {
            $entityId = $entity;
        }

        return PreschoolStudentHealthAuditLog::query()->create([
            'student_id' => $student->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== null ? (string) $entityId : null,
            'severity' => $severity,
            'visibility' => $visibility,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'message' => $message,
            'created_at' => now(),
        ]);
    }

    public function timeline(PreschoolStudent $student, array $filters = [], ?User $viewer = null): LengthAwarePaginator
    {
        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        $query = PreschoolStudentHealthAuditLog::query()
            ->with(['actor'])
            ->where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($viewer && ! in_array($viewer->role_code, ['superadmin', 'adminpreschool'], true)) {
            $query->whereIn('visibility', ['teacher', 'all']);
        }

        if (($filters['action'] ?? '') !== '') {
            $query->where('action', (string) $filters['action']);
        }

        if (($filters['severity'] ?? '') !== '') {
            $query->where('severity', (string) $filters['severity']);
        }

        if (($filters['visibility'] ?? '') !== '') {
            $query->where('visibility', (string) $filters['visibility']);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function alertSummary(?int $studentId = null): array
    {
        return [
            'summary' => $this->summaryCounts($studentId),
            'items' => $this->buildAlertItems($studentId),
            'unresolvedCriticalItems' => $this->buildCriticalItems($studentId),
        ];
    }

    public function dashboardSummary(): array
    {
        return $this->alertSummary();
    }

    public function sendNotificationHook(
        PreschoolStudent $student,
        ?User $actor,
        string $action,
        string $title,
        string $message,
        array $metadata = [],
    ): void {
        if ($actor === null) {
            return;
        }

        try {
            $this->notificationService->createNotification($actor, [
                // Notifications reuse the existing warning enum so health alerts
                // stay compatible with the shared notification subsystem.
                'type' => 'warning',
                'title' => $title,
                'message' => $message,
                'module' => 'preschool',
                'action_url' => '/module/preschool-admin/health/students/'.$student->id,
                'metadata' => array_merge([
                    'student_id' => $student->id,
                    'student_public_id' => $student->public_id,
                    'action' => $action,
                ], $metadata),
                'target_type' => 'role',
                'target_value' => 'adminpreschool',
            ]);
        } catch (AuthorizationException) {
            // Best effort. The audit timeline still records the event even if the
            // notification service rejects the publish attempt.
        }
    }

    public function emitReminderHook(array $payload): void
    {
        // TODO: Wire this into a scheduled reminder job once the reminders
        // delivery pipeline is available.
    }

    private function summaryCounts(?int $studentId = null): array
    {
        $criticalIncidents = PreschoolStudentHealthIncident::query()
            ->when($studentId !== null, static function ($query) use ($studentId): void {
                $query->where('student_id', $studentId);
            })
            ->whereIn('severity', ['high', 'critical'])
            ->where(function ($query): void {
                $query->whereNull('status')->orWhereNotIn('status', ['closed', 'resolved']);
            })
            ->count();

        $severeAllergies = PreschoolStudentAllergy::query()
            ->when($studentId !== null, static function ($query) use ($studentId): void {
                $query->where('student_id', $studentId);
            })
            ->whereIn('severity', ['high', 'critical'])
            ->where(function ($query): void {
                $query->whereNull('status')->orWhere('status', 'active');
            })
            ->count();

        $overdueVaccinations = PreschoolStudentVaccinationRecord::query()
            ->when($studentId !== null, static function ($query) use ($studentId): void {
                $query->where('student_id', $studentId);
            })
            ->where('status', 'overdue')
            ->count();

        $medicationReminders = PreschoolStudentMedicationRecord::query()
            ->when($studentId !== null, static function ($query) use ($studentId): void {
                $query->where('student_id', $studentId);
            })
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', now()->addDays(7))
            ->count();

        $missingEmergencyContacts = PreschoolStudent::query()
            ->where('status', 'active')
            ->when($studentId !== null, static function ($query) use ($studentId): void {
                $query->whereKey($studentId);
            })
            ->whereDoesntHave('emergencyHealthContacts', static function ($query): void {
                $query->where('status', 'active');
            })
            ->count();

        $recentAuditEvents = PreschoolStudentHealthAuditLog::query()
            ->when($studentId !== null, static function ($query) use ($studentId): void {
                $query->where('student_id', $studentId);
            })
            ->whereDate('created_at', '>=', now()->subDays(7)->toDateString())
            ->count();

        return [
            'criticalIncidents' => $criticalIncidents,
            'severeAllergies' => $severeAllergies,
            'missingEmergencyContacts' => $missingEmergencyContacts,
            'overdueVaccinations' => $overdueVaccinations,
            'medicationReminders' => $medicationReminders,
            'unresolvedItems' => $criticalIncidents + $severeAllergies + $missingEmergencyContacts + $overdueVaccinations + $medicationReminders,
            'recentAuditEvents' => $recentAuditEvents,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAlertItems(?int $studentId = null): array
    {
        return collect()
            ->merge($this->criticalIncidentItems($studentId))
            ->merge($this->severeAllergyItems($studentId))
            ->merge($this->overdueVaccinationItems($studentId))
            ->merge($this->missingContactItems($studentId))
            ->merge($this->medicationReminderItems($studentId))
            ->sortByDesc(static fn (array $item): string => $item['updatedAt'] ?? $item['createdAt'] ?? '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCriticalItems(?int $studentId = null): array
    {
        return collect($this->criticalIncidentItems($studentId))
            ->merge($this->severeAllergyItems($studentId))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function criticalIncidentItems(?int $studentId = null): array
    {
        return PreschoolStudentHealthIncident::query()
            ->with(['student'])
            ->when($studentId !== null, static function ($query) use ($studentId): void {
                $query->where('student_id', $studentId);
            })
            ->whereIn('severity', ['high', 'critical'])
            ->where(function ($query): void {
                $query->whereNull('status')->orWhereNotIn('status', ['closed', 'resolved']);
            })
            ->orderByDesc('incident_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(static function (PreschoolStudentHealthIncident $incident): array {
                return [
                    'id' => 'incident-'.$incident->id,
                    'studentId' => $incident->student_id,
                    'studentName' => $incident->student?->full_name ?? trim(($incident->student?->first_name ?? '').' '.($incident->student?->last_name ?? '')),
                    'title' => $incident->incident_type,
                    'message' => $incident->action_taken ?: $incident->notes,
                    'severity' => $incident->severity,
                    'entityType' => 'health_incident',
                    'entityId' => $incident->id,
                    'status' => $incident->status,
                    'updatedAt' => optional($incident->updated_at ?? $incident->created_at)?->toISOString(),
                    'createdAt' => optional($incident->created_at)?->toISOString(),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function severeAllergyItems(?int $studentId = null): array
    {
        return PreschoolStudentAllergy::query()
            ->with(['student'])
            ->when($studentId !== null, static function ($query) use ($studentId): void {
                $query->where('student_id', $studentId);
            })
            ->whereIn('severity', ['high', 'critical'])
            ->where(function ($query): void {
                $query->whereNull('status')->orWhere('status', 'active');
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(static function (PreschoolStudentAllergy $allergy): array {
                return [
                    'id' => 'allergy-'.$allergy->id,
                    'studentId' => $allergy->student_id,
                    'studentName' => $allergy->student?->full_name ?? trim(($allergy->student?->first_name ?? '').' '.($allergy->student?->last_name ?? '')),
                    'title' => $allergy->allergy_name,
                    'message' => $allergy->reaction ?: $allergy->action_taken,
                    'severity' => $allergy->severity,
                    'entityType' => 'allergy',
                    'entityId' => $allergy->id,
                    'status' => $allergy->status,
                    'updatedAt' => optional($allergy->updated_at ?? $allergy->created_at)?->toISOString(),
                    'createdAt' => optional($allergy->created_at)?->toISOString(),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overdueVaccinationItems(?int $studentId = null): array
    {
        return PreschoolStudentVaccinationRecord::query()
            ->with(['student'])
            ->when($studentId !== null, static function ($query) use ($studentId): void {
                $query->where('student_id', $studentId);
            })
            ->where('status', 'overdue')
            ->orderByDesc('vaccination_date')
            ->limit(10)
            ->get()
            ->map(static function (PreschoolStudentVaccinationRecord $record): array {
                return [
                    'id' => 'vaccination-'.$record->id,
                    'studentId' => $record->student_id,
                    'studentName' => $record->student?->full_name ?? trim(($record->student?->first_name ?? '').' '.($record->student?->last_name ?? '')),
                    'title' => $record->vaccine_name,
                    'message' => $record->provider,
                    'severity' => 'high',
                    'entityType' => 'vaccination',
                    'entityId' => $record->id,
                    'status' => $record->status,
                    'updatedAt' => optional($record->updated_at ?? $record->created_at)?->toISOString(),
                    'createdAt' => optional($record->created_at)?->toISOString(),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function missingContactItems(?int $studentId = null): array
    {
        return PreschoolStudent::query()
            ->where('status', 'active')
            ->when($studentId !== null, static function ($query) use ($studentId): void {
                $query->whereKey($studentId);
            })
            ->whereDoesntHave('emergencyHealthContacts', static function ($query): void {
                $query->where('status', 'active');
            })
            ->limit(10)
            ->get()
            ->map(static function (PreschoolStudent $student): array {
                return [
                    'id' => 'student-contact-'.$student->id,
                    'studentId' => $student->id,
                    'studentName' => $student->full_name,
                    'title' => 'Missing emergency contact',
                    'message' => 'Add or restore an active emergency contact.',
                    'severity' => 'critical',
                    'entityType' => 'emergency_contact',
                    'entityId' => null,
                    'status' => 'missing',
                    'updatedAt' => optional($student->updated_at)?->toISOString(),
                    'createdAt' => optional($student->created_at)?->toISOString(),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function medicationReminderItems(?int $studentId = null): array
    {
        return PreschoolStudentMedicationRecord::query()
            ->with(['student'])
            ->when($studentId !== null, static function ($query) use ($studentId): void {
                $query->where('student_id', $studentId);
            })
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', now()->addDays(7))
            ->orderBy('end_date')
            ->limit(10)
            ->get()
            ->map(static function (PreschoolStudentMedicationRecord $record): array {
                return [
                    'id' => 'medication-'.$record->id,
                    'studentId' => $record->student_id,
                    'studentName' => $record->student?->full_name ?? trim(($record->student?->first_name ?? '').' '.($record->student?->last_name ?? '')),
                    'title' => $record->medication_name,
                    'message' => $record->frequency,
                    'severity' => 'moderate',
                    'entityType' => 'medication',
                    'entityId' => $record->id,
                    'status' => $record->status,
                    'updatedAt' => optional($record->updated_at ?? $record->created_at)?->toISOString(),
                    'createdAt' => optional($record->created_at)?->toISOString(),
                ];
            })
            ->all();
    }

    private function normalizeState(array $state): array
    {
        return Arr::except($state, ['created_at', 'updated_at', 'deleted_at']);
    }
}
