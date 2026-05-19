<?php

namespace App\Http\Resources\Sport;

use App\Models\SportTeam;
use App\Support\SportMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportTeam */
class SportTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'teamCode' => $this->team_code,
            'name' => $this->name,
            'shortName' => $this->short_name,
            'coachUserId' => $this->coach_user_id,
            'coachDisplayName' => $this->coach_display_name,
            'coach' => $this->whenLoaded('coach', fn (): array => [
                'id' => $this->coach?->id,
                'firstName' => $this->coach?->first_name,
                'lastName' => $this->coach?->last_name,
                'username' => $this->coach?->username,
                'email' => $this->coach?->email,
                'avatar' => $this->coach?->avatar,
            ]),
            'activeCoachAssignment' => $this->whenLoaded('activeCoachAssignment', fn (): array => [
                'id' => $this->activeCoachAssignment?->id,
                'coachUserId' => $this->activeCoachAssignment?->coach_user_id,
                'status' => $this->activeCoachAssignment?->status,
                'assignedAt' => $this->activeCoachAssignment?->assigned_at?->toISOString(),
                'endedAt' => $this->activeCoachAssignment?->ended_at?->toISOString(),
            ]),
            'coachAssignments' => $this->whenLoaded('coachAssignments', fn (): array => SportCoachTeamAssignmentResource::collection($this->coachAssignments)->resolve($request)),
            'players' => $this->whenLoaded('players', fn (): array => SportPlayerResource::collection($this->players)->resolve($request)),
            'division' => $this->division,
            'captainName' => $this->captain_name,
            'playersCount' => $this->players_count ?? $this->whenCounted('players'),
            'activePlayersCount' => $this->whenLoaded('players', fn (): int => $this->players->where('roster_status', 'active')->count()),
            'matchesCount' => (int) ($this->matches_count ?? (($this->home_matches_count ?? 0) + ($this->away_matches_count ?? 0))),
            'wins' => (int) $this->wins,
            'draws' => (int) $this->draws,
            'losses' => (int) $this->losses,
            'points' => (int) $this->points,
            'venue' => $this->venue,
            'logo' => SportMedia::resolveUrl($this->logo),
            'status' => $this->status,
            'description' => $this->description,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
