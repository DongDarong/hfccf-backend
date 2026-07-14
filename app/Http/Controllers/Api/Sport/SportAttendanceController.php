<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Resources\Sport\SportAttendanceResource;
use App\Models\SportAttendanceRecord;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\SportCoachAssignmentService;
use App\Support\SportPlayerMembershipService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportAttendanceController extends SportController
{
    public function __construct(
        private readonly SportCoachAssignmentService $assignmentService,
        private readonly SportPlayerMembershipService $membershipService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAttendanceAccess($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_teams,id'],
            'player_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_players,id'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'attendance_date' => ['sometimes', 'nullable', 'date'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $teamId = (int) ($validated['team_id'] ?? 0);
        $playerId = (int) ($validated['player_id'] ?? 0);
        $status = trim((string) ($validated['status'] ?? ''));
        $attendanceDate = trim((string) ($validated['attendance_date'] ?? ''));
        $dateFrom = trim((string) ($validated['date_from'] ?? ''));
        $dateTo = trim((string) ($validated['date_to'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'attendance_date');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $user = $request->user();
        $coachTeamIds = $this->isSportCoach($user) ? $this->coachTeamIds($user) : [];

        $query = SportAttendanceRecord::query()
            ->where('attendance_type', 'player')
            ->with(['team', 'player.team', 'recordedBy']);

        if ($this->isSportCoach($user)) {
            if ($teamId > 0 && ! in_array($teamId, $coachTeamIds, true)) {
                return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
            }

            if ($coachTeamIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('team_id', $coachTeamIds);
            }
        }

        if ($teamId > 0) {
            $query->where('team_id', $teamId);
        }

        if ($playerId > 0) {
            $query->where('player_id', $playerId);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($attendanceDate !== '') {
            $query->whereDate('attendance_date', $attendanceDate);
        }

        if ($dateFrom !== '') {
            $query->whereDate('attendance_date', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('attendance_date', '<=', $dateTo);
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('status', 'like', $like)
                    ->orWhere('note', 'like', $like)
                    ->orWhereHas('team', static function (Builder $teamQuery) use ($like): void {
                        $teamQuery->where('name', 'like', $like)
                            ->orWhere('short_name', 'like', $like)
                            ->orWhere('team_code', 'like', $like);
                    })
                    ->orWhereHas('player', static function (Builder $playerQuery) use ($like): void {
                        $playerQuery->where('player_code', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like);
                    })
                    ->orWhereHas('coach', static function (Builder $coachQuery) use ($like): void {
                        $coachQuery->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('username', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
            });
        }

        $sortColumn = match ($sortBy) {
            'attendance_type' => 'attendance_type',
            'status' => 'status',
            'attendance_date' => 'attendance_date',
            'created_at' => 'created_at',
            default => 'attendance_date',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'Sport attendance retrieved successfully.',
            $paginator,
            $request,
            SportAttendanceResource::class,
        );
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAttendanceAccess($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_teams,id'],
            'player_id' => ['required', 'nullable', 'integer', 'exists:sport_players,id'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', 'string', 'in:present,absent,late,excused'],
            'note' => ['sometimes', 'nullable', 'string'],
        ]);

        $payload = $this->buildAttendancePayload($data, null, $request->user());

        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $attendance = $this->upsertAttendance(null, $payload, $request->user());

        return ApiResponse::successResponse(
            'Sport attendance saved successfully.',
            [
                'attendance' => SportAttendanceResource::make($attendance->load(['team', 'player.team', 'recordedBy']))->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAttendanceAccess($request->user())) {
            return $response;
        }

        $attendance = SportAttendanceRecord::query()->find($id);
        if (! $attendance) {
            return ApiResponse::errorResponse('Attendance record not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validate([
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_teams,id'],
            'player_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_players,id'],
            'attendance_date' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', 'string', 'in:present,absent,late,excused'],
            'note' => ['sometimes', 'nullable', 'string'],
        ]);

        $payload = $this->buildAttendancePayload($data, $attendance, $request->user());

        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $attendance = $this->upsertAttendance($attendance, $payload, $request->user());

        return ApiResponse::successResponse(
            'Sport attendance updated successfully.',
            [
                'attendance' => SportAttendanceResource::make($attendance->load(['team', 'player.team', 'recordedBy']))->resolve($request),
            ],
        );
    }

    private function upsertAttendance(?SportAttendanceRecord $attendance, array $data, ?User $user): SportAttendanceRecord
    {
        $attendanceDate = (string) ($data['attendance_date'] ?? $attendance?->attendance_date?->toDateString() ?? '');
        $status = (string) ($data['status'] ?? $attendance?->status ?? 'present');
        $note = $data['note'] ?? $attendance?->note;
        $teamId = (int) ($data['team_id'] ?? $attendance?->team_id ?? 0);
        $playerId = (int) ($data['player_id'] ?? $attendance?->player_id ?? 0);

        $subjectKey = 'player:'.(string) $playerId;

        if ($attendance) {
            $attendance->fill([
                'attendance_type' => 'player',
                'subject_key' => $subjectKey,
                'team_id' => $teamId,
                'player_id' => $playerId,
                'coach_user_id' => null,
                'recorded_by_user_id' => $attendance->recorded_by_user_id ?: $user?->id,
                'attendance_date' => $attendanceDate,
                'status' => $status,
                'note' => $note,
            ]);
            $attendance->save();

            return $attendance;
        }

        $query = SportAttendanceRecord::query()
            ->where('subject_key', $subjectKey)
            ->whereDate('attendance_date', $attendanceDate);

        if ($teamId !== null) {
            $query->where('team_id', $teamId);
        }

        $existing = $query->first();

        if ($existing) {
            $existing->fill([
                'attendance_type' => 'player',
                'subject_key' => $subjectKey,
                'team_id' => $teamId,
                'player_id' => $playerId,
                'coach_user_id' => null,
                'recorded_by_user_id' => $existing->recorded_by_user_id ?: $user?->id,
                'attendance_date' => $attendanceDate,
                'status' => $status,
                'note' => $note,
            ]);
            $existing->save();

            return $existing;
        }

        return SportAttendanceRecord::query()->create([
            'attendance_type' => 'player',
            'subject_key' => $subjectKey,
            'team_id' => $teamId,
            'player_id' => $playerId,
            'coach_user_id' => null,
            'recorded_by_user_id' => $user?->id,
            'attendance_date' => $attendanceDate,
            'status' => $status,
            'note' => $note,
        ]);
    }

    private function authorizeAttendanceAccess(?User $user): ?JsonResponse
    {
        if (! $user) {
            return ApiResponse::errorResponse('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminsport', 'coach'], true)) {
            return null;
        }

        return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|JsonResponse
     */
    private function buildAttendancePayload(array $data, ?SportAttendanceRecord $attendance, ?User $user): array|JsonResponse
    {
        $playerId = (int) ($data['player_id'] ?? $attendance?->player_id ?? 0);
        if ($playerId <= 0) {
            return ApiResponse::errorResponse('Player not found.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $player = SportPlayer::query()->with(['team', 'activeMembership', 'memberships.team'])->find($playerId);
        if (! $player) {
            return ApiResponse::errorResponse('Player not found.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $membership = $this->membershipService->currentActiveMembership($player);
        $teamId = (int) ($data['team_id'] ?? $attendance?->team_id ?? $membership?->team_id ?? $player->team_id ?? 0);
        if ($teamId <= 0) {
            return ApiResponse::errorResponse('Team not found.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $team = SportTeam::query()->find($teamId);
        if (! $team) {
            return ApiResponse::errorResponse('Team not found.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->isSportCoach($user) && ! $this->coachCanAccessTeam($user, $team)) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        if ($attendance) {
            if ((int) $attendance->team_id !== (int) $team->id) {
                return ApiResponse::errorResponse('Attendance record cannot be moved to another team.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ((int) $attendance->player_id !== (int) $player->id) {
                return ApiResponse::errorResponse('Attendance record cannot be moved to another player.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        } elseif (! $membership || (int) $membership->team_id !== (int) $team->id) {
            return ApiResponse::errorResponse('Player does not belong to the selected team.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return [
            'attendance_date' => (string) ($data['attendance_date'] ?? $attendance?->attendance_date?->toDateString() ?? ''),
            'status' => (string) ($data['status'] ?? $attendance?->status ?? 'present'),
            'note' => $data['note'] ?? $attendance?->note,
            'team_id' => $team->id,
            'player_id' => $player->id,
        ];
    }

    private function coachCanAccessTeam(?User $coach, SportTeam $team): bool
    {
        if (! $coach) {
            return false;
        }

        if (in_array($coach->role_code, ['superadmin', 'adminsport'], true)) {
            return true;
        }

        if ($coach->role_code !== 'coach') {
            return false;
        }

        return in_array((int) $team->id, $this->coachTeamIds($coach), true);
    }

    /**
     * @return array<int>
     */
    private function coachTeamIds(User $coach): array
    {
        return $this->assignmentService->assignedTeamsForCoach($coach)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
