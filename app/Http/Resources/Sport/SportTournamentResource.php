<?php

namespace App\Http\Resources\Sport;

use App\Models\SportTournament;
use App\Support\SportMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportTournament */
class SportTournamentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournamentCode' => $this->tournament_code,
            'slug' => $this->slug,
            'name' => $this->name,
            'season' => $this->season,
            'tournamentType' => $this->tournament_type,
            'sportType' => $this->tournament_type,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'registrationOpenAt' => $this->registration_open_at?->toISOString(),
            'registrationCloseAt' => $this->registration_close_at?->toISOString(),
            'startsAt' => $this->starts_at?->toISOString(),
            'endsAt' => $this->ends_at?->toISOString(),
            'description' => $this->description,
            'logoPath' => $this->logo_path,
            'logoUrl' => SportMedia::resolveUrl($this->logo_path),
            'bannerPath' => $this->banner_path,
            'bannerUrl' => SportMedia::resolveUrl($this->banner_path),
            'location' => $this->location,
            'organizer' => $this->organizer,
            'rules' => $this->rules,
            'settings' => $this->settings,
            'createdByUserId' => $this->created_by_user_id,
            'teamsCount' => $this->teams_count ?? $this->whenCounted('teams'),
            'matchesCount' => $this->matches_count ?? $this->whenCounted('matches'),
            'standingsCount' => $this->standings_count ?? $this->whenCounted('standings'),
            'groupsCount' => $this->groups_count ?? $this->whenCounted('groups'),
            'knockoutRoundsCount' => $this->knockout_rounds_count ?? $this->whenCounted('knockoutRounds'),
            'matchEventsCount' => $this->match_events_count ?? $this->whenCounted('matchEvents'),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
