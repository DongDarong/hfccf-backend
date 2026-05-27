<?php

namespace App\Services;

use App\Models\AuditLog;
use Throwable;

class AuditLogService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(array $data): AuditLog
    {
        return AuditLog::query()->create([
            'actor_user_id' => $data['actor_user_id'] ?? null,
            'domain' => $data['domain'],
            'action' => $data['action'],
            'entity_type' => $data['entity_type'],
            'entity_id' => isset($data['entity_id']) ? (string) $data['entity_id'] : null,
            'entity_label' => $data['entity_label'] ?? null,
            'old_values' => $data['old_values'] ?? null,
            'new_values' => $data['new_values'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'created_at' => $data['created_at'] ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordSafely(array $data): ?AuditLog
    {
        try {
            return $this->record($data);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
