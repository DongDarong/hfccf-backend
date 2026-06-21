<?php

namespace App\Support;

use App\Models\PreschoolAttendanceSetting;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolSchoolCalendarEvent;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PreschoolAttendanceConfigurationService
{
    public const DEFAULT_LATE_THRESHOLD_MINUTES = 15;
    public const DEFAULT_HALF_DAY_THRESHOLD_MINUTES = 180;
    public const DEFAULT_ABSENCE_ALERT_DAYS = 3;

    public const DEFAULT_WEEK_CONFIGURATION = [
        'monday_enabled' => true,
        'tuesday_enabled' => true,
        'wednesday_enabled' => true,
        'thursday_enabled' => true,
        'friday_enabled' => true,
        'saturday_enabled' => false,
        'sunday_enabled' => false,
    ];

    public function getSettings(): PreschoolAttendanceSetting
    {
        $record = PreschoolAttendanceSetting::query()->first();

        if ($record) {
            return $record;
        }

        return new PreschoolAttendanceSetting($this->defaultSettings());
    }

    public function updateSettings(array $data, ?User $actor = null): PreschoolAttendanceSetting
    {
        return DB::transaction(function () use ($data, $actor): PreschoolAttendanceSetting {
            $record = PreschoolAttendanceSetting::query()->first();
            $payload = $this->normalizeSettingsPayload($data);
            $payload['updated_by'] = $actor?->id;

            if ($record) {
                $record->fill($payload);
                if ($actor?->id && ! $record->created_by) {
                    $record->created_by = $actor->id;
                }
                $record->save();

                return $record->refresh();
            }

            $payload['created_by'] = $actor?->id;

            return PreschoolAttendanceSetting::query()->create($payload);
        });
    }

    public function getAttendanceSummary(): array
    {
        $settings = $this->getSettings();
        $schoolWeek = $this->getSchoolWeekConfiguration($settings);
        $enabledDays = $schoolWeek['enabled_days'];

        return [
            'late_threshold_minutes' => $this->getLateThreshold($settings),
            'absence_alert_days' => $this->getAbsenceAlertDays($settings),
            'school_days_per_week' => count($enabledDays),
            'calendar_events_count' => PreschoolSchoolCalendarEvent::query()->count(),
            'school_week' => $enabledDays,
        ];
    }

    public function listCalendarEvents(array $filters = []): LengthAwarePaginator
    {
        $query = PreschoolSchoolCalendarEvent::query()->with('academicYear')->orderBy('start_date')->orderBy('id');

        if (($status = trim((string) Arr::get($filters, 'status', ''))) !== '') {
            $query->where('status', $status);
        }

        if (($type = trim((string) Arr::get($filters, 'type', ''))) !== '') {
            $query->where('type', $type);
        }

        if (($academicYearId = trim((string) Arr::get($filters, 'academic_year_id', ''))) !== '') {
            $query->where('academic_year_id', $academicYearId);
        }

        if (($search = trim((string) Arr::get($filters, 'search', ''))) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(static function (Builder $builder) use ($like): void {
                $builder->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('type', 'like', $like);
            });
        }

        if (($date = trim((string) Arr::get($filters, 'date', ''))) !== '') {
            $query->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date);
        }

        $page = max((int) Arr::get($filters, 'page', 1), 1);
        $perPage = min(max((int) Arr::get($filters, 'per_page', 20), 1), 100);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function createCalendarEvent(array $data, ?User $actor = null): PreschoolSchoolCalendarEvent
    {
        return DB::transaction(function () use ($data, $actor): PreschoolSchoolCalendarEvent {
            $payload = $this->normalizeCalendarEventPayload($data);
            $payload['created_by'] = $actor?->id;
            $payload['updated_by'] = $actor?->id;

            $this->assertCalendarEventWithinAcademicYear(
                (int) $payload['academic_year_id'],
                $payload['start_date'],
                $payload['end_date'],
            );

            return PreschoolSchoolCalendarEvent::query()->create($payload);
        });
    }

    public function updateCalendarEvent(PreschoolSchoolCalendarEvent $event, array $data, ?User $actor = null): PreschoolSchoolCalendarEvent
    {
        return DB::transaction(function () use ($event, $data, $actor): PreschoolSchoolCalendarEvent {
            $payload = $this->normalizeCalendarEventPayload($data, false, $event);

            if (array_key_exists('academic_year_id', $payload) || array_key_exists('start_date', $payload) || array_key_exists('end_date', $payload)) {
                $academicYearId = (int) ($payload['academic_year_id'] ?? $event->academic_year_id);
                $startDate = $payload['start_date'] ?? $event->start_date?->toDateString();
                $endDate = $payload['end_date'] ?? $event->end_date?->toDateString();

                $this->assertCalendarEventWithinAcademicYear($academicYearId, $startDate, $endDate);
            }

            $payload['updated_by'] = $actor?->id;
            $event->fill($payload);
            $event->save();

            return $event->refresh();
        });
    }

    public function archiveCalendarEvent(PreschoolSchoolCalendarEvent $event, ?User $actor = null): PreschoolSchoolCalendarEvent
    {
        $event->status = PreschoolSchoolCalendarEvent::STATUS_ARCHIVED;
        $event->updated_by = $actor?->id;
        $event->save();

        return $event->refresh();
    }

    public function getLateThreshold(?PreschoolAttendanceSetting $settings = null): int
    {
        return (int) ($settings?->late_threshold_minutes ?? $this->getSettings()->late_threshold_minutes ?? self::DEFAULT_LATE_THRESHOLD_MINUTES);
    }

    public function getHalfDayThreshold(?PreschoolAttendanceSetting $settings = null): int
    {
        return (int) ($settings?->half_day_threshold_minutes ?? $this->getSettings()->half_day_threshold_minutes ?? self::DEFAULT_HALF_DAY_THRESHOLD_MINUTES);
    }

    public function getAbsenceAlertDays(?PreschoolAttendanceSetting $settings = null): int
    {
        return (int) ($settings?->absence_alert_days ?? $this->getSettings()->absence_alert_days ?? self::DEFAULT_ABSENCE_ALERT_DAYS);
    }

    public function getSchoolWeekConfiguration(?PreschoolAttendanceSetting $settings = null): array
    {
        $settings = $settings ?? $this->getSettings();
        $configuration = [
            'monday_enabled' => (bool) $settings->monday_enabled,
            'tuesday_enabled' => (bool) $settings->tuesday_enabled,
            'wednesday_enabled' => (bool) $settings->wednesday_enabled,
            'thursday_enabled' => (bool) $settings->thursday_enabled,
            'friday_enabled' => (bool) $settings->friday_enabled,
            'saturday_enabled' => (bool) $settings->saturday_enabled,
            'sunday_enabled' => (bool) $settings->sunday_enabled,
        ];

        $enabledDays = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            if ($configuration[$day.'_enabled']) {
                $enabledDays[] = $day;
            }
        }

        $configuration['enabled_days'] = $enabledDays;
        $configuration['school_days_per_week'] = count($enabledDays);

        return $configuration;
    }

    public function isSchoolDay(CarbonInterface|string $date): bool
    {
        $date = $date instanceof CarbonInterface ? Carbon::instance($date) : Carbon::parse($date);
        $dayKey = strtolower($date->format('l'));
        $schoolWeek = $this->getSchoolWeekConfiguration();

        return (bool) ($schoolWeek[$dayKey.'_enabled'] ?? false) && ! $this->isHoliday($date);
    }

    public function isHoliday(CarbonInterface|string $date): bool
    {
        $date = $date instanceof CarbonInterface ? Carbon::instance($date) : Carbon::parse($date);

        return PreschoolSchoolCalendarEvent::query()
            ->where('status', PreschoolSchoolCalendarEvent::STATUS_ACTIVE)
            ->whereIn('type', [PreschoolSchoolCalendarEvent::TYPE_HOLIDAY, PreschoolSchoolCalendarEvent::TYPE_CLOSURE])
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->exists();
    }

    private function defaultSettings(): array
    {
        return [
            'late_threshold_minutes' => self::DEFAULT_LATE_THRESHOLD_MINUTES,
            'half_day_threshold_minutes' => self::DEFAULT_HALF_DAY_THRESHOLD_MINUTES,
            'absence_alert_days' => self::DEFAULT_ABSENCE_ALERT_DAYS,
            'guardian_alert_enabled' => true,
            'teacher_alert_enabled' => true,
            'admin_alert_enabled' => true,
            ...self::DEFAULT_WEEK_CONFIGURATION,
        ];
    }

    private function normalizeSettingsPayload(array $data): array
    {
        return [
            'late_threshold_minutes' => (int) Arr::get($data, 'late_threshold_minutes', self::DEFAULT_LATE_THRESHOLD_MINUTES),
            'half_day_threshold_minutes' => (int) Arr::get($data, 'half_day_threshold_minutes', self::DEFAULT_HALF_DAY_THRESHOLD_MINUTES),
            'absence_alert_days' => (int) Arr::get($data, 'absence_alert_days', self::DEFAULT_ABSENCE_ALERT_DAYS),
            'guardian_alert_enabled' => (bool) Arr::get($data, 'guardian_alert_enabled', true),
            'teacher_alert_enabled' => (bool) Arr::get($data, 'teacher_alert_enabled', true),
            'admin_alert_enabled' => (bool) Arr::get($data, 'admin_alert_enabled', true),
            'monday_enabled' => (bool) Arr::get($data, 'monday_enabled', true),
            'tuesday_enabled' => (bool) Arr::get($data, 'tuesday_enabled', true),
            'wednesday_enabled' => (bool) Arr::get($data, 'wednesday_enabled', true),
            'thursday_enabled' => (bool) Arr::get($data, 'thursday_enabled', true),
            'friday_enabled' => (bool) Arr::get($data, 'friday_enabled', true),
            'saturday_enabled' => (bool) Arr::get($data, 'saturday_enabled', false),
            'sunday_enabled' => (bool) Arr::get($data, 'sunday_enabled', false),
        ];
    }

    private function normalizeCalendarEventPayload(array $data, bool $creating = true, ?PreschoolSchoolCalendarEvent $existingEvent = null): array
    {
        $payload = [];

        foreach (['academic_year_id', 'title', 'description', 'type', 'start_date', 'end_date', 'status'] as $field) {
            if ($creating || array_key_exists($field, $data)) {
                $payload[$field] = $data[$field] ?? ($existingEvent?->{$field} ?? null);
            }
        }

        if (isset($payload['academic_year_id'])) {
            $payload['academic_year_id'] = (int) $payload['academic_year_id'];
        }

        foreach (['title', 'description', 'type', 'status'] as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== null) {
                $payload[$field] = trim((string) $payload[$field]);
            }
        }

        if (($payload['status'] ?? null) === null || $payload['status'] === '') {
            $payload['status'] = PreschoolSchoolCalendarEvent::STATUS_ACTIVE;
        }

        return $payload;
    }

    private function assertCalendarEventWithinAcademicYear(int $academicYearId, mixed $startDate, mixed $endDate): void
    {
        $academicYear = PreschoolAcademicYear::query()->find($academicYearId);
        if (! $academicYear) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'The selected academic year is invalid.',
            ]);
        }

        $start = Carbon::parse((string) $startDate);
        $end = Carbon::parse((string) $endDate);

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => 'The end date must be after or equal to the start date.',
            ]);
        }

        if ($academicYear->start_date && $academicYear->end_date) {
            $yearStart = Carbon::parse($academicYear->start_date->toDateString());
            $yearEnd = Carbon::parse($academicYear->end_date->toDateString());

            if ($start->lt($yearStart) || $end->gt($yearEnd)) {
                throw ValidationException::withMessages([
                    'start_date' => 'The event dates must fall within the selected academic year.',
                    'end_date' => 'The event dates must fall within the selected academic year.',
                ]);
            }
        }
    }
}
