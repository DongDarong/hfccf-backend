<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Resources\Sport\SportTournamentStatisticsResource;
use App\Models\SportTournament;
use App\Support\ApiResponse;
use App\Support\SportTournament\TournamentStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportTournamentStatisticsController extends SportController
{
    public function __construct(private readonly TournamentStatisticsService $statisticsService) {}

    public function index(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportCoach($request->user())) {
            return $response;
        }

        $tournament = SportTournament::query()->find($id);
        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($this->isSportCoach($request->user()) && ! $this->canAccessTournament($request->user(), $tournament)) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $statistics = $this->statisticsService->calculate($tournament);

        return ApiResponse::successResponse('Tournament statistics retrieved successfully.', [
            'tournamentId' => $tournament->id,
            'statistics' => SportTournamentStatisticsResource::make($statistics)->resolve($request),
        ]);
    }
}
