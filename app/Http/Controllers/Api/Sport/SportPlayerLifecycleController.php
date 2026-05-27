<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Resources\Sport\SportPlayerResource;
use App\Http\Resources\Sport\SportPlayerTeamMembershipResource;
use App\Models\SportPlayer;
use App\Support\ApiResponse;
use App\Support\SportCoachAssignmentService;
use App\Support\SportPlayerLifecycleService;
use App\Support\SportPlayerRosterStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SportPlayerLifecycleController extends SportController
{
    public function __construct(
        private readonly SportPlayerLifecycleService $lifecycleService,
        private readonly SportCoachAssignmentService $assignmentService,
    ) {}

    public function history(Request $request, string $player): JsonResponse
    {
        $playerModel = SportPlayer::query()
            ->with(['team', 'createdBy', 'approvedBy', 'activeMembership.team', 'memberships.team', 'memberships.createdBy', 'memberships.updatedBy'])
            ->find($player);

        if (! $playerModel) {
            return ApiResponse::errorResponse('Player not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($response = $this->authorizePlayerAccess($request, $playerModel)) {
            return $response;
        }

        return ApiResponse::successResponse('Player history retrieved successfully.', [
            'player' => SportPlayerResource::make($playerModel)->resolve($request),
            'memberships' => SportPlayerTeamMembershipResource::collection($playerModel->memberships)->resolve($request),
        ]);
    }

    public function updateStatus(Request $request, string $player): JsonResponse
    {
        $request->merge([
            'roster_status' => $request->input('roster_status', $request->input('rosterStatus', $request->input('status'))),
        ]);

        $data = $request->validate([
            'roster_status' => ['required', 'string', 'in:active,injured,suspended,inactive,released,graduated,archived'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $playerModel = $this->loadPlayerOrFail($player, $request);
        if ($playerModel instanceof JsonResponse) {
            return $playerModel;
        }

        if ($response = $this->authorizePlayerAccess($request, $playerModel)) {
            return $response;
        }

        if ($data['roster_status'] === SportPlayerRosterStatus::ARCHIVED && ! $this->isSportAdmin($request->user())) {
            return ApiResponse::errorResponse('Only Sport Admin can archive players.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $playerModel = $this->lifecycleService->setRosterStatus($playerModel, $data['roster_status'], $request->user(), [
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse('Player status updated successfully.', [
            'player' => SportPlayerResource::make($playerModel)->resolve($request),
        ]);
    }

    public function injury(Request $request, string $player): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $playerModel = $this->loadPlayerOrFail($player, $request);
        if ($playerModel instanceof JsonResponse) {
            return $playerModel;
        }

        if ($response = $this->authorizePlayerAccess($request, $playerModel)) {
            return $response;
        }

        $playerModel = $this->lifecycleService->markInjured($playerModel, $data['notes'] ?? null, $request->user());

        return ApiResponse::successResponse('Player injury status updated successfully.', [
            'player' => SportPlayerResource::make($playerModel)->resolve($request),
        ]);
    }

    public function suspension(Request $request, string $player): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'suspension_until' => ['sometimes', 'nullable', 'date'],
        ]);

        $playerModel = $this->loadPlayerOrFail($player, $request);
        if ($playerModel instanceof JsonResponse) {
            return $playerModel;
        }

        if ($response = $this->authorizePlayerAccess($request, $playerModel)) {
            return $response;
        }

        $playerModel = $this->lifecycleService->markSuspended(
            $playerModel,
            $data['notes'] ?? null,
            isset($data['suspension_until']) ? Carbon::parse($data['suspension_until']) : null,
            $request->user(),
        );

        return ApiResponse::successResponse('Player suspension updated successfully.', [
            'player' => SportPlayerResource::make($playerModel)->resolve($request),
        ]);
    }

    public function release(Request $request, string $player): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $playerModel = $this->loadPlayerOrFail($player, $request);
        if ($playerModel instanceof JsonResponse) {
            return $playerModel;
        }

        if ($response = $this->authorizePlayerAccess($request, $playerModel)) {
            return $response;
        }

        $playerModel = $this->lifecycleService->release($playerModel, $data['notes'] ?? null, $request->user());

        return ApiResponse::successResponse('Player released successfully.', [
            'player' => SportPlayerResource::make($playerModel)->resolve($request),
        ]);
    }

    public function archive(Request $request, string $player): JsonResponse
    {
        if (! $this->isSportAdmin($request->user())) {
            return ApiResponse::errorResponse('Only Sport Admin can archive players.', null, Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $playerModel = $this->loadPlayerOrFail($player, $request);
        if ($playerModel instanceof JsonResponse) {
            return $playerModel;
        }

        $playerModel = $this->lifecycleService->archive($playerModel, $data['notes'] ?? null, $request->user());

        return ApiResponse::successResponse('Player archived successfully.', [
            'player' => SportPlayerResource::make($playerModel)->resolve($request),
        ]);
    }

    private function loadPlayerOrFail(string $player, Request $request): SportPlayer|JsonResponse
    {
        $playerModel = SportPlayer::query()
            ->with(['team', 'createdBy', 'approvedBy', 'activeMembership.team', 'memberships.team', 'memberships.createdBy', 'memberships.updatedBy'])
            ->find($player);

        if (! $playerModel) {
            return ApiResponse::errorResponse('Player not found.', null, Response::HTTP_NOT_FOUND);
        }

        return $playerModel;
    }

    private function authorizePlayerAccess(Request $request, SportPlayer $player): ?JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::errorResponse('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminsport'], true)) {
            return null;
        }

        if ($user->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $team = $player->team;
        if (! $team) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        return $this->assignmentService->coachCanManageTeam($user, $team)
            ? null
            : ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
    }
}
