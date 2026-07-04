<?php

return [
    'workflow_observability' => [
        'stale_pending_minutes' => (int) env('PRESCHOOL_WORKFLOW_OBSERVABILITY_STALE_PENDING_MINUTES', 60),
        'stale_running_minutes' => (int) env('PRESCHOOL_WORKFLOW_OBSERVABILITY_STALE_RUNNING_MINUTES', 30),
        'recent_failure_hours' => (int) env('PRESCHOOL_WORKFLOW_OBSERVABILITY_RECENT_FAILURE_HOURS', 24),
        'recent_activity_limit' => (int) env('PRESCHOOL_WORKFLOW_OBSERVABILITY_RECENT_ACTIVITY_LIMIT', 5),
        'slowest_runs_limit' => (int) env('PRESCHOOL_WORKFLOW_OBSERVABILITY_SLOWEST_RUNS_LIMIT', 5),
        'trend_days' => (int) env('PRESCHOOL_WORKFLOW_OBSERVABILITY_TREND_DAYS', 30),
        'warning_failure_rate' => (float) env('PRESCHOOL_WORKFLOW_OBSERVABILITY_WARNING_FAILURE_RATE', 10),
        'critical_failure_rate' => (float) env('PRESCHOOL_WORKFLOW_OBSERVABILITY_CRITICAL_FAILURE_RATE', 25),
    ],
];
