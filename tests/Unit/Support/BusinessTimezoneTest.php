<?php

namespace Tests\Unit\Support;

use App\Support\BusinessTimezone;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BusinessTimezoneTest extends TestCase
{
    public function test_local_cambodia_time_is_converted_to_utc(): void
    {
        $utc = BusinessTimezone::parseLocalDateTimeToUtc('2026-07-25', '08:00');

        $this->assertSame('2026-07-25T01:00:00+00:00', $utc->toIso8601String());
    }

    public function test_attendance_window_is_half_open(): void
    {
        $opens = Carbon::parse('2026-07-25T01:00:00Z');
        $closes = Carbon::parse('2026-07-25T03:00:00Z');

        $this->assertFalse(BusinessTimezone::isWithinHalfOpenWindow($opens->copy()->subSecond(), $opens, $closes));
        $this->assertTrue(BusinessTimezone::isWithinHalfOpenWindow($opens, $opens, $closes));
        $this->assertTrue(BusinessTimezone::isWithinHalfOpenWindow($closes->copy()->subSecond(), $opens, $closes));
        $this->assertFalse(BusinessTimezone::isWithinHalfOpenWindow($closes, $opens, $closes));
    }

    public function test_business_date_uses_phnom_penh(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-24T17:00:00Z'));

        try {
            $this->assertSame('2026-07-25', BusinessTimezone::businessDate());
        } finally {
            Carbon::setTestNow();
        }
    }
}
