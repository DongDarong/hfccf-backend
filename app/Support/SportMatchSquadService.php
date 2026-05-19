<?php

namespace App\Support;

use App\Models\SportMatch;
use App\Models\SportMatchSquad;
use App\Models\SportMatchSquadPlayer;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\User;
use App\Services\SportActivityRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SportMatchSquadService
{
    public function __construct(
        private readonly SportMatchEligibilityService $eligibilityService,
        private readonly SportMatchSquadSnapshotService $snapshotService,
        private readonly SportMatchSquadValidationService $validationService,
        private readonly SportActivityRecorder $activityRecorder,
    ) {}

    public function squadsForMatch(SportMatch $match, ?User $actor = null): Collection
    {
        $query = $match->squads()
            ->with(['team', 'selectedBy', 'approvedBy', 'players.player'])
            ->orderBy('id');

        if ($actor && $actor->role_code === 'coach') {
            $query->whereHas('team', function ($builder) use ($actor): void {
                $builder->where('coach_user_id', $actor->id)
                    ->orWhereHas('coachAssignments', function ($assignmentQuery) use ($actor): void {
                        $assignmentQuery->where('coach_user_id', $actor->id)->where('status', 'active');
                    });
            });
        }

        return $query->get();
    }

    public function squadForMatchTeam(SportMatch $match, SportTeam $team, ?User $actor = null): ?SportMatchSquad
    {
        return $match->squads()
            ->with(['team', 'selectedBy', 'approvedBy', 'players.player'])
            ->where('team_id', $team->id)
            ->first();
    }

    public function eligibilityForMatchTeam(SportMatch $match, SportTeam $team, ?User $actor = null): Collection
    {
        return $this->eligibilityService->playersForMatchTeam($match, $team, $actor);
    }

    public function buildPreviewSquad(SportMatch $match, SportTeam $team, ?User $actor = null): SportMatchSquad
    {
        $squad = new SportMatchSquad([
            'match_id' => $match->id,
            'team_id' => $team->id,
            'status' => SportMatchSquadStatus::DRAFT,
        ]);

        $players = $this->eligibilityForMatchTeam($match, $team, $actor)->map(function (array $row) use ($match, $team, $actor): SportMatchSquadPlayer {
            /** @var SportPlayer $player */
            $player = $row['player'];

            $snapshot = $this->snapshotService->buildPlayerSnapshot(
                $match,
                $team,
                $player,
                $row['isEligible'] ? SportMatchSquadPlayerRole::RESERVE : SportMatchSquadPlayerRole::UNAVAILABLE,
                $row['eligibilityStatus'],
                (bool) $row['isEligible'],
                $row['reason'],
                $actor,
            );

            return new SportMatchSquadPlayer($snapshot);
        })->values();

        $squad->setRelation('players', $players);
        $squad->setRelation('match', $match);
        $squad->setRelation('team', $team);

        return $squad;
    }

    public function saveSquad(SportMatch $match, SportTeam $team, array $payload, User $actor): SportMatchSquad
    {
        return DB::transaction(function () use ($match, $team, $payload, $actor): SportMatchSquad {
            $squad = SportMatchSquad::query()->firstOrCreate([
                'match_id' => $match->id,
                'team_id' => $team->id,
            ], [
                'status' => SportMatchSquadStatus::DRAFT,
                'selected_by_user_id' => $actor->id,
            ]);

            if ($response = $this->validationService->assertSquadMutable($squad)) {
                throw new \RuntimeException($response->getData(true)['message'] ?? 'Match squad is locked.');
            }

            $players = $payload['players'] ?? [];
            $this->validationService->assertUniquePlayers($players);

            $currentRows = $squad->players()->get()->keyBy('player_id');
            $incomingPlayerIds = [];

            foreach ($players as $row) {
                $player = SportPlayer::query()->with(['activeMembership'])->find($row['player_id'] ?? null);

                if (! $player) {
                    throw new \RuntimeException('Player not found.');
                }

                if ((int) ($player->team_id ?? 0) !== (int) $team->id && (int) ($player->activeMembership?->team_id ?? 0) !== (int) $team->id) {
                    throw new \RuntimeException('Player does not belong to the selected team.');
                }

                $role = strtolower((string) ($row['role'] ?? SportMatchSquadPlayerRole::RESERVE));
                if (! in_array($role, SportMatchSquadPlayerRole::values(), true)) {
                    throw new \RuntimeException('Invalid squad player role.');
                }

                $eligibility = $this->validationService->validatePlayerSelection($match, $team, $player, $role);
                $incomingPlayerIds[] = $player->id;

                $squadPlayer = $currentRows->get($player->id) ?? new SportMatchSquadPlayer;
                $squadPlayer->forceFill(array_merge(
                    [
                        'squad_id' => $squad->id,
                        'match_id' => $match->id,
                        'team_id' => $team->id,
                        'player_id' => $player->id,
                    ],
                    $this->snapshotService->buildPlayerSnapshot(
                        $match,
                        $team,
                        $player,
                        $role,
                        $eligibility['eligibilityStatus'],
                        (bool) $eligibility['isEligible'],
                        $eligibility['reason'] ?? null,
                        $actor,
                    ),
                ))->save();
            }

            $squad->forceFill([
                'status' => SportMatchSquadStatus::DRAFT,
                'notes' => $payload['notes'] ?? $squad->notes,
                'selected_by_user_id' => $actor->id,
            ])->save();

            if ($incomingPlayerIds === []) {
                $squad->players()->delete();
            } else {
                $squad->players()
                    ->whereNotIn('player_id', $incomingPlayerIds)
                    ->delete();
            }

            return $squad->refresh()->loadMissing(['match', 'team', 'selectedBy', 'approvedBy', 'players.player']);
        });
    }

    public function updateSquad(SportMatchSquad $squad, array $payload, User $actor): SportMatchSquad
    {
        $match = $squad->match()->firstOrFail();
        $team = $squad->team()->firstOrFail();

        return $this->saveSquad($match, $team, $payload, $actor);
    }

    public function submitSquad(SportMatchSquad $squad, User $actor): SportMatchSquad
    {
        if ($squad->status !== SportMatchSquadStatus::DRAFT) {
            throw new \RuntimeException('Only draft squads can be submitted.');
        }

        $squad = $this->transitionSquad($squad, SportMatchSquadStatus::SUBMITTED, $actor, true, [SportMatchSquadStatus::DRAFT], [
            'submitted_at' => Carbon::now(),
        ]);

        $this->activityRecorder->squadChanged($squad, $actor, SportAuditAction::MATCH_SQUAD_SUBMITTED);

        return $squad;
    }

    public function approveSquad(SportMatchSquad $squad, User $actor): SportMatchSquad
    {
        if ($squad->status !== SportMatchSquadStatus::SUBMITTED) {
            throw new \RuntimeException('Only submitted squads can be approved.');
        }

        $squad = $this->transitionSquad($squad, SportMatchSquadStatus::APPROVED, $actor, true, [SportMatchSquadStatus::SUBMITTED], [
            'approved_by_user_id' => $actor->id,
            'approved_at' => Carbon::now(),
        ]);

        $this->activityRecorder->squadChanged($squad, $actor, SportAuditAction::MATCH_SQUAD_APPROVED);

        return $squad;
    }

    public function lockSquad(SportMatchSquad $squad, User $actor): SportMatchSquad
    {
        if ($squad->status === SportMatchSquadStatus::LOCKED) {
            throw new \RuntimeException('Match squad is already locked.');
        }

        $squad = $this->transitionSquad($squad, SportMatchSquadStatus::LOCKED, $actor, false, [
            SportMatchSquadStatus::DRAFT,
            SportMatchSquadStatus::SUBMITTED,
            SportMatchSquadStatus::APPROVED,
        ], [
            'locked_at' => Carbon::now(),
        ]);

        $this->activityRecorder->squadChanged($squad, $actor, SportAuditAction::MATCH_SQUAD_LOCKED);

        return $squad;
    }

    /**
     * @param  array<int, string>  $allowedStatuses
     * @param  array<string, mixed>  $extra
     */
    private function transitionSquad(SportMatchSquad $squad, string $status, User $actor, bool $blockClosedMatch, array $allowedStatuses, array $extra = []): SportMatchSquad
    {
        return DB::transaction(function () use ($squad, $status, $actor, $blockClosedMatch, $allowedStatuses, $extra): SportMatchSquad {
            if (! in_array($squad->status, $allowedStatuses, true)) {
                throw new \RuntimeException('Match squad state cannot be changed.');
            }

            $match = $squad->match()->first();
            if ($blockClosedMatch && $match && in_array($match->status, ['live', 'halftime', 'completed'], true)) {
                throw new \RuntimeException('Match squad is locked.');
            }

            $squad->forceFill(array_merge([
                'status' => $status,
                'selected_by_user_id' => $squad->selected_by_user_id ?? $actor->id,
            ], $extra))->save();

            return $squad->refresh()->loadMissing(['match', 'team', 'selectedBy', 'approvedBy', 'players.player']);
        });
    }
}
