<?php

namespace App\Http\Resources\Sport;

use App\Models\SportAttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportAttendanceRecord */
class SportAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $player = $this->relationLoaded('player') ? $this->player : null;
        $coach = $this->relationLoaded('coach') ? $this->coach : null;
        $team = $this->relationLoaded('team') ? $this->team : null;
        $fallbackTeam = $player?->team;

        $playerName = trim(($player?->first_name ?? '').' '.($player?->last_name ?? ''));
        $coachName = trim(($coach?->first_name ?? '').' '.($coach?->last_name ?? ''));
        $recordedByName = trim(($this->recordedBy?->first_name ?? '').' '.($this->recordedBy?->last_name ?? ''));

        return [
            'id' => $this->id,
            'attendanceType' => $this->attendance_type,
            'teamId' => $this->team_id,
            'teamName' => $team?->name ?? $fallbackTeam?->name,
            'playerId' => $this->player_id,
            'playerName' => $playerName,
            'coachId' => $this->coach_user_id,
            'coachName' => $coachName,
            'personName' => $this->attendance_type === 'coach' ? $coachName : $playerName,
            'recordedByUserId' => $this->recorded_by_user_id,
            'recordedByName' => $recordedByName !== '' ? $recordedByName : $this->recordedBy?->username,
            'attendanceDate' => $this->attendance_date?->toDateString(),
            'status' => $this->status,
            'note' => $this->note,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
