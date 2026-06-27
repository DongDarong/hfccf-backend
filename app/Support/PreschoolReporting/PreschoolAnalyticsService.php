<?php

namespace App\Support\PreschoolReporting;

use Illuminate\Support\Collection;

class PreschoolAnalyticsService
{
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
}
