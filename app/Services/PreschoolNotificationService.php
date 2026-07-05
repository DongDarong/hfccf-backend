<?php

namespace App\Services;

use App\Models\PreschoolNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PreschoolNotificationService
{
    public function __construct(
        private readonly PreschoolWorkflowSourceLinkService $sourceLinkService,
    ) {
    }

    public function listNotifications(?User $viewer, array $filters = []): array
    {
        $query = $this->visibleQuery($viewer, $filters)
            ->with(['creator', 'targetUser', 'student', 'preschoolClass'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'summary' => $this->summary($viewer, $filters),
            'items' => $paginator->getCollection()->map(fn (PreschoolNotification $notification): array => $this->formatNotification($notification))->values()->all(),
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
        $notifications = $this->visibleQuery($viewer, $filters)->get();

        return [
            'total' => $notifications->count(),
            'unread' => $notifications->where('status', PreschoolNotification::STATUS_UNREAD)->count(),
            'read' => $notifications->where('status', PreschoolNotification::STATUS_READ)->count(),
            'archived' => $notifications->where('status', PreschoolNotification::STATUS_ARCHIVED)->count(),
            'critical' => $notifications->filter(static fn (PreschoolNotification $notification): bool => in_array($notification->severity, ['high', 'critical'], true) && $notification->status !== PreschoolNotification::STATUS_ARCHIVED)->count(),
            'byType' => $this->groupByKey($notifications, 'notification_type', 'notificationType'),
            'bySeverity' => $this->groupByKey($notifications, 'severity', 'severity'),
        ];
    }

    public function markRead(User $actor, PreschoolNotification $notification): PreschoolNotification
    {
        $this->ensureCanManage($actor, $notification);

        if ($notification->status !== PreschoolNotification::STATUS_ARCHIVED) {
            $notification->status = PreschoolNotification::STATUS_READ;
            $notification->read_at = $notification->read_at ?? now();
            $notification->save();
        }

        return $notification->fresh(['creator', 'targetUser', 'student', 'preschoolClass']);
    }

    public function archive(User $actor, PreschoolNotification $notification): PreschoolNotification
    {
        $this->ensureCanManage($actor, $notification);

        $notification->status = PreschoolNotification::STATUS_ARCHIVED;
        $notification->read_at = $notification->read_at ?? now();
        $notification->archived_at = $notification->archived_at ?? now();
        $notification->save();

        return $notification->fresh(['creator', 'targetUser', 'student', 'preschoolClass']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{notification: PreschoolNotification, created: bool, updated: bool}
     */
    public function upsertNotification(array $data): array
    {
        $notification = $this->findExistingNotification($data);
        $created = false;
        $updated = false;

        if (! $notification) {
            $notification = new PreschoolNotification();
            $created = true;
        }

        foreach ([
            'notification_type',
            'title',
            'body',
            'severity',
            'target_user_id',
            'target_role',
            'source_type',
            'source_id',
            'preschool_student_id',
            'preschool_class_id',
            'action_route',
            'action_params',
            'created_by',
        ] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $next = $this->normalizeValue($field, $data[$field]);
            if (! $created && $notification->{$field} !== $next) {
                $updated = true;
            }
            $notification->{$field} = $next;
        }

        if ($created) {
            $notification->status = PreschoolNotification::STATUS_UNREAD;
        }

        if (! $notification->exists || $created || $updated) {
            $notification->save();
        }

        return [
            'notification' => $notification->fresh(['creator', 'targetUser', 'student', 'preschoolClass']),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    private function visibleQuery(?User $viewer, array $filters = []): Builder
    {
        $query = PreschoolNotification::query();

        if (! $viewer || $viewer->role_code === 'superadmin') {
            $this->applyFilters($query, $filters);

            return $query;
        }

        $query->where(function (Builder $builder) use ($viewer): void {
            $builder->where('target_user_id', $viewer->id)
                ->orWhere('target_role', $viewer->role_code);
        });

        $this->applyFilters($query, $filters);

        return $query;
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (($status = trim((string) ($filters['status'] ?? ''))) !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if (($type = trim((string) ($filters['notification_type'] ?? $filters['type'] ?? ''))) !== '' && $type !== 'all') {
            $query->where('notification_type', $type);
        }

        if (($severity = trim((string) ($filters['severity'] ?? ''))) !== '' && $severity !== 'all') {
            $query->where('severity', $severity);
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
                    ->orWhere('body', 'like', $like)
                    ->orWhere('notification_type', 'like', $like)
                    ->orWhere('source_type', 'like', $like);
            });
        }
    }

    private function formatNotification(PreschoolNotification $notification): array
    {
        $workflowLink = $this->sourceLinkService->resolveWorkflowLink($notification->source_type, $notification->source_id);

        return [
            'id' => $notification->id,
            'notificationType' => $notification->notification_type,
            'title' => $notification->title,
            'body' => $notification->body,
            'severity' => $notification->severity,
            'status' => $notification->status,
            'targetUserId' => $notification->target_user_id,
            'targetRole' => $notification->target_role,
            'sourceType' => $notification->source_type,
            'sourceId' => $notification->source_id,
            'preschoolStudentId' => $notification->preschool_student_id,
            'preschoolClassId' => $notification->preschool_class_id,
            'studentName' => $this->studentName($notification),
            'className' => $this->className($notification),
            'actionRoute' => $notification->action_route,
            'actionParams' => $notification->action_params ?? [],
            'workflowInstanceId' => $workflowLink['workflowInstanceId'],
            'workflowStatus' => $workflowLink['workflowStatus'],
            'workflowRoute' => $workflowLink['workflowRoute'],
            'workflowActionParams' => $workflowLink['workflowActionParams'],
            'readAt' => $notification->read_at?->toISOString(),
            'archivedAt' => $notification->archived_at?->toISOString(),
            'createdBy' => $notification->created_by,
            'createdAt' => $notification->created_at?->toISOString(),
        ];
    }

    private function groupByKey(Collection $items, string $field, string $outputKey): array
    {
        return $items
            ->groupBy(static fn (PreschoolNotification $notification): string => trim((string) data_get($notification, $field)) ?: 'unknown')
            ->map(static function (Collection $group, string $key) use ($outputKey): array {
                return [
                    $outputKey => $key === 'unknown' ? null : $key,
                    'total' => $group->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function findExistingNotification(array $data): ?PreschoolNotification
    {
        return PreschoolNotification::query()
            ->where('notification_type', $this->normalizeValue('notification_type', $data['notification_type'] ?? ''))
            ->where('source_type', $this->normalizeValue('source_type', $data['source_type'] ?? null))
            ->where('source_id', $this->normalizeValue('source_id', $data['source_id'] ?? null))
            ->where('target_user_id', $this->normalizeValue('target_user_id', $data['target_user_id'] ?? null))
            ->where('target_role', $this->normalizeValue('target_role', $data['target_role'] ?? null))
            ->where('preschool_student_id', $this->normalizeValue('preschool_student_id', $data['preschool_student_id'] ?? null))
            ->where('preschool_class_id', $this->normalizeValue('preschool_class_id', $data['preschool_class_id'] ?? null))
            ->first();
    }

    private function normalizeValue(string $field, mixed $value): mixed
    {
        return match ($field) {
            'notification_type', 'title', 'body', 'severity', 'target_user_id', 'target_role', 'source_type', 'source_id', 'action_route', 'created_by' => $this->nullableString($value),
            'preschool_student_id', 'preschool_class_id' => $this->nullableInt($value),
            'action_params' => is_array($value) ? $value : null,
            default => $value,
        };
    }

    private function studentName(PreschoolNotification $notification): ?string
    {
        $student = $notification->relationLoaded('student') ? $notification->student : $notification->student()->first();

        return $student ? trim(($student->first_name ?? '').' '.($student->last_name ?? '')) ?: null : null;
    }

    private function className(PreschoolNotification $notification): ?string
    {
        $class = $notification->relationLoaded('preschoolClass') ? $notification->preschoolClass : $notification->preschoolClass()->first();

        return $class?->name ?: null;
    }

    private function ensureCanManage(User $actor, PreschoolNotification $notification): void
    {
        if (in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)) {
            return;
        }

        if ($actor->role_code === 'teacher-preschool' && ($notification->target_user_id === $actor->id || $notification->target_role === $actor->role_code)) {
            return;
        }

        throw ValidationException::withMessages([
            'notification' => ['You are not allowed to update this notification.'],
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
