<?php

namespace App\Support;

use App\Http\Resources\Sport\SportEquipmentItemResource;
use App\Models\SportEquipmentItem;
use App\Models\SportEquipmentRequest;
use App\Models\User;
use App\Services\SportActivityRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SportEquipmentService
{
    public function __construct(
        private readonly SportActivityRecorder $activityRecorder,
        private readonly SportEquipmentAssignmentService $assignmentService,
    ) {}

    public function summary(): array
    {
        $base = SportEquipmentItem::query()->where('status', SportEquipmentItemStatus::ACTIVE);

        return [
            'totalActiveItems' => (clone $base)->count(),
            'availableItems' => (clone $base)->where('available_quantity', '>', 0)->count(),
            'lowStockItems' => (clone $base)->whereColumn('available_quantity', '<=', 'minimum_stock_level')->count(),
            'outOfStockItems' => (clone $base)->where('available_quantity', 0)->count(),
        ];
    }

    public function listItems(array $filters = [], bool $activeOnly = false): LengthAwarePaginator
    {
        $query = $this->buildItemQuery($filters, $activeOnly);

        return $query
            ->paginate((int) ($filters['per_page'] ?? 10), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function findItemOrFail(string|int $id): SportEquipmentItem
    {
        $item = SportEquipmentItem::query()->with(['createdBy', 'updatedBy'])->find($id);

        if (! $item) {
            throw new ModelNotFoundException('Equipment item not found.');
        }

        return $item;
    }

    public function createItem(User $actor, array $data): SportEquipmentItem
    {
        $item = DB::transaction(function () use ($actor, $data): SportEquipmentItem {
            $totalQuantity = max(0, (int) $data['total_quantity']);
            $availableQuantity = max(0, (int) $data['available_quantity']);
            $minimumStockLevel = max(0, (int) $data['minimum_stock_level']);

            if ($availableQuantity > $totalQuantity) {
                throw new \RuntimeException('Available quantity cannot exceed total quantity.');
            }

            $item = SportEquipmentItem::query()->create([
                'equipment_code' => $data['equipment_code'] ?? $this->makeCode('equip'),
                'name' => $data['name'],
                'category' => $data['category'],
                'description' => $data['description'] ?? null,
                'unit' => $data['unit'],
                'total_quantity' => $totalQuantity,
                'available_quantity' => $availableQuantity,
                'minimum_stock_level' => $minimumStockLevel,
                'storage_location' => $data['storage_location'] ?? null,
                'status' => $data['status'],
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            return $item->refresh()->loadMissing(['createdBy', 'updatedBy']);
        });

        $this->activityRecorder->equipmentItemCreated($item, $actor);

        return $item;
    }

    public function updateItem(SportEquipmentItem $item, User $actor, array $data): SportEquipmentItem
    {
        $before = $item->toArray();

        $item = DB::transaction(function () use ($item, $actor, $data): SportEquipmentItem {
            foreach (['name', 'category', 'description', 'unit', 'storage_location', 'status'] as $field) {
                if (array_key_exists($field, $data)) {
                    $item->{$field} = $data[$field];
                }
            }

            if (array_key_exists('equipment_code', $data)) {
                $item->equipment_code = $data['equipment_code'] ?: $item->equipment_code ?: $this->makeCode('equip');
            }

            if (array_key_exists('total_quantity', $data)) {
                $item->total_quantity = max(0, (int) $data['total_quantity']);
            }

            if (array_key_exists('available_quantity', $data)) {
                $item->available_quantity = max(0, (int) $data['available_quantity']);
            }

            if (array_key_exists('minimum_stock_level', $data)) {
                $item->minimum_stock_level = max(0, (int) $data['minimum_stock_level']);
            }

            if ($item->available_quantity > $item->total_quantity) {
                throw new \RuntimeException('Available quantity cannot exceed total quantity.');
            }

            $item->updated_by_user_id = $actor->id;
            $item->save();

            return $item->refresh()->loadMissing(['createdBy', 'updatedBy']);
        });

        $this->activityRecorder->equipmentItemUpdated($item, $actor, $before, $item->toArray());

        return $item;
    }

    public function listRequestableItems(array $filters = []): LengthAwarePaginator
    {
        return $this->listItems($filters + ['status' => SportEquipmentItemStatus::ACTIVE], true);
    }

    public function applyIssue(SportEquipmentRequest $request, User $actor, int $issuedQuantity, ?string $adminNote = null): SportEquipmentRequest
    {
        return DB::transaction(function () use ($request, $actor, $issuedQuantity, $adminNote): SportEquipmentRequest {
            $lockedRequest = SportEquipmentRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $item = SportEquipmentItem::query()->whereKey($lockedRequest->equipment_item_id)->lockForUpdate()->firstOrFail();

            if ($lockedRequest->status !== SportEquipmentRequestStatus::APPROVED) {
                throw new \RuntimeException('Equipment request is not approved.');
            }

            if ($issuedQuantity <= 0) {
                throw new \RuntimeException('Issued quantity must be greater than zero.');
            }

            $approvedQuantity = (int) ($lockedRequest->approved_quantity ?? 0);
            if ($approvedQuantity <= 0) {
                throw new \RuntimeException('Approved quantity is required before issuing equipment.');
            }

            if ($issuedQuantity > $approvedQuantity) {
                throw new \RuntimeException('Issued quantity cannot exceed approved quantity.');
            }

            if ($issuedQuantity > $item->available_quantity) {
                throw new \RuntimeException('Insufficient stock.');
            }

            $item->available_quantity = max(0, $item->available_quantity - $issuedQuantity);
            $item->updated_by_user_id = $actor->id;
            $item->save();

            $lockedRequest->forceFill([
                'issued_quantity' => $issuedQuantity,
                'status' => SportEquipmentRequestStatus::ISSUED,
                'admin_note' => $adminNote,
                'issued_by_user_id' => $actor->id,
                'issued_at' => Carbon::now(),
            ])->save();

            $this->assignmentService->createFromIssuedRequest($lockedRequest, $actor, $issuedQuantity, $adminNote);

            return $lockedRequest->refresh()->loadMissing(['item', 'coach', 'team', 'reviewedBy', 'issuedBy', 'returnedBy']);
        });
    }

    public function applyReturn(SportEquipmentRequest $request, User $actor, int $returnedQuantity, int $damagedQuantity, int $missingQuantity, ?string $adminNote = null): SportEquipmentRequest
    {
        return DB::transaction(function () use ($request, $actor, $returnedQuantity, $damagedQuantity, $missingQuantity, $adminNote): SportEquipmentRequest {
            $lockedRequest = SportEquipmentRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $item = SportEquipmentItem::query()->whereKey($lockedRequest->equipment_item_id)->lockForUpdate()->firstOrFail();

            if ($lockedRequest->status !== SportEquipmentRequestStatus::ISSUED) {
                throw new \RuntimeException('Equipment request is not issued.');
            }

            $issuedQuantity = (int) $lockedRequest->issued_quantity;
            if ($returnedQuantity + $damagedQuantity + $missingQuantity !== $issuedQuantity) {
                throw new \RuntimeException('Returned, damaged, and missing quantities must equal the issued quantity.');
            }

            $nextAvailable = $item->available_quantity + $returnedQuantity;
            $nextTotal = $item->total_quantity - ($damagedQuantity + $missingQuantity);

            if ($nextTotal < 0) {
                throw new \RuntimeException('Stock cannot become negative.');
            }

            if ($nextAvailable > $nextTotal) {
                throw new \RuntimeException('Available quantity cannot exceed total quantity.');
            }

            $item->available_quantity = $nextAvailable;
            $item->total_quantity = $nextTotal;
            $item->updated_by_user_id = $actor->id;
            $item->save();

            $lockedRequest->forceFill([
                'returned_quantity' => $returnedQuantity,
                'damaged_quantity' => $damagedQuantity,
                'missing_quantity' => $missingQuantity,
                'status' => SportEquipmentRequestStatus::RETURNED,
                'admin_note' => $adminNote,
                'returned_by_user_id' => $actor->id,
                'returned_at' => Carbon::now(),
            ])->save();

            $this->assignmentService->completeFromReturnedRequest(
                $lockedRequest,
                $actor,
                $returnedQuantity,
                $damagedQuantity,
                $missingQuantity,
                $adminNote,
            );

            return $lockedRequest->refresh()->loadMissing(['item', 'coach', 'team', 'reviewedBy', 'issuedBy', 'returnedBy']);
        });
    }

    private function buildItemQuery(array $filters = [], bool $activeOnly = false): Builder
    {
        $query = SportEquipmentItem::query()->with(['createdBy', 'updatedBy']);

        if ($activeOnly) {
            $query->where('status', SportEquipmentItemStatus::ACTIVE);
        } elseif (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('equipment_code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('unit', 'like', $like)
                    ->orWhere('storage_location', 'like', $like);
            });
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $query->where('category', $category);
        }

        $stock = trim((string) ($filters['stock'] ?? ''));
        if ($stock === 'low') {
            $query->whereColumn('available_quantity', '<=', 'minimum_stock_level');
        } elseif ($stock === 'out') {
            $query->where('available_quantity', 0);
        } elseif ($stock === 'available') {
            $query->where('available_quantity', '>', 0);
        }

        $sortBy = (string) ($filters['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($filters['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortColumn = match ($sortBy) {
            'equipment_code' => 'equipment_code',
            'name' => 'name',
            'category' => 'category',
            'total_quantity' => 'total_quantity',
            'available_quantity' => 'available_quantity',
            'minimum_stock_level' => 'minimum_stock_level',
            'status' => 'status',
            default => 'created_at',
        };

        return $query->orderBy($sortColumn, $sortDirection)->orderBy('id', 'desc');
    }

    private function makeCode(string $prefix): string
    {
        return strtoupper($prefix.'-'.Str::random(8));
    }
}
