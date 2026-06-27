<?php

namespace Tests\Unit\Support;

use App\Support\PreschoolReporting\PreschoolAnalyticsService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PreschoolAnalyticsServiceTest extends TestCase
{
    private PreschoolAnalyticsService $analytics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analytics = app(PreschoolAnalyticsService::class);
    }

    public function test_it_builds_a_normalized_comparison(): void
    {
        $this->assertSame([
            'current' => 96,
            'previous' => 94,
            'delta' => 2,
            'percent' => 2.13,
            'trend' => 'up',
            'comparison' => 'previous_day',
        ], $this->analytics->comparison(96, 94, 'previous_day'));
    }

    public function test_it_returns_a_neutral_comparison_when_a_period_is_missing(): void
    {
        $comparison = $this->analytics->comparison(96, null, 'previous_day');

        $this->assertNull($comparison['delta']);
        $this->assertNull($comparison['percent']);
        $this->assertSame('neutral', $comparison['trend']);
    }

    #[DataProvider('healthStatusProvider')]
    public function test_it_normalizes_health_statuses(
        ?float $value,
        float $warning,
        float $critical,
        bool $higherIsWorse,
        string $expected,
    ): void {
        $this->assertSame(
            $expected,
            $this->analytics->healthStatus($value, $warning, $critical, $higherIsWorse),
        );
    }

    public static function healthStatusProvider(): array
    {
        return [
            'no data' => [null, 1, 3, true, 'neutral'],
            'healthy upper bound' => [0, 1, 3, true, 'healthy'],
            'warning upper bound' => [1, 1, 3, true, 'warning'],
            'critical upper bound' => [3, 1, 3, true, 'critical'],
            'healthy lower bound' => [95, 90, 80, false, 'healthy'],
            'warning lower bound' => [85, 90, 80, false, 'warning'],
            'critical lower bound' => [70, 90, 80, false, 'critical'],
        ];
    }
}
