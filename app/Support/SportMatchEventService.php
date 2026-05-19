<?php

namespace App\Support;

use App\Models\SportMatch;
use App\Models\SportMatchEvent;
use App\Models\SportMatchSquadPlayer;
use App\Models\SportPlayer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SportMatchEventService
{
    public function __construct(
        private readonly SportMatchEventValidationService $validationService,
        private readonly SportMatchTimelineService $timelineService,
        private readonly SportMatchScoreService $scoreService,
        private readonly SportStandingsService $standingsService,
    ) {}

    public function timelineForMatch(SportMatch $match): Collection
    {
        $match->loadMissing([
            'events.team',
            'events.player',
            'events.assistPlayer',
            'events.playerIn',
            'events.playerOut',
            'events.squad',
            'events.squadPlayer.player',
            'events.relatedSquadPlayer.player',
        ]);

        return $this->timelineService->sortEvents($match->events);
    }

    public function createEvent(SportMatch $match, array $data, User $actor): SportMatchEvent
    {
        return DB::transaction(function () use ($match, $data, $actor): SportMatchEvent {
            $teamId = (int) ($data['team_id'] ?? 0);
            $player = null;
            $squadPlayer = null;
            $relatedSquadPlayer = null;

            if ($response = $this->validationService->assertMatchReadyForTimeline($match)) {
                throw new \RuntimeException($response->getData(true)['message'] ?? 'Match cannot be edited.');
            }

            if ($response = $this->validationService->assertTeamBelongsToMatch($match, $teamId)) {
                throw new \RuntimeException($response->getData(true)['message'] ?? 'Event team must belong to this match.');
            }

            $eventType = SportMatchEventType::normalize((string) ($data['event_type'] ?? ''));

            if (! in_array($eventType, SportMatchEventType::values(), true)) {
                throw new \RuntimeException('Invalid match event type.');
            }

            if (! empty($data['squad_player_id'])) {
                $squadPlayer = $this->validationService->resolveSquadPlayerById($match, (int) $data['squad_player_id']);
                if (! $squadPlayer) {
                    throw new \RuntimeException('Event player must belong to the match squad.');
                }

                $teamId = (int) $squadPlayer->team_id;
                $player = $squadPlayer->player()->first();
            } else {
                $player = $this->validationService->resolveEventPlayer($match, $teamId, $data);
                if ($player) {
                    $squadPlayer = $match->squads()
                        ->with(['players.player'])
                        ->where('team_id', $teamId)
                        ->first()
                        ?->players
                        ->firstWhere('player_id', $player->id);
                }
            }

            $existingEvents = $this->timelineForMatch($match);
            if ($response = $this->validationService->assertTimelineRules($match, $data, $existingEvents)) {
                throw new \RuntimeException($response->getData(true)['message'] ?? 'Timeline validation failed.');
            }

            if ($eventType === SportMatchEventType::SUBSTITUTION_OUT && ! empty($data['related_squad_player_id'])) {
                $relatedSquadPlayer = $this->validationService->resolveSquadPlayerById($match, (int) $data['related_squad_player_id']);
            } elseif ($eventType === SportMatchEventType::SUBSTITUTION_IN && ! empty($data['related_squad_player_id'])) {
                $relatedSquadPlayer = $this->validationService->resolveSquadPlayerById($match, (int) $data['related_squad_player_id']);
            }

            $event = SportMatchEvent::query()->create([
                'match_id' => $match->id,
                'tournament_id' => $match->tournament_id,
                'team_id' => $teamId,
                'squad_id' => $squadPlayer?->squad_id ?? ($data['squad_id'] ?? null),
                'squad_player_id' => $squadPlayer?->id ?? ($data['squad_player_id'] ?? null),
                'related_squad_player_id' => $relatedSquadPlayer?->id ?? ($data['related_squad_player_id'] ?? null),
                'player_id' => $player?->id,
                'assist_player_id' => $data['assist_player_id'] ?? null,
                'player_in_id' => $eventType === SportMatchEventType::SUBSTITUTION_IN ? ($squadPlayer?->player_id ?? $player?->id) : null,
                'player_out_id' => $eventType === SportMatchEventType::SUBSTITUTION_OUT ? ($squadPlayer?->player_id ?? $player?->id) : null,
                'event_type' => $eventType,
                'minute' => (int) ($data['minute'] ?? 0),
                'extra_time_minute' => $data['extra_time_minute'] ?? null,
                'stoppage_minute' => $data['stoppage_minute'] ?? null,
                'side' => $data['side'] ?? null,
                'period' => $data['period'] ?? $match->current_period ?? null,
                'description' => $data['description'] ?? null,
                'player_name_snapshot' => $data['player_name_snapshot'] ?? trim(($player?->first_name ?? '').' '.($player?->last_name ?? '')),
                'jersey_number_snapshot' => $data['jersey_number_snapshot'] ?? $squadPlayer?->jersey_number_snapshot ?? $player?->jersey_number,
                'position_snapshot' => $data['position_snapshot'] ?? $squadPlayer?->position_snapshot ?? ($player?->primary_position ?: $player?->position),
                'metadata' => $this->buildMetadata($data, $player, $squadPlayer, $relatedSquadPlayer),
                'created_by_user_id' => $actor->id,
            ]);

            $this->recalculateMatch($match);

            return $event->refresh()->loadMissing([
                'team',
                'player',
                'assistPlayer',
                'playerIn',
                'playerOut',
                'squad',
                'squadPlayer.player',
                'relatedSquadPlayer.player',
                'createdBy',
            ]);
        });
    }

    public function updateEvent(SportMatchEvent $event, array $data, User $actor): SportMatchEvent
    {
        $match = $event->match()->firstOrFail();

        return DB::transaction(function () use ($event, $match, $data, $actor): SportMatchEvent {
            if ($response = $this->validationService->assertMatchReadyForTimeline($match)) {
                throw new \RuntimeException($response->getData(true)['message'] ?? 'Match cannot be edited.');
            }

            $existingEvents = $this->timelineForMatch($match);
            $eventType = SportMatchEventType::normalize((string) ($data['event_type'] ?? $event->event_type));
            $nextTeamId = (int) ($data['team_id'] ?? $event->team_id);

            if ($response = $this->validationService->assertTeamBelongsToMatch($match, $nextTeamId)) {
                throw new \RuntimeException($response->getData(true)['message'] ?? 'Event team must belong to this match.');
            }

            $candidate = array_merge($event->toArray(), $data, [
                'team_id' => $nextTeamId,
                'event_type' => $eventType,
                'squad_player_id' => $data['squad_player_id'] ?? $event->squad_player_id,
                'related_squad_player_id' => $data['related_squad_player_id'] ?? $event->related_squad_player_id,
            ]);

            if ($response = $this->validationService->assertTimelineRules($match, $candidate, $existingEvents, $event)) {
                throw new \RuntimeException($response->getData(true)['message'] ?? 'Timeline validation failed.');
            }

            $player = $event->player;
            $squadPlayer = $event->squadPlayer;
            $relatedSquadPlayer = $event->relatedSquadPlayer;

            if (! empty($candidate['squad_player_id'])) {
                $squadPlayer = $this->validationService->resolveSquadPlayerById($match, (int) $candidate['squad_player_id']);
                $player = $squadPlayer?->player()->first() ?: $player;
            } elseif (! empty($candidate['player_id'])) {
                $player = SportPlayer::query()->find($candidate['player_id']);
            }

            if (! empty($candidate['related_squad_player_id'])) {
                $relatedSquadPlayer = $this->validationService->resolveSquadPlayerById($match, (int) $candidate['related_squad_player_id']);
            }

            foreach ([
                'team_id' => $nextTeamId,
                'squad_id' => $squadPlayer?->squad_id ?? $event->squad_id,
                'squad_player_id' => $squadPlayer?->id ?? $event->squad_player_id,
                'related_squad_player_id' => $relatedSquadPlayer?->id ?? $event->related_squad_player_id,
                'player_id' => $player?->id,
                'event_type' => $eventType,
                'minute' => $data['minute'] ?? $event->minute,
                'extra_time_minute' => array_key_exists('extra_time_minute', $data) ? $data['extra_time_minute'] : $event->extra_time_minute,
                'stoppage_minute' => array_key_exists('stoppage_minute', $data) ? $data['stoppage_minute'] : $event->stoppage_minute,
                'side' => array_key_exists('side', $data) ? $data['side'] : $event->side,
                'period' => array_key_exists('period', $data) ? $data['period'] : $event->period,
                'description' => array_key_exists('description', $data) ? $data['description'] : $event->description,
            ] as $field => $value) {
                $event->{$field} = $value;
            }

            if (array_key_exists('player_name_snapshot', $data)) {
                $event->player_name_snapshot = $data['player_name_snapshot'];
            } elseif ($player) {
                $event->player_name_snapshot = trim($player->first_name.' '.$player->last_name);
            }

            if (array_key_exists('jersey_number_snapshot', $data)) {
                $event->jersey_number_snapshot = $data['jersey_number_snapshot'];
            } elseif ($player) {
                $event->jersey_number_snapshot = $player->jersey_number;
            }

            if (array_key_exists('position_snapshot', $data)) {
                $event->position_snapshot = $data['position_snapshot'];
            } elseif ($player) {
                $event->position_snapshot = $player->primary_position ?: $player->position;
            }

            $event->metadata = $this->buildMetadata($data, $player, $squadPlayer, $relatedSquadPlayer, $event->metadata ?? []);
            $event->save();

            $this->recalculateMatch($match);

            return $event->refresh()->loadMissing([
                'team',
                'player',
                'assistPlayer',
                'playerIn',
                'playerOut',
                'squad',
                'squadPlayer.player',
                'relatedSquadPlayer.player',
                'createdBy',
            ]);
        });
    }

    public function deleteEvent(SportMatchEvent $event, User $actor): void
    {
        $match = $event->match()->firstOrFail();

        if ($response = $this->validationService->assertMatchReadyForTimeline($match)) {
            throw new \RuntimeException($response->getData(true)['message'] ?? 'Match cannot be edited.');
        }

        $event->delete();
        $this->recalculateMatch($match);
    }

    private function buildMetadata(array $data, ?SportPlayer $player = null, ?SportMatchSquadPlayer $squadPlayer = null, ?SportMatchSquadPlayer $related = null, array $fallback = []): array
    {
        $metadata = array_merge($fallback, $data['metadata'] ?? []);

        if ($player) {
            $metadata['player_code'] = $player->player_code;
        }

        if ($squadPlayer) {
            $metadata['squad_player_id'] = $squadPlayer->id;
            $metadata['squad_player_role'] = $squadPlayer->role;
        }

        if ($related) {
            $metadata['related_squad_player_id'] = $related->id;
        }

        if (! empty($data['player_name'])) {
            $metadata['player_name'] = $data['player_name'];
        }

        return $metadata;
    }

    private function recalculateMatch(SportMatch $match): void
    {
        $this->scoreService->recalculate($match);

        if ($match->tournament_id) {
            $this->standingsService->rebuildTournamentById((int) $match->tournament_id);
        }
    }
}
