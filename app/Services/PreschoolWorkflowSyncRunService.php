<?php

namespace App\Services;

use App\Models\PreschoolWorkflowSyncRun;
use App\Models\PreschoolWorkflowSyncRunItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class PreschoolWorkflowSyncRunService
{
    private const DEFAULT_BATCH_SIZE = 25;
    private const MAX_BATCH_SIZE = 100;

    public function __construct(
        private readonly PreschoolWorkflowSyncService $syncService,
        private readonly PreschoolWorkflowSourceLinkService $sourceLinkService,
    ) {
    }

    public function run(array $filters, User $actor): array
    {
        $normalizedFilters = $this->normalizeSyncFilters($filters);
        $batchSize = $this->resolveBatchSize($normalizedFilters);
        $limit = $this->resolveLimit($normalizedFilters);
        $candidates = $this->syncService->discoverCandidates($normalizedFilters)->take($limit)->values();

        $run = $this->createRun([
            'mode' => 'run',
            'status' => 'running',
            'definition_key' => $normalizedFilters['definition_key'] ?? null,
            'source_type' => $normalizedFilters['source_type'] ?? null,
            'filters' => $normalizedFilters,
            'requested_limit' => $limit,
            'batch_size' => $batchSize,
            'eligible_count' => $candidates->count(),
            'started_by_user_id' => (string) $actor->getKey(),
            'started_at' => now(),
        ]);

        $summary = [
            'eligible' => $candidates->count(),
            'created' => 0,
            'existing' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $items = collect();

        try {
            foreach ($candidates->chunk($batchSize) as $batch) {
                $batchResults = $this->processBatch($run, $batch, $actor);

                foreach ($batchResults as $itemResult) {
                    $items->push($itemResult);
                    $summary[$itemResult['status']]++;
                }
            }

            $status = $summary['failed'] > 0
                ? ($summary['created'] > 0 || $summary['existing'] > 0 || $summary['skipped'] > 0 ? 'completed_with_errors' : 'failed')
                : 'completed';
        } catch (Throwable $exception) {
            Log::error('Preschool workflow sync run failed before completion.', [
                'runId' => $run->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $this->finalizeRun($run, [
                'status' => 'failed',
                'failure_message' => $exception->getMessage(),
                'summary' => $summary,
            ]);

            throw $exception;
        }

        $this->finalizeRun($run, [
            'status' => $status,
            'summary' => $summary,
        ]);

        return $this->renderRun($run->fresh(['startedBy', 'items']), $items);
    }

    public function listRuns(array $filters, User $actor): array
    {
        $validated = $this->normalizeHistoryFilters($filters);

        $query = PreschoolWorkflowSyncRun::query()->with('startedBy');

        if (($validated['mode'] ?? '') !== '') {
            $query->where('mode', $validated['mode']);
        }

        if (($validated['status'] ?? '') !== '') {
            $query->where('status', $validated['status']);
        }

        if (($validated['definition_key'] ?? '') !== '') {
            $query->where('definition_key', $validated['definition_key']);
        }

        if (($validated['source_type'] ?? '') !== '') {
            $query->where('source_type', $validated['source_type']);
        }

        if (($validated['started_by_user_id'] ?? '') !== '') {
            $query->where('started_by_user_id', $validated['started_by_user_id']);
        }

        if (($validated['date_from'] ?? '') !== '') {
            $query->whereDate('started_at', '>=', $validated['date_from']);
        }

        if (($validated['date_to'] ?? '') !== '') {
            $query->whereDate('started_at', '<=', $validated['date_to']);
        }

        $paginator = $query
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(
                $validated['per_page'],
                ['*'],
                'page',
                $validated['page'],
            );

        return [
            'items' => $paginator->getCollection()->map(fn (PreschoolWorkflowSyncRun $run): array => $this->normalizeRun($run))->values(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ];
    }

    public function showRun(PreschoolWorkflowSyncRun $run, User $actor): array
    {
        $run->loadMissing('startedBy');

        return $this->normalizeRun($run);
    }

    public function listRunItems(PreschoolWorkflowSyncRun $run, array $filters, User $actor): array
    {
        $validated = $this->normalizeItemFilters($filters);

        $query = $run->items()->orderByDesc('processed_at')->orderByDesc('id');

        if (($validated['result_status'] ?? '') !== '') {
            $query->where('result_status', $validated['result_status']);
        }

        $paginator = $query->paginate(
            $validated['per_page'],
            ['*'],
            'page',
            $validated['page'],
        );

        return [
            'items' => $paginator->getCollection()->map(fn (PreschoolWorkflowSyncRunItem $item): array => $this->normalizeRunItem($item))->values(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $batch
     * @return array<int, array<string, mixed>>
     */
    public function processBatch(PreschoolWorkflowSyncRun $run, Collection $batch, User $actor): array
    {
        $results = [];

        foreach ($batch as $candidate) {
            try {
                $result = $this->syncService->syncSource(
                    (string) $candidate['definitionKey'],
                    (string) $candidate['sourceType'],
                    $candidate['sourceId'],
                    $actor,
                );

                $this->recordRunItem($run, $result);
                $results[] = $result;
            } catch (Throwable $exception) {
                Log::warning('Preschool workflow sync item processing failed.', [
                    'runId' => $run->id,
                    'definitionKey' => $candidate['definitionKey'] ?? null,
                    'sourceType' => $candidate['sourceType'] ?? null,
                    'sourceId' => $candidate['sourceId'] ?? null,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                $failure = [
                    'definitionKey' => (string) ($candidate['definitionKey'] ?? ''),
                    'sourceType' => (string) ($candidate['sourceType'] ?? ''),
                    'sourceId' => isset($candidate['sourceId']) ? (string) $candidate['sourceId'] : null,
                    'sourceLabel' => $candidate['sourceLabel'] ?? null,
                    'sourceStatus' => $candidate['sourceStatus'] ?? null,
                    'sourceRouteName' => $candidate['sourceRouteName'] ?? null,
                    'sourceRouteParams' => $candidate['sourceRouteParams'] ?? [],
                    'status' => 'failed',
                    'reason' => $exception->getMessage(),
                    'workflowInstanceId' => null,
                    'errorMessage' => $exception->getMessage(),
                ];

                $this->recordRunItem($run, $failure);
                $results[] = $failure;
            }
        }

        return $results;
    }

    public function finalizeRun(PreschoolWorkflowSyncRun $run, array $data): PreschoolWorkflowSyncRun
    {
        $summary = $data['summary'] ?? [];

        $run->fill([
            'status' => $data['status'] ?? $run->status,
            'eligible_count' => (int) ($summary['eligible'] ?? $run->eligible_count),
            'processed_count' => (int) (($summary['created'] ?? 0) + ($summary['existing'] ?? 0) + ($summary['skipped'] ?? 0) + ($summary['failed'] ?? 0)),
            'created_count' => (int) ($summary['created'] ?? 0),
            'existing_count' => (int) ($summary['existing'] ?? 0),
            'skipped_count' => (int) ($summary['skipped'] ?? 0),
            'failed_count' => (int) ($summary['failed'] ?? 0),
            'failure_message' => $data['failure_message'] ?? $run->failure_message,
            'completed_at' => now(),
        ])->save();

        return $run;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function renderRun(PreschoolWorkflowSyncRun $run, Collection $items): array
    {
        return [
            'run' => $this->normalizeRun($run),
            'summary' => [
                'eligible' => $run->eligible_count,
                'created' => $run->created_count,
                'existing' => $run->existing_count,
                'skipped' => $run->skipped_count,
                'failed' => $run->failed_count,
            ],
            'items' => $items->values()->all(),
            'dryRun' => false,
            'limit' => $run->requested_limit,
            'batchSize' => $run->batch_size,
            'generatedAt' => $run->completed_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeRun(PreschoolWorkflowSyncRun $run): array
    {
        return [
            'id' => $run->id,
            'mode' => $run->mode,
            'status' => $run->status,
            'definitionKey' => $run->definition_key,
            'sourceType' => $run->source_type,
            'filters' => $run->filters ?? [],
            'requestedLimit' => $run->requested_limit,
            'batchSize' => $run->batch_size,
            'eligibleCount' => $run->eligible_count,
            'processedCount' => $run->processed_count,
            'createdCount' => $run->created_count,
            'existingCount' => $run->existing_count,
            'skippedCount' => $run->skipped_count,
            'failedCount' => $run->failed_count,
            'startedByUserId' => $run->started_by_user_id,
            'startedAt' => $run->started_at?->toISOString(),
            'completedAt' => $run->completed_at?->toISOString(),
            'failureMessage' => $run->failure_message,
            'startedBy' => $run->startedBy ? [
                'id' => $run->startedBy->id,
                'name' => trim(($run->startedBy->first_name ?? '').' '.($run->startedBy->last_name ?? '')) ?: $run->startedBy->username ?: $run->startedBy->email,
                'roleCode' => $run->startedBy->role_code,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeRunItem(PreschoolWorkflowSyncRunItem $item): array
    {
        $source = $this->sourceLinkService->resolveSource($item->source_type, $item->source_id, $item->source_label);
        $workflowLink = $this->sourceLinkService->resolveWorkflowLink($item->source_type, $item->source_id);

        return [
            'id' => $item->id,
            'syncRunId' => $item->sync_run_id,
            'definitionKey' => $item->definition_key,
            'sourceType' => $item->source_type,
            'sourceId' => $item->source_id,
            'sourceLabel' => $item->source_label ?? ($source['sourceLabel'] ?? null),
            'sourceRouteName' => $source['sourceRouteName'] ?? null,
            'sourceRouteParams' => $source['sourceRouteParams'] ?? [],
            'sourceExists' => $source['sourceExists'] ?? null,
            'resultStatus' => $item->result_status,
            'reason' => $item->reason,
            'workflowInstanceId' => $item->workflow_instance_id,
            'workflowRouteName' => $workflowLink['workflowRoute'] ?? null,
            'workflowRouteParams' => $workflowLink['workflowActionParams'] ?? [],
            'errorMessage' => $item->error_message,
            'processedAt' => $item->processed_at?->toISOString(),
        ];
    }

    /**
     * @return PreschoolWorkflowSyncRun
     */
    private function createRun(array $attributes): PreschoolWorkflowSyncRun
    {
        return PreschoolWorkflowSyncRun::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function recordRunItem(PreschoolWorkflowSyncRun $run, array $result): PreschoolWorkflowSyncRunItem
    {
        return PreschoolWorkflowSyncRunItem::query()->create([
            'sync_run_id' => $run->id,
            'definition_key' => $result['definitionKey'] ?? '',
            'source_type' => $result['sourceType'] ?? '',
            'source_id' => isset($result['sourceId']) ? (string) $result['sourceId'] : '',
            'source_label' => $result['sourceLabel'] ?? null,
            'result_status' => $result['status'] ?? 'failed',
            'reason' => $result['reason'] ?? null,
            'workflow_instance_id' => $result['workflowInstanceId'] ?? null,
            'error_message' => $result['errorMessage'] ?? null,
            'processed_at' => now(),
        ]);
    }

    private function normalizeSyncFilters(array $filters): array
    {
        $normalized = [];

        foreach (['definition_key', 'source_type', 'status', 'date_from', 'date_to'] as $key) {
            $value = isset($filters[$key]) ? trim((string) $filters[$key]) : '';
            if ($value !== '') {
                $normalized[$key] = $value;
            }
        }

        $normalized['limit'] = $this->resolveLimit($filters);
        $normalized['batch_size'] = $this->resolveBatchSize($filters);

        return $normalized;
    }

    private function normalizeHistoryFilters(array $filters): array
    {
        return [
            'mode' => $this->nullableFilterString($filters['mode'] ?? null),
            'status' => $this->nullableFilterString($filters['status'] ?? null),
            'definition_key' => $this->nullableFilterString($filters['definition_key'] ?? null),
            'source_type' => $this->nullableFilterString($filters['source_type'] ?? null),
            'started_by_user_id' => $this->nullableFilterString($filters['started_by_user_id'] ?? null),
            'date_from' => $this->nullableFilterString($filters['date_from'] ?? null),
            'date_to' => $this->nullableFilterString($filters['date_to'] ?? null),
            'page' => max((int) ($filters['page'] ?? 1), 1),
            'per_page' => min(max((int) ($filters['per_page'] ?? 20), 1), 100),
        ];
    }

    private function normalizeItemFilters(array $filters): array
    {
        return [
            'result_status' => $this->nullableFilterString($filters['result_status'] ?? null),
            'page' => max((int) ($filters['page'] ?? 1), 1),
            'per_page' => min(max((int) ($filters['per_page'] ?? 20), 1), 100),
        ];
    }

    private function resolveBatchSize(array $filters): int
    {
        $requested = (int) ($filters['batch_size'] ?? self::DEFAULT_BATCH_SIZE);

        return min(max($requested, 1), self::MAX_BATCH_SIZE);
    }

    private function resolveLimit(array $filters): int
    {
        return min(max((int) ($filters['limit'] ?? 50), 1), 500);
    }

    private function nullableFilterString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
