<?php

namespace App\Models;

use App\Models\Dsam\FormSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreschoolStudent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'student_code',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'guardian_name',
        'guardian_phone',
        'address',
        'status',
        'student_type',
        'avatar',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $student): void {
            if (blank($student->public_id)) {
                $student->public_id = self::nextPublicId();
            }

            if (blank($student->student_code)) {
                $student->student_code = self::nextStudentCode();
            }
        });
    }

    public static function nextPublicId(): string
    {
        $last = self::query()
            ->withTrashed()
            ->pluck('public_id')
            ->filter(static fn ($value) => is_string($value) && preg_match('/^STU-HFCCF-(\d+)$/', $value) === 1)
            ->map(static function (string $value): int {
                preg_match('/^STU-HFCCF-(\d+)$/', $value, $matches);

                return (int) ($matches[1] ?? 0);
            })
            ->max() ?? 0;

        return 'STU-HFCCF-'.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    public static function nextStudentCode(): string
    {
        $last = self::query()
            ->withTrashed()
            ->pluck('student_code')
            ->filter(static fn ($value) => is_string($value) && preg_match('/^PS-(\d+)$/', $value) === 1)
            ->map(static function (string $value): int {
                preg_match('/^PS-(\d+)$/', $value, $matches);

                return (int) ($matches[1] ?? 0);
            })
            ->max() ?? 0;

        return 'PS-'.str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(
            PreschoolClass::class,
            'preschool_class_students',
            'student_id',
            'class_id',
        )->withPivot(['enrolled_at', 'status'])->withTimestamps();
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(PreschoolAttendanceRecord::class, 'student_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PreschoolPayment::class, 'student_id');
    }

    /**
     * Keep the normalized guardian links alongside legacy guardian text fields
     * so Preschool pages can gradually move to the new contact foundation.
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(
            PreschoolGuardian::class,
            'preschool_student_guardians',
            'student_id',
            'guardian_id',
        )->withPivot([
            'relationship_type',
            'is_primary',
            'can_pickup',
            'emergency_priority',
            'status',
            'starts_at',
            'ends_at',
            'notes',
        ])->withTimestamps();
    }

    public function studentGuardians(): HasMany
    {
        return $this->hasMany(PreschoolStudentGuardian::class, 'student_id');
    }

    public function activeStudentGuardians(): HasMany
    {
        return $this->studentGuardians()->where('status', 'active');
    }

    // ── DSAM extensions ───────────────────────────────────────────────────────

    public function profile(): HasOne
    {
        return $this->hasOne(StudentProfile::class, 'student_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(StudentHistory::class, 'student_id')->orderByDesc('created_at');
    }

    public function dsamSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'student_id');
    }

    public function latestDsamSubmission(): HasOne
    {
        return $this->hasOne(FormSubmission::class, 'student_id')->latestOfMany();
    }
}