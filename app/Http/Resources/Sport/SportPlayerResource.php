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
            'approvalStatus' => $this->approval_status,
            'rosterStatus' => $this->roster_status ?? $this->status,
            'disciplinaryStatus' => $this->disciplinary_status,
            'injuryStatus' => $this->injury_status,
            'archivedAt' => $this->archived_at?->toISOString(),
            'statusNotes' => $this->status_notes,
            'createdByUserId' => $this->created_by_user_id,
            'approvedByUserId' => $this->approved_by_user_id,
            'approvedAt' => $this->approved_at?->toISOString(),
            'rejectionReason' => $this->rejection_reason,
            'createdBy' => $this->whenLoaded('createdBy', fn (): array => [
                'id' => $this->createdBy?->id,
                'firstName' => $this->createdBy?->first_name,
                'lastName' => $this->createdBy?->last_name,
                'username' => $this->createdBy?->username,
                'email' => $this->createdBy?->email,
            ]),
            'approvedBy' => $this->whenLoaded('approvedBy', fn (): array => [
                'id' => $this->approvedBy?->id,
                'firstName' => $this->approvedBy?->first_name,
                'lastName' => $this->approvedBy?->last_name,
                'username' => $this->approvedBy?->username,
                'email' => $this->approvedBy?->email,
            ]),
            'team' => $this->whenLoaded('team', fn (): array => [
                'id' => $this->team?->id,
                'teamCode' => $this->team?->team_code,
                'name' => $this->team?->name,
                'shortName' => $this->team?->short_name,
                'logo' => SportMedia::resolveUrl($this->team?->logo),
            ]),
            'memberships' => $this->whenLoaded('memberships', fn (): array => $this->memberships->map(static function ($membership): array {
                return [
                    'id' => $membership->id,
                    'teamId' => $membership->team_id,
                    'status' => $membership->status,
                    'joinedAt' => $membership->joined_at?->toISOString(),
                    'leftAt' => $membership->left_at?->toISOString(),
                    'suspensionUntil' => $membership->suspension_until?->toISOString(),
                    'injuryNotes' => $membership->injury_notes,
                    'notes' => $membership->notes,
                    'updatedByUserId' => $membership->updated_by_user_id,
                ];
            })->values()->all()),
            'activeMembership' => $this->whenLoaded('activeMembership', fn (): array => [
                'id' => $this->activeMembership?->id,
                'teamId' => $this->activeMembership?->team_id,
                'status' => $this->activeMembership?->status,
                'joinedAt' => $this->activeMembership?->joined_at?->toISOString(),
                'leftAt' => $this->activeMembership?->left_at?->toISOString(),
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
