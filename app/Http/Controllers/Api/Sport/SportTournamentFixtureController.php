<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\GenerateTournamentFixturesRequest;
use App\Http\Resources\Sport\SportTournamentMatchResource;
use App\Models\SportTournament;
use App\Support\ApiResponse;
use App\Support\SportTournament\TournamentFixtureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportTournamentFixtureController extends SportController
{
    public function __construct(private readonly TournamentFixtureService $fixtureService) {}

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

        $matches = $this->fixtureService->list($tournament);

        return ApiResponse::successResponse('Tournament fixtures retrieved successfully.', [
            'tournamentId' => $tournament->id,
            'matches' => SportTournamentMatchResource::collection($matches)->resolve($request),
        ]);
    }

    public function generate(GenerateTournamentFixturesRequest $request, string $id): JsonResponse
    {
        $tournament = SportTournament::query()->find($id);

        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        $matches = $this->fixtureService->generate($tournament, $request->validated());

        return ApiResponse::successResponse('Tournament fixtures generated successfully.', [
            'tournamentId' => $tournament->id,
            'matches' => SportTournamentMatchResource::collection($matches)->resolve($request),
        ]);
    }
}
