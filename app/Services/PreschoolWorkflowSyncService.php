<?php

namespace App\Services;

use App\Models\PreschoolAutomationTask;
use App\Models\PreschoolEnrollmentApplication;
use App\Models\PreschoolGuardianCommunication;
use App\Models\PreschoolHealthAlert;
use App\Models\PreschoolInvoice;
use App\Models\PreschoolWorkflowDefinition;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class PreschoolWorkflowSyncService
{
    private const TARGET_DEFINITIONS = [
        'enrollment_admission' => ['preschool_enrollment_application'],
        'health_alert_resolution' => ['preschool_health_alert'],
        'invoice_collection' => ['preschool_invoice'],
        'attendance_follow_up' => ['preschool_automation_task', 'preschool_guardian_communication'],
    ];

    public function __construct(
        private readonly PreschoolWorkflowService $workflowService,
        private readonly PreschoolWorkflowDefinitionService $definitionService,
        private readonly PreschoolWorkflowSourceLinkService $sourceLinkService,
    ) {
    }

    public function preview(array $filters, User $actor): array
    {
        return $this->execute($filters, $actor, true);
    }

    public function sync(array $filters, User $actor): array
    {
        return $this->execute($filters, $actor, false);
    }

    public function discoverCandidates(array $filters): Collection
    {
        return $this->collectCandidates($filters);
    }

    public function syncSource(string $definitionKey, string $sourceType, mixed $sourceId, User $actor): array
    {
        $candidate = $this->buildSingleCandidate($definitionKey, $sourceType, $sourceId);

        if ($candidate === null) {
            return $this->resultItem($definitionKey, $sourceType, $sourceId, null, 'skipped', 'Unsupported source type.', null, null, null, null);
        }

        return $this->evaluateCandidate($candidate, $actor, false);
    }

    private function execute(array $filters, User $actor, bool $dryRun): array
    {
        $candidates = $this->discoverCandidates($filters);
        $limit = $this->resolveLimit($filters);
        $selected = $candidates->take($limit);

        $items = [];
        $summary = [
            'eligible' => 0,
            'created' => 0,
            'existing' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($selected as $candidate) {
            $result = $this->evaluateCandidate($candidate, $actor, $dryRun);
            $summary[$result['status']]++;
            $summary['eligible']++;
            $items[] = $result;
        }

        return [
            'dryRun' => $dryRun,
            'limit' => $limit,
            'batchSize' => $this->resolveBatchSize($filters),
            'summary' => $summary,
            'items' => $items,
            'generatedAt' => now()->toISOString(),
        ];
    }

    private function evaluateCandidate(array $candidate, User $actor, bool $dryRun): array
    {
        $definition = $this->definitionService->findByKey($candidate['definitionKey']);

        if (! $definition || ! $definition->is_active) {
            return $this->resultItem(
                $candidate['definitionKey'],
                $candidate['sourceType'],
                $candidate['sourceId'],
                $candidate['sourceLabel'],
                'skipped',
                'Workflow definition was not found.',
                null,
                $candidate['sourceStatus'],
                $candidate['sourceRouteName'] ?? null,
                $candidate['sourceRouteParams'] ?? null,
            );
        }

        $eligibility = $this->evaluateEligibility($candidate);
        if (! $eligibility['eligible']) {
            return $this->resultItem(
                $candidate['definitionKey'],
                $candidate['sourceType'],
                $candidate['sourceId'],
                $candidate['sourceLabel'],
                'skipped',
                $eligibility['reason'],
                null,
                $candidate['sourceStatus'],
                $candidate['sourceRouteName'] ?? null,
                $candidate['sourceRouteParams'] ?? null,
            );
        }

        $existing = $this->workflowService->findExistingForSource($candidate['definitionKey'], $candidate['sourceType'], $candidate['sourceId']);
        if ($existing) {
            return $this->resultItem(
                $candidate['definitionKey'],
                $candidate['sourceType'],
                $candidate['sourceId'],
                $candidate['sourceLabel'],
                'existing',
                'Workflow already exists.',
                $existing->id,
                $candidate['sourceStatus'],
                $candidate['sourceRouteName'] ?? null,
                $candidate['sourceRouteParams'] ?? null,
            );
        }

        if ($dryRun) {
            return $this->resultItem(
                $candidate['definitionKey'],
                $candidate['sourceType'],
                $candidate['sourceId'],
                $candidate['sourceLabel'],
                'created',
                'Workflow would be created.',
                null,
                $candidate['sourceStatus'],
                $candidate['sourceRouteName'] ?? null,
                $candidate['sourceRouteParams'] ?? null,
            );
        }

        try {
            $workflow = $this->workflowService->startForSource(
                $candidate['definitionKey'],
                $candidate['sourceType'],
                $candidate['sourceId'],
                [
                    'source_label' => $candidate['sourceLabel'],
                    'metadata' => [
                        'syncMode' => 'admin_backfill',
                        'sourceStatus' => $candidate['sourceStatus'],
                        'sourceType' => $candidate['sourceType'],
                        'sourceId' => $candidate['sourceId'],
                        'definitionKey' => $candidate['definitionKey'],
                    ],
                ],
                $actor,
            );
        } catch (Throwable $exception) {
            Log::warning('Preschool workflow sync failed for candidate.', [
                'definitionKey' => $candidate['definitionKey'],
                'sourceType' => $candidate['sourceType'],
                'sourceId' => $candidate['sourceId'],
                'exception' => $exception::class,
            ]);

            return $this->resultItem(
                $candidate['definitionKey'],
                $candidate['sourceType'],
                $candidate['sourceId'],
                $candidate['sourceLabel'],
                'failed',
                $exception->getMessage(),
                null,
                $candidate['sourceStatus'],
                $candidate['sourceRouteName'] ?? null,
                $candidate['sourceRouteParams'] ?? null,
            );
        }

        if (! $workflow) {
            return $this->resultItem(
                $candidate['definitionKey'],
                $candidate['sourceType'],
                $candidate['sourceId'],
                $candidate['sourceLabel'],
                'failed',
                'Workflow creation returned no instance.',
                null,
                $candidate['sourceStatus'],
                $candidate['sourceRouteName'] ?? null,
                $candidate['sourceRouteParams'] ?? null,
            );
        }

        return $this->resultItem(
            $candidate['definitionKey'],
            $candidate['sourceType'],
            $candidate['sourceId'],
            $candidate['sourceLabel'],
            'created',
            'Workflow created successfully.',
            $workflow->id,
            $candidate['sourceStatus'],
            $candidate['sourceRouteName'] ?? null,
            $candidate['sourceRouteParams'] ?? null,
        );
    }

    private function evaluateEligibility(array $candidate): array
    {
        return match ($candidate['definitionKey']) {
            'enrollment_admission' => $this->eligibilityFromList($candidate['sourceStatus'], PreschoolEnrollmentApplication::STATUSES, true),
            'health_alert_resolution' => $this->eligibilityFromList($candidate['sourceStatus'], ['new', 'acknowledged', 'in_progress', 'resolved', 'closed']),
            'invoice_collection' => $this->eligibilityFromList($candidate['sourceStatus'], ['issued', 'partial', 'paid', 'overdue', 'cancelled'], false, 'Draft invoices are excluded from this sync.'),
            'attendance_follow_up' => ['eligible' => true, 'reason' => null],
            default => ['eligible' => false, 'reason' => 'Unsupported workflow definition.'],
        };
    }

    private function eligibilityFromList(?string $status, array $allowedStatuses, bool $excludeDraft = false, ?string $customReason = null): array
    {
        $normalized = strtolower(trim((string) ($status ?? '')));

        if ($excludeDraft && $normalized === 'draft') {
            return ['eligible' => false, 'reason' => $customReason ?? 'Draft records are excluded from this sync.'];
        }

        if ($normalized === '' || in_array($normalized, $allowedStatuses, true)) {
            return ['eligible' => true, 'reason' => null];
        }

        return ['eligible' => false, 'reason' => 'Source status is not eligible for backfill.'];
    }

    private function collectCandidates(array $filters): Collection
    {
        $definitionKey = $this->normalizeFilterString($filters['definition_key'] ?? null);
        $sourceType = $this->normalizeFilterString($filters['source_type'] ?? null);

        $candidates = collect();

        if ($this->matchesTarget($definitionKey, $sourceType, 'enrollment_admission', 'preschool_enrollment_application')) {
            $candidates = $candidates->merge($this->collectEnrollmentCandidates($filters));
        }

        if ($this->matchesTarget($definitionKey, $sourceType, 'health_alert_resolution', 'preschool_health_alert')) {
            $candidates = $candidates->merge($this->collectHealthCandidates($filters));
        }

        if ($this->matchesTarget($definitionKey, $sourceType, 'invoice_collection', 'preschool_invoice')) {
            $candidates = $candidates->merge($this->collectInvoiceCandidates($filters));
        }

        if ($this->matchesTarget($definitionKey, $sourceType, 'attendance_follow_up', 'preschool_automation_task')) {
            $candidates = $candidates->merge($this->collectAutomationTaskCandidates($filters));
        }

        if ($this->matchesTarget($definitionKey, $sourceType, 'attendance_follow_up', 'preschool_guardian_communication')) {
            $candidates = $candidates->merge($this->collectAttendanceCommunicationCandidates($filters));
        }

        return $candidates
            ->values()
            ->sortBy(function (array $candidate): string {
                return implode('|', [
                    $candidate['createdAt'] ?? '',
                    $candidate['sourceType'] ?? '',
                    $candidate['sourceId'] ?? '',
                ]);
            })
            ->values();
    }

    private function collectEnrollmentCandidates(array $filters): Collection
    {
        $query = PreschoolEnrollmentApplication::query()->select(['id', 'application_code', 'status', 'created_at', 'updated_at']);
        $this->applyStatusFilter($query, $filters, 'status');
        $this->applyDateFilters($query, $filters);

        return $query->get()->map(function (PreschoolEnrollmentApplication $application): array {
            return $this->buildCandidate(
                'enrollment_admission',
                'preschool_enrollment_application',
                (string) $application->id,
                $application->application_code ?: 'Enrollment application '.$application->id,
                (string) $application->status,
                $application->created_at?->toISOString(),
                $application->updated_at?->toISOString(),
                'dashboard-preschool-admin-enrollments',
                [],
            );
        });
    }

    private function collectHealthCandidates(array $filters): Collection
    {
        $query = PreschoolHealthAlert::query()->select(['id', 'title', 'status', 'created_at', 'updated_at']);
        $this->applyStatusFilter($query, $filters, 'status');
        $this->applyDateFilters($query, $filters);

        return $query->get()->map(function (PreschoolHealthAlert $alert): array {
            $source = $this->sourceLinkService->resolveSource('preschool_health_alert', (string) $alert->id, $alert->title);

            return $this->buildCandidate(
                'health_alert_resolution',
                'preschool_health_alert',
                (string) $alert->id,
                $source['sourceLabel'] ?? $alert->title ?? ('Health alert '.$alert->id),
                (string) $alert->status,
                $alert->created_at?->toISOString(),
                $alert->updated_at?->toISOString(),
                $source['sourceRouteName'] ?? null,
                $source['sourceRouteParams'] ?? [],
            );
        });
    }

    private function collectInvoiceCandidates(array $filters): Collection
    {
        $query = PreschoolInvoice::query()->select(['id', 'invoice_number', 'status', 'created_at', 'updated_at']);
        $this->applyStatusFilter($query, $filters, 'status');
        $this->applyDateFilters($query, $filters);

        return $query->get()->map(function (PreschoolInvoice $invoice): array {
            $source = $this->sourceLinkService->resolveSource('preschool_invoice', (string) $invoice->id, $invoice->invoice_number);

            return $this->buildCandidate(
                'invoice_collection',
                'preschool_invoice',
                (string) $invoice->id,
                $source['sourceLabel'] ?? $invoice->invoice_number ?? ('Invoice '.$invoice->id),
                (string) $invoice->status,
                $invoice->created_at?->toISOString(),
                $invoice->updated_at?->toISOString(),
                $source['sourceRouteName'] ?? null,
                $source['sourceRouteParams'] ?? [],
            );
        });
    }

    private function collectAutomationTaskCandidates(array $filters): Collection
    {
        $query = PreschoolAutomationTask::query()
            ->select(['id', 'task_type', 'title', 'status', 'created_at', 'updated_at'])
            ->where('task_type', 'attendance.follow_up');

        $this->applyStatusFilter($query, $filters, 'status');
        $this->applyDateFilters($query, $filters);

        return $query->get()->map(function (PreschoolAutomationTask $task): array {
            $source = $this->sourceLinkService->resolveSource('preschool_automation_task', (string) $task->id, $task->title);

            return $this->buildCandidate(
                'attendance_follow_up',
                'preschool_automation_task',
                (string) $task->id,
                $source['sourceLabel'] ?? $task->title ?? ('Automation task '.$task->id),
                (string) $task->status,
                $task->created_at?->toISOString(),
                $task->updated_at?->toISOString(),
                $source['sourceRouteName'] ?? null,
                $source['sourceRouteParams'] ?? [],
            );
        });
    }

    private function collectAttendanceCommunicationCandidates(array $filters): Collection
    {
        $query = PreschoolGuardianCommunication::query()
            ->select(['id', 'subject', 'status', 'created_at', 'updated_at', 'communication_type'])
            ->where('source_type', 'attendance')
            ->where('communication_type', 'repeated_absence');

        $this->applyStatusFilter($query, $filters, 'status');
        $this->applyDateFilters($query, $filters);

        return $query->get()->map(function (PreschoolGuardianCommunication $communication): array {
            $source = $this->sourceLinkService->resolveSource('preschool_guardian_communication', (string) $communication->id, $communication->subject);

            return $this->buildCandidate(
                'attendance_follow_up',
                'preschool_guardian_communication',
                (string) $communication->id,
                $source['sourceLabel'] ?? $communication->subject ?? ('Guardian communication '.$communication->id),
                (string) $communication->status,
                $communication->created_at?->toISOString(),
                $communication->updated_at?->toISOString(),
                $source['sourceRouteName'] ?? null,
                $source['sourceRouteParams'] ?? [],
            );
        });
    }

    private function buildSingleCandidate(string $definitionKey, string $sourceType, mixed $sourceId): ?array
    {
        $definitionKey = trim($definitionKey);
        $normalizedSourceType = $this->normalizeSourceType($sourceType);
        $resolvedSourceId = $this->normalizeFilterString($sourceId);

        if ($definitionKey === '' || $resolvedSourceId === null) {
            return null;
        }

        return match ($definitionKey) {
            'enrollment_admission' => $this->buildModelCandidate(
                'enrollment_admission',
                'preschool_enrollment_application',
                PreschoolEnrollmentApplication::query()->find($resolvedSourceId),
                fn (PreschoolEnrollmentApplication $model): string => $model->application_code ?: 'Enrollment application '.$model->id,
            ),
            'health_alert_resolution' => $this->buildModelCandidate(
                'health_alert_resolution',
                'preschool_health_alert',
                PreschoolHealthAlert::query()->find($resolvedSourceId),
                fn (PreschoolHealthAlert $model): string => $model->title ?: 'Health alert '.$model->id,
            ),
            'invoice_collection' => $this->buildModelCandidate(
                'invoice_collection',
                'preschool_invoice',
                PreschoolInvoice::query()->find($resolvedSourceId),
                fn (PreschoolInvoice $model): string => $model->invoice_number ?: 'Invoice '.$model->id,
            ),
            'attendance_follow_up' => match ($normalizedSourceType) {
                'preschool_automation_task' => $this->buildModelCandidate(
                    'attendance_follow_up',
                    'preschool_automation_task',
                    PreschoolAutomationTask::query()->find($resolvedSourceId),
                    fn (PreschoolAutomationTask $model): string => $model->title ?: 'Automation task '.$model->id,
                ),
                'preschool_guardian_communication' => $this->buildModelCandidate(
                    'attendance_follow_up',
                    'preschool_guardian_communication',
                    PreschoolGuardianCommunication::query()->where('source_type', 'attendance')->where('communication_type', 'repeated_absence')->find($resolvedSourceId),
                    fn (PreschoolGuardianCommunication $model): string => $model->subject ?: 'Guardian communication '.$model->id,
                ),
                default => null,
            },
            default => null,
        };
    }

    private function buildModelCandidate(string $definitionKey, string $sourceType, mixed $model, callable $labelResolver): ?array
    {
        if (! $model) {
            return null;
        }

        $source = $this->sourceLinkService->resolveSource($sourceType, (string) $model->getKey(), $labelResolver($model));

        return $this->buildCandidate(
            $definitionKey,
            $sourceType,
            (string) $model->getKey(),
            $source['sourceLabel'] ?? $labelResolver($model),
            (string) ($model->status ?? ''),
            $model->created_at?->toISOString(),
            $model->updated_at?->toISOString(),
            $source['sourceRouteName'] ?? null,
            $source['sourceRouteParams'] ?? [],
        );
    }

    private function buildCandidate(
        string $definitionKey,
        string $sourceType,
        string $sourceId,
        ?string $sourceLabel,
        string $sourceStatus,
        ?string $createdAt,
        ?string $updatedAt,
        ?string $sourceRouteName,
        array $sourceRouteParams,
    ): array {
        return [
            'definitionKey' => $definitionKey,
            'sourceType' => $sourceType,
            'sourceId' => $sourceId,
            'sourceLabel' => $sourceLabel ?: $sourceId,
            'sourceStatus' => $sourceStatus,
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt,
            'sourceRouteName' => $sourceRouteName,
            'sourceRouteParams' => $sourceRouteParams,
        ];
    }

    private function resultItem(
        string $definitionKey,
        string $sourceType,
        mixed $sourceId,
        ?string $sourceLabel,
        string $status,
        ?string $reason,
        int|string|null $workflowInstanceId,
        ?string $sourceStatus,
        ?string $sourceRouteName,
        ?array $sourceRouteParams,
    ): array {
        return [
            'definitionKey' => $definitionKey,
            'sourceType' => $sourceType,
            'sourceId' => $sourceId === null ? null : (string) $sourceId,
            'sourceLabel' => $sourceLabel,
            'sourceStatus' => $sourceStatus,
            'sourceRouteName' => $sourceRouteName,
            'sourceRouteParams' => $sourceRouteParams ?? [],
            'status' => $status,
            'reason' => $reason,
            'workflowInstanceId' => $workflowInstanceId,
        ];
    }

    private function applyStatusFilter(Builder $query, array $filters, string $column): void
    {
        $status = $this->normalizeFilterString($filters['status'] ?? null);

        if ($status !== null && $status !== 'all') {
            $query->where($column, $status);
        }
    }

    private function applyDateFilters(Builder $query, array $filters): void
    {
        if (($dateFrom = $this->normalizeFilterString($filters['date_from'] ?? null)) !== null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if (($dateTo = $this->normalizeFilterString($filters['date_to'] ?? null)) !== null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
    }

    private function matchesTarget(?string $definitionKey, ?string $sourceType, string $targetDefinition, string $targetSourceType): bool
    {
        if ($definitionKey !== null && $definitionKey !== '' && $definitionKey !== $targetDefinition) {
            return false;
        }

        if ($sourceType !== null && $sourceType !== '' && $sourceType !== $targetSourceType) {
            return false;
        }

        return true;
    }

    private function resolveLimit(array $filters): int
    {
        return min(max((int) ($filters['limit'] ?? 50), 1), 500);
    }

    private function resolveBatchSize(array $filters): int
    {
        return min(max((int) ($filters['batch_size'] ?? 25), 1), 100);
    }

    private function normalizeFilterString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeSourceType(string $sourceType): string
    {
        $sourceType = strtolower(trim($sourceType));

        if ($sourceType === '') {
            return '';
        }

        return preg_replace('/^preschool_/', 'preschool_', $sourceType) ?: $sourceType;
    }
}
