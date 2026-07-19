<?php

namespace App\Support;

use App\Models\SportEquipmentAssignment;
use App\Models\SportEquipmentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SportEquipmentAssignmentService
{
    public function createFromIssuedRequest(SportEquipmentRequest $request, User $actor, int $assignedQuantity, ?string $notes = null): SportEquipmentAssignment
    {
        if ($assignedQuantity <= 0) {
            throw new \RuntimeException('Assigned quantity must be greater than zero.');
        }

        if (SportEquipmentAssignment::query()->where('equipment_request_id', $request->id)->exists()) {
            throw new \RuntimeException('An assignment already exists for this equipment request.');
        }

        return SportEquipmentAssignment::query()->create([
            'assignment_code' => $this->makeCode(),
            'equipment_request_id' => $request->id,
            'equipment_item_id' => $request->equipment_item_id,
            'team_id' => $request->team_id,
            'coach_user_id' => $request->coach_user_id,
            'assigned_quantity' => $assignedQuantity,
            'returned_quantity' => 0,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'status' => 'assigned',
            'assigned_at' => Carbon::now(),
            'expected_return_at' => $request->expected_return_date ? Carbon::parse($request->expected_return_date)->endOfDay() : null,
            'assigned_by_user_id' => $actor->id,
            'notes' => $notes,
        ]);
    }

    public function completeFromReturnedRequest(SportEquipmentRequest $request, User $actor, int $returnedQuantity, int $damagedQuantity, int $missingQuantity, ?string $notes = null): SportEquipmentAssignment
    {
        $assignment = SportEquipmentAssignment::query()->where('equipment_request_id', $request->id)->lockForUpdate()->first();

        if (! $assignment) {
            throw new ModelNotFoundException('Equipment assignment not found.');
        }

        if ($returnedQuantity < 0 || $damagedQuantity < 0 || $missingQuantity < 0) {
            throw new \RuntimeException('Return quantities cannot be negative.');
        }

        if ($returnedQuantity + $damagedQuantity + $missingQuantity !== (int) $assignment->assigned_quantity) {
            throw new \RuntimeException('Returned, damaged, and missing quantities must equal the assigned quantity.');
        }

        $assignment->forceFill([
            'returned_quantity' => $returnedQuantity,
            'damaged_quantity' => $damagedQuantity,
            'missing_quantity' => $missingQuantity,
            'status' => 'returned',
            'returned_at' => Carbon::now(),
            'returned_by_user_id' => $actor->id,
            'notes' => $notes,
        ])->save();

        return $assignment->refresh();
    }

    public function listAdminAssignments(array $filters = []): LengthAwarePaginator
    {
        return $this->buildQuery($filters)->paginate((int) ($filters['per_page'] ?? 10), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function listCoachAssignments(User $coach, array $filters = []): LengthAwarePaginator
    {
        $query = $this->buildQuery($filters)->whereHas('team', static fn (Builder $teamQuery) => $teamQuery->where('coach_user_id', $coach->id));

        return $query->paginate((int) ($filters['per_page'] ?? 10), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function findAssignmentOrFail(string|int $id): SportEquipmentAssignment
    {
        return $this->baseQuery()->whereKey($id)->firstOrFail();
    }

    public function coachCanView(User $coach, SportEquipmentAssignment $assignment): bool
    {
        return $assignment->team?->coach_user_id === $coach->id;
    }

    private function baseQuery(): Builder
    {
        return SportEquipmentAssignment::query()->with(['request', 'item', 'team', 'coach', 'assignedBy', 'returnedBy']);
    }

    private function buildQuery(array $filters = []): Builder
    {
        $query = $this->baseQuery();

        foreach (['equipment_item_id', 'team_id', 'coach_user_id', 'status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('assignment_code', 'like', $like)
                    ->orWhereHas('item', fn (Builder $item) => $item->where('name', 'like', $like)->orWhere('equipment_code', 'like', $like))
                    ->orWhereHas('team', fn (Builder $team) => $team->where('name', 'like', $like)->orWhere('team_code', 'like', $like));
            });
        }

        return $query->orderByDesc('assigned_at')->orderByDesc('id');
    }

    private function makeCode(): string
    {
        return strtoupper('equip-asgn-'.Str::random(8));
    }
}
