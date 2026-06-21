<?php

namespace App\Support;

use App\Models\PreschoolHealthCheckCategory;
use App\Models\PreschoolHealthIncidentCategory;
use App\Models\PreschoolHealthSetting;
use App\Models\PreschoolHealthSeverityLevel;
use App\Models\PreschoolVaccinationCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PreschoolHealthConfigurationService
{
    public const DEFAULT_SETTINGS = [
        'critical_alert_enabled' => true,
        'guardian_notification_enabled' => true,
        'teacher_notification_enabled' => true,
        'admin_notification_enabled' => true,
        'medication_reminder_enabled' => true,
        'vaccination_reminder_enabled' => true,
        'overdue_vaccination_alert_days' => 30,
        'medication_reminder_minutes_before' => 30,
    ];

    public function getSettings(): PreschoolHealthSetting
    {
        $settings = PreschoolHealthSetting::query()->first();

        if ($settings) {
            return $settings;
        }

        return DB::transaction(function (): PreschoolHealthSetting {
            $existing = PreschoolHealthSetting::query()->first();
            if ($existing) {
                return $existing;
            }

            return PreschoolHealthSetting::query()->create(self::DEFAULT_SETTINGS);
        });
    }

    public function updateSettings(array $data, ?User $actor = null): PreschoolHealthSetting
    {
        return DB::transaction(function () use ($data, $actor): PreschoolHealthSetting {
            $payload = $this->normalizeSettingsPayload($data);
            $settings = PreschoolHealthSetting::query()->first();

            if ($settings) {
                $settings->fill($payload);
                $settings->updated_by = $this->resolveActorId($actor);
                if (! $settings->created_by && $this->resolveActorId($actor)) {
                    $settings->created_by = $this->resolveActorId($actor);
                }
                $settings->save();

                return $settings->refresh();
            }

            $payload['created_by'] = $this->resolveActorId($actor);
            $payload['updated_by'] = $this->resolveActorId($actor);

            return PreschoolHealthSetting::query()->create($payload);
        });
    }

    public function listSeverityLevels(array $filters = []): Collection
    {
        $this->ensureDefaultSeverityLevels();

        $query = PreschoolHealthSeverityLevel::query()->withTrashed();
        $this->applyStatusFilter($query, Arr::get($filters, 'status'));
        $this->applySearchFilter($query, Arr::get($filters, 'search'));

        return $query->orderBy('sort_order')->orderBy('priority')->orderBy('id')->get();
    }

    public function createSeverityLevel(array $data, ?User $actor = null): PreschoolHealthSeverityLevel
    {
        return DB::transaction(function () use ($data, $actor): PreschoolHealthSeverityLevel {
            $payload = $this->normalizeSeverityPayload($data);
            $payload['created_by'] = $this->resolveActorId($actor);
            $payload['updated_by'] = $this->resolveActorId($actor);

            return PreschoolHealthSeverityLevel::query()->create($payload);
        });
    }

    public function updateSeverityLevel(PreschoolHealthSeverityLevel|string|int $severity, array $data, ?User $actor = null): PreschoolHealthSeverityLevel
    {
        return DB::transaction(function () use ($severity, $data, $actor): PreschoolHealthSeverityLevel {
            $record = $this->findSeverityLevelOrFail($severity, true);
            $payload = $this->normalizeSeverityPayload($data, false);
            $payload['updated_by'] = $this->resolveActorId($actor);
            $record->fill($payload);
            $record->save();

            return $record->refresh();
        });
    }

    public function archiveSeverityLevel(PreschoolHealthSeverityLevel|string|int $severity, ?User $actor = null): PreschoolHealthSeverityLevel
    {
        return DB::transaction(function () use ($severity, $actor): PreschoolHealthSeverityLevel {
            $record = $this->findSeverityLevelOrFail($severity, true);
            $record->is_active = false;
            $record->updated_by = $this->resolveActorId($actor);
            $record->save();
            $record->delete();

            return $record->refresh();
        });
    }

    public function listIncidentCategories(array $filters = []): Collection
    {
        $this->ensureDefaultIncidentCategories();

        $query = PreschoolHealthIncidentCategory::query()->withTrashed();
        $this->applyStatusFilter($query, Arr::get($filters, 'status'));
        $this->applySearchFilter($query, Arr::get($filters, 'search'));

        return $query->orderBy('sort_order')->orderBy('id')->get();
    }

    public function createIncidentCategory(array $data, ?User $actor = null): PreschoolHealthIncidentCategory
    {
        return DB::transaction(function () use ($data, $actor): PreschoolHealthIncidentCategory {
            $payload = $this->normalizeIncidentCategoryPayload($data);
            $payload['created_by'] = $this->resolveActorId($actor);
            $payload['updated_by'] = $this->resolveActorId($actor);

            return PreschoolHealthIncidentCategory::query()->create($payload);
        });
    }

    public function updateIncidentCategory(PreschoolHealthIncidentCategory|string|int $category, array $data, ?User $actor = null): PreschoolHealthIncidentCategory
    {
        return DB::transaction(function () use ($category, $data, $actor): PreschoolHealthIncidentCategory {
            $record = $this->findIncidentCategoryOrFail($category, true);
            $payload = $this->normalizeIncidentCategoryPayload($data, false);
            $payload['updated_by'] = $this->resolveActorId($actor);
            $record->fill($payload);
            $record->save();

            return $record->refresh();
        });
    }

    public function archiveIncidentCategory(PreschoolHealthIncidentCategory|string|int $category, ?User $actor = null): PreschoolHealthIncidentCategory
    {
        return DB::transaction(function () use ($category, $actor): PreschoolHealthIncidentCategory {
            $record = $this->findIncidentCategoryOrFail($category, true);
            $record->is_active = false;
            $record->updated_by = $this->resolveActorId($actor);
            $record->save();
            $record->delete();

            return $record->refresh();
        });
    }

    public function listVaccinationCategories(array $filters = []): Collection
    {
        $this->ensureDefaultVaccinationCategories();

        $query = PreschoolVaccinationCategory::query()->withTrashed();
        $this->applyStatusFilter($query, Arr::get($filters, 'status'));
        $this->applySearchFilter($query, Arr::get($filters, 'search'));

        return $query->orderBy('sort_order')->orderBy('id')->get();
    }

    public function createVaccinationCategory(array $data, ?User $actor = null): PreschoolVaccinationCategory
    {
        return DB::transaction(function () use ($data, $actor): PreschoolVaccinationCategory {
            $payload = $this->normalizeVaccinationCategoryPayload($data);
            $payload['created_by'] = $this->resolveActorId($actor);
            $payload['updated_by'] = $this->resolveActorId($actor);

            return PreschoolVaccinationCategory::query()->create($payload);
        });
    }

    public function updateVaccinationCategory(PreschoolVaccinationCategory|string|int $category, array $data, ?User $actor = null): PreschoolVaccinationCategory
    {
        return DB::transaction(function () use ($category, $data, $actor): PreschoolVaccinationCategory {
            $record = $this->findVaccinationCategoryOrFail($category, true);
            $payload = $this->normalizeVaccinationCategoryPayload($data, false);
            $payload['updated_by'] = $this->resolveActorId($actor);
            $record->fill($payload);
            $record->save();

            return $record->refresh();
        });
    }

    public function archiveVaccinationCategory(PreschoolVaccinationCategory|string|int $category, ?User $actor = null): PreschoolVaccinationCategory
    {
        return DB::transaction(function () use ($category, $actor): PreschoolVaccinationCategory {
            $record = $this->findVaccinationCategoryOrFail($category, true);
            $record->is_active = false;
            $record->updated_by = $this->resolveActorId($actor);
            $record->save();
            $record->delete();

            return $record->refresh();
        });
    }

    public function listHealthCheckCategories(array $filters = []): Collection
    {
        $this->ensureDefaultHealthCheckCategories();

        $query = PreschoolHealthCheckCategory::query()->withTrashed();
        $this->applyStatusFilter($query, Arr::get($filters, 'status'));
        $this->applySearchFilter($query, Arr::get($filters, 'search'));

        return $query->orderBy('sort_order')->orderBy('id')->get();
    }

    public function createHealthCheckCategory(array $data, ?User $actor = null): PreschoolHealthCheckCategory
    {
        return DB::transaction(function () use ($data, $actor): PreschoolHealthCheckCategory {
            $payload = $this->normalizeHealthCheckCategoryPayload($data);
            $payload['created_by'] = $this->resolveActorId($actor);
            $payload['updated_by'] = $this->resolveActorId($actor);

            return PreschoolHealthCheckCategory::query()->create($payload);
        });
    }

    public function updateHealthCheckCategory(PreschoolHealthCheckCategory|string|int $category, array $data, ?User $actor = null): PreschoolHealthCheckCategory
    {
        return DB::transaction(function () use ($category, $data, $actor): PreschoolHealthCheckCategory {
            $record = $this->findHealthCheckCategoryOrFail($category, true);
            $payload = $this->normalizeHealthCheckCategoryPayload($data, false);
            $payload['updated_by'] = $this->resolveActorId($actor);
            $record->fill($payload);
            $record->save();

            return $record->refresh();
        });
    }

    public function archiveHealthCheckCategory(PreschoolHealthCheckCategory|string|int $category, ?User $actor = null): PreschoolHealthCheckCategory
    {
        return DB::transaction(function () use ($category, $actor): PreschoolHealthCheckCategory {
            $record = $this->findHealthCheckCategoryOrFail($category, true);
            $record->is_active = false;
            $record->updated_by = $this->resolveActorId($actor);
            $record->save();
            $record->delete();

            return $record->refresh();
        });
    }

    public function getSeverityLevel(string $code): ?PreschoolHealthSeverityLevel
    {
        $normalizedCode = $this->normalizeCode($code);
        if ($normalizedCode === '') {
            return null;
        }

        $this->ensureDefaultSeverityLevels();

        $record = PreschoolHealthSeverityLevel::query()
            ->where('code', $normalizedCode)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if ($record) {
            return $record;
        }

        $default = collect($this->defaultSeverityLevels())->firstWhere('code', $normalizedCode);
        if (! $default) {
            return null;
        }

        return new PreschoolHealthSeverityLevel($default);
    }

    public function shouldTriggerHealthNotification(string $severityCode): bool
    {
        return (bool) ($this->getSeverityLevel($severityCode)?->triggers_notification ?? false);
    }

    public function requiresAcknowledgment(string $severityCode): bool
    {
        return (bool) ($this->getSeverityLevel($severityCode)?->requires_acknowledgment ?? false);
    }

    public function getIncidentCategories(): Collection
    {
        $this->ensureDefaultIncidentCategories();

        return PreschoolHealthIncidentCategory::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getVaccinationCategories(): Collection
    {
        $this->ensureDefaultVaccinationCategories();

        return PreschoolVaccinationCategory::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getHealthCheckCategories(): Collection
    {
        $this->ensureDefaultHealthCheckCategories();

        return PreschoolHealthCheckCategory::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getMedicationReminderMinutes(): int
    {
        return (int) $this->getSettings()->medication_reminder_minutes_before;
    }

    public function getOverdueVaccinationAlertDays(): int
    {
        return (int) $this->getSettings()->overdue_vaccination_alert_days;
    }

    public function getDashboardSummary(): array
    {
        $settings = $this->getSettings();
        $this->ensureDefaultSeverityLevels();
        $this->ensureDefaultIncidentCategories();
        $this->ensureDefaultVaccinationCategories();
        $this->ensureDefaultHealthCheckCategories();

        return [
            'critical_alert_enabled' => (bool) $settings->critical_alert_enabled,
            'severity_levels_count' => PreschoolHealthSeverityLevel::query()->whereNull('deleted_at')->where('is_active', true)->count(),
            'incident_categories_count' => PreschoolHealthIncidentCategory::query()->whereNull('deleted_at')->where('is_active', true)->count(),
            'vaccination_categories_count' => PreschoolVaccinationCategory::query()->whereNull('deleted_at')->where('is_active', true)->count(),
            'health_check_categories_count' => PreschoolHealthCheckCategory::query()->whereNull('deleted_at')->where('is_active', true)->count(),
            'medication_reminder_enabled' => (bool) $settings->medication_reminder_enabled,
            'vaccination_reminder_enabled' => (bool) $settings->vaccination_reminder_enabled,
            'is_configured' => true,
        ];
    }

    private function defaultSeverityLevels(): array
    {
        return [
            [
                'name' => 'Low',
                'code' => 'low',
                'priority' => 1,
                'color' => '#22c55e',
                'requires_acknowledgment' => false,
                'triggers_notification' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Medium',
                'code' => 'medium',
                'priority' => 2,
                'color' => '#f59e0b',
                'requires_acknowledgment' => false,
                'triggers_notification' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'High',
                'code' => 'high',
                'priority' => 3,
                'color' => '#fb7185',
                'requires_acknowledgment' => true,
                'triggers_notification' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Critical',
                'code' => 'critical',
                'priority' => 4,
                'color' => '#ef4444',
                'requires_acknowledgment' => true,
                'triggers_notification' => true,
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];
    }

    private function defaultIncidentCategories(): array
    {
        return [
            ['name' => 'Injury', 'code' => 'injury', 'description' => null, 'default_severity_code' => 'high', 'is_active' => true, 'sort_order' => 1],
            ['name' => 'Fever', 'code' => 'fever', 'description' => null, 'default_severity_code' => 'medium', 'is_active' => true, 'sort_order' => 2],
            ['name' => 'Allergy Reaction', 'code' => 'allergy_reaction', 'description' => null, 'default_severity_code' => 'critical', 'is_active' => true, 'sort_order' => 3],
            ['name' => 'Medication Issue', 'code' => 'medication_issue', 'description' => null, 'default_severity_code' => 'critical', 'is_active' => true, 'sort_order' => 4],
            ['name' => 'Behavior Concern', 'code' => 'behavior_concern', 'description' => null, 'default_severity_code' => 'low', 'is_active' => true, 'sort_order' => 5],
            ['name' => 'Other', 'code' => 'other', 'description' => null, 'default_severity_code' => 'medium', 'is_active' => true, 'sort_order' => 6],
        ];
    }

    private function defaultVaccinationCategories(): array
    {
        return [
            ['name' => 'MMR', 'code' => 'mmr', 'description' => null, 'recommended_age_months' => 12, 'is_required' => true, 'is_active' => true, 'sort_order' => 1],
            ['name' => 'DTP', 'code' => 'dtp', 'description' => null, 'recommended_age_months' => 2, 'is_required' => true, 'is_active' => true, 'sort_order' => 2],
            ['name' => 'Polio', 'code' => 'polio', 'description' => null, 'recommended_age_months' => 2, 'is_required' => true, 'is_active' => true, 'sort_order' => 3],
            ['name' => 'Hepatitis B', 'code' => 'hepatitis_b', 'description' => null, 'recommended_age_months' => 0, 'is_required' => true, 'is_active' => true, 'sort_order' => 4],
            ['name' => 'Influenza', 'code' => 'influenza', 'description' => null, 'recommended_age_months' => 6, 'is_required' => false, 'is_active' => true, 'sort_order' => 5],
        ];
    }

    private function defaultHealthCheckCategories(): array
    {
        return [
            ['name' => 'Temperature', 'code' => 'temperature', 'description' => null, 'is_active' => true, 'sort_order' => 1],
            ['name' => 'Weight', 'code' => 'weight', 'description' => null, 'is_active' => true, 'sort_order' => 2],
            ['name' => 'Height', 'code' => 'height', 'description' => null, 'is_active' => true, 'sort_order' => 3],
            ['name' => 'Vision', 'code' => 'vision', 'description' => null, 'is_active' => true, 'sort_order' => 4],
            ['name' => 'Hearing', 'code' => 'hearing', 'description' => null, 'is_active' => true, 'sort_order' => 5],
            ['name' => 'Hygiene', 'code' => 'hygiene', 'description' => null, 'is_active' => true, 'sort_order' => 6],
        ];
    }

    private function ensureDefaultSeverityLevels(): void
    {
        if (PreschoolHealthSeverityLevel::withTrashed()->exists()) {
            return;
        }

        foreach ($this->defaultSeverityLevels() as $payload) {
            PreschoolHealthSeverityLevel::query()->create($payload);
        }
    }

    private function ensureDefaultIncidentCategories(): void
    {
        if (PreschoolHealthIncidentCategory::withTrashed()->exists()) {
            return;
        }

        foreach ($this->defaultIncidentCategories() as $payload) {
            PreschoolHealthIncidentCategory::query()->create($payload);
        }
    }

    private function ensureDefaultVaccinationCategories(): void
    {
        if (PreschoolVaccinationCategory::withTrashed()->exists()) {
            return;
        }

        foreach ($this->defaultVaccinationCategories() as $payload) {
            PreschoolVaccinationCategory::query()->create($payload);
        }
    }

    private function ensureDefaultHealthCheckCategories(): void
    {
        if (PreschoolHealthCheckCategory::withTrashed()->exists()) {
            return;
        }

        foreach ($this->defaultHealthCheckCategories() as $payload) {
            PreschoolHealthCheckCategory::query()->create($payload);
        }
    }

    private function normalizeSettingsPayload(array $data): array
    {
        return [
            'critical_alert_enabled' => (bool) Arr::get($data, 'critical_alert_enabled', self::DEFAULT_SETTINGS['critical_alert_enabled']),
            'guardian_notification_enabled' => (bool) Arr::get($data, 'guardian_notification_enabled', self::DEFAULT_SETTINGS['guardian_notification_enabled']),
            'teacher_notification_enabled' => (bool) Arr::get($data, 'teacher_notification_enabled', self::DEFAULT_SETTINGS['teacher_notification_enabled']),
            'admin_notification_enabled' => (bool) Arr::get($data, 'admin_notification_enabled', self::DEFAULT_SETTINGS['admin_notification_enabled']),
            'medication_reminder_enabled' => (bool) Arr::get($data, 'medication_reminder_enabled', self::DEFAULT_SETTINGS['medication_reminder_enabled']),
            'vaccination_reminder_enabled' => (bool) Arr::get($data, 'vaccination_reminder_enabled', self::DEFAULT_SETTINGS['vaccination_reminder_enabled']),
            'overdue_vaccination_alert_days' => (int) Arr::get($data, 'overdue_vaccination_alert_days', self::DEFAULT_SETTINGS['overdue_vaccination_alert_days']),
            'medication_reminder_minutes_before' => (int) Arr::get($data, 'medication_reminder_minutes_before', self::DEFAULT_SETTINGS['medication_reminder_minutes_before']),
        ];
    }

    private function normalizeSeverityPayload(array $data, bool $creating = true): array
    {
        $payload = [
            'name' => trim((string) Arr::get($data, 'name', '')),
            'code' => $this->normalizeCode(Arr::get($data, 'code', '')),
            'priority' => (int) Arr::get($data, 'priority', 0),
            'color' => $this->normalizeNullableString(Arr::get($data, 'color')),
            'requires_acknowledgment' => (bool) Arr::get($data, 'requires_acknowledgment', false),
            'triggers_notification' => (bool) Arr::get($data, 'triggers_notification', true),
            'is_active' => (bool) Arr::get($data, 'is_active', true),
            'sort_order' => (int) Arr::get($data, 'sort_order', Arr::get($data, 'priority', 0)),
        ];

        if (! $creating) {
            $payload['code'] = $payload['code'] ?: null;
        }

        return $payload;
    }

    private function normalizeIncidentCategoryPayload(array $data, bool $creating = true): array
    {
        $payload = [
            'name' => trim((string) Arr::get($data, 'name', '')),
            'code' => $this->normalizeCode(Arr::get($data, 'code', '')),
            'description' => $this->normalizeNullableString(Arr::get($data, 'description')),
            'default_severity_code' => $this->normalizeCode(Arr::get($data, 'default_severity_code', '')),
            'is_active' => (bool) Arr::get($data, 'is_active', true),
            'sort_order' => (int) Arr::get($data, 'sort_order', 0),
        ];

        if (! $creating && $payload['code'] === '') {
            $payload['code'] = null;
        }

        if ($payload['default_severity_code'] === '') {
            $payload['default_severity_code'] = null;
        }

        return $payload;
    }

    private function normalizeVaccinationCategoryPayload(array $data, bool $creating = true): array
    {
        $payload = [
            'name' => trim((string) Arr::get($data, 'name', '')),
            'code' => $this->normalizeCode(Arr::get($data, 'code', '')),
            'description' => $this->normalizeNullableString(Arr::get($data, 'description')),
            'recommended_age_months' => Arr::has($data, 'recommended_age_months') && Arr::get($data, 'recommended_age_months') !== ''
                ? (int) Arr::get($data, 'recommended_age_months')
                : null,
            'is_required' => (bool) Arr::get($data, 'is_required', false),
            'is_active' => (bool) Arr::get($data, 'is_active', true),
            'sort_order' => (int) Arr::get($data, 'sort_order', 0),
        ];

        if (! $creating && $payload['code'] === '') {
            $payload['code'] = null;
        }

        return $payload;
    }

    private function normalizeHealthCheckCategoryPayload(array $data, bool $creating = true): array
    {
        $payload = [
            'name' => trim((string) Arr::get($data, 'name', '')),
            'code' => $this->normalizeCode(Arr::get($data, 'code', '')),
            'description' => $this->normalizeNullableString(Arr::get($data, 'description')),
            'is_active' => (bool) Arr::get($data, 'is_active', true),
            'sort_order' => (int) Arr::get($data, 'sort_order', 0),
        ];

        if (! $creating && $payload['code'] === '') {
            $payload['code'] = null;
        }

        return $payload;
    }

    private function findSeverityLevelOrFail(PreschoolHealthSeverityLevel|string|int $severity, bool $includeTrashed = false): PreschoolHealthSeverityLevel
    {
        $query = PreschoolHealthSeverityLevel::query();
        if ($includeTrashed) {
            $query->withTrashed();
        }

        $record = $severity instanceof PreschoolHealthSeverityLevel
            ? $severity
            : $query->find($severity);

        if (! $record) {
            abort(404, 'Severity level not found.');
        }

        return $record;
    }

    private function findIncidentCategoryOrFail(PreschoolHealthIncidentCategory|string|int $category, bool $includeTrashed = false): PreschoolHealthIncidentCategory
    {
        $query = PreschoolHealthIncidentCategory::query();
        if ($includeTrashed) {
            $query->withTrashed();
        }

        $record = $category instanceof PreschoolHealthIncidentCategory
            ? $category
            : $query->find($category);

        if (! $record) {
            abort(404, 'Incident category not found.');
        }

        return $record;
    }

    private function findVaccinationCategoryOrFail(PreschoolVaccinationCategory|string|int $category, bool $includeTrashed = false): PreschoolVaccinationCategory
    {
        $query = PreschoolVaccinationCategory::query();
        if ($includeTrashed) {
            $query->withTrashed();
        }

        $record = $category instanceof PreschoolVaccinationCategory
            ? $category
            : $query->find($category);

        if (! $record) {
            abort(404, 'Vaccination category not found.');
        }

        return $record;
    }

    private function findHealthCheckCategoryOrFail(PreschoolHealthCheckCategory|string|int $category, bool $includeTrashed = false): PreschoolHealthCheckCategory
    {
        $query = PreschoolHealthCheckCategory::query();
        if ($includeTrashed) {
            $query->withTrashed();
        }

        $record = $category instanceof PreschoolHealthCheckCategory
            ? $category
            : $query->find($category);

        if (! $record) {
            abort(404, 'Health check category not found.');
        }

        return $record;
    }

    private function applyStatusFilter($query, mixed $status): void
    {
        $status = $this->normalizeNullableString($status);
        if ($status === '') {
            return;
        }

        if ($status === 'active') {
            $query->whereNull('deleted_at')->where('is_active', true);
        } elseif ($status === 'archived') {
            $query->where(function ($builder): void {
                $builder->whereNotNull('deleted_at')->orWhere('is_active', false);
            });
        }
    }

    private function applySearchFilter($query, mixed $search): void
    {
        $search = $this->normalizeNullableString($search);
        if ($search === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
        $query->where(function ($builder) use ($like): void {
            $builder->where('name', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('default_severity_code', 'like', $like);
        });
    }

    private function resolveActorId(?User $actor): ?string
    {
        $id = $actor?->getKey();

        return $id === null ? null : (string) $id;
    }

    private function normalizeCode(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
