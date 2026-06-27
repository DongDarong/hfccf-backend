<?php

namespace App\Support\PreschoolReporting;

use Illuminate\Support\Collection;

class PreschoolAnalyticsService
{
    public const HEALTHY = 'healthy';

    public const WARNING = 'warning';

    public const CRITICAL = 'critical';

    public const NEUTRAL = 'neutral';

    public function percentage(int|float|null $numerator, int|float|null $denominator, int $precision = 2): ?float
    {
        $numerator = $this->normalizeNumber($numerator);
        $denominator = $this->normalizeNumber($denominator);

        if ($denominator <= 0.0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, $precision);
    }

    public function growth(int|float|null $current, int|float|null $previous, int $precision = 2): ?float
    {
        $current = $this->normalizeNumber($current);
        $previous = $this->normalizeNumber($previous);

        if ($previous === 0.0) {
            return $current === 0.0 ? 0.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, $precision);
    }

    /**
     * @return array{current: float|int|null, previous: float|int|null, delta: float|int|null, percent: float|null, trend: string, comparison: string}
     */
    public function comparison(
        int|float|null $current,
        int|float|null $previous,
        string $comparison,
        int $precision = 2,
    ): array {
        if ($current === null || $previous === null) {
            return [
                'current' => $current,
                'previous' => $previous,
                'delta' => null,
                'percent' => null,
                'trend' => 'neutral',
                'comparison' => $comparison,
            ];
        }

        $currentValue = $this->normalizeNumber($current);
        $previousValue = $this->normalizeNumber($previous);
        $delta = round($currentValue - $previousValue, $precision);

        return [
            'current' => $this->compactNumber($currentValue),
            'previous' => $this->compactNumber($previousValue),
            'delta' => $this->compactNumber($delta),
            'percent' => $this->growth($currentValue, $previousValue, $precision),
            'trend' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'neutral'),
            'comparison' => $comparison,
        ];
    }

    public function healthStatus(
        int|float|null $value,
        int|float $warningThreshold,
        int|float $criticalThreshold,
        bool $higherIsWorse = true,
    ): string {
        if ($value === null) {
            return self::NEUTRAL;
        }

        $value = $this->normalizeNumber($value);
        if ($higherIsWorse) {
            if ($value >= $criticalThreshold) {
                return self::CRITICAL;
            }

            return $value >= $warningThreshold ? self::WARNING : self::HEALTHY;
        }

        if ($value < $criticalThreshold) {
            return self::CRITICAL;
        }

        return $value < $warningThreshold ? self::WARNING : self::HEALTHY;
    }

    /**
     * @param  array<int, string>  $statuses
     */
    public function worstHealthStatus(array $statuses): string
    {
        $rank = [
            self::NEUTRAL => 0,
            self::HEALTHY => 1,
            self::WARNING => 2,
            self::CRITICAL => 3,
        ];

        return collect($statuses)
            ->filter(fn (string $status): bool => isset($rank[$status]))
            ->sortByDesc(fn (string $status): int => $rank[$status])
            ->first() ?? self::NEUTRAL;
    }

    /**
     * @param  iterable<array<string, mixed>>|Collection<int, array<string, mixed>>  $items
     * @return array<int, array{label: string, value: float|int, raw: array<string, mixed>}>
     */
    public function series(iterable|Collection $items, string $labelKey = 'label', string $valueKey = 'value'): array
    {
        $collection = $items instanceof Collection ? $items : collect($items);

        return $collection->map(function (array $item) use ($labelKey, $valueKey): array {
            return [
                'label' => trim((string) ($item[$labelKey] ?? '')),
                'value' => is_numeric($item[$valueKey] ?? null) ? $item[$valueKey] + 0 : 0,
                'raw' => $item,
            ];
        })->values()->all();
    }

    /**
     * @param  iterable<array<string, mixed>>|Collection<int, array<string, mixed>>  $items
     * @return array<int, array{label: string, value: float|int, raw: array<string, mixed>}>
     */
    public function topRows(iterable|Collection $items, string $labelKey = 'label', string $valueKey = 'value', int $limit = 8): array
    {
        $collection = $items instanceof Collection ? $items : collect($items);

        return $collection
            ->sortByDesc(function (array $item) use ($valueKey): float {
                return $this->normalizeNumber($item[$valueKey] ?? 0);
            })
            ->take($limit)
            ->values()
            ->map(function (array $item) use ($labelKey, $valueKey): array {
                return [
                    'label' => trim((string) ($item[$labelKey] ?? '')),
                    'value' => is_numeric($item[$valueKey] ?? null) ? $item[$valueKey] + 0 : 0,
                    'raw' => $item,
                ];
            })
            ->all();
    }

    /**
     * @param  iterable<array<string, mixed>>|Collection<int, array<string, mixed>>  $items
     * @return array<int, array{label: string, value: float|int, raw: array<string, mixed>}>
     */
    public function distribution(iterable|Collection $items, string $key, string $labelKey = 'label', string $valueKey = 'value'): array
    {
        $collection = $items instanceof Collection ? $items : collect($items);

        return $collection
            ->groupBy(fn (array $item): string => trim((string) ($item[$key] ?? 'unknown')) ?: 'unknown')
            ->map(function (Collection $group, string $bucket) use ($labelKey, $valueKey): array {
                $first = $group->first() ?? [];

                return [
                    'label' => trim((string) ($first[$labelKey] ?? $bucket)),
                    'value' => $group->sum(fn (array $item): float => $this->normalizeNumber($item[$valueKey] ?? 0)),
                    'raw' => [
                        'bucket' => $bucket,
                        'items' => $group->values()->all(),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeNumber(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function compactNumber(float $value): float|int
    {
        return floor($value) === $value ? (int) $value : $value;
    }
}
