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
        $team = $this->relationLoaded('team') ? $this->team : null;
        $fallbackTeam = $player?->team;

        $playerName = trim(($player?->first_name ?? '').' '.($player?->last_name ?? ''));
        $recordedByName = trim(($this->recordedBy?->first_name ?? '').' '.($this->recordedBy?->last_name ?? ''));

        return [
            'id' => $this->id,
            'attendanceType' => $this->attendance_type,
            'teamId' => $this->team_id,
            'teamName' => $team?->name ?? $fallbackTeam?->name,
            'playerId' => $this->player_id,
            'playerName' => $playerName,
            'personName' => $playerName,
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
