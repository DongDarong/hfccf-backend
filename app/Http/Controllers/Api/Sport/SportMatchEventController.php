<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\StoreSportEventRequest;
use App\Http\Requests\Sport\UpdateSportEventRequest;
use App\Http\Resources\Sport\SportMatchEventResource;
use App\Models\SportMatch;
use App\Models\SportMatchEvent;
use App\Models\SportPlayer;
use App\Support\ApiResponse;
use App\Support\SportMatchScoreService;
use App\Support\SportStandingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportMatchEventController extends SportController
{
    public function __construct(
        private readonly SportMatchScoreService $scoreService,
        private readonly SportStandingsService $standingsService,
    ) {}

    public function index(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $match = SportMatch::query()->with(['events.team', 'events.player'])->find($id);

        if (! $match) {
            return ApiResponse::errorResponse('Match not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse('Match events retrieved successfully.', [
            'items' => SportMatchEventResource::collection($match->events)->resolve($request),
            'pagination' => [
                'page' => 1,
                'perPage' => $match->events->count() ?: 10,
                'total' => $match->events->count(),
                'totalPages' => 1,
            ],
        ]);
    }

    public function store(StoreSportEventRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $match = SportMatch::query()->with(['homeTeam', 'awayTeam', 'events'])->find($id);

        if (! $match) {
            return ApiResponse::errorResponse('Match not found.', null, Response::HTTP_NOT_FOUND);
        }

        if (! in_array($match->status, ['live', 'halftime', 'completed'], true)) {
            return ApiResponse::errorResponse('Events can only be edited while the match is live, halftime, or completed.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validated();
        $teamId = (int) $data['team_id'];

        if (! in_array($teamId, [$match->home_team_id, $match->away_team_id], true)) {
            return ApiResponse::errorResponse('Event team must belong to this match.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $playerId = $data['player_id'] ?? null;
        $player = $playerId ? $this->resolvePlayerReference((string) $playerId) : null;

        if (! $player && ! empty($data['player_name'])) {
            $player = $this->resolvePlayerReference($data['player_name']);
        }

        if ($player && (int) $player->team_id !== $teamId) {
            return ApiResponse::errorResponse('Player must belong to the selected team.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $event = SportMatchEvent::query()->create([
            'match_id' => $match->id,
            'team_id' => $teamId,
            'player_id' => $player?->id,
            'event_type' => $data['event_type'],
            'minute' => (int) $data['minute'],
            'extra_time_minute' => $data['extra_time_minute'] ?? null,
            'metadata' => $this->mergeEventMetadata($data, $player),
            'created_by_user_id' => $request->user()?->id,
        ]);

        $this->scoreService->recalculate($match);
        $this->refreshStandingsForMatch($match);
        $event->loadMissing(['team', 'player']);

        return ApiResponse::successResponse(
            'Match event created successfully.',
            [
                'event' => SportMatchEventResource::make($event)->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateSportEventRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $event = SportMatchEvent::query()->with(['match.homeTeam', 'match.awayTeam'])->find($id);

        if (! $event) {
            return ApiResponse::errorResponse('Event not found.', null, Response::HTTP_NOT_FOUND);
        }

        $match = $event->match;

        if (! $match || ! in_array($match->status, ['live', 'halftime', 'completed'], true)) {
            return ApiResponse::errorResponse('Events can only be edited while the match is live, halftime, or completed.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validated();
        $playerProvided = array_key_exists('player_id', $data) || array_key_exists('player_name', $data);
        $player = $event->player;

        if (array_key_exists('team_id', $data)) {
            $teamId = (int) $data['team_id'];
            if (! in_array($teamId, [$match->home_team_id, $match->away_team_id], true)) {
                return ApiResponse::errorResponse('Event team must belong to this match.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $event->team_id = $teamId;
        }

        if (array_key_exists('player_id', $data) && $data['player_id']) {
            $player = $this->resolvePlayerReference((string) $data['player_id']);
        } elseif (! empty($data['player_name'])) {
            $player = $this->resolvePlayerReference($data['player_name']);
        } elseif ($playerProvided && empty($data['player_id']) && empty($data['player_name'])) {
            $player = null;
        }

        if ($player && (int) $player->team_id !== (int) $event->team_id) {
            return ApiResponse::errorResponse('Player must belong to the selected team.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $playerProvided && $event->player_id && (int) $event->player?->team_id !== (int) $event->team_id) {
            $player = null;
        }

        foreach (['event_type', 'minute', 'extra_time_minute'] as $field) {
            if (array_key_exists($field, $data)) {
                $event->{$field} = $data[$field];
            }
        }

        $event->player_id = $player?->id;
        $event->metadata = $this->mergeEventMetadata($data, $player, $event->metadata ?? []);
        $event->save();

        $this->scoreService->recalculate($match);
        $this->refreshStandingsForMatch($match);
        $event->loadMissing(['team', 'player']);

        return ApiResponse::successResponse(
            'Match event updated successfully.',
            [
                'event' => SportMatchEventResource::make($event)->resolve($request),
            ],
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $event = SportMatchEvent::query()->with(['match.homeTeam', 'match.awayTeam'])->find($id);

        if (! $event) {
            return ApiResponse::errorResponse('Event not found.', null, Response::HTTP_NOT_FOUND);
        }

        $match = $event->match;

        if (! $match || ! in_array($match->status, ['live', 'halftime', 'completed'], true)) {
            return ApiResponse::errorResponse('Events can only be edited while the match is live, halftime, or completed.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $event->delete();

        if ($match) {
            $this->scoreService->recalculate($match);
            $this->refreshStandingsForMatch($match);
        }

        return ApiResponse::successResponse('Match event deleted successfully.');
    }

    private function mergeEventMetadata(array $data, ?SportPlayer $player = null, array $fallback = []): array
    {
        $metadata = array_merge($fallback, $data['metadata'] ?? []);

        if (! empty($data['player_name'])) {
            $metadata['player_name'] = $data['player_name'];
        }

        if ($player) {
            $metadata['player_code'] = $player->player_code;
        }

        return $metadata;
    }

    private function refreshStandingsForMatch(?SportMatch $match): void
    {
        if (! $match || ! $match->tournament_id) {
            return;
        }

        $this->standingsService->rebuildTournamentById((int) $match->tournament_id);
    }
}
