<?php

namespace App\Support;

use App\Models\PreschoolGuardianGovernanceIssue;
use App\Models\User;
use App\Services\PreschoolGuardianCommunicationService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class PreschoolGuardianGovernanceService
{
    public function __construct(
        private readonly PreschoolGuardianConsistencyService $consistency,
        private readonly PreschoolGuardianDuplicateService $duplicates,
        private readonly PreschoolGuardianCommunicationService $communicationService,
    ) {}

    /**
     * Sync governance issues from both consistency and duplicate reports.
     * Existing active issues are updated in place; resolved/dismissed issues
     * that resurface create a new record to preserve the prior history.
     */
    public function syncAll(User $user): array
    {
        $this->ensureAdminAccess($user);

        $consistencyReport = $this->consistency->report($user);
        $duplicateReport = $this->duplicates->report($user);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($consistencyReport, $duplicateReport, &$created, &$updated, &$skipped): void {
            foreach ($consistencyReport['issues'] ?? [] as $issue) {
                $result = $this->upsertFromConsistencyIssue($issue);
                match ($result) {
                    'created' => $created++,
                    'updated' => $updated++,
                    default => $skipped++,
                };
            }

            foreach ($duplicateReport['items'] ?? [] as $group) {
                foreach ($group['guardians'] ?? [] as $guardian) {
                    $result = $this->upsertFromDuplicateGroup($group, $guardian);
                    match ($result) {
                        'created' => $created++,
                        'updated' => $updated++,
                        default => $skipped++,
                    };
                }
            }
        });

        return [
            'success' => true,
            'message' => 'Governance issues synced from integrity reports.',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'total' => $created + $updated + $skipped,
            ],
        ];
    }

    public function listIssues(array $filters = []): LengthAwarePaginator
    {
        $query = PreschoolGuardianGovernanceIssue::query()
            ->with(['assignedTo'])
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->orderBy('detected_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['issue_type'])) {
            $query->where('issue_type', $filters['issue_type']);
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', (int) $filters['student_id']);
        }

        if (! empty($filters['guardian_id'])) {
            $query->where('guardian_id', (int) $filters['guardian_id']);
        }

        if (! empty($filters['assigned_to_user_id'])) {
            $query->where('assigned_to_user_id', (int) $filters['assigned_to_user_id']);
        }

        if (isset($filters['unassigned']) && filter_var($filters['unassigned'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNull('assigned_to_user_id');
        }

        if (isset($filters['active_only']) && filter_var($filters['active_only'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereIn('status', PreschoolGuardianGovernanceStatus::ACTIVE_STATES);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 25), 5), 100);

        return $query->paginate($perPage);
    }

    public function findIssue(int $id): PreschoolGuardianGovernanceIssue
    {
        return PreschoolGuardianGovernanceIssue::query()
            ->with(['assignedTo'])
            ->findOrFail($id);
    }

    /**
     * Upsert a governance issue from a single consistency report item.
     * Returns: 'created', 'updated', or 'skipped'.
     */
    private function upsertFromConsistencyIssue(array $issue): string
    {
        $issueKey = $this->stableKeyForConsistencyIssue($issue);
        $snapshot = $this->buildConsistencySnapshot($issue);
        $severity = $issue['severity'] ?? 'info';

        $existing = PreschoolGuardianGovernanceIssue::query()
            ->where('issue_key', $issueKey)
            ->whereIn('status', PreschoolGuardianGovernanceStatus::ACTIVE_STATES)
            ->latest('detected_at')
            ->first();

        if ($existing) {
            $newRecurrenceCount = $existing->recurrence_count + 1;
            $existing->update([
                'recurrence_count' => $newRecurrenceCount,
                'latest_snapshot' => $snapshot,
                'priority' => PreschoolGuardianGovernancePriority::fromSeverityAndRecurrence($severity, $newRecurrenceCount),
            ]);

            return 'updated';
        }

        // Check if this issue was previously resolved/dismissed to carry the recurrence count
        $previousCount = 0;
        $previous = PreschoolGuardianGovernanceIssue::query()
            ->where('issue_key', $issueKey)
            ->whereIn('status', [
                PreschoolGuardianGovernanceStatus::RESOLVED,
                PreschoolGuardianGovernanceStatus::DISMISSED,
            ])
            ->max('recurrence_count');

        if ($previous !== null) {
            $previousCount = (int) $previous + 1;
        }

        PreschoolGuardianGovernanceIssue::query()->create([
            'issue_type' => $issue['type'],
            'issue_key' => $issueKey,
            'severity' => $severity,
            'priority' => PreschoolGuardianGovernancePriority::fromSeverityAndRecurrence($severity, $previousCount),
            'status' => PreschoolGuardianGovernanceStatus::DETECTED,
            'student_id' => $issue['student']['id'] ?? null,
            'guardian_id' => $issue['guardian']['id'] ?? null,
            'relationship_id' => $issue['relationship']['id'] ?? null,
            'detected_at' => now(),
            'recurrence_count' => $previousCount,
            'latest_snapshot' => $snapshot,
        ]);
        $issueModel = PreschoolGuardianGovernanceIssue::query()->where('issue_key', $issueKey)->latest('id')->first();
        if ($issueModel) {
            $this->communicationService->syncGovernanceIssue($issueModel, auth()->user());
        }

        return 'created';
    }

    private function upsertFromDuplicateGroup(array $group, array $guardian): string
    {
        $issueKey = 'duplicate_guardian-g-'.($guardian['id'] ?? '0');
        $severity = $group['severity'] ?? 'warning';
        $snapshot = [
            'guardianId' => $guardian['id'] ?? null,
            'fullName' => $guardian['fullName'] ?? null,
            'phone' => $guardian['phone'] ?? null,
            'signals' => $group['signals'] ?? [],
            'groupSize' => count($group['guardians'] ?? []),
        ];

        $existing = PreschoolGuardianGovernanceIssue::query()
            ->where('issue_key', $issueKey)
            ->whereIn('status', PreschoolGuardianGovernanceStatus::ACTIVE_STATES)
            ->latest('detected_at')
            ->first();

        if ($existing) {
            $newCount = $existing->recurrence_count + 1;
            $existing->update([
                'recurrence_count' => $newCount,
                'latest_snapshot' => $snapshot,
                'priority' => PreschoolGuardianGovernancePriority::fromSeverityAndRecurrence($severity, $newCount),
            ]);

            return 'updated';
        }

        $previousCount = (int) (PreschoolGuardianGovernanceIssue::query()
            ->where('issue_key', $issueKey)
            ->whereIn('status', [
                PreschoolGuardianGovernanceStatus::RESOLVED,
                PreschoolGuardianGovernanceStatus::DISMISSED,
            ])
            ->max('recurrence_count') ?? 0);

        if ($previousCount > 0) {
            $previousCount++;
        }

        PreschoolGuardianGovernanceIssue::query()->create([
            'issue_type' => 'duplicate_guardian',
            'issue_key' => $issueKey,
            'severity' => $severity,
            'priority' => PreschoolGuardianGovernancePriority::fromSeverityAndRecurrence($severity, $previousCount),
            'status' => PreschoolGuardianGovernanceStatus::DETECTED,
            'guardian_id' => $guardian['id'] ?? null,
            'detected_at' => now(),
            'recurrence_count' => $previousCount,
            'latest_snapshot' => $snapshot,
        ]);
        $issueModel = PreschoolGuardianGovernanceIssue::query()->where('issue_key', $issueKey)->latest('id')->first();
        if ($issueModel) {
            $this->communicationService->syncGovernanceIssue($issueModel, auth()->user());
        }

        return 'created';
    }

    /**
     * Generate a stable, human-readable key that identifies the same issue
     * across multiple consistency report runs so we can deduplicate without
     * relying on the MD5 hash the consistency service uses internally.
     */
    private function stableKeyForConsistencyIssue(array $issue): string
    {
        $type = $issue['type'] ?? 'unknown';

        return match ($type) {
            'student_no_active_guardian',
            'multiple_active_primary_guardians',
            'legacy_guardian_mismatch' => $type.'-s-'.($issue['student']['id'] ?? '0'),

            'archived_primary_relationship',
            'inactive_emergency_contact',
            'pickup_permission_issue' => $type.'-r-'.($issue['relationship']['id'] ?? '0'),

            'guardian_without_students' => $type.'-g-'.($issue['guardian']['id'] ?? '0'),

            default => $type.'-'.md5(json_encode($issue)),
        };
    }

    private function buildConsistencySnapshot(array $issue): array
    {
        return array_filter([
            'type' => $issue['type'] ?? null,
            'severity' => $issue['severity'] ?? null,
            'title' => $issue['title'] ?? null,
            'student' => $issue['student'] ?? null,
            'guardian' => $issue['guardian'] ?? null,
            'relationship' => $issue['relationship'] ?? null,
            'difference' => $issue['difference'] ?? null,
            'preferredGuardian' => $issue['preferredGuardian'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function ensureAdminAccess(User $user): void
    {
        abort_unless(
            in_array($user->role_code, ['superadmin', 'adminpreschool'], true),
            Response::HTTP_FORBIDDEN,
            'Forbidden.',
        );
    }
}
