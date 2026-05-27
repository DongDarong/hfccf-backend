<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\StoreSportEventRequest;
use App\Http\Requests\Sport\UpdateSportEventRequest;
use App\Http\Resources\Sport\SportMatchEventResource;
use App\Models\SportMatch;
use App\Models\SportMatchEvent;
use App\Support\ApiResponse;
use App\Support\SportMatchEventService;
use App\Support\SportMatchEventValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportMatchEventController extends SportController
{
    public function __construct(
        private readonly SportMatchEventService $eventService,
        private readonly SportMatchEventValidationService $validationService,
    ) {}

    public function index(Request $request, string $id): JsonResponse
    {
        $match = SportMatch::query()->with(['homeTeam', 'awayTeam', 'squads.players.player'])->find($id);

        if (! $match) {
            return ApiResponse::errorResponse('Match not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($response = $this->validationService->assertActorCanAccessMatch($request->user(), $match)) {
            return $response;
        }

        $events = $this->eventService->timelineForMatch($match);

        return ApiResponse::successResponse('Match events retrieved successfully.', [
            'items' => SportMatchEventResource::collection($events)->resolve($request),
            'pagination' => [
                'page' => 1,
                'perPage' => $events->count() ?: 10,
                'total' => $events->count(),
                'totalPages' => 1,
            ],
            'match' => [
                'id' => $match->id,
                'homeTeamId' => $match->home_team_id,
                'awayTeamId' => $match->away_team_id,
            ],
        ]);
    }

    public function store(StoreSportEventRequest $request, string $id): JsonResponse
    {
        $match = SportMatch::query()->with(['homeTeam', 'awayTeam', 'squads.players.player'])->find($id);

        if (! $match) {
            return ApiResponse::errorResponse('Match not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($response = $this->validationService->assertActorCanAccessMatch($request->user(), $match)) {
            return $response;
        }

        try {
            $event = $this->eventService->createEvent($match, $request->validated(), $request->user());

            return ApiResponse::successResponse(
                'Match event created successfully.',
                [
                    'event' => SportMatchEventResource::make($event)->resolve($request),
                ],
                Response::HTTP_CREATED,
            );
        } catch (\Throwable $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function update(UpdateSportEventRequest $request, string $id): JsonResponse
    {
        $event = SportMatchEvent::query()
            ->with([
                'match.homeTeam',
                'match.awayTeam',
                'match.squads.players.player',
                'team',
                'player',
                'assistPlayer',
                'playerIn',
                'playerOut',
                'squad',
                'squadPlayer.player',
                'relatedSquadPlayer.player',
            ])
            ->find($id);

        if (! $event) {
            return ApiResponse::errorResponse('Event not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($response = $this->validationService->assertActorCanAccessMatch($request->user(), $event->match)) {
            return $response;
        }

        try {
            $updated = $this->eventService->updateEvent($event, $request->validated(), $request->user());

            return ApiResponse::successResponse('Match event updated successfully.', [
                'event' => SportMatchEventResource::make($updated)->resolve($request),
            ]);
        } catch (\Throwable $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $event = SportMatchEvent::query()->with(['match.homeTeam', 'match.awayTeam'])->find($id);

        if (! $event) {
            return ApiResponse::errorResponse('Event not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($response = $this->validationService->assertActorCanAccessMatch($request->user(), $event->match)) {
            return $response;
        }

        try {
            $this->eventService->deleteEvent($event, $request->user());

            return ApiResponse::successResponse('Match event deleted successfully.');
        } catch (\Throwable $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
