<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\StoreSportMatchSquadRequest;
use App\Http\Resources\Sport\SportMatchEligibilityResource;
use App\Http\Resources\Sport\SportMatchSquadResource;
use App\Models\SportMatch;
use App\Models\SportMatchSquad;
use App\Models\SportTeam;
use App\Support\ApiResponse;
use App\Support\SportMatchSquadService;
use App\Support\SportMatchSquadValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportMatchSquadController extends SportController
{
    public function __construct(
        private readonly SportMatchSquadService $squadService,
        private readonly SportMatchSquadValidationService $validationService,
    ) {}

    public function eligibility(Request $request, SportMatch $match, SportTeam $team): JsonResponse
    {
        if ($response = $this->authorizeMatchTeamAccess($request, $match, $team)) {
            return $response;
        }

        return ApiResponse::successResponse('Match eligibility retrieved successfully.', [
            'match' => [
                'id' => $match->id,
                'matchCode' => $match->match_code,
                'status' => $match->status,
            ],
            'team' => [
                'id' => $team->id,
                'teamCode' => $team->team_code,
                'name' => $team->name,
                'shortName' => $team->short_name,
            ],
            'players' => SportMatchEligibilityResource::collection(
                $this->squadService->eligibilityForMatchTeam($match, $team, $request->user())
            )->resolve($request),
        ]);
    }

    public function index(Request $request, SportMatch $match): JsonResponse
    {
        if ($response = $this->authorizeMatchAccess($request, $match)) {
            return $response;
        }

        return ApiResponse::successResponse('Match squads retrieved successfully.', [
            'squads' => SportMatchSquadResource::collection(
                $this->squadService->squadsForMatch($match, $request->user())
            )->resolve($request),
        ]);
    }

    public function show(Request $request, SportMatch $match, SportTeam $team): JsonResponse
    {
        if ($response = $this->authorizeMatchTeamAccess($request, $match, $team)) {
            return $response;
        }

        $squad = $this->squadService->squadForMatchTeam($match, $team, $request->user())
            ?? $this->squadService->buildPreviewSquad($match, $team, $request->user());

        return ApiResponse::successResponse('Match squad retrieved successfully.', [
            'squad' => SportMatchSquadResource::make($squad)->resolve($request),
            'players' => SportMatchEligibilityResource::collection(
                $this->squadService->eligibilityForMatchTeam($match, $team, $request->user())
            )->resolve($request),
        ]);
    }

    public function store(StoreSportMatchSquadRequest $request, SportMatch $match, SportTeam $team): JsonResponse
    {
        if ($response = $this->authorizeMatchTeamAccess($request, $match, $team)) {
            return $response;
        }

        try {
            $squad = $this->squadService->saveSquad($match, $team, $request->validated(), $request->user());
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse('Match squad saved successfully.', [
            'squad' => SportMatchSquadResource::make($squad)->resolve($request),
        ], Response::HTTP_CREATED);
    }

    public function update(StoreSportMatchSquadRequest $request, SportMatchSquad $squad): JsonResponse
    {
        $squad->loadMissing(['match', 'team', 'selectedBy', 'approvedBy', 'players.player']);

        if ($response = $this->authorizeMatchTeamAccess($request, $squad->match, $squad->team)) {
            return $response;
        }

        try {
            $updated = $this->squadService->updateSquad($squad, $request->validated(), $request->user());
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse('Match squad updated successfully.', [
            'squad' => SportMatchSquadResource::make($updated)->resolve($request),
        ]);
    }

    public function submit(Request $request, SportMatchSquad $squad): JsonResponse
    {
        $squad->loadMissing(['match', 'team', 'selectedBy', 'approvedBy', 'players.player']);

        if ($response = $this->authorizeMatchTeamAccess($request, $squad->match, $squad->team)) {
            return $response;
        }

        try {
            $updated = $this->squadService->submitSquad($squad, $request->user());
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse('Match squad submitted successfully.', [
            'squad' => SportMatchSquadResource::make($updated)->resolve($request),
        ]);
    }

    public function approve(Request $request, SportMatchSquad $squad): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $squad->loadMissing(['match', 'team', 'selectedBy', 'approvedBy', 'players.player']);

        try {
            $updated = $this->squadService->approveSquad($squad, $request->user());
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse('Match squad approved successfully.', [
            'squad' => SportMatchSquadResource::make($updated)->resolve($request),
        ]);
    }

    public function lock(Request $request, SportMatchSquad $squad): JsonResponse
    {
        $squad->loadMissing(['match', 'team', 'selectedBy', 'approvedBy', 'players.player']);

        if ($response = $this->authorizeMatchTeamAccess($request, $squad->match, $squad->team)) {
            return $response;
        }

        try {
            $updated = $this->squadService->lockSquad($squad, $request->user());
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse('Match squad locked successfully.', [
            'squad' => SportMatchSquadResource::make($updated)->resolve($request),
        ]);
    }

    private function authorizeMatchAccess(Request $request, SportMatch $match): ?JsonResponse
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

        $homeAllowed = $match->homeTeam && $this->validationService->coachCanManageTeam($user, $match->homeTeam);
        $awayAllowed = $match->awayTeam && $this->validationService->coachCanManageTeam($user, $match->awayTeam);

        return ($homeAllowed || $awayAllowed)
            ? null
            : ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
    }

    private function authorizeMatchTeamAccess(Request $request, SportMatch $match, SportTeam $team): ?JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::errorResponse('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        return $this->validationService->assertActorCanManageTeam($user, $match, $team);
    }
}
