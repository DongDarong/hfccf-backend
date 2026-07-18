<?php

namespace App\Support;

use App\Models\SportTeam;
use App\Models\SportTrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SportTrainingSessionService
{
    public function __construct(private readonly SportCoachAssignmentService $assignmentService) {}

    public function visibleQuery(User $user): Builder
    {
        $query = SportTrainingSession::query()->with(['team', 'coach', 'creator', 'updater']);

        if ($user->role_code === 'coach') {
            $teamIds = $this->assignmentService->assignedTeamsForCoach($user)->pluck('id')->all();

            return $teamIds === [] ? $query->whereRaw('1 = 0') : $query->whereIn('team_id', $teamIds);
        }

        return $query;
    }

    public function findVisible(User $user, int|string $id): ?SportTrainingSession
    {
        return $this->visibleQuery($user)->whereKey($id)->first();
    }

    public function create(array $data, User $actor): SportTrainingSession
    {
        $team = SportTeam::query()->with('activeCoachAssignment')->findOrFail($data['team_id']);
        $data['session_code'] = $data['session_code'] ?? $this->nextSessionCode();
        $data['session_code'] = $data['session_code'] ?: $this->nextSessionCode();
        $data['coach_user_id'] ??= $team->activeCoachAssignment?->coach_user_id ?? $team->coach_user_id;
        $data['created_by_user_id'] = $actor->id;
        $data['updated_by_user_id'] = $actor->id;

        return SportTrainingSession::query()->create($data)->load(['team', 'coach', 'creator', 'updater']);
    }

    public function update(SportTrainingSession $session, array $data, User $actor): SportTrainingSession
    {
        $data['updated_by_user_id'] = $actor->id;
        $session->fill($data)->save();

        return $session->refresh()->load(['team', 'coach', 'creator', 'updater']);
    }

    public function delete(SportTrainingSession $session): void
    {
        $session->delete();
    }

    private function nextSessionCode(): string
    {
        do {
            $code = 'TRN-'.Str::upper(Str::random(8));
        } while (SportTrainingSession::withTrashed()->where('session_code', $code)->exists());

        return $code;
    }
}
