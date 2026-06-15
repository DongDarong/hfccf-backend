<?php

namespace App\Observers;

use App\Models\PreschoolStudentAllergy;
use App\Models\PreschoolStudentHealthCheckLog;
use App\Models\PreschoolStudentHealthContact;
use App\Models\PreschoolStudentHealthIncident;
use App\Models\PreschoolStudentMedicalProfile;
use App\Models\PreschoolStudentMedicationRecord;
use App\Models\PreschoolStudentVaccinationRecord;
use App\Services\PreschoolHealthAuditService;
use Illuminate\Database\Eloquent\Model;

class PreschoolStudentHealthObserver
{
    public function __construct(
        private readonly PreschoolHealthAuditService $auditService,
    ) {
    }

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    private function record(Model $model, string $action): void
    {
        if (! $this->isSupported($model)) {
            return;
        }

        $student = $model->student()->withTrashed()->first();
        if (! $student) {
            return;
        }

        $actor = auth()->user();
        $entityType = $this->entityType($model);
        $severity = $this->severity($model);
        $visibility = $this->visibility($model);
        $message = $this->message($model, $action);

        $this->auditService->record(
            $student,
            $actor,
            $entityType.'.'.$action,
            $entityType,
            $model,
            $action === 'deleted' ? $this->normalizeState($model->getOriginal()) : null,
            $action === 'deleted' ? null : $this->normalizeState($model->getAttributes()),
            $severity,
            $visibility,
            $message,
        );

        if ($this->shouldNotify($model, $severity)) {
            $this->auditService->sendNotificationHook(
                $student,
                $actor,
                $entityType.'.'.$action,
                $this->notificationTitle($model, $action),
                $message,
                [
                    'entity_type' => $entityType,
                    'entity_id' => (string) $model->getKey(),
                    'severity' => $severity,
                ],
            );
        }
    }

    private function isSupported(Model $model): bool
    {
        return $model instanceof PreschoolStudentMedicalProfile
            || $model instanceof PreschoolStudentAllergy
            || $model instanceof PreschoolStudentVaccinationRecord
            || $model instanceof PreschoolStudentMedicationRecord
            || $model instanceof PreschoolStudentHealthIncident
            || $model instanceof PreschoolStudentHealthContact
            || $model instanceof PreschoolStudentHealthCheckLog;
    }

    private function entityType(Model $model): string
    {
        return match (true) {
            $model instanceof PreschoolStudentMedicalProfile => 'medical_profile',
            $model instanceof PreschoolStudentAllergy => 'allergy',
            $model instanceof PreschoolStudentVaccinationRecord => 'vaccination',
            $model instanceof PreschoolStudentMedicationRecord => 'medication',
            $model instanceof PreschoolStudentHealthIncident => 'health_incident',
            $model instanceof PreschoolStudentHealthContact => 'emergency_contact',
            $model instanceof PreschoolStudentHealthCheckLog => 'health_check',
            default => 'health_record',
        };
    }

    private function visibility(Model $model): string
    {
        return match (true) {
            $model instanceof PreschoolStudentHealthIncident,
            $model instanceof PreschoolStudentHealthCheckLog => 'teacher',
            default => 'admin',
        };
    }

    private function severity(Model $model): ?string
    {
        return match (true) {
            $model instanceof PreschoolStudentAllergy && in_array((string) $model->severity, ['high', 'critical'], true) => (string) $model->severity,
            $model instanceof PreschoolStudentHealthIncident => (string) ($model->severity ?? ''),
            $model instanceof PreschoolStudentVaccinationRecord && $model->status === 'overdue' => 'high',
            default => null,
        };
    }

    private function shouldNotify(Model $model, ?string $severity): bool
    {
        if ($model instanceof PreschoolStudentHealthIncident) {
            return in_array((string) $severity, ['high', 'critical'], true);
        }

        if ($model instanceof PreschoolStudentAllergy) {
            return in_array((string) $severity, ['high', 'critical'], true);
        }

        return $model instanceof PreschoolStudentHealthContact;
    }

    private function notificationTitle(Model $model, string $action): string
    {
        return match (true) {
            $model instanceof PreschoolStudentHealthIncident => 'Health incident '.$action,
            $model instanceof PreschoolStudentAllergy => 'Severe allergy '.$action,
            $model instanceof PreschoolStudentHealthContact => 'Emergency contact '.$action,
            default => 'Health record '.$action,
        };
    }

    private function message(Model $model, string $action): string
    {
        return match (true) {
            $model instanceof PreschoolStudentMedicalProfile => 'Medical profile '.$action,
            $model instanceof PreschoolStudentAllergy => trim((string) ($model->allergy_name ?? 'Allergy')).' '.$action,
            $model instanceof PreschoolStudentVaccinationRecord => trim((string) ($model->vaccine_name ?? 'Vaccination')).' '.$action,
            $model instanceof PreschoolStudentMedicationRecord => trim((string) ($model->medication_name ?? 'Medication')).' '.$action,
            $model instanceof PreschoolStudentHealthIncident => trim((string) ($model->incident_type ?? 'Health incident')).' '.$action,
            $model instanceof PreschoolStudentHealthContact => trim((string) ($model->name ?? 'Emergency contact')).' '.$action,
            $model instanceof PreschoolStudentHealthCheckLog => 'Health check '.$action,
            default => 'Health record '.$action,
        };
    }

    private function normalizeState(array $state): array
    {
        unset($state['created_at'], $state['updated_at'], $state['deleted_at']);

        return $state;
    }
}