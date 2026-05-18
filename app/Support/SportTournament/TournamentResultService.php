<?php

namespace App\Support\SportTournament;

use App\Models\SportMatch;
use App\Models\SportMatchEvent;
use App\Models\SportPlayer;
use App\Models\User;
use App\Support\SportMatchScoreService;
use Illuminate\Support\Carbon;

class TournamentResultService
{
    public function __construct(
        private readonly SportMatchScoreService $scoreService,
        private readonly TournamentKnockoutService $knockoutService,
    ) {
    }

    /**
     * Save the final score snapshot for a tournament match.
     *
     * The source of truth stays the match row plus its events, so the score is
     * always recalculated instead of trusting a client-side total.
     */
    public function saveResult(SportMatch $match, array $data, ?User $user = null): SportMatch
    {
        if (in_array($match->status, ['cancelled', 'postponed'], true)) {
            throw new \RuntimeException('Cancelled or postponed matches cannot be completed.');
        }

        $match->fill([
            'home_score' => (int) ($data['home_score'] ?? $match->home_score),
            'away_score' => (int) ($data['away_score'] ?? $match->away_score),
            'extra_time_home_score' => (int) ($data['extra_time_home_score'] ?? $match->extra_time_home_score ?? 0),
            'extra_time_away_score' => (int) ($data['extra_time_away_score'] ?? $match->extra_time_away_score ?? 0),
            'penalty_home_score' => (int) ($data['penalty_home_score'] ?? $match->penalty_home_score ?? 0),
            'penalty_away_score' => (int) ($data['penalty_away_score'] ?? $match->penalty_away_score ?? 0),
            'status' => $data['status'] ?? 'completed',
            'winner_team_id' => $this->resolveWinnerTeamId($match, $data),
            'completed_at' => $match->completed_at ?? Carbon::now(),
            'current_period' => $data['current_period'] ?? $match->current_period ?? 'final',
            'notes' => $data['notes'] ?? $match->notes,
        ]);

        if ($match->knockoutRound && ($data['status'] ?? $match->status) === 'completed') {
            $resolvedWinner = $this->resolveWinnerTeamId($match, $data);
            $isDraw = (int) $match->home_score === (int) $match->away_score
                && (int) $match->extra_time_home_score === (int) $match->extra_time_away_score
                && (int) $match->penalty_home_score === (int) $match->penalty_away_score;

            if ($isDraw || ! $resolvedWinner) {
                throw new \RuntimeException('Knockout matches require a winner before completion.');
            }

            $match->winner_team_id = $resolvedWinner;
        }

        $match->save();

        $this->scoreService->recalculate($match);
        $match->refresh()->loadMissing(['homeTeam', 'awayTeam', 'tournament', 'group', 'knockoutRound', 'events.team', 'events.player', 'events.assistPlayer', 'events.playerIn', 'events.playerOut']);

        $this->knockoutService->propagateWinner($match, $user);

        return $match;
    }

    /**
     * Create a new event for a match and keep the score snapshot in sync.
     */
    public function saveEvent(SportMatch $match, array $data, ?User $user = null): SportMatchEvent
    {
        $teamId = (int) $data['team_id'];
        $playerId = $this->resolvePlayerId($data['player_id'] ?? null, $data['player_name'] ?? null);
        $assistPlayerId = $this->resolvePlayerId($data['assist_player_id'] ?? null, $data['assist_player_name'] ?? null);
        $playerInId = $this->resolvePlayerId($data['player_in_id'] ?? null, $data['player_in_name'] ?? null);
        $playerOutId = $this->resolvePlayerId($data['player_out_id'] ?? null, $data['player_out_name'] ?? null);

        foreach ([$playerId, $assistPlayerId, $playerInId, $playerOutId] as $candidateId) {
            if (! $candidateId) {
                continue;
            }

            $candidate = SportPlayer::query()->find($candidateId);
            if ($candidate && (int) $candidate->team_id !== $teamId) {
                throw new \RuntimeException('Player must belong to the selected team.');
            }
        }

        $event = SportMatchEvent::query()->create([
            'tournament_id' => $match->tournament_id,
            'match_id' => $match->id,
            'team_id' => $teamId,
            'player_id' => $playerId,
            'assist_player_id' => $assistPlayerId,
            'player_in_id' => $playerInId,
            'player_out_id' => $playerOutId,
            'event_type' => $this->normalizeEventType((string) $data['event_type']),
            'minute' => (int) $data['minute'],
            'extra_time_minute' => $data['extra_time_minute'] ?? null,
            'stoppage_minute' => $data['stoppage_minute'] ?? null,
            'side' => $data['side'] ?? null,
            'description' => $data['description'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'created_by_user_id' => $user?->id,
        ]);

        $this->scoreService->recalculate($match);
        $match->refresh()->loadMissing(['events.team', 'events.player', 'events.assistPlayer', 'events.playerIn', 'events.playerOut']);

        return $event->loadMissing(['team', 'player', 'assistPlayer', 'playerIn', 'playerOut']);
    }

    private function resolveWinnerTeamId(SportMatch $match, array $data): ?int
    {
        $winnerId = isset($data['winner_team_id']) ? (int) $data['winner_team_id'] : null;
        if ($winnerId) {
            return $winnerId;
        }

        $homeScore = (int) ($data['home_score'] ?? $match->home_score);
        $awayScore = (int) ($data['away_score'] ?? $match->away_score);
        $penaltyHome = (int) ($data['penalty_home_score'] ?? $match->penalty_home_score ?? 0);
        $penaltyAway = (int) ($data['penalty_away_score'] ?? $match->penalty_away_score ?? 0);

        if ($penaltyHome !== $penaltyAway && ($penaltyHome > 0 || $penaltyAway > 0)) {
            return $penaltyHome > $penaltyAway ? (int) $match->home_team_id : (int) $match->away_team_id;
        }

        if ($homeScore === $awayScore) {
            return null;
        }

        return $homeScore > $awayScore ? (int) $match->home_team_id : (int) $match->away_team_id;
    }

    private function resolvePlayerId(mixed $id, mixed $name): ?int
    {
        if ($id !== null && $id !== '') {
            return (int) $id;
        }

        if (blank($name)) {
            return null;
        }

        $player = SportPlayer::query()
            ->whereRaw('LOWER(CONCAT(first_name, " ", last_name)) = ?', [mb_strtolower(trim((string) $name))])
            ->orWhereRaw('LOWER(first_name) = ?', [mb_strtolower(trim((string) $name))])
            ->orWhereRaw('LOWER(last_name) = ?', [mb_strtolower(trim((string) $name))])
            ->first();

        return $player?->id ? (int) $player->id : null;
    }

    private function normalizeEventType(string $eventType): string
    {
        return match (strtolower(trim($eventType))) {
            'penalty_missed' => 'penalty_miss',
            default => strtolower(trim($eventType)),
        };
    }
}
