<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\GenerateTournamentKnockoutRequest;
use App\Http\Resources\Sport\SportTournamentGroupResource;
use App\Http\Resources\Sport\SportTournamentKnockoutResource;
use App\Http\Resources\Sport\SportTournamentMatchResource;
use App\Models\SportTournament;
use App\Support\ApiResponse;
use App\Support\SportTournament\TournamentKnockoutService;
use App\Support\SportTournament\TournamentQualificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportTournamentKnockoutController extends SportController
{
    public function __construct(
        private readonly TournamentKnockoutService $knockoutService,
        private readonly TournamentQualificationService $qualificationService,
    ) {
    }

    public function index(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportCoach($request->user())) {
            return $response;
        }

        $tournament = SportTournament::query()->with(['knockoutRounds.matches.homeTeam', 'knockoutRounds.matches.awayTeam', 'knockoutRounds.matches.winnerTeam'])->find($id);
        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($this->isSportCoach($request->user()) && ! $this->canAccessTournament($request->user(), $tournament)) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $qualification = $this->qualificationService->calculate($tournament);

        return ApiResponse::successResponse('Tournament knockout retrieved successfully.', [
            'tournamentId' => $tournament->id,
            'qualifiers' => $qualification['qualifiers'],
            'rounds' => $tournament->knockoutRounds
                ->sortBy('position')
                ->map(fn ($round) => [
                    'id' => $round->id,
                    'tournamentId' => $round->tournament_id,
                    'name' => $round->name,
                    'code' => $round->code,
                    'position' => (int) $round->position,
                    'bracketSize' => (int) $round->bracket_size,
                    'status' => $round->status,
                    'completedAt' => $round->completed_at?->toISOString(),
                    'metadata' => $round->metadata,
                    'matches' => SportTournamentMatchResource::collection($round->matches)->resolve($request),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function generate(GenerateTournamentKnockoutRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $tournament = SportTournament::query()->find($id);
        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        try {
            $result = $this->knockoutService->generate($tournament, $request->validated());
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse('Tournament knockout generated successfully.', [
            'tournamentId' => $tournament->id,
            'rounds' => $result['rounds']->map(fn ($round) => [
                'id' => $round->id,
                'tournamentId' => $round->tournament_id,
                'name' => $round->name,
                'code' => $round->code,
                'position' => (int) $round->position,
                'bracketSize' => (int) $round->bracket_size,
                'status' => $round->status,
                'completedAt' => $round->completed_at?->toISOString(),
                'metadata' => $round->metadata,
            ])->values()->all(),
            'matches' => SportTournamentMatchResource::collection($result['matches'])->resolve($request),
            'qualifiers' => $this->qualificationService->calculate($result['tournament'])['qualifiers'],
        ]);
    }
}
