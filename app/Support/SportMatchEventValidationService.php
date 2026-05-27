<?php

namespace App\Support;

use App\Models\SportMatch;
use App\Models\SportMatchEvent;
use App\Models\SportMatchSquad;
use App\Models\SportMatchSquadPlayer;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class SportMatchEventValidationService
{
    public function assertActorCanAccessMatch(?User $actor, SportMatch $match): ?JsonResponse
    {
        if (! $actor) {
            return ApiResponse::errorResponse('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($actor->role_code, ['superadmin', 'adminsport'], true)) {
            return null;
        }

        if ($actor->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $hasAccess = SportTeam::query()
            ->whereIn('id', [$match->home_team_id, $match->away_team_id])
            ->where(function ($query) use ($actor): void {
                $query->where('coach_user_id', $actor->id)
                    ->orWhereHas('coachAssignments', function ($assignmentQuery) use ($actor): void {
                        $assignmentQuery->where('coach_user_id', $actor->id)
                            ->where('status', 'active');
                    });
            })
            ->exists();

        return $hasAccess
            ? null
            : ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
    }

    public function assertMatchEditable(SportMatch $match): ?JsonResponse
    {
        if (in_array($match->status, ['completed'], true)) {
            return ApiResponse::errorResponse('Completed matches cannot be edited.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    public function assertMatchReadyForTimeline(SportMatch $match): ?JsonResponse
    {
        if (! in_array($match->status, ['live', 'halftime'], true)) {
            return ApiResponse::errorResponse('Events can only be edited while the match is live or halftime.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    public function assertTeamBelongsToMatch(SportMatch $match, int $teamId): ?JsonResponse
    {
        if (! in_array($teamId, [(int) $match->home_team_id, (int) $match->away_team_id], true)) {
            return ApiResponse::errorResponse('Event team must belong to this match.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    public function resolveMatchSquad(SportMatch $match, int $teamId): ?SportMatchSquad
    {
        return $match->squads()
            ->with(['players.player', 'team'])
            ->where('team_id', $teamId)
            ->first();
    }

    public function resolveSquadPlayer(SportMatch $match, int $teamId, int $squadPlayerId): ?SportMatchSquadPlayer
    {
        return $match->squads()
            ->with(['players.player'])
            ->where('team_id', $teamId)
            ->whereHas('players', function ($query) use ($squadPlayerId): void {
                $query->where('id', $squadPlayerId);
            })
            ->first()
            ?->players
            ->firstWhere('id', $squadPlayerId);
    }

    public function resolveSquadPlayerById(SportMatch $match, int $squadPlayerId): ?SportMatchSquadPlayer
    {
        return SportMatchSquadPlayer::query()
            ->with(['squad.match', 'team', 'player'])
            ->where('id', $squadPlayerId)
            ->where('match_id', $match->id)
            ->first();
    }

    public function resolveEventPlayer(SportMatch $match, int $teamId, array $data): ?SportPlayer
    {
        if (! empty($data['player_id'])) {
            $player = SportPlayer::query()->with(['activeMembership', 'team'])->find($data['player_id']);

            if ($player && (int) $player->team_id === $teamId) {
                return $player;
            }

            return null;
        }

        if (! empty($data['player_name'])) {
            return SportPlayer::query()
                ->where('team_id', $teamId)
                ->whereRaw('LOWER(CONCAT(first_name, " ", last_name)) = ?', [mb_strtolower(trim((string) $data['player_name']))])
                ->first();
        }

        return null;
    }

    /**
     * @param  Collection<int, SportMatchEvent>  $events
     */
    public function assertTimelineRules(
        SportMatch $match,
        array $candidate,
        Collection $events,
        ?SportMatchEvent $existing = null,
    ): ?JsonResponse {
        $eventType = SportMatchEventType::normalize((string) ($candidate['event_type'] ?? ''));
        $subjectId = (int) ($candidate['squad_player_id'] ?? $existing?->squad_player_id ?? 0);

        if ($subjectId <= 0 && $match->squads()->exists()) {
            return ApiResponse::errorResponse('A match squad player is required for the event.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $subject = $subjectId > 0 ? $this->resolveSquadPlayerById($match, $subjectId) : null;
        if ($subjectId > 0 && ! $subject) {
            return ApiResponse::errorResponse('Event player must belong to the match squad.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($existing) {
            $events = $events->reject(fn (SportMatchEvent $event): bool => (string) $event->id === (string) $existing->id)->values();
        }

        $subjectHistory = $subject
            ? $events->filter(fn (SportMatchEvent $event): bool => (string) $event->squad_player_id === (string) $subject->id)
            : collect();

        if ($subject && $subjectHistory->contains(fn (SportMatchEvent $event): bool => SportMatchEventType::normalize((string) $event->event_type) === SportMatchEventType::RED_CARD) && $eventType !== SportMatchEventType::RED_CARD) {
            return ApiResponse::errorResponse('Red-carded players cannot continue participating.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($subject && $eventType === SportMatchEventType::RED_CARD && $subjectHistory->contains(fn (SportMatchEvent $event): bool => SportMatchEventType::normalize((string) $event->event_type) === SportMatchEventType::RED_CARD)) {
            return ApiResponse::errorResponse('A player cannot receive two red cards in the same match.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($subject && in_array($eventType, [SportMatchEventType::SUBSTITUTION_IN, SportMatchEventType::SUBSTITUTION_OUT], true)) {
            $relatedId = (int) ($candidate['related_squad_player_id'] ?? 0);
            $related = $relatedId > 0 ? $this->resolveSquadPlayerById($match, $relatedId) : null;

            if (! $related) {
                return ApiResponse::errorResponse('A substitution requires a paired squad player.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ((string) $related->team_id !== (string) $subject->team_id) {
                return ApiResponse::errorResponse('Substitution players must belong to the same team.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($subject->id === $related->id) {
                return ApiResponse::errorResponse('A player cannot substitute themselves.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $subjectHasSubIn = $subjectHistory->contains(fn (SportMatchEvent $event): bool => SportMatchEventType::normalize((string) $event->event_type) === SportMatchEventType::SUBSTITUTION_IN);
            $subjectHasSubOut = $subjectHistory->contains(fn (SportMatchEvent $event): bool => SportMatchEventType::normalize((string) $event->event_type) === SportMatchEventType::SUBSTITUTION_OUT);
            $relatedHistory = $events->filter(fn (SportMatchEvent $event): bool => (string) $event->squad_player_id === (string) $related->id);
            $relatedHasSubIn = $relatedHistory->contains(fn (SportMatchEvent $event): bool => SportMatchEventType::normalize((string) $event->event_type) === SportMatchEventType::SUBSTITUTION_IN);
            $relatedHasSubOut = $relatedHistory->contains(fn (SportMatchEvent $event): bool => SportMatchEventType::normalize((string) $event->event_type) === SportMatchEventType::SUBSTITUTION_OUT);

            if ($eventType === SportMatchEventType::SUBSTITUTION_IN && $subjectHasSubIn) {
                return ApiResponse::errorResponse('Player cannot be substituted in twice.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($eventType === SportMatchEventType::SUBSTITUTION_OUT && $subjectHasSubOut) {
                return ApiResponse::errorResponse('Player cannot be substituted out twice.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($eventType === SportMatchEventType::SUBSTITUTION_OUT && ($relatedHasSubIn || $relatedHasSubOut)) {
                return ApiResponse::errorResponse('Replacement player is already participating in the match.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($eventType === SportMatchEventType::SUBSTITUTION_IN && ($relatedHasSubIn || $relatedHasSubOut)) {
                return ApiResponse::errorResponse('Replacement pairing is already in use.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        return null;
    }
}
