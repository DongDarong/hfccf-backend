<?php

namespace App\Http\Resources\Sport;

use App\Models\SportMatchSquad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportMatchSquad */
class SportMatchSquadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matchId' => $this->match_id,
            'teamId' => $this->team_id,
            'selectedByUserId' => $this->selected_by_user_id,
            'status' => $this->status,
            'lockedAt' => $this->locked_at?->toISOString(),
            'submittedAt' => $this->submitted_at?->toISOString(),
            'approvedByUserId' => $this->approved_by_user_id,
            'approvedAt' => $this->approved_at?->toISOString(),
            'notes' => $this->notes,
            'match' => $this->whenLoaded('match', fn (): array => [
                'id' => $this->match?->id,
                'matchCode' => $this->match?->match_code,
                'status' => $this->match?->status,
                'scheduledAt' => $this->match?->scheduled_at?->toISOString(),
            ]),
            'team' => $this->whenLoaded('team', fn (): array => [
                'id' => $this->team?->id,
                'teamCode' => $this->team?->team_code,
                'name' => $this->team?->name,
                'shortName' => $this->team?->short_name,
            ]),
            'selectedBy' => $this->whenLoaded('selectedBy', fn (): array => [
                'id' => $this->selectedBy?->id,
                'firstName' => $this->selectedBy?->first_name,
                'lastName' => $this->selectedBy?->last_name,
                'username' => $this->selectedBy?->username,
                'email' => $this->selectedBy?->email,
            ]),
            'approvedBy' => $this->whenLoaded('approvedBy', fn (): array => [
                'id' => $this->approvedBy?->id,
                'firstName' => $this->approvedBy?->first_name,
                'lastName' => $this->approvedBy?->last_name,
                'username' => $this->approvedBy?->username,
                'email' => $this->approvedBy?->email,
            ]),
            'players' => $this->whenLoaded('players', fn (): array => $this->players->map(fn ($player) => SportMatchSquadPlayerResource::make($player)->resolve($request))->values()->all()),
            'playersCount' => $this->whenLoaded('players', fn (): int => $this->players->count()),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
