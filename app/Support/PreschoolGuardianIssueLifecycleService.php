<?php

namespace App\Support;

use App\Models\PreschoolGuardianGovernanceIssue;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class PreschoolGuardianIssueLifecycleService
{
    /**
     * Transition an issue to acknowledged. Any active status is valid
     * as the starting point so staff can acknowledge newly detected issues.
     */
    public function acknowledge(PreschoolGuardianGovernanceIssue $issue, User $user): PreschoolGuardianGovernanceIssue
    {
        $this->ensureAdminAccess($user);
        $this->abortIfClosed($issue);

        return DB::transaction(function () use ($issue): PreschoolGuardianGovernanceIssue {
            $issue->update([
                'status' => PreschoolGuardianGovernanceStatus::ACKNOWLEDGED,
                'acknowledged_at' => $issue->acknowledged_at ?? now(),
            ]);

            return $issue->fresh();
        });
    }

    /**
     * Assign issue to a staff member. Moves status to assigned so the
     * assignee's queue can show pending work.
     */
    public function assign(
        PreschoolGuardianGovernanceIssue $issue,
        User $user,
        string|int $assignedToUserId,
        ?string $notes = null,
    ): PreschoolGuardianGovernanceIssue {
        $this->ensureAdminAccess($user);
        $this->abortIfClosed($issue);

        return DB::transaction(function () use ($issue, $user, $assignedToUserId, $notes): PreschoolGuardianGovernanceIssue {
            $issue->update([
                'status' => PreschoolGuardianGovernanceStatus::ASSIGNED,
                'assigned_to_user_id' => $assignedToUserId,
                'acknowledged_at' => $issue->acknowledged_at ?? now(),
                'metadata' => array_merge($issue->metadata ?? [], [
                    'assigned_by_user_id' => $user->id,
                    'assignment_notes' => $notes,
                    'assigned_at' => now()->toISOString(),
                ]),
            ]);

            return $issue->fresh(['assignedTo']);
        });
    }

    /**
     * Mark issue as in-review (staff is actively investigating or remediating).
     */
    public function markInReview(PreschoolGuardianGovernanceIssue $issue, User $user): PreschoolGuardianGovernanceIssue
    {
        $this->ensureAdminAccess($user);
        $this->abortIfClosed($issue);

        return DB::transaction(function () use ($issue): PreschoolGuardianGovernanceIssue {
            $issue->update([
                'status' => PreschoolGuardianGovernanceStatus::IN_REVIEW,
                'acknowledged_at' => $issue->acknowledged_at ?? now(),
            ]);

            return $issue->fresh();
        });
    }

    /**
     * Resolve issue with optional notes. Resolution is permanent from an
     * audit perspective; if the issue recurs, the sync creates a new record.
     */
    public function resolve(
        PreschoolGuardianGovernanceIssue $issue,
        User $user,
        ?string $notes,
    ): PreschoolGuardianGovernanceIssue {
        $this->ensureAdminAccess($user);
        $this->abortIfClosed($issue);

        return DB::transaction(function () use ($issue, $notes): PreschoolGuardianGovernanceIssue {
            $issue->update([
                'status' => PreschoolGuardianGovernanceStatus::RESOLVED,
                'resolved_at' => now(),
                'resolution_notes' => $notes,
            ]);

            return $issue->fresh();
        });
    }

    /**
     * Dismiss issue with mandatory notes explaining the decision.
     * Dismissed issues are never deleted so the governance history is complete.
     */
    public function dismiss(
        PreschoolGuardianGovernanceIssue $issue,
        User $user,
        string $notes,
    ): PreschoolGuardianGovernanceIssue {
        $this->ensureAdminAccess($user);
        $this->abortIfClosed($issue);

        return DB::transaction(function () use ($issue, $notes): PreschoolGuardianGovernanceIssue {
            $issue->update([
                'status' => PreschoolGuardianGovernanceStatus::DISMISSED,
                'dismissed_at' => now(),
                'resolution_notes' => $notes,
            ]);

            return $issue->fresh();
        });
    }

    private function abortIfClosed(PreschoolGuardianGovernanceIssue $issue): void
    {
        abort_if(
            ! PreschoolGuardianGovernanceStatus::isActive($issue->status),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            "Issue is already {$issue->status} and cannot be transitioned further.",
        );
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
