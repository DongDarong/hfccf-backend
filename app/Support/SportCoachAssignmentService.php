<?php

namespace App\Support;

use App\Models\CoachTeamAssignment;
use App\Models\SportTeam;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SportCoachAssignmentService
{
    public function activeAssignmentsForCoach(User $coach): Collection
    {
        return CoachTeamAssignment::query()
            ->with(['team', 'coach', 'assignedBy'])
            ->where('coach_user_id', $coach->id)
            ->where('status', 'active')
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->get();
    }

    public function assignedTeamsForCoach(User $coach): Collection
    {
        $assignments = $this->activeAssignmentsForCoach($coach);

        if ($assignments->isNotEmpty()) {
            return SportTeam::query()
                ->with(['coach'])
                ->whereIn('id', $assignments->pluck('team_id'))
                ->orderBy('name')
                ->get();
        }

        // Legacy fallback keeps older coach/team data usable until the new assignment rows are populated.
        return SportTeam::query()
            ->with(['coach'])
            ->where('coach_user_id', $coach->id)
            ->orderBy('name')
            ->get();
    }

    public function coachCanManageTeam(User $user, SportTeam $team): bool
    {
        if (in_array($user->role_code, ['superadmin', 'adminsport'], true)) {
            return true;
        }

        if ($user->role_code !== 'coach') {
            return false;
        }

        if ((string) $team->coach_user_id === (string) $user->id) {
            return true;
        }

        return CoachTeamAssignment::query()
            ->where('coach_user_id', $user->id)
            ->where('team_id', $team->id)
            ->where('status', 'active')
            ->exists();
    }

    public function activeAssignmentForTeam(SportTeam|int $team): ?CoachTeamAssignment
    {
        $teamId = $team instanceof SportTeam ? $team->id : $team;

        return CoachTeamAssignment::query()
            ->with(['team', 'coach', 'assignedBy'])
            ->where('team_id', $teamId)
            ->where('status', 'active')
            ->latest('assigned_at')
            ->latest('id')
            ->first();
    }

    public function assignTeamToCoach(SportTeam $team, User $coach, User $assignedBy): CoachTeamAssignment
    {
        return DB::transaction(function () use ($team, $coach, $assignedBy): CoachTeamAssignment {
            $now = Carbon::now();

            $currentActive = $this->activeAssignmentForTeam($team);
            if ($currentActive && (string) $currentActive->coach_user_id !== (string) $coach->id) {
                $currentActive->forceFill([
                    'status' => 'inactive',
                    'ended_at' => $now,
                ])->save();
            }

            $assignment = CoachTeamAssignment::query()->firstOrNew([
                'coach_user_id' => $coach->id,
                'team_id' => $team->id,
            ]);

            $assignment->forceFill([
                'assigned_by_user_id' => $assignedBy->id,
                'status' => 'active',
                'assigned_at' => $assignment->assigned_at ?? $now,
                'ended_at' => null,
            ])->save();

            $team->forceFill([
                'coach_user_id' => $coach->id,
                'coach_display_name' => trim($coach->first_name.' '.$coach->last_name),
            ])->save();

            return $assignment->refresh()->loadMissing(['team', 'coach', 'assignedBy']);
        });
    }

    public function updateAssignment(CoachTeamAssignment $assignment, array $data, User $changedBy): CoachTeamAssignment
    {
        return DB::transaction(function () use ($assignment, $data, $changedBy): CoachTeamAssignment {
            $team = $assignment->team()->first();
            $coach = $assignment->coach()->first();
            $now = Carbon::now();

            if (! $team || ! $coach) {
                return $assignment;
            }

            $status = strtolower((string) ($data['status'] ?? $assignment->status));
            $assignment->forceFill([
                'assigned_by_user_id' => $changedBy->id,
                'status' => $status,
                'assigned_at' => $assignment->assigned_at ?? $now,
                'ended_at' => $status === 'inactive' ? ($assignment->ended_at ?? $now) : null,
            ])->save();

            if ($status === 'active') {
                $team->forceFill([
                    'coach_user_id' => $coach->id,
                    'coach_display_name' => trim($coach->first_name.' '.$coach->last_name),
                ])->save();
            } elseif ((string) $team->coach_user_id === (string) $coach->id) {
                $team->forceFill([
                    'coach_user_id' => null,
                    'coach_display_name' => null,
                ])->save();
            }

            return $assignment->refresh()->loadMissing(['team', 'coach', 'assignedBy']);
        });
    }

    public function deactivateAssignment(CoachTeamAssignment $assignment, User $changedBy): CoachTeamAssignment
    {
        return $this->updateAssignment($assignment, ['status' => 'inactive'], $changedBy);
    }
}
