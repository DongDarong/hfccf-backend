<?php

namespace App\Support;

use App\Models\SportEquipmentItem;
use App\Models\SportEquipmentRequest;
use App\Models\SportTeam;
use App\Models\User;
use App\Services\SportActivityRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SportEquipmentRequestService
{
    public function __construct(
        private readonly SportCoachAssignmentService $assignmentService,
        private readonly SportEquipmentService $equipmentService,
        private readonly SportActivityRecorder $activityRecorder,
    ) {}

    public function adminRequests(array $filters = []): LengthAwarePaginator
    {
        return $this->buildRequestQuery($filters, false)->paginate((int) ($filters['per_page'] ?? 10), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function coachRequests(User $coach, array $filters = []): LengthAwarePaginator
    {
        return $this->buildRequestQuery($filters, true, $coach->id)->paginate((int) ($filters['per_page'] ?? 10), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function adminSummary(): array
    {
        $base = SportEquipmentRequest::query();

        return [
            'totalRequests' => (clone $base)->count(),
            'pendingRequests' => (clone $base)->where('status', SportEquipmentRequestStatus::PENDING)->count(),
            'approvedRequests' => (clone $base)->where('status', SportEquipmentRequestStatus::APPROVED)->count(),
            'issuedRequests' => (clone $base)->where('status', SportEquipmentRequestStatus::ISSUED)->count(),
            'returnedRequests' => (clone $base)->where('status', SportEquipmentRequestStatus::RETURNED)->count(),
            'rejectedRequests' => (clone $base)->where('status', SportEquipmentRequestStatus::REJECTED)->count(),
        ];
    }

    public function coachSummary(User $coach): array
    {
        $base = SportEquipmentRequest::query()->where('coach_user_id', $coach->id);

        return [
            'totalRequests' => (clone $base)->count(),
            'pendingRequests' => (clone $base)->where('status', SportEquipmentRequestStatus::PENDING)->count(),
            'approvedRequests' => (clone $base)->where('status', SportEquipmentRequestStatus::APPROVED)->count(),
            'issuedRequests' => (clone $base)->where('status', SportEquipmentRequestStatus::ISSUED)->count(),
            'returnedRequests' => (clone $base)->where('status', SportEquipmentRequestStatus::RETURNED)->count(),
            'rejectedRequests' => (clone $base)->where('status', SportEquipmentRequestStatus::REJECTED)->count(),
        ];
    }

    public function createCoachRequest(User $coach, SportTeam $team, SportEquipmentItem $item, array $data): SportEquipmentRequest
    {
        if (! $this->assignmentService->coachCanManageTeam($coach, $team)) {
            throw new \RuntimeException('Coach cannot manage the selected team.');
        }

        if ($item->status !== SportEquipmentItemStatus::ACTIVE) {
            throw new \RuntimeException('Equipment item is inactive.');
        }

        $request = DB::transaction(function () use ($coach, $team, $item, $data): SportEquipmentRequest {
            $request = SportEquipmentRequest::query()->create([
                'request_code' => $data['request_code'] ?? $this->makeCode('equip-req'),
                'equipment_item_id' => $item->id,
                'coach_user_id' => $coach->id,
                'team_id' => $team->id,
                'requested_quantity' => (int) $data['requested_quantity'],
                'approved_quantity' => null,
                'issued_quantity' => 0,
                'returned_quantity' => 0,
                'damaged_quantity' => 0,
                'missing_quantity' => 0,
                'purpose' => $data['purpose'],
                'required_date' => $data['required_date'],
                'expected_return_date' => $data['expected_return_date'],
                'status' => SportEquipmentRequestStatus::PENDING,
                'admin_note' => null,
                'rejected_reason' => null,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'issued_by_user_id' => null,
                'issued_at' => null,
                'returned_by_user_id' => null,
                'returned_at' => null,
            ]);

            return $request->refresh()->loadMissing(['item', 'coach', 'team']);
        });

        $this->activityRecorder->equipmentRequestCreated($request, $coach);

        return $request;
    }

    public function approveRequest(SportEquipmentRequest $request, User $actor, int $approvedQuantity, ?string $adminNote = null): SportEquipmentRequest
    {
        $request = DB::transaction(function () use ($request, $actor, $approvedQuantity, $adminNote): SportEquipmentRequest {
            $lockedRequest = SportEquipmentRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($lockedRequest->status !== SportEquipmentRequestStatus::PENDING) {
                throw new \RuntimeException('Equipment request is not pending.');
            }

            if ($approvedQuantity <= 0) {
                throw new \RuntimeException('Approved quantity must be greater than zero.');
            }

            if ($approvedQuantity > (int) $lockedRequest->requested_quantity) {
                throw new \RuntimeException('Approved quantity cannot exceed requested quantity.');
            }

            $lockedRequest->forceFill([
                'approved_quantity' => $approvedQuantity,
                'status' => SportEquipmentRequestStatus::APPROVED,
                'admin_note' => $adminNote,
                'rejected_reason' => null,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => Carbon::now(),
            ])->save();

            return $lockedRequest->refresh()->loadMissing(['item', 'coach', 'team', 'reviewedBy', 'issuedBy', 'returnedBy']);
        });

        $this->activityRecorder->equipmentRequestReviewed($request, $actor, SportEquipmentRequestStatus::APPROVED, null);

        return $request;
    }

    public function rejectRequest(SportEquipmentRequest $request, User $actor, string $reason, ?string $adminNote = null): SportEquipmentRequest
    {
        $request = DB::transaction(function () use ($request, $actor, $reason, $adminNote): SportEquipmentRequest {
            $lockedRequest = SportEquipmentRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($lockedRequest->status !== SportEquipmentRequestStatus::PENDING) {
                throw new \RuntimeException('Equipment request is not pending.');
            }

            $lockedRequest->forceFill([
                'status' => SportEquipmentRequestStatus::REJECTED,
                'admin_note' => $adminNote,
                'rejected_reason' => $reason,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => Carbon::now(),
            ])->save();

            return $lockedRequest->refresh()->loadMissing(['item', 'coach', 'team', 'reviewedBy', 'issuedBy', 'returnedBy']);
        });

        $this->activityRecorder->equipmentRequestReviewed($request, $actor, SportEquipmentRequestStatus::REJECTED, $reason);

        return $request;
    }

    public function issueRequest(SportEquipmentRequest $request, User $actor, int $issuedQuantity, ?string $adminNote = null): SportEquipmentRequest
    {
        $request = $this->equipmentService->applyIssue(
            $this->ensureRequestState($request, SportEquipmentRequestStatus::APPROVED),
            $actor,
            $issuedQuantity,
            $adminNote,
        );

        $this->activityRecorder->equipmentRequestIssued($request, $actor);

        return $request;
    }

    public function returnRequest(SportEquipmentRequest $request, User $actor, int $returnedQuantity, int $damagedQuantity, int $missingQuantity, ?string $adminNote = null): SportEquipmentRequest
    {
        $request = $this->equipmentService->applyReturn(
            $this->ensureRequestState($request, SportEquipmentRequestStatus::ISSUED),
            $actor,
            $returnedQuantity,
            $damagedQuantity,
            $missingQuantity,
            $adminNote,
        );

        $this->activityRecorder->equipmentRequestReturned($request, $actor);

        return $request;
    }

    public function findRequestOrFail(string|int $id): SportEquipmentRequest
    {
        return SportEquipmentRequest::query()
            ->with(['item', 'coach', 'team', 'reviewedBy', 'issuedBy', 'returnedBy'])
            ->findOrFail($id);
    }

    private function buildRequestQuery(array $filters = [], bool $coachOnly = false, ?string $coachUserId = null): Builder
    {
        $query = SportEquipmentRequest::query()->with(['item', 'coach', 'team', 'reviewedBy', 'issuedBy', 'returnedBy']);

        if ($coachOnly && $coachUserId) {
            $query->where('coach_user_id', $coachUserId);
        } elseif (! empty($filters['coach_user_id'])) {
            $query->where('coach_user_id', $filters['coach_user_id']);
        }

        if (! empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        if (! empty($filters['equipment_item_id'])) {
            $query->where('equipment_item_id', $filters['equipment_item_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('request_code', 'like', $like)
                    ->orWhere('purpose', 'like', $like)
                    ->orWhereHas('team', static function (Builder $teamQuery) use ($like): void {
                        $teamQuery->where('name', 'like', $like)
                            ->orWhere('team_code', 'like', $like);
                    })
                    ->orWhereHas('item', static function (Builder $itemQuery) use ($like): void {
                        $itemQuery->where('name', 'like', $like)
                            ->orWhere('equipment_code', 'like', $like)
                            ->orWhere('category', 'like', $like);
                    })
                    ->orWhereHas('coach', static function (Builder $coachQuery) use ($like): void {
                        $coachQuery->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);
                    });
            });
        }

        $sortBy = (string) ($filters['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($filters['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortColumn = match ($sortBy) {
            'request_code' => 'request_code',
            'required_date' => 'required_date',
            'expected_return_date' => 'expected_return_date',
            'requested_quantity' => 'requested_quantity',
            'status' => 'status',
            default => 'created_at',
        };

        return $query->orderBy($sortColumn, $sortDirection)->orderBy('id', 'desc');
    }

    private function ensureRequestState(SportEquipmentRequest $request, string $expectedStatus): SportEquipmentRequest
    {
        $request = SportEquipmentRequest::query()
            ->with(['item', 'coach', 'team', 'reviewedBy', 'issuedBy', 'returnedBy'])
            ->whereKey($request->id)
            ->firstOrFail();

        if ($request->status !== $expectedStatus) {
            throw new \RuntimeException('Equipment request is not in the required state.');
        }

        return $request;
    }

    private function makeCode(string $prefix): string
    {
        return strtoupper($prefix.'-'.Str::random(8));
    }
}
