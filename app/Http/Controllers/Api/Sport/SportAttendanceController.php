<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Resources\Sport\SportAttendanceResource;
use App\Models\SportAttendanceRecord;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportAttendanceController extends SportController
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'attendance_type' => ['sometimes', 'nullable', 'string', 'in:player,coach'],
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_teams,id'],
            'player_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_players,id'],
            'coach_id' => ['sometimes', 'nullable', 'string', 'max:16'],
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
        $attendanceType = trim((string) ($validated['attendance_type'] ?? ''));
        $teamId = (int) ($validated['team_id'] ?? 0);
        $playerId = (int) ($validated['player_id'] ?? 0);
        $coachId = trim((string) ($validated['coach_id'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $attendanceDate = trim((string) ($validated['attendance_date'] ?? ''));
        $dateFrom = trim((string) ($validated['date_from'] ?? ''));
        $dateTo = trim((string) ($validated['date_to'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'attendance_date');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = SportAttendanceRecord::query()->with(['team', 'player.team', 'coach', 'recordedBy']);

        if ($attendanceType !== '') {
            $query->where('attendance_type', $attendanceType);
        }

        if ($teamId > 0) {
            $query->where('team_id', $teamId);
        }

        if ($playerId > 0) {
            $query->where('player_id', $playerId);
        }

        if ($coachId !== '') {
            $query->where('coach_user_id', $coachId);
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
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'attendance_type' => ['required', 'string', 'in:player,coach'],
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_teams,id'],
            'player_id' => ['required_if:attendance_type,player', 'nullable', 'integer', 'exists:sport_players,id'],
            'coach_id' => ['required_if:attendance_type,coach', 'nullable', 'string', 'max:16'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', 'string', 'in:present,absent,late,excused'],
            'note' => ['sometimes', 'nullable', 'string'],
        ]);

        $attendance = $this->upsertAttendance(null, $data, $request->user());

        return ApiResponse::successResponse(
            'Sport attendance saved successfully.',
            [
                'attendance' => SportAttendanceResource::make($attendance->load(['team', 'player.team', 'coach', 'recordedBy']))->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $attendance = SportAttendanceRecord::query()->find($id);
        if (! $attendance) {
            return ApiResponse::errorResponse('Attendance record not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validate([
            'attendance_type' => ['sometimes', 'required_with:attendance_date,status', 'string', 'in:player,coach'],
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_teams,id'],
            'player_id' => ['required_if:attendance_type,player', 'nullable', 'integer', 'exists:sport_players,id'],
            'coach_id' => ['required_if:attendance_type,coach', 'nullable', 'string', 'max:16'],
            'attendance_date' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', 'string', 'in:present,absent,late,excused'],
            'note' => ['sometimes', 'nullable', 'string'],
        ]);

        $attendance = $this->upsertAttendance($attendance, $data, $request->user());

        return ApiResponse::successResponse(
            'Sport attendance updated successfully.',
            [
                'attendance' => SportAttendanceResource::make($attendance->load(['team', 'player.team', 'coach', 'recordedBy']))->resolve($request),
            ],
        );
    }

    private function upsertAttendance(?SportAttendanceRecord $attendance, array $data, ?User $user): SportAttendanceRecord
    {
        $attendanceType = (string) ($data['attendance_type'] ?? $attendance?->attendance_type ?? 'player');
        $attendanceDate = (string) ($data['attendance_date'] ?? $attendance?->attendance_date?->toDateString() ?? '');
        $status = (string) ($data['status'] ?? $attendance?->status ?? 'present');
        $note = $data['note'] ?? $attendance?->note;
        $teamId = array_key_exists('team_id', $data)
            ? ($data['team_id'] !== null ? (int) $data['team_id'] : null)
            : $attendance?->team_id;
        $playerId = array_key_exists('player_id', $data)
            ? ($data['player_id'] !== null ? (int) $data['player_id'] : null)
            : $attendance?->player_id;
        $coachId = array_key_exists('coach_id', $data)
            ? ($data['coach_id'] !== null ? trim((string) $data['coach_id']) : null)
            : $attendance?->coach_user_id;

        if ($attendanceType === 'player') {
            $player = $playerId ? SportPlayer::query()->with('team')->find($playerId) : null;
            $teamId = $teamId ?: $player?->team_id;
            $playerId = $player?->id ?? $playerId;
            $coachId = null;
        } else {
            $coach = $coachId !== null && $coachId !== '' ? $this->resolveUserReference($coachId, 'coach') : null;
            $coachId = $coach?->id ?? ($coachId !== '' ? $coachId : null);
            $playerId = null;
            if (! $teamId && $coachId) {
                $teamId = SportTeam::query()
                    ->where('coach_user_id', $coachId)
                    ->orderBy('id')
                    ->value('id');
            }
        }

        if ($attendance) {
            $subjectKey = $this->resolveAttendanceSubjectKey($attendanceType, $playerId, $coachId);
            $attendance->fill([
                'attendance_type' => $attendanceType,
                'subject_key' => $subjectKey,
                'team_id' => $teamId,
                'player_id' => $playerId,
                'coach_user_id' => $coachId,
                'recorded_by_user_id' => $attendance->recorded_by_user_id ?: $user?->id,
                'attendance_date' => $attendanceDate,
                'status' => $status,
                'note' => $note,
            ]);
            $attendance->save();

            return $attendance;
        }

        $subjectKey = $this->resolveAttendanceSubjectKey($attendanceType, $playerId, $coachId);

        $query = SportAttendanceRecord::query()
            ->where('subject_key', $subjectKey)
            ->whereDate('attendance_date', $attendanceDate);

        if ($teamId !== null) {
            $query->where('team_id', $teamId);
        }

        $existing = $query->first();

        if ($existing) {
            $existing->fill([
                'attendance_type' => $attendanceType,
                'subject_key' => $subjectKey,
                'team_id' => $teamId,
                'player_id' => $playerId,
                'coach_user_id' => $coachId,
                'recorded_by_user_id' => $existing->recorded_by_user_id ?: $user?->id,
                'attendance_date' => $attendanceDate,
                'status' => $status,
                'note' => $note,
            ]);
            $existing->save();

            return $existing;
        }

        return SportAttendanceRecord::query()->create([
            'attendance_type' => $attendanceType,
            'subject_key' => $subjectKey,
            'team_id' => $teamId,
            'player_id' => $playerId,
            'coach_user_id' => $coachId,
            'recorded_by_user_id' => $user?->id,
            'attendance_date' => $attendanceDate,
            'status' => $status,
            'note' => $note,
        ]);
    }

    private function resolveAttendanceSubjectKey(string $attendanceType, ?int $playerId, ?string $coachId): string
    {
        if ($attendanceType === 'coach') {
            return 'coach:'.trim((string) $coachId);
        }

        return 'player:'.(string) ($playerId ?? '');
    }
}
