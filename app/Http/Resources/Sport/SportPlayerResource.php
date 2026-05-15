<?php

namespace App\Http\Resources\Sport;

use App\Models\SportPlayer;
use App\Support\SportMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportPlayer */
class SportPlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'playerCode' => $this->player_code,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'name' => trim($this->first_name.' '.$this->last_name),
            'jerseyNumber' => $this->jersey_number,
            'position' => $this->position,
            'teamId' => $this->team_id,
            'division' => $this->division,
            'team' => $this->whenLoaded('team', fn (): array => [
                'id' => $this->team?->id,
                'teamCode' => $this->team?->team_code,
                'name' => $this->team?->name,
                'shortName' => $this->team?->short_name,
                'logo' => SportMedia::resolveUrl($this->team?->logo),
            ]),
            'gender' => $this->gender,
            'age' => $this->age,
            'dateOfBirth' => $this->date_of_birth?->toDateString(),
            'phone' => $this->phone,
            'photo' => SportMedia::resolveUrl($this->photo),
            'heightCm' => $this->height_cm,
            'weightKg' => $this->weight_kg !== null ? (float) $this->weight_kg : null,
            'preferredFoot' => $this->preferred_foot,
            'bloodType' => $this->blood_type,
            'village' => $this->village,
            'commune' => $this->commune,
            'district' => $this->district,
            'province' => $this->province,
            'currentSchool' => $this->current_school,
            'gradeYear' => $this->grade_year,
            'primaryPosition' => $this->primary_position,
            'registrationStatus' => $this->registration_status,
            'matchesPlayed' => $this->matches_played,
            'goalsScored' => $this->goals_scored,
            'status' => $this->status,
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
