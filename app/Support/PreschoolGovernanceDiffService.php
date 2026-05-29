<?php

namespace App\Support;

use App\Models\PreschoolClass;
use App\Models\PreschoolLifecycleAuditLog;
use App\Models\PreschoolReportExportRecord;
use App\Models\PreschoolReportSnapshot;
use App\Models\PreschoolStudent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Governance diff analysis stays snapshot-first and read-only so admins can
 * compare institutional states without mutating historical Preschool records
 * or duplicating the reconstruction logic already used by governance review.
 */
class PreschoolGovernanceDiffService
{
    public function __construct(
        private readonly PreschoolInstitutionalReconstructionService $reconstructionService,
        private readonly PreschoolInstitutionalIntegrityService $integrityService,
        private readonly PreschoolSnapshotArchiveService $snapshotArchiveService,
        private readonly PreschoolExportGovernanceService $exportGovernanceService,
        private readonly PreschoolLifecycleAuditService $auditService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function summary(array $filters = []): array
    {
        $review = $this->integrityService->review($filters);

        return [
            'overview' => [
                'totalSnapshots' => (int) ($review['overview']['snapshotCount'] ?? 0),
                'totalExports' => (int) ($review['overview']['exportEvents'] ?? 0),
                'totalAudits' => (int) ($review['overview']['totalEvents'] ?? 0),
                'overrideApprovals' => (int) ($review['overview']['overrideApprovals'] ?? 0),
                'blockedWrites' => (int) ($review['overview']['blockedWrites'] ?? 0),
                'riskScore' => (int) ($review['riskScore'] ?? 0),
                'riskLevel' => $review['riskLevel'] ?? 'Low',
                'warnings' => count($review['warnings'] ?? []),
            ],
            'comparisonModes' => [
                ['value' => 'snapshot_vs_snapshot'],
                ['value' => 'reconstruction_vs_reconstruction'],
                ['value' => 'academic_year_vs_academic_year'],
                ['value' => 'term_vs_term'],
                ['value' => 'report_period_vs_report_period'],
                ['value' => 'class_vs_class'],
                ['value' => 'student_progression'],
                ['value' => 'report_export_vs_report_export'],
                ['value' => 'snapshot_version_vs_version'],
            ],
            'severityBands' => [
                ['value' => 'LOW'],
                ['value' => 'MEDIUM'],
                ['value' => 'HIGH'],
                ['value' => 'CRITICAL'],
            ],
            'reviewActions' => [
                ['value' => 'marked_reviewed'],
                ['value' => 'flagged'],
                ['value' => 'escalated'],
                ['value' => 'resolved'],
            ],
            'retentionReview' => $review['retentionReview'] ?? [],
            'filters' => $filters,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function compare(array $filters = []): array
    {
        $leftResolved = $this->resolveContext([
            'context_type' => Arr::get($filters, 'left_context_type', 'reconstruction'),
            'snapshot_id' => Arr::get($filters, 'left_snapshot_id'),
            'export_record_id' => Arr::get($filters, 'left_export_record_id'),
            'academic_year_id' => Arr::get($filters, 'left_academic_year_id'),
            'term_id' => Arr::get($filters, 'left_term_id'),
            'report_period_id' => Arr::get($filters, 'left_report_period_id'),
            'class_id' => Arr::get($filters, 'left_class_id'),
            'student_id' => Arr::get($filters, 'left_student_id'),
            'search' => Arr::get($filters, 'left_search'),
        ], []);

        $rightResolved = $this->resolveContext([
            'context_type' => Arr::get($filters, 'right_context_type', 'reconstruction'),
            'snapshot_id' => Arr::get($filters, 'right_snapshot_id'),
            'export_record_id' => Arr::get($filters, 'right_export_record_id'),
            'academic_year_id' => Arr::get($filters, 'right_academic_year_id'),
            'term_id' => Arr::get($filters, 'right_term_id'),
            'report_period_id' => Arr::get($filters, 'right_report_period_id'),
            'class_id' => Arr::get($filters, 'right_class_id'),
            'student_id' => Arr::get($filters, 'right_student_id'),
            'search' => Arr::get($filters, 'right_search'),
        ], []);

        $rows = $this->comparisonRows($leftResolved, $rightResolved);
        $integrity = $this->integrityService->compare($leftResolved, $rightResolved, $rows, $filters);
        $reviewKey = (string) ($integrity['reviewKey'] ?? $this->comparisonReviewKey($leftResolved, $rightResolved, $filters));

        return [
            'comparisonMode' => $this->comparisonMode($leftResolved, $rightResolved, $filters),
            'reviewKey' => $reviewKey,
            'left' => $leftResolved,
            'right' => $rightResolved,
            'summary' => $this->comparisonSummary($rows, $integrity, $leftResolved, $rightResolved),
            'rows' => $rows,
            'warnings' => $integrity['warnings'] ?? [],
            'mismatches' => $integrity['mismatches'] ?? [],
            'integrityWarnings' => $integrity['integrityWarnings'] ?? [],
            'riskScore' => (int) ($integrity['riskScore'] ?? 0),
            'riskLevel' => $integrity['riskLevel'] ?? 'Low',
            'timeline' => $this->mergeTimeline($leftResolved['timeline'] ?? [], $rightResolved['timeline'] ?? []),
            'auditEvidence' => $integrity['auditEvidence'] ?? [],
            'reviewStatus' => $integrity['reviewStatus'] ?? 'open',
            'reviewTrail' => $integrity['reviewTrail'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>|string  $context
     * @param  array<string, mixed>  $filters
     */
    public function resolveContext(array|string $context, array $filters = []): array
    {
        $context = is_string($context) ? $this->parseContextString($context) : $context;
        $contextType = strtolower((string) Arr::get($context, 'context_type', Arr::get($context, 'type', 'reconstruction')));

        return match ($contextType) {
            'snapshot' => $this->resolveSnapshotContext($context, $filters),
            'export', 'report_export' => $this->resolveExportContext($context, $filters),
            'diff', 'governance-diff', 'system', 'integrity_review', 'institutional_integrity_review' => $this->resolveSyntheticReviewContext($context, $filters),
            'academic-year', 'academic_year', 'term', 'report-period', 'report_period', 'class', 'student' => $this->resolveReconstructionContext($context, $filters, $contextType),
            default => $this->resolveReconstructionContext($context, $filters),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function integrityReview(array $filters = []): array
    {
        return $this->integrityService->review($filters);
    }

    /**
     * @param  array<string, mixed>|string  $context
     * @param  array<string, mixed>  $filters
     */
    public function integrityContext(array|string $context, array $filters = []): array
    {
        $resolved = $this->resolveContext($context, $filters);

        return $this->integrityService->contextFromResolved($resolved, $filters);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function parseContextString(string $context): array
    {
        $context = trim($context);

        if ($context === '') {
            return ['context_type' => 'reconstruction'];
        }

        [$type, $id] = array_pad(explode(':', $context, 2), 2, null);

        return [
            'context_type' => $type ?: 'reconstruction',
            'context_id' => $id,
            'review_key' => $id ? $type.':'.$id : $context,
            'snapshot_id' => is_numeric($id) ? (int) $id : null,
            'export_record_id' => is_numeric($id) ? (int) $id : null,
            'academic_year_id' => is_numeric($id) ? (int) $id : null,
            'term_id' => is_numeric($id) ? (int) $id : null,
            'report_period_id' => is_numeric($id) ? (int) $id : null,
            'class_id' => is_numeric($id) ? (int) $id : null,
            'student_id' => is_numeric($id) ? (int) $id : null,
            'review_key' => $context,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $filters
     */
    private function resolveSnapshotContext(array $context, array $filters = []): array
    {
        $snapshotId = (int) Arr::get($context, 'snapshot_id', Arr::get($context, 'snapshotId', 0));
        $snapshot = PreschoolReportSnapshot::query()
            ->with(['student', 'preschoolClass', 'academicYear', 'term', 'reportPeriod', 'generatedBy'])
            ->find($snapshotId);

        if (! $snapshot) {
            return $this->emptyContext('snapshot', 'Snapshot #'.$snapshotId, $filters);
        }

        $detail = $this->snapshotArchiveService->detail($snapshot);
        $preview = $detail['snapshot'] ?? $this->snapshotArchiveService->preview($snapshot);
        $auditTrail = $detail['auditTrail'] ?? [];
        $timeline = $this->auditTimeline($auditTrail, 'audit');

        return [
            'contextType' => 'snapshot',
            'contextKey' => 'snapshot:'.$snapshot->id,
            'reviewKey' => 'snapshot:'.$snapshot->id,
            'label' => $preview['contextLabel'] ?: 'Snapshot #'.$snapshot->id,
            'context' => [
                'snapshotId' => $snapshot->id,
                'snapshotType' => $snapshot->snapshot_type,
            ],
            'summary' => $this->summaryFromSnapshotPreviews([$preview]),
            'snapshots' => [$preview],
            'timeline' => $timeline ?: $this->timelineFromSnapshots([$preview]),
            'references' => [
                'snapshotIds' => [$snapshot->id],
                'exportIds' => [],
                'auditIds' => collect($auditTrail)->pluck('id')->filter()->values()->all(),
                'assignmentIds' => [],
            ],
            'raw' => $detail,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $filters
     */
    private function resolveExportContext(array $context, array $filters = []): array
    {
        $exportRecordId = (int) Arr::get($context, 'export_record_id', Arr::get($context, 'exportRecordId', 0));
        $record = PreschoolReportExportRecord::query()->with(['actor', 'academicYear', 'term', 'reportPeriod'])->find($exportRecordId);

        if (! $record) {
            return $this->emptyContext('export', 'Export #'.$exportRecordId, $filters);
        }

        $detail = $this->exportGovernanceService->detail($record);
        $recordPreview = $this->exportGovernanceService->previewRecord($record);
        $snapshots = $detail['includedSnapshots'] ?? [];
        $auditTrail = $detail['auditTrail'] ?? [];
        $timeline = $this->auditTimeline($auditTrail, 'audit');

        return [
            'contextType' => 'export',
            'contextKey' => 'export:'.$record->id,
            'reviewKey' => 'export:'.$record->id,
            'label' => $recordPreview['contextLabel'] ?: ($recordPreview['fileName'] ?: 'Export #'.$record->id),
            'context' => [
                'exportRecordId' => $record->id,
                'exportType' => $record->export_type,
                'exportFormat' => $record->export_format,
            ],
            'summary' => $this->summaryFromSnapshotPreviews($snapshots, [
                'recordCount' => $recordPreview['recordCount'] ?? 0,
                'exportCount' => 1,
                'auditCount' => count($auditTrail),
            ]),
            'snapshots' => $snapshots,
            'timeline' => $timeline,
            'references' => [
                'snapshotIds' => $detail['includedSnapshotIds'] ?? [],
                'exportIds' => [$record->id],
                'auditIds' => collect($auditTrail)->pluck('id')->filter()->values()->all(),
                'assignmentIds' => [],
            ],
            'raw' => $detail,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $filters
     */
    private function resolveSyntheticReviewContext(array $context, array $filters = []): array
    {
        $contextType = str_replace('-', '_', strtolower((string) Arr::get($context, 'context_type', 'diff')));
        $reviewKey = (string) Arr::get($context, 'review_key', Arr::get($context, 'context_id', $contextType));

        return [
            'contextType' => $contextType,
            'contextKey' => $contextType.':'.$reviewKey,
            'reviewKey' => $reviewKey,
            'label' => ucfirst(str_replace('_', ' ', $contextType)).' review',
            'context' => $filters,
            'summary' => $this->summaryFromSnapshotPreviews([]),
            'snapshots' => [],
            'timeline' => [],
            'references' => [
                'snapshotIds' => [],
                'exportIds' => [],
                'auditIds' => [],
                'assignmentIds' => [],
            ],
            'raw' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $filters
     */
    private function resolveReconstructionContext(array $context, array $filters = [], string $contextType = 'reconstruction'): array
    {
        $reconstructionFilters = array_filter([
            'academic_year_id' => Arr::get($context, 'academic_year_id', Arr::get($context, 'academicYearId')),
            'term_id' => Arr::get($context, 'term_id', Arr::get($context, 'termId')),
            'report_period_id' => Arr::get($context, 'report_period_id', Arr::get($context, 'reportPeriodId')),
            'class_id' => Arr::get($context, 'class_id', Arr::get($context, 'classId')),
            'student_id' => Arr::get($context, 'student_id', Arr::get($context, 'studentId')),
            'snapshot_type' => Arr::get($context, 'snapshot_type', Arr::get($context, 'snapshotType')),
            'lifecycle_state' => Arr::get($context, 'lifecycle_state', Arr::get($context, 'lifecycleState')),
            'source' => Arr::get($context, 'source'),
            'search' => Arr::get($context, 'search'),
        ], static fn ($value) => $value !== null && $value !== '');

        $reconstruction = $this->reconstructionService->reconstruct($reconstructionFilters);
        $review = $this->reconstructionService->review($reconstructionFilters);
        $timeline = array_merge($reconstruction['timeline'] ?? [], $review['timeline'] ?? []);
        $previewSnapshots = $reconstruction['historicalState']['snapshots'] ?? [];

        return [
            'contextType' => str_replace('-', '_', $contextType),
            'contextKey' => 'reconstruction:'.sha1(json_encode($reconstructionFilters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''),
            'reviewKey' => 'reconstruction:'.sha1(json_encode($reconstructionFilters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''),
            'label' => $this->contextLabel($reconstructionFilters, $review),
            'context' => $reconstructionFilters,
            'summary' => $this->summaryFromReconstruction($reconstruction, $review),
            'snapshots' => $previewSnapshots,
            'timeline' => $timeline,
            'retentionReview' => $review['retentionReview'] ?? [],
            'references' => [
                'snapshotIds' => $reconstruction['references']['snapshotIds'] ?? [],
                'exportIds' => $reconstruction['references']['exportIds'] ?? [],
                'auditIds' => $reconstruction['references']['auditIds'] ?? [],
                'assignmentIds' => $reconstruction['references']['assignmentIds'] ?? [],
            ],
            'raw' => [
                'reconstruction' => $reconstruction,
                'review' => $review,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $left
     * @param  array<int, array<string, mixed>>  $right
     * @return array<int, array<string, mixed>>
     */
    private function comparisonRows(array $left, array $right): array
    {
        $metrics = [
            ['section' => 'Governance', 'entity' => 'Audits', 'field' => 'auditCount', 'label' => 'Audit Count', 'severity' => 'CRITICAL', 'impact' => 'Historical accountability trail'],
            ['section' => 'Governance', 'entity' => 'Overrides', 'field' => 'overrideCount', 'label' => 'Override Count', 'severity' => 'CRITICAL', 'impact' => 'Override usage and governance pressure'],
            ['section' => 'Governance', 'entity' => 'Blocked Writes', 'field' => 'blockedWriteCount', 'label' => 'Blocked Write Count', 'severity' => 'HIGH', 'impact' => 'Lifecycle guard pressure'],
            ['section' => 'Historical', 'entity' => 'Snapshots', 'field' => 'snapshotCount', 'label' => 'Snapshot Count', 'severity' => 'CRITICAL', 'impact' => 'Frozen report history'],
            ['section' => 'Historical', 'entity' => 'Exports', 'field' => 'exportCount', 'label' => 'Export Count', 'severity' => 'CRITICAL', 'impact' => 'Institutional export history'],
            ['section' => 'Academic', 'entity' => 'Academic Years', 'field' => 'academicYearCount', 'label' => 'Academic Year Count', 'severity' => 'HIGH', 'impact' => 'Academic structure'],
            ['section' => 'Academic', 'entity' => 'Terms', 'field' => 'termCount', 'label' => 'Term Count', 'severity' => 'HIGH', 'impact' => 'Academic structure'],
            ['section' => 'Academic', 'entity' => 'Report Periods', 'field' => 'reportPeriodCount', 'label' => 'Report Period Count', 'severity' => 'CRITICAL', 'impact' => 'Frozen reporting windows'],
            ['section' => 'Enrollment', 'entity' => 'Classes', 'field' => 'classCount', 'label' => 'Class Count', 'severity' => 'HIGH', 'impact' => 'Class membership'],
            ['section' => 'Enrollment', 'entity' => 'Students', 'field' => 'studentCount', 'label' => 'Student Count', 'severity' => 'HIGH', 'impact' => 'Student membership'],
            ['section' => 'Enrollment', 'entity' => 'Assignments', 'field' => 'assignmentCount', 'label' => 'Assignment Count', 'severity' => 'MEDIUM', 'impact' => 'Student and teacher assignment lifecycle'],
            ['section' => 'Reporting', 'entity' => 'Assessments', 'field' => 'finalizedAssessments', 'label' => 'Finalized Assessments', 'severity' => 'HIGH', 'impact' => 'Report completeness'],
            ['section' => 'Reporting', 'entity' => 'Attendance', 'field' => 'attendanceCount', 'label' => 'Attendance Count', 'severity' => 'MEDIUM', 'impact' => 'Attendance history'],
            ['section' => 'Reporting', 'entity' => 'Attendance', 'field' => 'absentCount', 'label' => 'Absent Count', 'severity' => 'MEDIUM', 'impact' => 'Attendance history'],
            ['section' => 'Reporting', 'entity' => 'Progress', 'field' => 'averageScore', 'label' => 'Average Score', 'severity' => 'LOW', 'impact' => 'Progress trend'],
            ['section' => 'Reporting', 'entity' => 'Observations', 'field' => 'observationCount', 'label' => 'Observation Count', 'severity' => 'LOW', 'impact' => 'Behavioural/wellbeing notes'],
        ];

        $rows = [];

        foreach ($metrics as $metric) {
            $previous = $left['summary'][$metric['field']] ?? null;
            $current = $right['summary'][$metric['field']] ?? null;

            if ($previous === $current) {
                continue;
            }

            $differenceType = $this->differenceType($previous, $current, $metric['field']);
            $severity = $this->adjustSeverity($metric['severity'], $metric['field'], $differenceType);

            $rows[] = [
                'section' => $metric['section'],
                'entity' => $metric['entity'],
                'field' => $metric['label'],
                'previousValue' => $this->formatValue($previous),
                'currentValue' => $this->formatValue($current),
                'differenceType' => $differenceType,
                'severity' => $severity,
                'governanceImpact' => $metric['impact'],
                'reviewStatus' => $right['reviewStatus'] ?? $left['reviewStatus'] ?? 'open',
            ];
        }

        return collect($rows)
            ->sortByDesc(fn (array $row): int => $this->severityWeight($row['severity'] ?? 'LOW'))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function comparisonSummary(array $rows, array $integrity, array $left, array $right): array
    {
        $changed = collect($rows)->values();
        $sections = $changed->pluck('section')->unique()->values()->all();

        return [
            'totalFieldsChanged' => $changed->count(),
            'criticalChanges' => $changed->where('severity', 'CRITICAL')->count(),
            'governanceSensitiveChanges' => $changed->filter(fn (array $row): bool => in_array($row['severity'] ?? 'LOW', ['HIGH', 'CRITICAL'], true))->count(),
            'integrityWarnings' => count($integrity['warnings'] ?? []),
            'unchangedSections' => $this->unchangedSections($sections),
            'riskScore' => (int) ($integrity['riskScore'] ?? 0),
            'riskLevel' => $integrity['riskLevel'] ?? 'Low',
            'comparisonMode' => $this->comparisonMode($left, $right),
            'reviewKey' => $integrity['reviewKey'] ?? null,
            'reviewStatus' => $integrity['reviewStatus'] ?? 'open',
        ];
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function comparisonMode(array $left, array $right, array $filters = []): string
    {
        return sprintf('%s_vs_%s',
            (string) ($left['contextType'] ?? 'reconstruction'),
            (string) ($right['contextType'] ?? 'reconstruction'),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function contextLabel(array $filters, array $review = []): string
    {
        $parts = [];

        if (($filters['academic_year_id'] ?? null) !== null) {
            $parts[] = 'Academic year #'.$filters['academic_year_id'];
        }
        if (($filters['term_id'] ?? null) !== null) {
            $parts[] = 'Term #'.$filters['term_id'];
        }
        if (($filters['report_period_id'] ?? null) !== null) {
            $parts[] = 'Report period #'.$filters['report_period_id'];
        }
        if (($filters['class_id'] ?? null) !== null) {
            $parts[] = 'Class #'.$filters['class_id'];
        }
        if (($filters['student_id'] ?? null) !== null) {
            $parts[] = 'Student #'.$filters['student_id'];
        }

        if (! $parts) {
            return 'Institutional reconstruction';
        }

        return implode(' | ', $parts);
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshots
     * @param  array<string, mixed>  $overrides
     */
    private function summaryFromSnapshotPreviews(array $snapshots, array $overrides = []): array
    {
        $snapshotCollection = collect($snapshots);
        $attendance = $this->sumSnapshotMetric($snapshotCollection, 'attendanceSummary.attendanceCount');
        $present = $this->sumSnapshotMetric($snapshotCollection, 'attendanceSummary.presentCount');
        $late = $this->sumSnapshotMetric($snapshotCollection, 'attendanceSummary.lateCount');
        $absent = $this->sumSnapshotMetric($snapshotCollection, 'attendanceSummary.absentCount');
        $excused = $this->sumSnapshotMetric($snapshotCollection, 'attendanceSummary.excusedCount');
        $assessments = $this->sumSnapshotMetric($snapshotCollection, 'reportSummary.finalizedAssessments');
        $observations = $this->sumSnapshotMetric($snapshotCollection, 'reportSummary.observationCount');
        $students = $this->maxSnapshotMetric($snapshotCollection, 'progressSummary.studentCount');
        $averageScore = $this->averageSnapshotMetric($snapshotCollection, 'reportSummary.averageScore');

        return array_merge([
            'snapshotCount' => $snapshotCollection->count(),
            'reportPeriodCount' => $snapshotCollection->pluck('reportPeriodId')->filter()->unique()->count(),
            'academicYearCount' => $snapshotCollection->pluck('academicYearId')->filter()->unique()->count(),
            'termCount' => $snapshotCollection->pluck('termId')->filter()->unique()->count(),
            'classCount' => $snapshotCollection->pluck('classId')->filter()->unique()->count(),
            'studentCount' => $students,
            'assignmentCount' => 0,
            'teacherAssignmentCount' => 0,
            'auditCount' => 0,
            'exportCount' => 0,
            'overrideCount' => 0,
            'blockedWriteCount' => 0,
            'finalizedAssessments' => $assessments,
            'attendanceCount' => $attendance,
            'presentCount' => $present,
            'lateCount' => $late,
            'absentCount' => $absent,
            'excusedCount' => $excused,
            'observationCount' => $observations,
            'averageScore' => $averageScore,
            'sourceStatus' => $snapshotCollection->pluck('sourceStatus')->filter()->unique()->first() ?: 'snapshot',
            'lifecycleState' => $snapshotCollection->pluck('lifecycleState')->filter()->unique()->first() ?: 'finalized',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $reconstruction
     * @param  array<string, mixed>  $review
     */
    private function summaryFromReconstruction(array $reconstruction, array $review = []): array
    {
        $snapshots = collect($reconstruction['historicalState']['snapshots'] ?? []);
        $metrics = $this->summaryFromSnapshotPreviews($snapshots->all(), [
            'snapshotCount' => (int) ($reconstruction['summary']['snapshotCount'] ?? $snapshots->count()),
            'auditCount' => (int) ($reconstruction['summary']['auditCount'] ?? 0),
            'exportCount' => (int) ($reconstruction['summary']['exportCount'] ?? 0),
            'assignmentCount' => (int) ($reconstruction['summary']['assignmentCount'] ?? 0),
            'reportPeriodCount' => (int) ($reconstruction['summary']['reportPeriodCount'] ?? 0),
            'academicYearCount' => (int) ($reconstruction['summary']['academicYearCount'] ?? 0),
            'termCount' => (int) ($reconstruction['summary']['termCount'] ?? 0),
        ]);

        $metrics['auditCount'] = (int) ($review['overview']['totalAudits'] ?? $metrics['auditCount']);
        $metrics['overrideCount'] = (int) ($review['overview']['overrideApprovals'] ?? 0);
        $metrics['blockedWriteCount'] = (int) ($review['overview']['blockedWrites'] ?? 0);
        $metrics['exportCount'] = (int) ($reconstruction['summary']['exportCount'] ?? $metrics['exportCount']);
        $metrics['assignmentCount'] = (int) ($reconstruction['summary']['assignmentCount'] ?? $metrics['assignmentCount']);
        $metrics['reportPeriodCount'] = (int) ($reconstruction['summary']['reportPeriodCount'] ?? $metrics['reportPeriodCount']);
        $metrics['academicYearCount'] = (int) ($reconstruction['summary']['academicYearCount'] ?? $metrics['academicYearCount']);
        $metrics['termCount'] = (int) ($reconstruction['summary']['termCount'] ?? $metrics['termCount']);

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $resolvedContext
     */
    private function buildContextSummary(array $resolvedContext): array
    {
        return array_merge($resolvedContext['summary'] ?? [], [
            'reviewKey' => $resolvedContext['reviewKey'] ?? null,
            'reviewStatus' => $resolvedContext['reviewStatus'] ?? 'open',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function severityWeight(string $severity): int
    {
        return match (strtoupper($severity)) {
            'CRITICAL' => 4,
            'HIGH' => 3,
            'MEDIUM' => 2,
            default => 1,
        };
    }

    private function adjustSeverity(string $severity, string $field, string $differenceType): string
    {
        if ($differenceType === 'Archived' || in_array($field, ['snapshotCount', 'exportCount'], true)) {
            return strtoupper($severity);
        }

        return strtoupper($severity);
    }

    private function differenceType(mixed $previous, mixed $current, string $field): string
    {
        if ($previous === null || $previous === '') {
            return 'Added';
        }

        if ($current === null || $current === '') {
            return 'Removed';
        }

        if (in_array($field, ['lifecycleState', 'sourceStatus'], true)) {
            if ((string) $current === 'archived') {
                return 'Archived';
            }

            return 'Reclassified';
        }

        return 'Modified';
    }

    private function formatValue(mixed $value): mixed
    {
        if (is_float($value) || is_int($value)) {
            return round((float) $value, 2);
        }

        return $value;
    }

    private function unchangedSections(array $changedSections): int
    {
        $allSections = ['Governance', 'Historical', 'Academic', 'Enrollment', 'Reporting'];

        return count(array_diff($allSections, $changedSections));
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshots
     */
    private function timelineFromSnapshots(array $snapshots): array
    {
        return collect($snapshots)
            ->map(function (array $snapshot, int $index): array {
                return [
                    'id' => 'snapshot-'.$index,
                    'source' => 'snapshot',
                    'actionType' => 'report_snapshot.generated',
                    'title' => (string) ($snapshot['snapshotType'] ?? 'snapshot'),
                    'description' => trim(implode(' | ', array_filter([
                        $snapshot['lifecycleState'] ?? null,
                        'v'.($snapshot['snapshotVersion'] ?? 0),
                        $snapshot['contextLabel'] ?? null,
                    ]))),
                    'actor' => $snapshot['generatedBy'] ?? null,
                    'context' => [
                        'academicYearId' => $snapshot['academicYearId'] ?? null,
                        'termId' => $snapshot['termId'] ?? null,
                        'reportPeriodId' => $snapshot['reportPeriodId'] ?? null,
                    ],
                    'recordedAt' => $snapshot['generatedAt'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function mergeTimeline(array ...$sources): array
    {
        return collect($sources)
            ->flatten(1)
            ->map(function (array $event): array {
                $recordedAt = $event['recordedAt'] ?? $event['createdAt'] ?? $event['created_at'] ?? $event['exportedAt'] ?? $event['exported_at'] ?? null;
                if ($recordedAt !== null && ! isset($event['recordedAt'])) {
                    $event['recordedAt'] = $recordedAt;
                }

                return $event;
            })
            ->filter(fn (array $event): bool => ! empty($event['recordedAt'] ?? null))
            ->sortByDesc('recordedAt')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $auditTrail
     * @return array<int, array<string, mixed>>
     */
    private function auditTimeline(array $auditTrail, string $source = 'audit'): array
    {
        return collect($auditTrail)
            ->map(function (array $log) use ($source): array {
                return [
                    'id' => 'audit-'.($log['id'] ?? Str::uuid()->toString()),
                    'source' => $source,
                    'actionType' => $log['actionType'] ?? $log['action_type'] ?? 'audit.event',
                    'title' => $log['actionType'] ?? $log['action_type'] ?? 'audit.event',
                    'description' => trim(implode(' | ', array_filter([
                        $log['entityType'] ?? $log['entity_type'] ?? null,
                        isset($log['entityId']) || isset($log['entity_id']) ? '#'.(string) ($log['entityId'] ?? $log['entity_id']) : null,
                        $log['lockReason'] ?? $log['lock_reason'] ?? null,
                        $log['overrideReason'] ?? $log['override_reason'] ?? null,
                        $log['note'] ?? Arr::get($log, 'new_state.note'),
                    ]))),
                    'actor' => [
                        'id' => $log['actorUserId'] ?? $log['actor_user_id'] ?? null,
                        'roleCode' => $log['actorRole'] ?? $log['actor_role'] ?? null,
                        'displayName' => trim(implode(' ', array_filter([
                            Arr::get($log, 'actor.firstName'),
                            Arr::get($log, 'actor.lastName'),
                        ]))) ?: ($log['actorRole'] ?? $log['actor_role'] ?? null),
                    ],
                    'context' => [
                        'academicYearId' => $log['academicYearId'] ?? $log['academic_year_id'] ?? null,
                        'termId' => $log['termId'] ?? $log['term_id'] ?? null,
                        'reportPeriodId' => $log['reportPeriodId'] ?? $log['report_period_id'] ?? null,
                    ],
                    'recordedAt' => $log['createdAt'] ?? $log['created_at'] ?? null,
                ];
            })
            ->filter(fn (array $event): bool => ! empty($event['recordedAt'] ?? null))
            ->values()
            ->all();
    }

    private function emptyContext(string $type, string $label, array $filters = []): array
    {
        return [
            'contextType' => $type,
            'contextKey' => $type.':missing',
            'reviewKey' => $type.':missing',
            'label' => $label,
            'context' => $filters,
            'summary' => $this->summaryFromSnapshotPreviews([]),
            'snapshots' => [],
            'timeline' => [],
            'references' => [
                'snapshotIds' => [],
                'exportIds' => [],
                'auditIds' => [],
                'assignmentIds' => [],
            ],
            'raw' => null,
        ];
    }

    private function sumSnapshotMetric(Collection $snapshots, string $path): float
    {
        return (float) $snapshots->sum(function (array $snapshot) use ($path): float {
            $value = data_get($snapshot, $path);

            return is_numeric($value) ? (float) $value : 0.0;
        });
    }

    private function maxSnapshotMetric(Collection $snapshots, string $path): int
    {
        return (int) $snapshots->reduce(function (int $carry, array $snapshot) use ($path): int {
            $value = data_get($snapshot, $path);

            return max($carry, is_numeric($value) ? (int) $value : 0);
        }, 0);
    }

    private function averageSnapshotMetric(Collection $snapshots, string $path): ?float
    {
        $values = $snapshots
            ->map(fn (array $snapshot) => data_get($snapshot, $path))
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (float) $value);

        return $values->count() ? round((float) $values->avg(), 2) : null;
    }
}
