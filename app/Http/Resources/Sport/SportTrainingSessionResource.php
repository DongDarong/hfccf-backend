<?php

namespace App\Http\Resources\Sport;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SportTrainingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sessionCode' => $this->session_code,
            'teamId' => $this->team_id,
            'team' => $this->whenLoaded('team', fn (): array => [
                'id' => $this->team?->id, 'teamCode' => $this->team?->team_code,
                'name' => $this->team?->name, 'shortName' => $this->team?->short_name,
            ]),
            'coachUserId' => $this->coach_user_id,
            'coach' => $this->whenLoaded('coach', fn (): array => [
                'id' => $this->coach?->id, 'firstName' => $this->coach?->first_name,
                'lastName' => $this->coach?->last_name, 'username' => $this->coach?->username,
                'email' => $this->coach?->email,
            ]),
            'title' => $this->title,
            'trainingType' => $this->training_type,
            'focus' => $this->focus,
            'venue' => $this->venue,
            'startsAt' => $this->starts_at?->toISOString(),
            'endsAt' => $this->ends_at?->toISOString(),
            'intensity' => $this->intensity,
            'status' => $this->status,
            'notes' => $this->notes,
            'createdByUserId' => $this->created_by_user_id,
            'updatedByUserId' => $this->updated_by_user_id,
            'creator' => $this->whenLoaded('creator', fn (): array => [
                'id' => $this->creator?->id, 'firstName' => $this->creator?->first_name,
                'lastName' => $this->creator?->last_name, 'username' => $this->creator?->username,
            ]),
            'updater' => $this->whenLoaded('updater', fn (): array => [
                'id' => $this->updater?->id, 'firstName' => $this->updater?->first_name,
                'lastName' => $this->updater?->last_name, 'username' => $this->updater?->username,
            ]),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
