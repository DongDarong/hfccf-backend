<?php

namespace App\Support;

use App\Models\PreschoolLifecycleAuditLog;
use Illuminate\Http\Request;
use Throwable;

/**
 * Preschool lifecycle audit logging stays separate from the generic audit log
 * table so lock/override events can carry term/report-period context without
 * overloading unrelated modules.
 */
class PreschoolLifecycleAuditService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(array $data): PreschoolLifecycleAuditLog
    {
        return PreschoolLifecycleAuditLog::query()->create([
            'actor_user_id' => $data['actor_user_id'] ?? null,
            'actor_role' => $data['actor_role'] ?? null,
            'action_type' => $data['action_type'],
            'entity_type' => $data['entity_type'],
            'entity_id' => isset($data['entity_id']) ? (string) $data['entity_id'] : null,
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'term_id' => $data['term_id'] ?? null,
            'report_period_id' => $data['report_period_id'] ?? null,
            'previous_state' => $data['previous_state'] ?? null,
            'new_state' => $data['new_state'] ?? null,
            'override_reason' => $data['override_reason'] ?? null,
            'lock_code' => $data['lock_code'] ?? null,
            'lock_reason' => $data['lock_reason'] ?? null,
            'request_context' => $data['request_context'] ?? null,
            'created_at' => $data['created_at'] ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordSafely(array $data): ?PreschoolLifecycleAuditLog
    {
        try {
            return $this->record($data);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function requestContext(?Request $request = null, array $context = []): array
    {
        $request ??= request();

        return array_merge([
            'method' => $request?->method(),
            'path' => $request?->path(),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ], $context);
    }
}
