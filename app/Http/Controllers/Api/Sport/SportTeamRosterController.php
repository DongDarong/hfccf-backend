<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\StoreTeamRosterMembershipRequest;
use App\Http\Requests\Sport\UpdateTeamRosterMembershipRequest;
use App\Http\Resources\Sport\SportPlayerResource;
use App\Http\Resources\Sport\SportPlayerTeamMembershipResource;
use App\Http\Resources\Sport\SportTeamResource;
use App\Models\SportPlayer;
use App\Models\SportPlayerTeamMembership;
use App\Models\SportTeam;
use App\Support\ApiResponse;
use App\Support\SportCoachAssignmentService;
use App\Support\SportTeamRosterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportTeamRosterController extends SportController
{
    public function __construct(
        private readonly SportTeamRosterService $rosterService,
        private readonly SportCoachAssignmentService $assignmentService,
    ) {}

    public function index(Request $request, string $team): JsonResponse
    {
        $teamModel = SportTeam::query()
            ->with(['coach', 'activeCoachAssignment.coach', 'activeCoachAssignment.assignedBy', 'coachAssignments.coach', 'coachAssignments.assignedBy'])
            ->find($team);

        if (! $teamModel) {
            return ApiResponse::errorResponse('Team not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($response = $this->authorizeTeamAccess($request, $teamModel)) {
            return $response;
        }

        $players = $this->rosterService->rosterForTeam($teamModel);
        $memberships = $players->flatMap(fn (SportPlayer $player) => $player->memberships)->values();

        return ApiResponse::successResponse('Team roster retrieved successfully.', [
            'team' => SportTeamResource::make($teamModel->loadMissing(['coach', 'activeCoachAssignment.coach', 'activeCoachAssignment.assignedBy', 'coachAssignments.coach', 'coachAssignments.assignedBy']))->resolve($request),
            'players' => SportPlayerResource::collection($players)->resolve($request),
            'memberships' => SportPlayerTeamMembershipResource::collection($memberships)->resolve($request),
        ]);
    }

    public function candidates(Request $request, ?string $team = null): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $teamModel = $team ? SportTeam::query()
            ->with(['coach', 'activeCoachAssignment.coach', 'activeCoachAssignment.assignedBy', 'coachAssignments.coach', 'coachAssignments.assignedBy'])
            ->find($team) : null;

        if ($team && ! $teamModel) {
            return ApiResponse::errorResponse('Team not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($teamModel) {
            if ($response = $this->authorizeTeamAccess($request, $teamModel)) {
                return $response;
            }
        } elseif ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $players = $this->rosterService->eligiblePlayersForTeam($teamModel, $search);

        return ApiResponse::successResponse('Team roster candidates retrieved successfully.', [
            'team' => $teamModel
                ? SportTeamResource::make($teamModel->loadMissing(['coach', 'activeCoachAssignment.coach', 'activeCoachAssignment.assignedBy', 'coachAssignments.coach', 'coachAssignments.assignedBy']))->resolve($request)
                : null,
            'items' => $players->map(static fn (SportPlayer $player): array => [
                'id' => $player->id,
                'playerCode' => $player->player_code,
                'firstName' => $player->first_name,
                'lastName' => $player->last_name,
                'name' => trim($player->first_name.' '.$player->last_name),
                'jerseyNumber' => $player->jersey_number,
                'position' => $player->position,
                'primaryPosition' => $player->primary_position,
                'division' => $player->division,
                'approvalStatus' => $player->approval_status,
                'rosterStatus' => $player->roster_status ?? $player->status,
                'teamId' => $player->team_id,
                'teamName' => $player->team?->name,
                'activeMembership' => $player->activeMembership ? [
                    'id' => $player->activeMembership->id,
                    'teamId' => $player->activeMembership->team_id,
                    'status' => $player->activeMembership->status,
                    'joinedAt' => $player->activeMembership->joined_at?->toISOString(),
                    'leftAt' => $player->activeMembership->left_at?->toISOString(),
                ] : null,
            ])->values()->all(),
            'pagination' => [
                'page' => 1,
                'perPage' => $players->count() ?: 10,
                'total' => $players->count(),
                'totalPages' => 1,
            ],
        ]);
    }

    public function store(StoreTeamRosterMembershipRequest $request, string $team): JsonResponse
    {
        $teamModel = SportTeam::query()->find($team);

        if (! $teamModel) {
            return ApiResponse::errorResponse('Team not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($response = $this->authorizeTeamAccess($request, $teamModel)) {
            return $response;
        }

        $data = $request->validated();
        $player = SportPlayer::query()->with(['team', 'memberships'])->find($data['player_id']);

        if (! $player) {
            return ApiResponse::errorResponse('Player not found.', null, Response::HTTP_NOT_FOUND);
        }

        try {
            $membership = $this->rosterService->attachPlayer(
                $teamModel,
                $player,
                $request->user(),
                (string) ($data['membership_status'] ?? 'active'),
                $data['notes'] ?? null,
            );
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse('Team roster player added successfully.', [
            'membership' => SportPlayerTeamMembershipResource::make($membership)->resolve($request),
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateTeamRosterMembershipRequest $request, string $membership): JsonResponse
    {
        $membershipModel = SportPlayerTeamMembership::query()
            ->with(['team', 'player', 'createdBy', 'updatedBy'])
            ->find($membership);

        if (! $membershipModel) {
            return ApiResponse::errorResponse('Membership not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($response = $this->authorizeTeamAccess($request, $membershipModel->team)) {
            return $response;
        }

        $membershipModel = $this->rosterService->updateMembership($membershipModel, $request->validated(), $request->user());

        return ApiResponse::successResponse('Team roster membership updated successfully.', [
            'membership' => SportPlayerTeamMembershipResource::make($membershipModel)->resolve($request),
        ]);
    }

    public function destroy(Request $request, string $membership): JsonResponse
    {
        $membershipModel = SportPlayerTeamMembership::query()
            ->with(['team', 'player', 'createdBy', 'updatedBy'])
            ->find($membership);

        if (! $membershipModel) {
            return ApiResponse::errorResponse('Membership not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($response = $this->authorizeTeamAccess($request, $membershipModel->team)) {
            return $response;
        }

        $membershipModel = $this->rosterService->deactivateMembership($membershipModel, $request->user());

        return ApiResponse::successResponse('Team roster membership deactivated successfully.', [
            'membership' => SportPlayerTeamMembershipResource::make($membershipModel)->resolve($request),
        ]);
    }

    private function authorizeTeamAccess(Request $request, ?SportTeam $team): ?JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $team) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        if (in_array($user->role_code, ['superadmin', 'adminsport'], true)) {
            return null;
        }

        if ($user->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        return $this->assignmentService->coachCanManageTeam($user, $team)
            ? null
            : ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
    }
}
