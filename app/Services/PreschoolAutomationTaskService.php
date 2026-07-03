<?php

namespace App\Services;

use App\Models\PreschoolAutomationTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PreschoolAutomationTaskService
{
    public function listTasks(?User $viewer, array $filters = []): array
    {
        $query = $this->visibleQuery($viewer, $filters)
            ->with(['creator', 'assignedToUser', 'student', 'preschoolClass'])
            ->orderByRaw("CASE status WHEN 'overdue' THEN 1 WHEN 'open' THEN 2 WHEN 'in_progress' THEN 3 WHEN 'completed' THEN 4 WHEN 'cancelled' THEN 5 ELSE 6 END ASC")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 ELSE 5 END ASC")
            ->orderByRaw('due_at IS NULL ASC')
            ->orderBy('due_at')
            ->orderByDesc('id');

        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'summary' => $this->summary($viewer, $filters),
            'items' => $paginator->getCollection()->map(fn (PreschoolAutomationTask $task): array => $this->formatTask($task))->values()->all(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function summary(?User $viewer, array $filters = []): array
    {
        $tasks = $this->visibleQuery($viewer, $filters)->get();
        $now = now();

        return [
            'total' => $tasks->count(),
            'open' => $tasks->whereIn('status', [PreschoolAutomationTask::STATUS_OPEN, PreschoolAutomationTask::STATUS_IN_PROGRESS])->count(),
            'inProgress' => $tasks->where('status', PreschoolAutomationTask::STATUS_IN_PROGRESS)->count(),
            'completed' => $tasks->where('status', PreschoolAutomationTask::STATUS_COMPLETED)->count(),
            'cancelled' => $tasks->where('status', PreschoolAutomationTask::STATUS_CANCELLED)->count(),
            'overdue' => $tasks->filter(static fn (PreschoolAutomationTask $task): bool => $task->status === PreschoolAutomationTask::STATUS_OVERDUE || ($task->due_at !== null && $task->due_at->lt($now) && ! in_array($task->status, [PreschoolAutomationTask::STATUS_COMPLETED, PreschoolAutomationTask::STATUS_CANCELLED], true)))->count(),
            'today' => $tasks->filter(static fn (PreschoolAutomationTask $task): bool => $task->due_at !== null && $task->due_at->isToday() && ! in_array($task->status, [PreschoolAutomationTask::STATUS_COMPLETED, PreschoolAutomationTask::STATUS_CANCELLED], true))->count(),
            'byType' => $this->groupByKey($tasks, 'task_type', 'taskType'),
            'byPriority' => $this->groupByKey($tasks, 'priority', 'priority'),
        ];
    }

    public function complete(User $actor, PreschoolAutomationTask $task): PreschoolAutomationTask
    {
        $this->ensureCanManage($actor, $task);

        if (! in_array($task->status, [PreschoolAutomationTask::STATUS_COMPLETED, PreschoolAutomationTask::STATUS_CANCELLED], true)) {
            $task->status = PreschoolAutomationTask::STATUS_COMPLETED;
            $task->completed_by = $actor->id;
            $task->completed_at = now();
            $task->save();
        }

        return $task->fresh(['creator', 'assignedToUser', 'student', 'preschoolClass']);
    }

    public function cancel(User $actor, PreschoolAutomationTask $task): PreschoolAutomationTask
    {
        $this->ensureCanManage($actor, $task);

        if (! in_array($task->status, [PreschoolAutomationTask::STATUS_COMPLETED, PreschoolAutomationTask::STATUS_CANCELLED], true)) {
            $task->status = PreschoolAutomationTask::STATUS_CANCELLED;
            $task->cancelled_by = $actor->id;
            $task->cancelled_at = now();
            $task->save();
        }

        return $task->fresh(['creator', 'assignedToUser', 'student', 'preschoolClass']);
    }

    public function assign(User $actor, PreschoolAutomationTask $task, array $data): PreschoolAutomationTask
    {
        $this->ensureCanManage($actor, $task);

        if (in_array($task->status, [PreschoolAutomationTask::STATUS_COMPLETED, PreschoolAutomationTask::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Completed or cancelled tasks cannot be reassigned.'],
            ]);
        }

        $task->assigned_to_user_id = $this->nullableString($data['assigned_to_user_id'] ?? null);
        $task->assigned_role = $this->nullableString($data['assigned_role'] ?? null);
        if ($task->status === PreschoolAutomationTask::STATUS_OVERDUE) {
            $task->status = PreschoolAutomationTask::STATUS_IN_PROGRESS;
        }
        $task->save();

        return $task->fresh(['creator', 'assignedToUser', 'student', 'preschoolClass']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{task: PreschoolAutomationTask, created: bool, updated: bool}
     */
    public function upsertTask(array $data): array
    {
        $task = $this->findExistingTask($data);
        $created = false;
        $updated = false;

        if (! $task) {
            $task = new PreschoolAutomationTask();
            $created = true;
        }

        foreach ([
            'task_type',
            'title',
            'description',
            'priority',
            'status',
            'assigned_to_user_id',
            'assigned_role',
            'due_at',
            'source_type',
            'source_id',
            'preschool_student_id',
            'preschool_class_id',
            'action_route',
            'action_params',
            'created_by',
            'completed_by',
            'completed_at',
            'cancelled_by',
            'cancelled_at',
        ] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $next = $this->normalizeValue($field, $data[$field]);
            if (! $created && $task->{$field} !== $next) {
                $updated = true;
            }
            $task->{$field} = $next;
        }

        if ($task->due_at !== null && ! in_array($task->status, [PreschoolAutomationTask::STATUS_COMPLETED, PreschoolAutomationTask::STATUS_CANCELLED], true) && $task->due_at->lt(now())) {
            $task->status = PreschoolAutomationTask::STATUS_OVERDUE;
        }

        if ($created && blank($task->status)) {
            $task->status = PreschoolAutomationTask::STATUS_OPEN;
        }

        if (! $task->exists || $created || $updated) {
            $task->save();
        }

        return [
            'task' => $task->fresh(['creator', 'assignedToUser', 'student', 'preschoolClass']),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    private function visibleQuery(?User $viewer, array $filters = []): Builder
    {
        $query = PreschoolAutomationTask::query();

        if (! $viewer || $viewer->role_code === 'superadmin') {
            $this->applyFilters($query, $filters);

            return $query;
        }

        $query->where(function (Builder $builder) use ($viewer): void {
            $builder->where('assigned_to_user_id', $viewer->id)
                ->orWhere('assigned_role', $viewer->role_code);
        });

        $this->applyFilters($query, $filters);

        return $query;
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (($status = trim((string) ($filters['status'] ?? ''))) !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if (($type = trim((string) ($filters['task_type'] ?? $filters['type'] ?? ''))) !== '' && $type !== 'all') {
            $query->where('task_type', $type);
        }

        if (($priority = trim((string) ($filters['priority'] ?? ''))) !== '' && $priority !== 'all') {
            $query->where('priority', $priority);
        }

        if (($studentId = trim((string) ($filters['preschool_student_id'] ?? $filters['student_id'] ?? ''))) !== '') {
            $query->where('preschool_student_id', $studentId);
        }

        if (($classId = trim((string) ($filters['preschool_class_id'] ?? $filters['class_id'] ?? ''))) !== '') {
            $query->where('preschool_class_id', $classId);
        }

        if (($search = trim((string) ($filters['search'] ?? ''))) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(static function (Builder $searchQuery) use ($like): void {
                $searchQuery->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('task_type', 'like', $like)
                    ->orWhere('source_type', 'like', $like);
            });
        }
    }

    private function formatTask(PreschoolAutomationTask $task): array
    {
        return [
            'id' => $task->id,
            'taskType' => $task->task_type,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => $task->status,
            'assignedToUserId' => $task->assigned_to_user_id,
            'assignedRole' => $task->assigned_role,
            'dueAt' => $task->due_at?->toISOString(),
            'sourceType' => $task->source_type,
            'sourceId' => $task->source_id,
            'preschoolStudentId' => $task->preschool_student_id,
            'preschoolClassId' => $task->preschool_class_id,
            'studentName' => $this->studentName($task),
            'className' => $this->className($task),
            'actionRoute' => $task->action_route,
            'actionParams' => $task->action_params ?? [],
            'createdBy' => $task->created_by,
            'completedBy' => $task->completed_by,
            'completedAt' => $task->completed_at?->toISOString(),
            'cancelledBy' => $task->cancelled_by,
            'cancelledAt' => $task->cancelled_at?->toISOString(),
            'createdAt' => $task->created_at?->toISOString(),
        ];
    }

    private function groupByKey(Collection $items, string $field, string $outputKey): array
    {
        return $items
            ->groupBy(static fn (PreschoolAutomationTask $task): string => trim((string) data_get($task, $field)) ?: 'unknown')
            ->map(static function (Collection $group, string $key) use ($outputKey): array {
                return [
                    $outputKey => $key === 'unknown' ? null : $key,
                    'total' => $group->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function findExistingTask(array $data): ?PreschoolAutomationTask
    {
        return PreschoolAutomationTask::query()
            ->where('task_type', $this->normalizeValue('task_type', $data['task_type'] ?? ''))
            ->where('source_type', $this->normalizeValue('source_type', $data['source_type'] ?? null))
            ->where('source_id', $this->normalizeValue('source_id', $data['source_id'] ?? null))
            ->where('assigned_to_user_id', $this->normalizeValue('assigned_to_user_id', $data['assigned_to_user_id'] ?? null))
            ->where('assigned_role', $this->normalizeValue('assigned_role', $data['assigned_role'] ?? null))
            ->where('preschool_student_id', $this->normalizeValue('preschool_student_id', $data['preschool_student_id'] ?? null))
            ->where('preschool_class_id', $this->normalizeValue('preschool_class_id', $data['preschool_class_id'] ?? null))
            ->first();
    }

    private function normalizeValue(string $field, mixed $value): mixed
    {
        return match ($field) {
            'task_type', 'title', 'description', 'priority', 'status', 'assigned_to_user_id', 'assigned_role', 'source_type', 'source_id', 'action_route', 'created_by', 'completed_by', 'cancelled_by' => $this->nullableString($value),
            'preschool_student_id', 'preschool_class_id' => $this->nullableInt($value),
            'action_params' => is_array($value) ? $value : null,
            'due_at', 'completed_at', 'cancelled_at' => $value ?: null,
            default => $value,
        };
    }

    private function studentName(PreschoolAutomationTask $task): ?string
    {
        $student = $task->relationLoaded('student') ? $task->student : $task->student()->first();

        return $student ? trim(($student->first_name ?? '').' '.($student->last_name ?? '')) ?: null : null;
    }

    private function className(PreschoolAutomationTask $task): ?string
    {
        $class = $task->relationLoaded('preschoolClass') ? $task->preschoolClass : $task->preschoolClass()->first();

        return $class?->name ?: null;
    }

    private function ensureCanManage(User $actor, PreschoolAutomationTask $task): void
    {
        if (in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)) {
            return;
        }

        if ($actor->role_code === 'teacher-preschool' && $task->assigned_to_user_id === $actor->id) {
            return;
        }

        throw ValidationException::withMessages([
            'task' => ['You are not allowed to update this task.'],
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
