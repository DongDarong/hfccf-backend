<?php

namespace App\Services;

use App\Models\PreschoolWorkflowDefinition;
use App\Models\PreschoolWorkflowSyncRun;
use App\Models\PreschoolWorkflowSyncRunItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PreschoolWorkflowObservabilityService
{
    public function dashboard(array $filters, User $actor): array
    {
        $validated = $this->normalizeFilters($filters);
        $runs = $this->filteredRunsQuery($validated)->with('startedBy')->get();
        $items = $this->filteredItemsQuery($validated)->get();
        $definitionNames = $this->definitionNames($runs);
        $now = now();

        $runSnapshots = $runs->map(fn (PreschoolWorkflowSyncRun $run): array => $this->normalizeRunSnapshot($run, $definitionNames));
        $itemSnapshots = $items->map(fn (PreschoolWorkflowSyncRunItem $item): array => $this->normalizeItemSnapshot($item));
        $failureEvents = $this->failureEvents($runs, $items, $runSnapshots);
        $staleRuns = $runSnapshots->filter(fn (array $run): bool => (bool) ($run['stale']['isStale'] ?? false))->values();
        $durationValues = $runSnapshots
            ->pluck('durationMs')
            ->filter(fn ($value): bool => is_int($value) && $value >= 0)
            ->values();
        $processedValues = $runSnapshots
            ->pluck('processedCount')
            ->map(fn ($value): int => (int) $value)
            ->values();
        $failedItemCount = $itemSnapshots->where('resultStatus', 'failed')->count();
        $successfulRuns = $runs->where('status', 'completed')->count();
        $runsWithErrors = $runs->where('status', 'completed_with_errors')->count();
        $failedRuns = $runs->where('status', 'failed')->count();
        $runningRuns = $runs->where('status', 'running')->count();
        $totalRuns = $runs->count();
        $totalProcessed = (int) $runSnapshots->sum('processedCount');
        $totalCreated = (int) $runSnapshots->sum('createdCount');
        $totalExisting = (int) $runSnapshots->sum('existingCount');
        $totalSkipped = (int) $runSnapshots->sum('skippedCount');
        $totalFailedItems = (int) $runSnapshots->sum('failedCount');
        $successRate = $totalRuns > 0 ? round(($successfulRuns / $totalRuns) * 100, 2) : 0.0;
        $failureRate = $totalRuns > 0 ? round((($runsWithErrors + $failedRuns) / $totalRuns) * 100, 2) : 0.0;
        $averageDurationMs = $durationValues->isNotEmpty() ? (int) round($durationValues->avg()) : null;
        $longestDurationMs = $durationValues->isNotEmpty() ? (int) $durationValues->max() : null;
        $averageItemsPerRun = $totalRuns > 0 ? round($totalProcessed / $totalRuns, 2) : 0.0;
        $recentWindow = $this->recentWindow($now);
        $recentRuns = $runSnapshots
            ->sortByDesc(fn (array $run): string => (string) ($run['startedAt'] ?? $run['createdAt'] ?? ''))
            ->take($this->recentActivityLimit())
            ->values();
        $recentCompletedRuns = $runSnapshots
            ->filter(fn (array $run): bool => in_array($run['status'], ['completed', 'completed_with_errors', 'failed', 'cancelled'], true))
            ->sortByDesc(fn (array $run): string => (string) ($run['completedAt'] ?? $run['startedAt'] ?? $run['createdAt'] ?? ''))
            ->take($this->recentActivityLimit())
            ->values();
        $recentFailures = $failureEvents
            ->sortByDesc(fn (array $failure): string => (string) ($failure['occurredAt'] ?? ''))
            ->take($this->recentActivityLimit())
            ->values();
        $recentFailedRuns = $runSnapshots
            ->filter(fn (array $run): bool => $run['status'] === 'failed')
            ->sortByDesc(fn (array $run): string => (string) ($run['completedAt'] ?? $run['startedAt'] ?? $run['createdAt'] ?? ''))
            ->take($this->recentActivityLimit())
            ->values();
        $recentRunsWithErrors = $runSnapshots
            ->filter(fn (array $run): bool => $run['status'] === 'completed_with_errors')
            ->sortByDesc(fn (array $run): string => (string) ($run['completedAt'] ?? $run['startedAt'] ?? $run['createdAt'] ?? ''))
            ->take($this->recentActivityLimit())
            ->values();
        $highFailureRateRuns = $runSnapshots
            ->filter(fn (array $run): bool => ($run['processedCount'] ?? 0) > 0 && (($run['failedCount'] ?? 0) / max((int) $run['processedCount'], 1)) >= ($this->criticalFailureRate() / 100))
            ->sortByDesc(fn (array $run): float => (float) (($run['failedCount'] ?? 0) / max((int) ($run['processedCount'] ?? 0), 1)))
            ->take($this->recentActivityLimit())
            ->values();
        $healthStatus = $this->healthStatus($staleRuns->count(), $recentFailedRuns->count(), $recentRunsWithErrors->count(), $failureRate);

        return [
            'summary' => [
                'totalRuns' => $totalRuns,
                'successfulRuns' => $successfulRuns,
                'runsWithErrors' => $runsWithErrors,
                'failedRuns' => $failedRuns,
                'runningRuns' => $runningRuns,
                'staleRuns' => $staleRuns->count(),
                'totalProcessed' => $totalProcessed,
                'totalCreated' => $totalCreated,
                'totalExisting' => $totalExisting,
                'totalSkipped' => $totalSkipped,
                'totalFailedItems' => $totalFailedItems,
                'successRate' => $successRate,
                'failureRate' => $failureRate,
                'averageDurationMs' => $averageDurationMs,
                'longestDurationMs' => $longestDurationMs,
                'averageItemsPerRun' => $averageItemsPerRun,
            ],
            'performance' => [
                'averageDurationMs' => $averageDurationMs,
                'longestDurationMs' => $longestDurationMs,
                'slowestRuns' => $runSnapshots
                    ->filter(fn (array $run): bool => is_int($run['durationMs']) && $run['durationMs'] >= 0)
                    ->sortByDesc('durationMs')
                    ->take($this->slowestRunsLimit())
                    ->values()
                    ->all(),
                'durationTrend' => $this->durationTrend($runSnapshots),
                'processedItemsTrend' => $this->processedTrend($runSnapshots),
                'throughputTrend' => $this->throughputTrend($runSnapshots),
            ],
            'health' => [
                'status' => $healthStatus,
                'staleRuns' => $staleRuns->all(),
                'recentFailedRuns' => $recentFailedRuns->all(),
                'recentRunsWithErrors' => $recentRunsWithErrors->all(),
                'highFailureRateRuns' => $highFailureRateRuns->all(),
            ],
            'breakdowns' => [
                'byDefinition' => $this->breakdownByDefinition($runSnapshots, $definitionNames),
                'bySourceType' => $this->breakdownBySourceType($runSnapshots),
                'byRunStatus' => $this->breakdownByRunStatus($runSnapshots),
                'byItemStatus' => $this->breakdownByItemStatus($itemSnapshots),
                'byActor' => $this->breakdownByActor($runSnapshots),
                'byFailureCategory' => $this->breakdownByFailureCategory($failureEvents),
            ],
            'trends' => [
                'runsOverTime' => $this->runsOverTime($runSnapshots),
                'processedItemsOverTime' => $this->processedItemsOverTime($runSnapshots),
                'failureRateOverTime' => $this->failureRateOverTime($runSnapshots),
                'durationOverTime' => $this->durationOverTime($runSnapshots),
            ],
            'recentActivity' => [
                'recentRuns' => $recentRuns->all(),
                'recentFailures' => $recentFailures->all(),
                'recentlyCompletedRuns' => $recentCompletedRuns->all(),
            ],
            'governance' => [
                'oldestRunAt' => $this->oldestRunAt($runSnapshots),
                'totalRunRecords' => $totalRuns,
                'totalItemRecords' => $items->count(),
                'retentionMode' => 'policy_only',
                'automaticPruningEnabled' => false,
            ],
            'filters' => $validated,
            'generatedAt' => $now->toISOString(),
        ];
    }

    private function filteredRunsQuery(array $filters): Builder
    {
        $query = PreschoolWorkflowSyncRun::query();
        $this->applyRunFilters($query, $filters);

        return $query->orderByDesc('started_at')->orderByDesc('id');
    }

    private function filteredItemsQuery(array $filters): Builder
    {
        $query = PreschoolWorkflowSyncRunItem::query()
            ->from('preschool_workflow_sync_run_items as items')
            ->join('preschool_workflow_sync_runs as runs', 'runs.id', '=', 'items.sync_run_id')
            ->select('items.*');

        $this->applyRunFilters($query, $filters, 'runs');

        return $query->orderByDesc('items.processed_at')->orderByDesc('items.id');
    }

    private function applyRunFilters(Builder $query, array $filters, string $alias = 'preschool_workflow_sync_runs'): void
    {
        if (($mode = $filters['mode'] ?? null) !== null) {
            $query->where("{$alias}.mode", $mode);
        }

        if (($status = $filters['status'] ?? null) !== null) {
            $query->where("{$alias}.status", $status);
        }

        if (($definitionKey = $filters['definition_key'] ?? null) !== null) {
            $query->where("{$alias}.definition_key", $definitionKey);
        }

        if (($sourceType = $filters['source_type'] ?? null) !== null) {
            $query->where("{$alias}.source_type", $sourceType);
        }

        if (($startedByUserId = $filters['started_by_user_id'] ?? null) !== null) {
            $query->where("{$alias}.started_by_user_id", $startedByUserId);
        }

        if (($dateFrom = $filters['date_from'] ?? null) !== null) {
            $query->whereDate(DB::raw("COALESCE({$alias}.started_at, {$alias}.created_at)"), '>=', $dateFrom);
        }

        if (($dateTo = $filters['date_to'] ?? null) !== null) {
            $query->whereDate(DB::raw("COALESCE({$alias}.started_at, {$alias}.created_at)"), '<=', $dateTo);
        }
    }

    private function normalizeFilters(array $filters): array
    {
        $normalized = [];

        foreach (['definition_key', 'source_type', 'status', 'started_by_user_id', 'date_from', 'date_to', 'mode'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));

            if ($value !== '') {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function definitionNames(Collection $runs): array
    {
        $keys = $runs->pluck('definition_key')->filter()->unique()->values()->all();

        if ($keys === []) {
            return [];
        }

        return PreschoolWorkflowDefinition::query()
            ->whereIn('key', $keys)
            ->pluck('name', 'key')
            ->all();
    }

    private function normalizeRunSnapshot(PreschoolWorkflowSyncRun $run, array $definitionNames): array
    {
        $startedAt = $run->started_at;
        $completedAt = $run->completed_at;
        $ageAnchor = $run->status === 'pending'
            ? ($run->created_at ?? $run->updated_at)
            : ($startedAt ?? $run->updated_at ?? $run->created_at);
        $durationMs = $this->durationMs($run);
        $throughput = $this->throughputFromDuration($run, $durationMs);
        $stale = $this->staleMeta($run, $ageAnchor);

        return [
            'id' => $run->id,
            'mode' => $run->mode,
            'status' => $run->status,
            'definitionKey' => $run->definition_key,
            'definitionName' => $definitionNames[$run->definition_key] ?? null,
            'sourceType' => $run->source_type,
            'startedByUserId' => $run->started_by_user_id,
            'startedBy' => $this->userSnapshot($run->startedBy),
            'requestedLimit' => $run->requested_limit,
            'batchSize' => $run->batch_size,
            'eligibleCount' => $run->eligible_count,
            'processedCount' => $run->processed_count,
            'createdCount' => $run->created_count,
            'existingCount' => $run->existing_count,
            'skippedCount' => $run->skipped_count,
            'failedCount' => $run->failed_count,
            'startedAt' => $startedAt?->toISOString(),
            'completedAt' => $completedAt?->toISOString(),
            'createdAt' => $run->created_at?->toISOString(),
            'updatedAt' => $run->updated_at?->toISOString(),
            'durationMs' => $durationMs,
            'throughputItemsPerSecond' => $throughput,
            'failureMessage' => $run->failure_message,
            'stale' => $stale,
        ];
    }

    private function normalizeItemSnapshot(PreschoolWorkflowSyncRunItem $item): array
    {
        return [
            'id' => $item->id,
            'syncRunId' => $item->sync_run_id,
            'definitionKey' => $item->definition_key,
            'sourceType' => $item->source_type,
            'sourceId' => $item->source_id,
            'sourceLabel' => $item->source_label,
            'resultStatus' => $item->result_status,
            'reason' => $item->reason,
            'workflowInstanceId' => $item->workflow_instance_id,
            'errorMessage' => $item->error_message,
            'processedAt' => $item->processed_at?->toISOString(),
            'failureCategory' => $this->classifyFailureText($item->error_message ?: $item->reason),
        ];
    }

    private function failureEvents(Collection $runs, Collection $items, Collection $runSnapshots): Collection
    {
        $runsById = $runSnapshots->keyBy('id');
        $events = collect();

        foreach ($runs as $run) {
            if ($run->status !== 'failed' && trim((string) $run->failure_message) === '') {
                continue;
            }

            $events->push([
                'kind' => 'run',
                'id' => 'run-'.$run->id,
                'runId' => $run->id,
                'definitionKey' => $run->definition_key,
                'sourceType' => $run->source_type,
                'sourceId' => null,
                'sourceLabel' => null,
                'status' => $run->status,
                'failureCategory' => $this->classifyFailureText($run->failure_message),
                'reason' => $run->failure_message,
                'errorMessage' => $run->failure_message,
                'occurredAt' => $run->completed_at?->toISOString() ?? $run->started_at?->toISOString() ?? $run->created_at?->toISOString(),
                'run' => $runsById->get($run->id),
            ]);
        }

        foreach ($items as $item) {
            if ($item->result_status !== 'failed' && trim((string) ($item->error_message ?? $item->reason ?? '')) === '') {
                continue;
            }

            $run = $runsById->get((int) $item->sync_run_id);

            $events->push([
                'kind' => 'item',
                'id' => 'item-'.$item->id,
                'runId' => $item->sync_run_id,
                'definitionKey' => $item->definition_key,
                'sourceType' => $item->source_type,
                'sourceId' => $item->source_id,
                'sourceLabel' => $item->source_label,
                'status' => $item->result_status,
                'failureCategory' => $this->classifyFailureText($item->error_message ?: $item->reason),
                'reason' => $item->reason,
                'errorMessage' => $item->error_message,
                'occurredAt' => $item->processed_at?->toISOString(),
                'run' => $run,
            ]);
        }

        return $events->values();
    }

    private function breakdownByDefinition(Collection $runs, array $definitionNames): array
    {
        return $runs
            ->groupBy(fn (array $run): string => (string) ($run['definitionKey'] ?? 'unknown'))
            ->map(fn (Collection $group, string $definitionKey): array => [
                'definitionKey' => $definitionKey === 'unknown' ? null : $definitionKey,
                'definitionName' => $definitionNames[$definitionKey] ?? null,
                'totalRuns' => $group->count(),
                'successfulRuns' => $group->where('status', 'completed')->count(),
                'runsWithErrors' => $group->where('status', 'completed_with_errors')->count(),
                'failedRuns' => $group->where('status', 'failed')->count(),
                'staleRuns' => $group->filter(fn (array $run): bool => (bool) ($run['stale']['isStale'] ?? false))->count(),
                'totalProcessed' => (int) $group->sum('processedCount'),
                'averageDurationMs' => $this->averageOf($group->pluck('durationMs')),
            ])
            ->values()
            ->all();
    }

    private function breakdownBySourceType(Collection $runs): array
    {
        return $runs
            ->groupBy(fn (array $run): string => (string) ($run['sourceType'] ?? 'unknown'))
            ->map(fn (Collection $group, string $sourceType): array => [
                'sourceType' => $sourceType === 'unknown' ? null : $sourceType,
                'sourceLabel' => $this->labelFromSourceType($sourceType),
                'totalRuns' => $group->count(),
                'successfulRuns' => $group->where('status', 'completed')->count(),
                'runsWithErrors' => $group->where('status', 'completed_with_errors')->count(),
                'failedRuns' => $group->where('status', 'failed')->count(),
                'staleRuns' => $group->filter(fn (array $run): bool => (bool) ($run['stale']['isStale'] ?? false))->count(),
            ])
            ->values()
            ->all();
    }

    private function breakdownByRunStatus(Collection $runs): array
    {
        return $runs
            ->groupBy('status')
            ->map(fn (Collection $group, string $status): array => [
                'status' => $status,
                'totalRuns' => $group->count(),
            ])
            ->values()
            ->all();
    }

    private function breakdownByItemStatus(Collection $items): array
    {
        return $items
            ->groupBy('resultStatus')
            ->map(fn (Collection $group, string $status): array => [
                'resultStatus' => $status,
                'totalItems' => $group->count(),
            ])
            ->values()
            ->all();
    }

    private function breakdownByActor(Collection $runs): array
    {
        return $runs
            ->groupBy(fn (array $run): string => (string) ($run['startedByUserId'] ?? 'unknown'))
            ->map(fn (Collection $group, string $actorId): array => [
                'startedByUserId' => $actorId === 'unknown' ? null : $actorId,
                'startedBy' => $group->first()['startedBy'] ?? null,
                'totalRuns' => $group->count(),
                'successfulRuns' => $group->where('status', 'completed')->count(),
                'runsWithErrors' => $group->where('status', 'completed_with_errors')->count(),
                'failedRuns' => $group->where('status', 'failed')->count(),
                'totalProcessed' => (int) $group->sum('processedCount'),
            ])
            ->values()
            ->all();
    }

    private function breakdownByFailureCategory(Collection $failureEvents): array
    {
        return $failureEvents
            ->groupBy(fn (array $event): string => (string) ($event['failureCategory'] ?? 'unknown'))
            ->map(fn (Collection $group, string $category): array => [
                'failureCategory' => $category,
                'totalFailures' => $group->count(),
                'runFailures' => $group->where('kind', 'run')->count(),
                'itemFailures' => $group->where('kind', 'item')->count(),
            ])
            ->sortByDesc('totalFailures')
            ->values()
            ->all();
    }

    private function runsOverTime(Collection $runs): array
    {
        return $this->groupRunsByDate($runs)->map(fn (Collection $group, string $date): array => [
            'date' => $date,
            'totalRuns' => $group->count(),
            'successfulRuns' => $group->where('status', 'completed')->count(),
            'runsWithErrors' => $group->where('status', 'completed_with_errors')->count(),
            'failedRuns' => $group->where('status', 'failed')->count(),
            'runningRuns' => $group->where('status', 'running')->count(),
            'staleRuns' => $group->filter(fn (array $run): bool => (bool) ($run['stale']['isStale'] ?? false))->count(),
        ])->values()->all();
    }

    private function processedItemsOverTime(Collection $runs): array
    {
        return $this->groupRunsByDate($runs)->map(fn (Collection $group, string $date): array => [
            'date' => $date,
            'processedItems' => (int) $group->sum('processedCount'),
            'createdItems' => (int) $group->sum('createdCount'),
            'existingItems' => (int) $group->sum('existingCount'),
            'skippedItems' => (int) $group->sum('skippedCount'),
            'failedItems' => (int) $group->sum('failedCount'),
        ])->values()->all();
    }

    private function failureRateOverTime(Collection $runs): array
    {
        return $this->groupRunsByDate($runs)->map(fn (Collection $group, string $date): array => [
            'date' => $date,
            'totalRuns' => $group->count(),
            'failedRuns' => $group->where('status', 'failed')->count(),
            'runsWithErrors' => $group->where('status', 'completed_with_errors')->count(),
            'failureRate' => $group->count() > 0
                ? round((($group->where('status', 'failed')->count() + $group->where('status', 'completed_with_errors')->count()) / $group->count()) * 100, 2)
                : 0.0,
        ])->values()->all();
    }

    private function durationOverTime(Collection $runs): array
    {
        return $this->groupRunsByDate($runs)->map(fn (Collection $group, string $date): array => [
            'date' => $date,
            'totalRuns' => $group->count(),
            'completedRuns' => $group->filter(fn (array $run): bool => is_int($run['durationMs']) && $run['durationMs'] >= 0)->count(),
            'averageDurationMs' => $this->averageOf($group->pluck('durationMs')),
            'longestDurationMs' => $this->longestOf($group->pluck('durationMs')),
        ])->values()->all();
    }

    private function durationTrend(Collection $runs): array
    {
        return $this->durationOverTime($runs);
    }

    private function processedTrend(Collection $runs): array
    {
        return $this->processedItemsOverTime($runs);
    }

    private function throughputTrend(Collection $runs): array
    {
        return $this->groupRunsByDate($runs)->map(fn (Collection $group, string $date): array => [
            'date' => $date,
            'throughputItemsPerSecond' => $this->averageOf($group->pluck('throughputItemsPerSecond')),
            'totalRuns' => $group->count(),
        ])->values()->all();
    }

    private function groupRunsByDate(Collection $runs): Collection
    {
        return $runs
            ->groupBy(fn (array $run): string => $this->runDateKey($run))
            ->sortKeys();
    }

    private function runDateKey(array $run): string
    {
        return $this->dateKeyFromTimestamp($run['startedAt'] ?? $run['createdAt'] ?? null);
    }

    private function dateKeyFromTimestamp(?string $value): string
    {
        if (trim((string) $value) === '') {
            return 'unknown';
        }

        return Carbon::parse($value)->toDateString();
    }

    private function durationMs(PreschoolWorkflowSyncRun $run): ?int
    {
        if (! $run->started_at || ! $run->completed_at) {
            return null;
        }

        if ($run->completed_at->lessThan($run->started_at)) {
            return null;
        }

        return (int) $run->started_at->diffInMilliseconds($run->completed_at);
    }

    private function throughputFromDuration(PreschoolWorkflowSyncRun $run, ?int $durationMs): ?float
    {
        if ($durationMs === null || $durationMs <= 0) {
            return null;
        }

        return round(((int) $run->processed_count / $durationMs) * 1000, 4);
    }

    private function staleMeta(PreschoolWorkflowSyncRun $run, ?Carbon $anchor): ?array
    {
        if (! $anchor) {
            return null;
        }

        $status = $run->status;
        $thresholdMs = match ($status) {
            'pending' => $this->stalePendingThresholdMs(),
            'running' => $this->staleRunningThresholdMs(),
            default => null,
        };

        if ($thresholdMs === null) {
            return null;
        }

        $ageMs = (int) $anchor->diffInMilliseconds(now());

        if ($ageMs < $thresholdMs) {
            return null;
        }

        return [
            'isStale' => true,
            'staleReason' => $status === 'pending'
                ? 'Pending longer than the configured threshold.'
                : 'Running longer than the configured threshold.',
            'ageMs' => $ageMs,
            'thresholdMs' => $thresholdMs,
            'run' => [
                'id' => $run->id,
                'mode' => $run->mode,
                'status' => $run->status,
                'definitionKey' => $run->definition_key,
                'sourceType' => $run->source_type,
                'startedByUserId' => $run->started_by_user_id,
                'startedBy' => $this->userSnapshot($run->startedBy),
                'requestedLimit' => $run->requested_limit,
                'batchSize' => $run->batch_size,
                'eligibleCount' => $run->eligible_count,
                'processedCount' => $run->processed_count,
                'createdCount' => $run->created_count,
                'existingCount' => $run->existing_count,
                'skippedCount' => $run->skipped_count,
                'failedCount' => $run->failed_count,
                'startedAt' => $run->started_at?->toISOString(),
                'completedAt' => $run->completed_at?->toISOString(),
                'createdAt' => $run->created_at?->toISOString(),
                'updatedAt' => $run->updated_at?->toISOString(),
                'failureMessage' => $run->failure_message,
            ],
        ];
    }

    private function healthStatus(int $staleRuns, int $recentFailedRuns, int $recentRunsWithErrors, float $failureRate): string
    {
        if ($staleRuns > 0 || $failureRate >= $this->criticalFailureRate()) {
            return 'critical';
        }

        if ($recentFailedRuns > 0 || $recentRunsWithErrors > 0 || $failureRate >= $this->warningFailureRate()) {
            return 'warning';
        }

        return 'healthy';
    }

    private function classifyFailureText(?string $text): string
    {
        $normalized = Str::of((string) $text)->lower()->trim()->toString();

        if ($normalized === '') {
            return 'unknown';
        }

        if (Str::contains($normalized, ['validation', 'required', 'invalid', 'must be'])) {
            return 'validation_error';
        }

        if (Str::contains($normalized, ['workflow definition was not found', 'definition was not found'])) {
            return 'definition_missing';
        }

        if (Str::contains($normalized, ['source was not found', 'source not found', 'record not found', 'model not found'])) {
            return 'source_missing';
        }

        if (Str::contains($normalized, ['forbidden', 'unauthorized', 'permission denied', 'not allowed'])) {
            return 'permission_error';
        }

        if (Str::contains($normalized, ['sql', 'database', 'queryexception', 'pdoexception', 'integrity constraint', 'deadlock'])) {
            return 'database_error';
        }

        if (Str::contains($normalized, ['exception', 'error'])) {
            return 'unexpected_error';
        }

        return 'unknown';
    }

    private function userSnapshot(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->username ?: $user->email,
            'roleCode' => $user->role_code,
        ];
    }

    private function averageOf(Collection $values): ?float
    {
        $filtered = $values->filter(fn ($value): bool => is_numeric($value))->values();

        if ($filtered->isEmpty()) {
            return null;
        }

        return round((float) $filtered->avg(), 2);
    }

    private function longestOf(Collection $values): ?int
    {
        $filtered = $values->filter(fn ($value): bool => is_numeric($value))->values();

        if ($filtered->isEmpty()) {
            return null;
        }

        return (int) $filtered->max();
    }

    private function oldestRunAt(Collection $runs): ?string
    {
        $timestamps = $runs
            ->map(fn (array $run): ?string => $run['startedAt'] ?? $run['createdAt'] ?? null)
            ->filter()
            ->values();

        if ($timestamps->isEmpty()) {
            return null;
        }

        return $timestamps->sort()->first();
    }

    private function labelFromSourceType(string $sourceType): ?string
    {
        $normalized = trim($sourceType);

        if ($normalized === '' || $normalized === 'unknown') {
            return null;
        }

        return Str::of($normalized)
            ->replace('preschool_', '')
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }

    private function recentActivityLimit(): int
    {
        return max((int) config('preschool.workflow_observability.recent_activity_limit', 5), 1);
    }

    private function slowestRunsLimit(): int
    {
        return max((int) config('preschool.workflow_observability.slowest_runs_limit', 5), 1);
    }

    private function stalePendingThresholdMs(): int
    {
        return max((int) config('preschool.workflow_observability.stale_pending_minutes', 60), 1) * 60 * 1000;
    }

    private function staleRunningThresholdMs(): int
    {
        return max((int) config('preschool.workflow_observability.stale_running_minutes', 30), 1) * 60 * 1000;
    }

    private function warningFailureRate(): float
    {
        return max((float) config('preschool.workflow_observability.warning_failure_rate', 10), 0);
    }

    private function criticalFailureRate(): float
    {
        return max((float) config('preschool.workflow_observability.critical_failure_rate', 25), 0);
    }

    private function recentWindow(Carbon $now): array
    {
        return [
            'from' => $now->copy()->subDays(max((int) config('preschool.workflow_observability.trend_days', 30), 1))->toDateString(),
            'to' => $now->toDateString(),
        ];
    }
}
