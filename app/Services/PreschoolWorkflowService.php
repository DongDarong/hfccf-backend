<?php

namespace App\Services;

use App\Models\PreschoolWorkflowDefinition;
use App\Models\PreschoolWorkflowApproval;
use App\Models\PreschoolWorkflowEvent;
use App\Models\PreschoolWorkflowInstance;
use App\Models\PreschoolWorkflowStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PreschoolWorkflowService
{
    public const STATUSES = ['open', 'in_progress', 'pending_approval', 'approved', 'rejected', 'returned', 'completed', 'cancelled', 'escalated', 'overdue'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public function __construct(
        private readonly PreschoolWorkflowDefinitionService $definitionService,
        private readonly PreschoolWorkflowTimelineService $timelineService,
        private readonly PreschoolWorkflowSourceLinkService $sourceLinkService,
    ) {
    }

    public function listInstances(?User $viewer, array $filters = []): array
    {
        $query = $this->visibleQuery($viewer, $filters)
            ->with(['definition.steps', 'currentStep', 'assignee'])
            ->withCount(['approvals', 'events'])
            ->orderByRaw("CASE status WHEN 'overdue' THEN 1 WHEN 'escalated' THEN 2 WHEN 'pending_approval' THEN 3 WHEN 'open' THEN 4 WHEN 'in_progress' THEN 5 WHEN 'returned' THEN 6 WHEN 'approved' THEN 7 WHEN 'completed' THEN 8 WHEN 'cancelled' THEN 9 WHEN 'rejected' THEN 10 ELSE 11 END ASC")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 ELSE 5 END ASC")
            ->orderByRaw('due_at IS NULL ASC')
            ->orderBy('due_at')
            ->orderByDesc('updated_at');

        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'summary' => $this->summary($viewer, $filters),
            'items' => $paginator->getCollection()->map(fn (PreschoolWorkflowInstance $instance): array => $this->formatInstance($instance))->values()->all(),
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
        $instances = $this->visibleQuery($viewer, $filters)->with(['approvals', 'definition'])->get();
        $now = now();
        $activeStatuses = ['open', 'in_progress', 'pending_approval', 'returned', 'approved', 'overdue', 'escalated'];

        return [
            'total' => $instances->count(),
            'pendingWorkflows' => $instances->filter(static fn (PreschoolWorkflowInstance $instance): bool => in_array($instance->status, $activeStatuses, true) && ! in_array($instance->status, ['completed', 'cancelled', 'rejected'], true))->count(),
            'open' => $instances->where('status', 'open')->count(),
            'inProgress' => $instances->where('status', 'in_progress')->count(),
            'pendingApproval' => $instances->where('status', 'pending_approval')->count(),
            'pendingApprovals' => $instances->where('status', 'pending_approval')->count(),
            'approved' => $instances->where('status', 'approved')->count(),
            'rejected' => $instances->where('status', 'rejected')->count(),
            'returned' => $instances->where('status', 'returned')->count(),
            'completed' => $instances->where('status', 'completed')->count(),
            'cancelled' => $instances->where('status', 'cancelled')->count(),
            'escalated' => $instances->where('status', 'escalated')->count(),
            'overdue' => $instances->filter(static fn (PreschoolWorkflowInstance $instance): bool => in_array($instance->status, ['open', 'in_progress', 'pending_approval', 'returned', 'approved'], true) && $instance->due_at !== null && $instance->due_at->lt($now))->count(),
            'myAssignments' => $viewer ? $instances->where('assigned_to_user_id', $viewer->id)->count() : 0,
            'assignedToMe' => $viewer ? $instances->where('assigned_to_user_id', $viewer->id)->count() : 0,
            'myApprovals' => $viewer ? $instances->filter(static fn (PreschoolWorkflowInstance $instance): bool => $instance->approvals->contains(fn ($approval) => in_array($approval->status, ['pending'], true) && ($approval->requested_to_user_id === $viewer->id || $approval->requested_to_role === $viewer->role_code)))->count() : 0,
            'byDefinition' => $instances
                ->groupBy(fn (PreschoolWorkflowInstance $instance): string => (string) ($instance->workflow_definition_id ?? 'unknown'))
                ->map(static function (Collection $group, string $definitionId): array {
                    $first = $group->first();

                    return [
                        'workflowDefinitionId' => $definitionId === 'unknown' ? null : (int) $definitionId,
                        'workflowDefinitionKey' => $first?->definition?->key,
                        'workflowDefinitionName' => $first?->definition?->name,
                        'total' => $group->count(),
                    ];
                })
                ->values()
                ->all(),
            'byStatus' => $instances
                ->groupBy('status')
                ->map(static fn (Collection $group, string $status): array => [
                    'status' => $status,
                    'total' => $group->count(),
                ])
                ->values()
                ->all(),
            'byPriority' => $instances
                ->groupBy('priority')
                ->map(static fn (Collection $group, string $priority): array => [
                    'priority' => $priority,
                    'total' => $group->count(),
                ])
                ->values()
                ->all(),
            'recentlyUpdatedWorkflows' => $instances->filter(static fn (PreschoolWorkflowInstance $instance): bool => $instance->updated_at !== null && $instance->updated_at->gte(now()->subDay()))->count(),
        ];
    }

    public function show(PreschoolWorkflowInstance $instance, ?User $viewer = null): array
    {
        $this->ensureCanView($viewer, $instance);

        $instance->loadMissing(['definition.steps', 'currentStep', 'assignee', 'approvals.requestedBy', 'approvals.requestedTo', 'approvals.decidedBy', 'events.actor', 'events.fromStep', 'events.toStep']);

        return $this->formatInstance($instance, true);
    }

    public function create(array $data, ?User $actor = null): PreschoolWorkflowInstance
    {
        return DB::transaction(function () use ($data, $actor): PreschoolWorkflowInstance {
            $definition = $this->resolveDefinition($data);
            $sourceType = $this->nullableString($data['source_type'] ?? null);
            $sourceId = $this->nullableString($data['source_id'] ?? null);

            if ($sourceType === null || $sourceId === null) {
                throw ValidationException::withMessages([
                    'source' => ['Workflow source type and source id are required.'],
                ]);
            }

            $existing = $this->findExistingInstance($definition, $sourceType, $sourceId);
            if ($existing) {
                return $this->refreshInstance($existing, $data, $actor, false);
            }

            $firstStep = $this->resolveStartingStep($definition, $data);
            $status = $this->normalizeStatus($data['status'] ?? null) ?? ($firstStep?->step_type === 'approval' ? 'pending_approval' : 'open');
            $dueAt = $this->normalizeDateTime($data['due_at'] ?? null) ?? $this->calculateDueAt($firstStep);

            $instance = PreschoolWorkflowInstance::query()->create([
                'workflow_definition_id' => $definition->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_label' => $this->nullableString($data['source_label'] ?? null),
                'current_step_id' => $firstStep?->id,
                'status' => $status,
                'priority' => $this->normalizePriority($data['priority'] ?? null),
                'assigned_to_user_id' => $this->nullableString($data['assigned_to_user_id'] ?? null),
                'assigned_role' => $this->nullableString($data['assigned_role'] ?? null),
                'due_at' => $dueAt,
                'started_at' => now(),
                'metadata' => $this->normalizeArray($data['metadata'] ?? null),
            ]);

            $this->recordEvent($instance, 'workflow_instance_created', 'Workflow instance created.', $actor, null, $status, null, $firstStep?->id, [
                'definitionKey' => $definition->key,
                'sourceType' => $sourceType,
                'sourceId' => $sourceId,
            ]);

            return $instance->fresh(['definition.steps', 'currentStep', 'assignee', 'approvals', 'events']);
        });
    }

    public function assign(PreschoolWorkflowInstance $instance, array $data, ?User $actor = null): PreschoolWorkflowInstance
    {
        return DB::transaction(function () use ($instance, $data, $actor): PreschoolWorkflowInstance {
            $this->ensureCanManage($actor, $instance);
            $before = $instance->fresh(['currentStep', 'definition.steps']);
            $instance->assigned_to_user_id = $this->nullableString($data['assigned_to_user_id'] ?? null);
            $instance->assigned_role = $this->nullableString($data['assigned_role'] ?? null);
            if (array_key_exists('due_at', $data)) {
                $instance->due_at = $this->normalizeDateTime($data['due_at'] ?? null);
            }
            if (in_array($instance->status, ['open', 'returned'], true)) {
                $instance->status = 'in_progress';
            }
            $instance->save();

            $this->recordEvent($instance, 'workflow_assigned', 'Workflow assignment updated.', $actor, $before?->status, $instance->status, $before?->current_step_id, $instance->current_step_id, [
                'assignedToUserId' => $instance->assigned_to_user_id,
                'assignedRole' => $instance->assigned_role,
                'dueAt' => $instance->due_at?->toISOString(),
            ]);

            return $instance->fresh(['definition.steps', 'currentStep', 'assignee', 'approvals', 'events']);
        });
    }

    public function transition(PreschoolWorkflowInstance $instance, array $data, ?User $actor = null): PreschoolWorkflowInstance
    {
        return DB::transaction(function () use ($instance, $data, $actor): PreschoolWorkflowInstance {
            $this->ensureCanManage($actor, $instance);
            $instance->loadMissing(['definition.steps', 'currentStep']);
            $beforeStep = $instance->currentStep;
            $beforeStatus = $instance->status;
            $step = $this->resolveStep($instance, $data);

            if ($step) {
                $instance->current_step_id = $step->id;
                $instance->status = $this->statusForStep($step, $instance->status);
                if ($step->step_type === 'final') {
                    $instance->completed_at = $instance->completed_at ?? now();
                }
                $instance->due_at = $this->normalizeDateTime($data['due_at'] ?? null) ?? $this->calculateDueAt($step);
            } elseif (array_key_exists('status', $data)) {
                $instance->status = $this->normalizeStatus($data['status']) ?? $instance->status;
            }

            if (array_key_exists('metadata', $data)) {
                $instance->metadata = array_merge($instance->metadata ?? [], $this->normalizeArray($data['metadata']));
            }

            $instance->save();

            $this->recordEvent($instance, 'workflow_transitioned', 'Workflow transitioned.', $actor, $beforeStatus, $instance->status, $beforeStep?->id, $instance->current_step_id, [
                'stepKey' => $step?->key,
                'stepType' => $step?->step_type,
            ]);

            return $instance->fresh(['definition.steps', 'currentStep', 'assignee', 'approvals', 'events']);
        });
    }

    public function complete(PreschoolWorkflowInstance $instance, array $data = [], ?User $actor = null): PreschoolWorkflowInstance
    {
        return DB::transaction(function () use ($instance, $data, $actor): PreschoolWorkflowInstance {
            $this->ensureCanManage($actor, $instance);
            $before = $instance->fresh(['currentStep']);
            $instance->status = 'completed';
            $instance->completed_at = now();
            if (array_key_exists('metadata', $data)) {
                $instance->metadata = array_merge($instance->metadata ?? [], $this->normalizeArray($data['metadata']));
            }
            $instance->save();

            $this->recordEvent($instance, 'workflow_completed', 'Workflow completed.', $actor, $before?->status, 'completed', $before?->current_step_id, $instance->current_step_id, []);

            return $instance->fresh(['definition.steps', 'currentStep', 'assignee', 'approvals', 'events']);
        });
    }

    public function cancel(PreschoolWorkflowInstance $instance, array $data = [], ?User $actor = null): PreschoolWorkflowInstance
    {
        return DB::transaction(function () use ($instance, $data, $actor): PreschoolWorkflowInstance {
            $this->ensureCanManage($actor, $instance);
            $before = $instance->fresh(['currentStep']);
            $instance->status = 'cancelled';
            $instance->cancelled_at = now();
            if (array_key_exists('metadata', $data)) {
                $instance->metadata = array_merge($instance->metadata ?? [], $this->normalizeArray($data['metadata']));
            }
            $instance->save();

            $this->recordEvent($instance, 'workflow_cancelled', 'Workflow cancelled.', $actor, $before?->status, 'cancelled', $before?->current_step_id, $instance->current_step_id, []);

            return $instance->fresh(['definition.steps', 'currentStep', 'assignee', 'approvals', 'events']);
        });
    }

    public function escalate(PreschoolWorkflowInstance $instance, array $data = [], ?User $actor = null): PreschoolWorkflowInstance
    {
        return DB::transaction(function () use ($instance, $data, $actor): PreschoolWorkflowInstance {
            $this->ensureCanManage($actor, $instance);
            $before = $instance->fresh(['currentStep']);
            $instance->status = 'escalated';
            $instance->escalated_at = now();
            if (array_key_exists('due_at', $data)) {
                $instance->due_at = $this->normalizeDateTime($data['due_at'] ?? null);
            }
            if (array_key_exists('metadata', $data)) {
                $instance->metadata = array_merge($instance->metadata ?? [], $this->normalizeArray($data['metadata']));
            }
            $instance->save();

            $this->recordEvent($instance, 'workflow_escalated', 'Workflow escalated.', $actor, $before?->status, 'escalated', $before?->current_step_id, $instance->current_step_id, [
                'reason' => $data['reason'] ?? null,
            ]);

            return $instance->fresh(['definition.steps', 'currentStep', 'assignee', 'approvals', 'events']);
        });
    }

    public function findById(string|int $id): ?PreschoolWorkflowInstance
    {
        return PreschoolWorkflowInstance::query()
            ->with(['definition.steps', 'currentStep', 'assignee', 'approvals', 'events'])
            ->find($id);
    }

    public function listApprovals(?User $viewer, array $filters = []): array
    {
        $query = PreschoolWorkflowApproval::query()
            ->with(['instance.definition', 'instance.currentStep', 'requestedBy', 'requestedTo', 'decidedBy', 'step'])
            ->orderByDesc('created_at');

        if (! $this->canSeeAll($viewer)) {
            $query->where(function (Builder $builder) use ($viewer): void {
                $builder->where('requested_to_user_id', $viewer?->id)
                    ->orWhere('requested_to_role', $viewer?->role_code)
                    ->orWhere('requested_by_user_id', $viewer?->id);
            });
        }

        if (($status = trim((string) ($filters['status'] ?? ''))) !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if (($search = trim((string) ($filters['search'] ?? ''))) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('decision_notes', 'like', $like)
                    ->orWhere('requested_to_role', 'like', $like)
                    ->orWhereHas('instance', fn (Builder $instanceQuery) => $instanceQuery->where('source_label', 'like', $like)->orWhere('source_type', 'like', $like)->orWhere('source_id', 'like', $like));
            });
        }

        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection()->map(fn (PreschoolWorkflowApproval $approval): array => $this->formatApproval($approval))->values()->all(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function formatApproval(PreschoolWorkflowApproval $approval): array
    {
        $approval->loadMissing(['instance.definition', 'instance.currentStep', 'requestedBy', 'requestedTo', 'decidedBy', 'step']);

        return [
            'id' => $approval->id,
            'workflowInstanceId' => $approval->workflow_instance_id,
            'workflowStepId' => $approval->workflow_step_id,
            'requestedByUserId' => $approval->requested_by_user_id,
            'requestedToUserId' => $approval->requested_to_user_id,
            'requestedToRole' => $approval->requested_to_role,
            'status' => $approval->status,
            'decisionNotes' => $approval->decision_notes,
            'decidedByUserId' => $approval->decided_by_user_id,
            'decidedAt' => $approval->decided_at?->toISOString(),
            'dueAt' => $approval->due_at?->toISOString(),
            'metadata' => $approval->metadata ?? [],
            'instance' => $approval->instance ? $this->formatInstance($approval->instance) : null,
            'step' => $approval->step ? [
                'id' => $approval->step->id,
                'key' => $approval->step->key,
                'name' => $approval->step->name,
                'stepType' => $approval->step->step_type,
            ] : null,
            'requestedBy' => $this->userSnapshot($approval->requestedBy),
            'requestedTo' => $this->userSnapshot($approval->requestedTo),
            'decidedBy' => $this->userSnapshot($approval->decidedBy),
            'createdAt' => $approval->created_at?->toISOString(),
            'updatedAt' => $approval->updated_at?->toISOString(),
        ];
    }

    public function requestApproval(PreschoolWorkflowInstance $instance, array $data, ?User $actor = null): PreschoolWorkflowApproval
    {
        return DB::transaction(function () use ($instance, $data, $actor): PreschoolWorkflowApproval {
            $this->ensureCanManage($actor, $instance);
            $instance->loadMissing(['definition.steps', 'currentStep']);
            $step = $this->resolveApprovalStep($instance, $data);
            $status = $instance->status === 'pending_approval' ? $instance->status : 'pending_approval';
            $dueAt = $this->normalizeDateTime($data['due_at'] ?? null) ?? $this->calculateDueAt($step ?? $instance->currentStep);

            $approval = PreschoolWorkflowApproval::query()->create([
                'workflow_instance_id' => $instance->id,
                'workflow_step_id' => $step?->id,
                'requested_by_user_id' => $actor?->id,
                'requested_to_user_id' => $this->nullableString($data['requested_to_user_id'] ?? null),
                'requested_to_role' => $this->nullableString($data['requested_to_role'] ?? null),
                'status' => 'pending',
                'decision_notes' => $this->nullableString($data['decision_notes'] ?? null),
                'due_at' => $dueAt,
                'metadata' => $this->normalizeArray($data['metadata'] ?? null),
            ]);

            $instance->status = $status;
            if ($step) {
                $instance->current_step_id = $step->id;
            }
            if ($dueAt) {
                $instance->due_at = $dueAt;
            }
            $instance->save();

            $this->recordEvent($instance, 'workflow_approval_requested', 'Approval requested.', $actor, null, $instance->status, null, $step?->id, [
                'approvalId' => $approval->id,
                'requestedToUserId' => $approval->requested_to_user_id,
                'requestedToRole' => $approval->requested_to_role,
            ]);

            return $approval->fresh(['instance.definition.steps', 'instance.currentStep', 'requestedBy', 'requestedTo', 'decidedBy', 'step']);
        });
    }

    public function approve(PreschoolWorkflowApproval $approval, array $data = [], ?User $actor = null): PreschoolWorkflowApproval
    {
        return $this->decideApproval($approval, 'approved', $data, $actor);
    }

    public function reject(PreschoolWorkflowApproval $approval, array $data = [], ?User $actor = null): PreschoolWorkflowApproval
    {
        return $this->decideApproval($approval, 'rejected', $data, $actor);
    }

    public function returnApproval(PreschoolWorkflowApproval $approval, array $data = [], ?User $actor = null): PreschoolWorkflowApproval
    {
        return $this->decideApproval($approval, 'returned', $data, $actor);
    }

    public function cancelApproval(PreschoolWorkflowApproval $approval, array $data = [], ?User $actor = null): PreschoolWorkflowApproval
    {
        return $this->decideApproval($approval, 'cancelled', $data, $actor);
    }

    public function timeline(PreschoolWorkflowInstance $instance): array
    {
        return $this->timelineService->buildTimeline($instance);
    }

    public function findExistingInstance(PreschoolWorkflowDefinition $definition, string $sourceType, string $sourceId): ?PreschoolWorkflowInstance
    {
        return PreschoolWorkflowInstance::query()
            ->with(['definition.steps', 'currentStep', 'assignee', 'approvals', 'events'])
            ->where('workflow_definition_id', $definition->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();
    }

    public function resolveDefinition(array $data): PreschoolWorkflowDefinition
    {
        if (($definitionId = Arr::get($data, 'workflow_definition_id')) !== null && $definitionId !== '') {
            return PreschoolWorkflowDefinition::query()->with('steps')->findOrFail($definitionId);
        }

        if (($definitionKey = $this->nullableString(Arr::get($data, 'workflow_definition_key'))) !== null) {
            $definition = $this->definitionService->findByKey($definitionKey);
            if ($definition) {
                return $definition;
            }
        }

        throw ValidationException::withMessages([
            'workflow_definition_id' => ['Workflow definition is required.'],
        ]);
    }

    public function formatInstance(PreschoolWorkflowInstance $instance, bool $includeChildren = false): array
    {
        $instance->loadMissing(['definition.steps', 'currentStep', 'assignee']);
        $source = $this->sourceLinkService->resolveSource($instance->source_type, $instance->source_id, $instance->source_label);

        return [
            'id' => $instance->id,
            'workflowDefinitionId' => $instance->workflow_definition_id,
            'workflowDefinitionKey' => $instance->definition?->key,
            'workflowDefinitionName' => $instance->definition?->name,
            'workflowDefinitionDomain' => $instance->definition?->domain,
            'sourceType' => $source['sourceType'],
            'sourceId' => $source['sourceId'],
            'sourceLabel' => $source['sourceLabel'],
            'sourceRouteName' => $source['sourceRouteName'],
            'sourceRouteParams' => $source['sourceRouteParams'] ?? [],
            'sourceExists' => $source['sourceExists'],
            'currentStepId' => $instance->current_step_id,
            'currentStep' => $instance->currentStep ? [
                'id' => $instance->currentStep->id,
                'key' => $instance->currentStep->key,
                'name' => $instance->currentStep->name,
                'stepType' => $instance->currentStep->step_type,
                'assignedRole' => $instance->currentStep->assigned_role,
                'slaHours' => $instance->currentStep->sla_hours,
                'sortOrder' => $instance->currentStep->sort_order,
            ] : null,
            'status' => $instance->status,
            'priority' => $instance->priority,
            'assignedToUserId' => $instance->assigned_to_user_id,
            'assignedRole' => $instance->assigned_role,
            'dueAt' => $instance->due_at?->toISOString(),
            'startedAt' => $instance->started_at?->toISOString(),
            'completedAt' => $instance->completed_at?->toISOString(),
            'cancelledAt' => $instance->cancelled_at?->toISOString(),
            'escalatedAt' => $instance->escalated_at?->toISOString(),
            'metadata' => $instance->metadata ?? [],
            'assignee' => $this->userSnapshot($instance->assignee),
            'approvalsCount' => $instance->approvals_count ?? $instance->approvals()->count(),
            'eventsCount' => $instance->events_count ?? $instance->events()->count(),
            'definitions' => $includeChildren && $instance->relationLoaded('definition') ? $instance->definition->steps->map(fn (PreschoolWorkflowStep $step): array => $this->formatStep($step))->values()->all() : null,
            'timeline' => $includeChildren ? $this->timeline($instance) : null,
            'approvals' => $includeChildren ? $instance->approvals->map(fn (PreschoolWorkflowApproval $approval): array => $this->formatApproval($approval))->values()->all() : null,
            'createdAt' => $instance->created_at?->toISOString(),
            'updatedAt' => $instance->updated_at?->toISOString(),
        ];
    }

    public function formatStep(PreschoolWorkflowStep $step): array
    {
        return [
            'id' => $step->id,
            'workflowDefinitionId' => $step->workflow_definition_id,
            'key' => $step->key,
            'name' => $step->name,
            'sortOrder' => $step->sort_order,
            'stepType' => $step->step_type,
            'assignedRole' => $step->assigned_role,
            'slaHours' => $step->sla_hours,
            'config' => $step->config ?? [],
            'createdAt' => $step->created_at?->toISOString(),
            'updatedAt' => $step->updated_at?->toISOString(),
        ];
    }

    public function listDefinitions(): array
    {
        return $this->definitionService->listActive()->map(function (PreschoolWorkflowDefinition $definition): array {
            return [
                'id' => $definition->id,
                'key' => $definition->key,
                'name' => $definition->name,
                'description' => $definition->description,
                'domain' => $definition->domain,
                'isActive' => $definition->is_active,
                'config' => $definition->config ?? [],
                'steps' => $definition->steps->map(fn (PreschoolWorkflowStep $step): array => $this->formatStep($step))->values()->all(),
                'createdAt' => $definition->created_at?->toISOString(),
                'updatedAt' => $definition->updated_at?->toISOString(),
            ];
        })->values()->all();
    }

    private function visibleQuery(?User $viewer, array $filters = []): Builder
    {
        $query = PreschoolWorkflowInstance::query();

        if (! $this->canSeeAll($viewer)) {
            $query->where(function (Builder $builder) use ($viewer): void {
                $builder->where('assigned_to_user_id', $viewer?->id)
                    ->orWhere('assigned_role', $viewer?->role_code)
                    ->orWhereHas('approvals', function (Builder $approvalQuery) use ($viewer): void {
                        $approvalQuery->where('requested_to_user_id', $viewer?->id)
                            ->orWhere('requested_to_role', $viewer?->role_code);
                    });
            });
        }

        if (($status = trim((string) ($filters['status'] ?? ''))) !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if (($priority = trim((string) ($filters['priority'] ?? ''))) !== '' && $priority !== 'all') {
            $query->where('priority', $priority);
        }

        if (($definitionKey = trim((string) ($filters['workflow_definition_key'] ?? $filters['definition_key'] ?? ''))) !== '' && $definitionKey !== 'all') {
            $query->whereHas('definition', fn (Builder $builder) => $builder->where('key', $definitionKey));
        }

        if (($sourceType = trim((string) ($filters['source_type'] ?? ''))) !== '' && $sourceType !== 'all') {
            $query->where('source_type', $sourceType);
        }

        if (($search = trim((string) ($filters['search'] ?? ''))) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('source_label', 'like', $like)
                    ->orWhere('source_type', 'like', $like)
                    ->orWhere('source_id', 'like', $like)
                    ->orWhereHas('definition', fn (Builder $definitionQuery) => $definitionQuery->where('key', 'like', $like)->orWhere('name', 'like', $like));
            });
        }

        if (($assignedToUserId = $this->nullableString($filters['assigned_to_user_id'] ?? null)) !== null) {
            $query->where('assigned_to_user_id', $assignedToUserId);
        }

        if (($assignedRole = $this->nullableString($filters['assigned_role'] ?? null)) !== null) {
            $query->where('assigned_role', $assignedRole);
        }

        return $query;
    }

    private function refreshInstance(PreschoolWorkflowInstance $instance, array $data, ?User $actor, bool $allowUpdateMetadata = true): PreschoolWorkflowInstance
    {
        $instance->loadMissing(['definition.steps', 'currentStep', 'assignee', 'approvals', 'events']);
        $changes = [];

        if (array_key_exists('source_label', $data) && $this->nullableString($data['source_label'] ?? null) !== $instance->source_label) {
            $instance->source_label = $this->nullableString($data['source_label'] ?? null);
            $changes['sourceLabel'] = $instance->source_label;
        }

        if (array_key_exists('priority', $data) && $this->normalizePriority($data['priority'] ?? null) !== $instance->priority) {
            $instance->priority = $this->normalizePriority($data['priority'] ?? null);
            $changes['priority'] = $instance->priority;
        }

        if (array_key_exists('assigned_to_user_id', $data) && $this->nullableString($data['assigned_to_user_id'] ?? null) !== $instance->assigned_to_user_id) {
            $instance->assigned_to_user_id = $this->nullableString($data['assigned_to_user_id'] ?? null);
            $changes['assignedToUserId'] = $instance->assigned_to_user_id;
        }

        if (array_key_exists('assigned_role', $data) && $this->nullableString($data['assigned_role'] ?? null) !== $instance->assigned_role) {
            $instance->assigned_role = $this->nullableString($data['assigned_role'] ?? null);
            $changes['assignedRole'] = $instance->assigned_role;
        }

        if (array_key_exists('metadata', $data) && $allowUpdateMetadata) {
            $merged = array_merge($instance->metadata ?? [], $this->normalizeArray($data['metadata']));
            if ($merged !== ($instance->metadata ?? [])) {
                $instance->metadata = $merged;
                $changes['metadata'] = $instance->metadata;
            }
        }

        if ($changes !== []) {
            $instance->save();
            $this->recordEvent($instance, 'workflow_instance_deduped', 'Workflow instance reused.', $actor, $instance->getOriginal('status'), $instance->status, $instance->getOriginal('current_step_id'), $instance->current_step_id, $changes);
        }

        return $instance->fresh(['definition.steps', 'currentStep', 'assignee', 'approvals', 'events']);
    }

    private function recordEvent(PreschoolWorkflowInstance $instance, string $eventType, string $title, ?User $actor, ?string $fromStatus, ?string $toStatus, mixed $fromStepId, mixed $toStepId, array $metadata = []): PreschoolWorkflowEvent
    {
        return PreschoolWorkflowEvent::query()->create([
            'workflow_instance_id' => $instance->id,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $metadata['description'] ?? null,
            'actor_user_id' => $actor?->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_step_id' => $fromStepId ?: null,
            'to_step_id' => $toStepId ?: null,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }

    private function decideApproval(PreschoolWorkflowApproval $approval, string $status, array $data, ?User $actor): PreschoolWorkflowApproval
    {
        return DB::transaction(function () use ($approval, $status, $data, $actor): PreschoolWorkflowApproval {
            $approval->loadMissing(['instance.definition.steps', 'instance.currentStep', 'step']);
            $this->ensureCanDecide($actor, $approval);

            if ($approval->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => ['Only pending approvals can be decided.'],
                ]);
            }

            $approval->status = $status;
            $approval->decision_notes = $this->nullableString($data['decision_notes'] ?? null);
            $approval->decided_by_user_id = $actor?->id;
            $approval->decided_at = now();
            if (array_key_exists('metadata', $data)) {
                $approval->metadata = array_merge($approval->metadata ?? [], $this->normalizeArray($data['metadata']));
            }
            $approval->save();

            $instance = $approval->instance;
            $beforeStatus = $instance->status;
            $instance->status = match ($status) {
                'approved' => 'approved',
                'rejected' => 'rejected',
                'returned' => 'returned',
                'cancelled' => 'cancelled',
                default => 'escalated',
            };
            if (in_array($status, ['approved', 'rejected', 'returned', 'cancelled'], true)) {
                $instance->current_step_id = $approval->workflow_step_id ?: $instance->current_step_id;
            }
            if ($status === 'cancelled') {
                $instance->cancelled_at = $instance->cancelled_at ?? now();
            }
            $instance->save();

            $this->recordEvent($instance, 'workflow_approval_'.$status, 'Workflow approval '.$status.'.', $actor, $beforeStatus, $instance->status, $approval->workflow_step_id, $instance->current_step_id, [
                'approvalId' => $approval->id,
                'decisionNotes' => $approval->decision_notes,
            ]);

            return $approval->fresh(['instance.definition.steps', 'instance.currentStep', 'requestedBy', 'requestedTo', 'decidedBy', 'step']);
        });
    }

    private function ensureCanView(?User $viewer, PreschoolWorkflowInstance $instance): void
    {
        if ($this->canSeeAll($viewer)) {
            return;
        }

        abort_unless(
            $viewer && (
                $instance->assigned_to_user_id === $viewer->id
                || $instance->assigned_role === $viewer->role_code
                || $instance->approvals()->where('requested_to_user_id', $viewer->id)->orWhere('requested_to_role', $viewer->role_code)->exists()
            ),
            403,
            'Forbidden.',
        );
    }

    private function ensureCanManage(?User $actor, PreschoolWorkflowInstance $instance): void
    {
        abort_unless($actor, 401, 'Unauthenticated.');

        abort_unless(in_array($actor->role_code, ['superadmin', 'adminpreschool'], true), 403, 'Forbidden.');

        if ($this->canSeeAll($actor)) {
            return;
        }

        abort_unless(
            $instance->assigned_to_user_id === $actor->id || $instance->assigned_role === $actor->role_code,
            403,
            'Forbidden.',
        );
    }

    private function ensureCanDecide(?User $actor, PreschoolWorkflowApproval $approval): void
    {
        abort_unless($actor, 401, 'Unauthenticated.');

        if ($this->canSeeAll($actor)) {
            return;
        }

        abort_unless(
            $approval->requested_to_user_id === $actor->id || $approval->requested_to_role === $actor->role_code,
            403,
            'Forbidden.',
        );
    }

    private function canSeeAll(?User $user): bool
    {
        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    private function resolveStartingStep(PreschoolWorkflowDefinition $definition, array $data): ?PreschoolWorkflowStep
    {
        if (($stepId = Arr::get($data, 'current_step_id')) !== null && $stepId !== '') {
            return $definition->steps->firstWhere('id', (int) $stepId) ?? PreschoolWorkflowStep::query()->where('workflow_definition_id', $definition->id)->find($stepId);
        }

        if (($stepKey = $this->nullableString(Arr::get($data, 'current_step_key'))) !== null) {
            return $definition->steps->firstWhere('key', $stepKey);
        }

        return $definition->steps->sortBy('sort_order')->first();
    }

    private function resolveApprovalStep(PreschoolWorkflowInstance $instance, array $data): ?PreschoolWorkflowStep
    {
        if (($stepId = Arr::get($data, 'workflow_step_id')) !== null && $stepId !== '') {
            return $instance->definition->steps->firstWhere('id', (int) $stepId);
        }

        if (($stepKey = $this->nullableString(Arr::get($data, 'workflow_step_key'))) !== null) {
            return $instance->definition->steps->firstWhere('key', $stepKey);
        }

        return $instance->currentStep;
    }

    private function resolveStep(PreschoolWorkflowInstance $instance, array $data): ?PreschoolWorkflowStep
    {
        if (($stepId = Arr::get($data, 'workflow_step_id')) !== null && $stepId !== '') {
            return $instance->definition->steps->firstWhere('id', (int) $stepId);
        }

        if (($stepKey = $this->nullableString(Arr::get($data, 'workflow_step_key'))) !== null) {
            return $instance->definition->steps->firstWhere('key', $stepKey);
        }

        return null;
    }

    private function resolveDefinitionStep(PreschoolWorkflowDefinition $definition, mixed $stepId): ?PreschoolWorkflowStep
    {
        if ($stepId === null || $stepId === '') {
            return null;
        }

        return $definition->steps->firstWhere('id', (int) $stepId);
    }

    private function calculateDueAt(?PreschoolWorkflowStep $step): ?Carbon
    {
        if (! $step || ! $step->sla_hours) {
            return null;
        }

        return now()->addHours($step->sla_hours);
    }

    private function statusForStep(PreschoolWorkflowStep $step, string $fallback): string
    {
        return match ($step->step_type) {
            'start' => 'open',
            'review' => 'pending_approval',
            'approval' => 'approved',
            'final' => 'completed',
            default => in_array($fallback, self::STATUSES, true) ? $fallback : 'in_progress',
        };
    }

    private function normalizePriority(mixed $value): string
    {
        $value = strtolower(trim((string) ($value ?? '')));

        return in_array($value, self::PRIORITIES, true) ? $value : 'normal';
    }

    private function normalizeStatus(mixed $value): ?string
    {
        $value = strtolower(trim((string) ($value ?? '')));

        return $value !== '' && in_array($value, self::STATUSES, true) ? $value : null;
    }

    private function normalizeDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function userSnapshot(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'username' => $user->username,
            'email' => $user->email,
            'roleCode' => $user->role_code,
            'status' => $user->status,
        ];
    }

    private function formatApprovalStep(?PreschoolWorkflowStep $step): ?array
    {
        if (! $step) {
            return null;
        }

        return [
            'id' => $step->id,
            'key' => $step->key,
            'name' => $step->name,
            'stepType' => $step->step_type,
            'assignedRole' => $step->assigned_role,
            'slaHours' => $step->sla_hours,
        ];
    }
}
