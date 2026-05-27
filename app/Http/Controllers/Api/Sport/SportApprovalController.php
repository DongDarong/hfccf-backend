<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Resources\Sport\SportMatchResource;
use App\Http\Resources\Sport\SportPlayerResource;
use App\Models\SportMatch;
use App\Models\SportPlayer;
use App\Support\ApiResponse;
use App\Support\SportApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportApprovalController extends SportController
{
    public function __construct(
        private readonly SportApprovalService $approvalService,
    ) {}

    public function pendingPlayers(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->approvalService->pendingPlayersQuery()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 10), ['*'], 'page', (int) ($validated['page'] ?? 1));

        return ApiResponse::paginatedResponse(
            'Pending players retrieved successfully.',
            $paginator,
            $request,
            SportPlayerResource::class,
        );
    }

    public function approvePlayer(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $player = SportPlayer::query()->with(['team', 'createdBy', 'approvedBy', 'activeMembership', 'memberships'])->find($id);

        if (! $player) {
            return ApiResponse::errorResponse('Player not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($player->approval_status !== 'pending') {
            return ApiResponse::errorResponse('Player is not pending approval.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $player = $this->approvalService->approvePlayer($player, $request->user());
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse(
            'Player approved successfully.',
            [
                'player' => SportPlayerResource::make($player)->resolve($request),
            ],
        );
    }

    public function rejectPlayer(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $player = SportPlayer::query()->with(['team', 'createdBy', 'approvedBy', 'activeMembership', 'memberships'])->find($id);

        if (! $player) {
            return ApiResponse::errorResponse('Player not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($player->approval_status !== 'pending') {
            return ApiResponse::errorResponse('Player is not pending approval.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $player = $this->approvalService->rejectPlayer($player, $request->user(), $data['rejection_reason']);

        return ApiResponse::successResponse(
            'Player rejected successfully.',
            [
                'player' => SportPlayerResource::make($player)->resolve($request),
            ],
        );
    }

    public function pendingMatches(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->approvalService->pendingMatchesQuery()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 10), ['*'], 'page', (int) ($validated['page'] ?? 1));

        return ApiResponse::paginatedResponse(
            'Pending matches retrieved successfully.',
            $paginator,
            $request,
            SportMatchResource::class,
        );
    }

    public function approveMatch(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $match = SportMatch::query()->with(['homeTeam', 'awayTeam', 'creator', 'approvedBy'])->find($id);

        if (! $match) {
            return ApiResponse::errorResponse('Match not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($match->approval_status !== 'pending') {
            return ApiResponse::errorResponse('Match is not pending approval.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $match = $this->approvalService->approveMatch($match, $request->user());

        return ApiResponse::successResponse(
            'Match approved successfully.',
            [
                'match' => SportMatchResource::make($match)->resolve($request),
            ],
        );
    }

    public function rejectMatch(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $match = SportMatch::query()->with(['homeTeam', 'awayTeam', 'creator', 'approvedBy'])->find($id);

        if (! $match) {
            return ApiResponse::errorResponse('Match not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($match->approval_status !== 'pending') {
            return ApiResponse::errorResponse('Match is not pending approval.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $match = $this->approvalService->rejectMatch($match, $request->user(), $data['rejection_reason']);

        return ApiResponse::successResponse(
            'Match rejected successfully.',
            [
                'match' => SportMatchResource::make($match)->resolve($request),
            ],
        );
    }
}
