<?php

namespace App\Support;

use App\Models\PreschoolBillingRule;
use App\Models\PreschoolFeeType;
use App\Models\PreschoolPaymentMethod;
use App\Models\PreschoolPaymentSetting;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PreschoolPaymentConfigurationService
{
    public const DEFAULT_SETTINGS = [
        'invoice_prefix' => 'INV',
        'receipt_prefix' => 'RCT',
        'next_invoice_number' => 1,
        'next_receipt_number' => 1,
        'late_fee_enabled' => true,
        'late_fee_type' => PreschoolPaymentSetting::LATE_FEE_FIXED,
        'late_fee_amount' => 5.00,
        'grace_period_days' => 5,
        'proration_enabled' => false,
    ];

    public function getSettings(): PreschoolPaymentSetting
    {
        $settings = PreschoolPaymentSetting::query()->first();
        if ($settings) {
            return $settings;
        }

        return DB::transaction(function (): PreschoolPaymentSetting {
            $existing = PreschoolPaymentSetting::query()->first();
            if ($existing) {
                return $existing;
            }

            return PreschoolPaymentSetting::query()->create(self::DEFAULT_SETTINGS);
        });
    }

    public function updateSettings(array $data, ?User $actor = null): PreschoolPaymentSetting
    {
        return DB::transaction(function () use ($data, $actor): PreschoolPaymentSetting {
            $payload = $this->normalizeSettingsPayload($data);
            $settings = PreschoolPaymentSetting::query()->first();

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

            return PreschoolPaymentSetting::query()->create($payload);
        });
    }

    public function listFeeTypes(array $filters = []): Collection
    {
        $this->ensureDefaultFeeTypes();

        $query = PreschoolFeeType::query()->withTrashed();
        $this->applyStatusFilter($query, Arr::get($filters, 'status'));
        $this->applySearchFilter($query, Arr::get($filters, 'search'));

        return $query->orderBy('sort_order')->orderBy('id')->get();
    }

    public function createFeeType(array $data, ?User $actor = null): PreschoolFeeType
    {
        return DB::transaction(function () use ($data, $actor): PreschoolFeeType {
            $payload = $this->normalizeFeeTypePayload($data);
            $payload['created_by'] = $this->resolveActorId($actor);
            $payload['updated_by'] = $this->resolveActorId($actor);

            return PreschoolFeeType::query()->create($payload);
        });
    }

    public function updateFeeType(PreschoolFeeType|string|int $feeType, array $data, ?User $actor = null): PreschoolFeeType
    {
        return DB::transaction(function () use ($feeType, $data, $actor): PreschoolFeeType {
            $record = $this->findFeeTypeOrFail($feeType, true);
            $payload = $this->normalizeFeeTypePayload($data, false);
            $payload['updated_by'] = $this->resolveActorId($actor);
            $record->fill($payload);
            $record->save();

            return $record->refresh();
        });
    }

    public function archiveFeeType(PreschoolFeeType|string|int $feeType, ?User $actor = null): PreschoolFeeType
    {
        return DB::transaction(function () use ($feeType, $actor): PreschoolFeeType {
            $record = $this->findFeeTypeOrFail($feeType, true);
            $record->is_active = false;
            $record->updated_by = $this->resolveActorId($actor);
            $record->save();
            $record->delete();

            return $record->refresh();
        });
    }

    public function listPaymentMethods(array $filters = []): Collection
    {
        $this->ensureDefaultPaymentMethods();

        $query = PreschoolPaymentMethod::query()->withTrashed();
        $this->applyStatusFilter($query, Arr::get($filters, 'status'));
        $this->applySearchFilter($query, Arr::get($filters, 'search'));

        return $query->orderBy('sort_order')->orderBy('id')->get();
    }

    public function createPaymentMethod(array $data, ?User $actor = null): PreschoolPaymentMethod
    {
        return DB::transaction(function () use ($data, $actor): PreschoolPaymentMethod {
            $payload = $this->normalizePaymentMethodPayload($data);
            $payload['created_by'] = $this->resolveActorId($actor);
            $payload['updated_by'] = $this->resolveActorId($actor);

            return PreschoolPaymentMethod::query()->create($payload);
        });
    }

    public function updatePaymentMethod(PreschoolPaymentMethod|string|int $method, array $data, ?User $actor = null): PreschoolPaymentMethod
    {
        return DB::transaction(function () use ($method, $data, $actor): PreschoolPaymentMethod {
            $record = $this->findPaymentMethodOrFail($method, true);
            $payload = $this->normalizePaymentMethodPayload($data, false);
            $payload['updated_by'] = $this->resolveActorId($actor);
            $record->fill($payload);
            $record->save();

            return $record->refresh();
        });
    }

    public function archivePaymentMethod(PreschoolPaymentMethod|string|int $method, ?User $actor = null): PreschoolPaymentMethod
    {
        return DB::transaction(function () use ($method, $actor): PreschoolPaymentMethod {
            $record = $this->findPaymentMethodOrFail($method, true);
            $record->is_active = false;
            $record->updated_by = $this->resolveActorId($actor);
            $record->save();
            $record->delete();

            return $record->refresh();
        });
    }

    public function listBillingRules(): Collection
    {
        $this->ensureDefaultBillingRules();

        return PreschoolBillingRule::query()
            ->where('is_active', true)
            ->orderBy('rule_code')
            ->get();
    }

    public function updateBillingRules(array $data, ?User $actor = null): Collection
    {
        return DB::transaction(function () use ($data, $actor): Collection {
            $rules = collect(Arr::get($data, 'rules', $data))
                ->filter(fn ($item) => is_array($item) || $item instanceof \ArrayAccess)
                ->map(fn ($item) => is_array($item) ? $item : (array) $item)
                ->values();

            foreach ($rules as $ruleData) {
                $payload = $this->normalizeBillingRulePayload($ruleData);
                if ($payload['rule_code'] === '') {
                    continue;
                }

                $record = PreschoolBillingRule::query()->firstOrNew(['rule_code' => $payload['rule_code']]);
                $record->fill($payload);
                $record->is_active = true;
                $record->created_by = $record->exists ? $record->created_by : $this->resolveActorId($actor);
                $record->updated_by = $this->resolveActorId($actor);
                $record->save();
            }

            return $this->listBillingRules();
        });
    }

    public function generateNextInvoiceNumber(?User $actor = null): string
    {
        return DB::transaction(function () use ($actor): string {
            $settings = $this->getSettings();
            $year = now()->format('Y');
            $number = (int) $settings->next_invoice_number;
            $formatted = sprintf('%s-%s-%05d', strtoupper(trim($settings->invoice_prefix ?: self::DEFAULT_SETTINGS['invoice_prefix'])), $year, $number);

            $settings->next_invoice_number = $number + 1;
            $settings->updated_by = $this->resolveActorId($actor);
            $settings->save();

            return $formatted;
        });
    }

    public function generateNextReceiptNumber(?User $actor = null): string
    {
        return DB::transaction(function () use ($actor): string {
            $settings = $this->getSettings();
            $year = now()->format('Y');
            $number = (int) $settings->next_receipt_number;
            $formatted = sprintf('%s-%s-%05d', strtoupper(trim($settings->receipt_prefix ?: self::DEFAULT_SETTINGS['receipt_prefix'])), $year, $number);

            $settings->next_receipt_number = $number + 1;
            $settings->updated_by = $this->resolveActorId($actor);
            $settings->save();

            return $formatted;
        });
    }

    public function getFeeTypes(): Collection
    {
        return PreschoolFeeType::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getPaymentMethods(): Collection
    {
        return PreschoolPaymentMethod::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getGracePeriodDays(): int
    {
        return (int) $this->getSettings()->grace_period_days;
    }

    public function calculateLateFee(float|int $outstandingBalance): float
    {
        $settings = $this->getSettings();

        if (! $settings->late_fee_enabled) {
            return 0.0;
        }

        if ($settings->late_fee_type === PreschoolPaymentSetting::LATE_FEE_PERCENTAGE) {
            return round(((float) $outstandingBalance * (float) $settings->late_fee_amount) / 100, 2);
        }

        return round((float) $settings->late_fee_amount, 2);
    }

    public function shouldApplyLateFee(CarbonInterface|string $dueDate, CarbonInterface|string|null $currentDate = null): bool
    {
        $settings = $this->getSettings();
        if (! $settings->late_fee_enabled) {
            return false;
        }

        $due = $dueDate instanceof CarbonInterface ? Carbon::instance($dueDate) : Carbon::parse($dueDate);
        $current = $currentDate instanceof CarbonInterface
            ? Carbon::instance($currentDate)
            : ($currentDate ? Carbon::parse($currentDate) : Carbon::today());

        return $current->startOfDay()->greaterThan($due->copy()->addDays((int) $settings->grace_period_days)->endOfDay());
    }

    public function getDashboardSummary(): array
    {
        $settings = $this->getSettings();
        $this->ensureDefaultFeeTypes();
        $this->ensureDefaultPaymentMethods();
        $this->ensureDefaultBillingRules();

        return [
            'fee_types_count' => PreschoolFeeType::query()->whereNull('deleted_at')->where('is_active', true)->count(),
            'payment_methods_count' => PreschoolPaymentMethod::query()->whereNull('deleted_at')->where('is_active', true)->count(),
            'late_fee_enabled' => (bool) $settings->late_fee_enabled,
            'grace_period_days' => (int) $settings->grace_period_days,
            'invoice_prefix' => (string) $settings->invoice_prefix,
            'receipt_prefix' => (string) $settings->receipt_prefix,
            'late_fee_type' => (string) $settings->late_fee_type,
            'late_fee_amount' => (float) $settings->late_fee_amount,
            'proration_enabled' => (bool) $settings->proration_enabled,
            'is_configured' => true,
        ];
    }

    private function defaultFeeTypes(): array
    {
        return [
            ['name' => 'Registration Fee', 'code' => 'registration_fee', 'description' => null, 'default_amount' => 25.00, 'is_required' => true, 'is_active' => true, 'sort_order' => 1],
            ['name' => 'Tuition Fee', 'code' => 'tuition_fee', 'description' => null, 'default_amount' => 150.00, 'is_required' => true, 'is_active' => true, 'sort_order' => 2],
            ['name' => 'Materials Fee', 'code' => 'materials_fee', 'description' => null, 'default_amount' => 20.00, 'is_required' => true, 'is_active' => true, 'sort_order' => 3],
            ['name' => 'Uniform Fee', 'code' => 'uniform_fee', 'description' => null, 'default_amount' => 35.00, 'is_required' => false, 'is_active' => true, 'sort_order' => 4],
            ['name' => 'Activity Fee', 'code' => 'activity_fee', 'description' => null, 'default_amount' => 15.00, 'is_required' => false, 'is_active' => true, 'sort_order' => 5],
            ['name' => 'Transportation Fee', 'code' => 'transportation_fee', 'description' => null, 'default_amount' => 40.00, 'is_required' => false, 'is_active' => true, 'sort_order' => 6],
        ];
    }

    private function defaultPaymentMethods(): array
    {
        return [
            ['name' => 'Cash', 'code' => 'cash', 'description' => null, 'is_active' => true, 'sort_order' => 1],
            ['name' => 'ABA', 'code' => 'aba', 'description' => null, 'is_active' => true, 'sort_order' => 2],
            ['name' => 'ACLEDA', 'code' => 'acleda', 'description' => null, 'is_active' => true, 'sort_order' => 3],
            ['name' => 'Wing', 'code' => 'wing', 'description' => null, 'is_active' => true, 'sort_order' => 4],
            ['name' => 'Bank Transfer', 'code' => 'bank_transfer', 'description' => null, 'is_active' => true, 'sort_order' => 5],
        ];
    }

    private function defaultBillingRules(): array
    {
        return [
            ['rule_name' => 'Due Day of Month', 'rule_code' => 'due_day_of_month', 'rule_value' => '5', 'description' => null, 'is_active' => true],
            ['rule_name' => 'Grace Period Days', 'rule_code' => 'grace_period_days', 'rule_value' => '5', 'description' => null, 'is_active' => true],
            ['rule_name' => 'Invoice Generation Day', 'rule_code' => 'invoice_generation_day', 'rule_value' => '1', 'description' => null, 'is_active' => true],
            ['rule_name' => 'Late Fee Enabled', 'rule_code' => 'late_fee_enabled', 'rule_value' => 'true', 'description' => null, 'is_active' => true],
        ];
    }

    private function ensureDefaultFeeTypes(): void
    {
        if (PreschoolFeeType::withTrashed()->exists()) {
            return;
        }

        foreach ($this->defaultFeeTypes() as $payload) {
            PreschoolFeeType::query()->create($payload);
        }
    }

    private function ensureDefaultPaymentMethods(): void
    {
        if (PreschoolPaymentMethod::withTrashed()->exists()) {
            return;
        }

        foreach ($this->defaultPaymentMethods() as $payload) {
            PreschoolPaymentMethod::query()->create($payload);
        }
    }

    private function ensureDefaultBillingRules(): void
    {
        if (PreschoolBillingRule::query()->exists()) {
            return;
        }

        foreach ($this->defaultBillingRules() as $payload) {
            PreschoolBillingRule::query()->create($payload);
        }
    }

    private function normalizeSettingsPayload(array $data): array
    {
        return [
            'invoice_prefix' => strtoupper(trim((string) Arr::get($data, 'invoice_prefix', self::DEFAULT_SETTINGS['invoice_prefix']))),
            'receipt_prefix' => strtoupper(trim((string) Arr::get($data, 'receipt_prefix', self::DEFAULT_SETTINGS['receipt_prefix']))),
            'next_invoice_number' => max(1, (int) Arr::get($data, 'next_invoice_number', self::DEFAULT_SETTINGS['next_invoice_number'])),
            'next_receipt_number' => max(1, (int) Arr::get($data, 'next_receipt_number', self::DEFAULT_SETTINGS['next_receipt_number'])),
            'late_fee_enabled' => (bool) Arr::get($data, 'late_fee_enabled', self::DEFAULT_SETTINGS['late_fee_enabled']),
            'late_fee_type' => in_array(Arr::get($data, 'late_fee_type', self::DEFAULT_SETTINGS['late_fee_type']), [PreschoolPaymentSetting::LATE_FEE_FIXED, PreschoolPaymentSetting::LATE_FEE_PERCENTAGE], true)
                ? Arr::get($data, 'late_fee_type', self::DEFAULT_SETTINGS['late_fee_type'])
                : self::DEFAULT_SETTINGS['late_fee_type'],
            'late_fee_amount' => max(0, (float) Arr::get($data, 'late_fee_amount', self::DEFAULT_SETTINGS['late_fee_amount'])),
            'grace_period_days' => max(0, (int) Arr::get($data, 'grace_period_days', self::DEFAULT_SETTINGS['grace_period_days'])),
            'proration_enabled' => (bool) Arr::get($data, 'proration_enabled', self::DEFAULT_SETTINGS['proration_enabled']),
        ];
    }

    private function normalizeFeeTypePayload(array $data, bool $creating = true): array
    {
        $payload = [
            'name' => trim((string) Arr::get($data, 'name', '')),
            'code' => $this->normalizeCode(Arr::get($data, 'code', '')),
            'description' => $this->normalizeNullableString(Arr::get($data, 'description')),
            'default_amount' => max(0, (float) Arr::get($data, 'default_amount', 0)),
            'is_required' => (bool) Arr::get($data, 'is_required', false),
            'is_active' => (bool) Arr::get($data, 'is_active', true),
            'sort_order' => (int) Arr::get($data, 'sort_order', 0),
        ];

        if (! $creating && $payload['code'] === '') {
            $payload['code'] = null;
        }

        return $payload;
    }

    private function normalizePaymentMethodPayload(array $data, bool $creating = true): array
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

    private function normalizeBillingRulePayload(array $data): array
    {
        return [
            'rule_name' => trim((string) Arr::get($data, 'rule_name', '')),
            'rule_code' => $this->normalizeCode(Arr::get($data, 'rule_code', '')),
            'rule_value' => trim((string) Arr::get($data, 'rule_value', '')),
            'description' => $this->normalizeNullableString(Arr::get($data, 'description')),
            'is_active' => (bool) Arr::get($data, 'is_active', true),
        ];
    }

    private function findFeeTypeOrFail(PreschoolFeeType|string|int $feeType, bool $includeTrashed = false): PreschoolFeeType
    {
        $query = PreschoolFeeType::query();
        if ($includeTrashed) {
            $query->withTrashed();
        }

        $record = $feeType instanceof PreschoolFeeType ? $feeType : $query->find($feeType);

        if (! $record) {
            abort(404, 'Fee type not found.');
        }

        return $record;
    }

    private function findPaymentMethodOrFail(PreschoolPaymentMethod|string|int $method, bool $includeTrashed = false): PreschoolPaymentMethod
    {
        $query = PreschoolPaymentMethod::query();
        if ($includeTrashed) {
            $query->withTrashed();
        }

        $record = $method instanceof PreschoolPaymentMethod ? $method : $query->find($method);

        if (! $record) {
            abort(404, 'Payment method not found.');
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
                ->orWhere('rule_name', 'like', $like)
                ->orWhere('rule_code', 'like', $like)
                ->orWhere('rule_value', 'like', $like);
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
