<?php

namespace App\Support;

use App\Models\PreschoolGuardian;
use App\Models\PreschoolGuardianRemediationLog;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class PreschoolGuardianRemediationAuditService
{
    /**
     * Persist a remediation log entry and return it.
     * All callers are responsible for wrapping in a DB::transaction.
     */
    public function log(
        string $issueType,
        string $action,
        User $performedBy,
        array $options = [],
    ): PreschoolGuardianRemediationLog {
        return PreschoolGuardianRemediationLog::query()->create([
            'issue_type' => $issueType,
            'issue_key' => $options['issue_key'] ?? null,
            'student_id' => $options['student_id'] ?? null,
            'guardian_id' => $options['guardian_id'] ?? null,
            'related_guardian_id' => $options['related_guardian_id'] ?? null,
            'relationship_id' => $options['relationship_id'] ?? null,
            'action' => $action,
            'before_snapshot' => $options['before_snapshot'] ?? null,
            'after_snapshot' => $options['after_snapshot'] ?? null,
            'notes' => $options['notes'] ?? null,
            'performed_by_user_id' => $performedBy->id,
            'performed_at' => now(),
        ]);
    }

    public function paginatedLogs(array $filters = []): LengthAwarePaginator
    {
        $query = PreschoolGuardianRemediationLog::query()
            ->with(['performedBy'])
            ->orderByDesc('performed_at')
            ->orderByDesc('id');

        if (! empty($filters['issue_type'])) {
            $query->where('issue_type', $filters['issue_type']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', (int) $filters['student_id']);
        }

        if (! empty($filters['guardian_id'])) {
            $query->where('guardian_id', (int) $filters['guardian_id']);
        }

        if (! empty($filters['performed_by_user_id'])) {
            $query->where('performed_by_user_id', (int) $filters['performed_by_user_id']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 25), 5), 100);

        return $query->paginate($perPage);
    }

    public function guardianSnapshot(PreschoolGuardian $guardian): array
    {
        return [
            'id' => $guardian->id,
            'fullName' => $guardian->full_name,
            'phone' => $guardian->phone,
            'secondaryPhone' => $guardian->secondary_phone,
            'email' => $guardian->email,
            'status' => $guardian->status,
        ];
    }

    public function studentSnapshot(PreschoolStudent $student): array
    {
        return [
            'id' => $student->id,
            'studentCode' => $student->student_code,
            'fullName' => trim($student->first_name.' '.$student->last_name),
            'guardianName' => $student->guardian_name,
            'guardianPhone' => $student->guardian_phone,
        ];
    }

    public function relationshipSnapshot(PreschoolStudentGuardian $rel): array
    {
        return [
            'id' => $rel->id,
            'studentId' => $rel->student_id,
            'guardianId' => $rel->guardian_id,
            'relationshipType' => $rel->relationship_type,
            'isPrimary' => (bool) $rel->is_primary,
            'canPickup' => (bool) $rel->can_pickup,
            'emergencyPriority' => $rel->emergency_priority,
            'status' => $rel->status,
            'startsAt' => $rel->starts_at?->toDateString(),
            'endsAt' => $rel->ends_at?->toDateString(),
            'notes' => $rel->notes,
        ];
    }
}
