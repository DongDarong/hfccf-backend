<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class BusinessTimezone
{
    public const TIMEZONE = 'Asia/Phnom_Penh';

    public static function businessTimezone(): string
    {
        return self::TIMEZONE;
    }

    public static function nowInBusinessTimezone(): Carbon
    {
        return Carbon::now('UTC')->setTimezone(self::TIMEZONE);
    }

    public static function parseLocalDateTimeToUtc(string $date, string $time): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.self::normalizeTime($time), self::TIMEZONE)
            ->setTimezone('UTC');
    }

    public static function businessDate(): string
    {
        return self::nowInBusinessTimezone()->toDateString();
    }

    public static function isWithinHalfOpenWindow(Carbon|string $now, Carbon|string $opensAt, Carbon|string $closesAt): bool
    {
        $now = self::asUtc($now);
        $opensAt = self::asUtc($opensAt);
        $closesAt = self::asUtc($closesAt);

        return $now->greaterThanOrEqualTo($opensAt) && $now->lessThan($closesAt);
    }

    private static function asUtc(Carbon|string $value): Carbon
    {
        return ($value instanceof Carbon ? $value->copy() : Carbon::parse($value))->setTimezone('UTC');
    }

    private static function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
