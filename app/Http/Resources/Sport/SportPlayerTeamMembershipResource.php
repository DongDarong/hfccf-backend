<?php

namespace App\Http\Resources\Sport;

use App\Models\SportPlayerTeamMembership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportPlayerTeamMembership */
class SportPlayerTeamMembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'teamId' => $this->team_id,
            'playerId' => $this->player_id,
            'status' => $this->status,
            'joinedAt' => $this->joined_at?->toISOString(),
            'leftAt' => $this->left_at?->toISOString(),
            'suspensionUntil' => $this->suspension_until?->toISOString(),
            'injuryNotes' => $this->injury_notes,
            'notes' => $this->notes,
            'createdByUserId' => $this->created_by_user_id,
            'updatedByUserId' => $this->updated_by_user_id,
            'team' => $this->whenLoaded('team', fn (): array => [
                'id' => $this->team?->id,
                'teamCode' => $this->team?->team_code,
                'name' => $this->team?->name,
                'shortName' => $this->team?->short_name,
            ]),
            'player' => $this->whenLoaded('player', fn (): array => [
                'id' => $this->player?->id,
                'playerCode' => $this->player?->player_code,
                'firstName' => $this->player?->first_name,
                'lastName' => $this->player?->last_name,
                'name' => trim(($this->player?->first_name ?? '').' '.($this->player?->last_name ?? '')),
                'approvalStatus' => $this->player?->approval_status,
                'rosterStatus' => $this->player?->roster_status ?? $this->player?->status,
            ]),
        ];
    }
}
