<?php

namespace App\Http\Resources\Sport;

use App\Models\CoachTeamAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CoachTeamAssignment */
class SportCoachTeamAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'coachUserId' => $this->coach_user_id,
            'teamId' => $this->team_id,
            'assignedByUserId' => $this->assigned_by_user_id,
            'status' => $this->status,
            'assignedAt' => $this->assigned_at?->toISOString(),
            'endedAt' => $this->ended_at?->toISOString(),
            'coach' => $this->whenLoaded('coach', fn (): array => [
                'id' => $this->coach?->id,
                'firstName' => $this->coach?->first_name,
                'lastName' => $this->coach?->last_name,
                'username' => $this->coach?->username,
                'email' => $this->coach?->email,
            ]),
            'team' => $this->whenLoaded('team', fn (): array => [
                'id' => $this->team?->id,
                'teamCode' => $this->team?->team_code,
                'name' => $this->team?->name,
                'shortName' => $this->team?->short_name,
            ]),
            'assignedBy' => $this->whenLoaded('assignedBy', fn (): array => [
                'id' => $this->assignedBy?->id,
                'firstName' => $this->assignedBy?->first_name,
                'lastName' => $this->assignedBy?->last_name,
                'username' => $this->assignedBy?->username,
                'email' => $this->assignedBy?->email,
            ]),
        ];
    }
}
