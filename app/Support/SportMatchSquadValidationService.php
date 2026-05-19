<?php

namespace App\Support;

use App\Models\SportMatch;
use App\Models\SportMatchSquad;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SportMatchSquadValidationService
{
    public function __construct(
        private readonly SportCoachAssignmentService $assignmentService,
        private readonly SportMatchEligibilityService $eligibilityService,
    ) {}

    public function assertActorCanManageTeam(User $actor, SportMatch $match, SportTeam $team): ?JsonResponse
    {
        if (in_array($actor->role_code, ['superadmin', 'adminsport'], true)) {
            return null;
        }

        if ($actor->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        if (! $this->assignmentService->coachCanManageTeam($actor, $team)) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        if (! in_array((int) $team->id, [(int) $match->home_team_id, (int) $match->away_team_id], true)) {
            return ApiResponse::errorResponse('Team is not part of the selected match.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    public function assertSquadMutable(SportMatchSquad $squad): ?JsonResponse
    {
        $match = $squad->match()->first();

        if (! $match) {
            return ApiResponse::errorResponse('Match squad cannot be edited.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! in_array($squad->status, [SportMatchSquadStatus::DRAFT], true) || in_array($match->status, ['live', 'halftime', 'completed'], true)) {
            return ApiResponse::errorResponse('Match squad is locked.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    public function validatePlayerSelection(SportMatch $match, SportTeam $team, SportPlayer $player, string $role): array
    {
        $eligibility = $this->eligibilityService->playerEligibility($player, $team);

        if ($role !== SportMatchSquadPlayerRole::UNAVAILABLE && ! $eligibility['isEligible']) {
            throw new \RuntimeException($eligibility['reason'] ?? 'Player is not eligible.');
        }

        return $eligibility;
    }

    /**
     * @param  array<int, array{player_id:mixed, role?:mixed}>  $players
     */
    public function assertUniquePlayers(array $players): void
    {
        $ids = array_values(array_filter(array_map(static fn (array $row) => (string) ($row['player_id'] ?? ''), $players)));

        if (count($ids) !== count(array_unique($ids))) {
            throw new \RuntimeException('Duplicate players are not allowed in the same squad.');
        }
    }
}
