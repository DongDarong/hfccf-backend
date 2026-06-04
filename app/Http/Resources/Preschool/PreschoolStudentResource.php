<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolStudent;
use App\Support\ImageStorage;
use App\Support\PreschoolGuardianSnapshotService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolStudent */
class PreschoolStudentResource extends JsonResource
{
    private static function toIsoString(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toISOString();
        }

        return Carbon::parse((string) $value)->toISOString();
    }

    public function toArray(Request $request): array
    {
        // Resolve the underlying model so the canonical guardian snapshot
        // always works with the real PreschoolStudent instance, not the
        // JsonResource wrapper. This keeps normalized guardian data stable
        // without breaking legacy student CRUD responses.
        $guardianSnapshot = app(PreschoolGuardianSnapshotService::class)->preferredGuardianSnapshot($this->resource);

        $activeClassAssignments = $this->whenLoaded('classes', function () {
            return $this->classes
                ->filter(static fn ($class) => ($class->pivot->status ?? 'active') === 'active')
                ->values()
                ->map(static function ($class) {
                    return [
                        'id' => $class->id,
                        'code' => $class->code,
                        'name' => $class->name,
                        'teacherUserId' => $class->teacher_user_id,
                        'teacherDisplayName' => $class->teacher_display_name ?: ($class->relationLoaded('teacher') ? $class->teacher?->name : null),
                        'status' => $class->pivot->status ?? 'active',
                        // Pivot timestamps can arrive as strings when the relation
                        // is reloaded after assignment updates. Normalize them
                        // defensively so student CRUD responses never crash.
                        'enrolledAt' => self::toIsoString($class->pivot->enrolled_at),
                        'academicYear' => $class->pivot->academic_year,
                        'termLabel' => $class->pivot->term_label,
                        'academicYearId' => $class->pivot->academic_year_id,
                        'termId' => $class->pivot->term_id,
                        'enrollmentStatus' => $class->pivot->enrollment_status ?? $class->pivot->status ?? 'active',
                        'enrollmentStartedAt' => self::toIsoString($class->pivot->enrollment_started_at),
                        'enrollmentEndedAt' => self::toIsoString($class->pivot->enrollment_ended_at),
                        'updatedAt' => self::toIsoString($class->pivot->updated_at),
                    ];
                })
                ->all();
        }, []);

        $allClassAssignments = $this->whenLoaded('classes', function () {
            return $this->classes
                ->values()
                ->map(static function ($class) {
                    return [
                        'id' => $class->id,
                        'code' => $class->code,
                        'name' => $class->name,
                        'teacherUserId' => $class->teacher_user_id,
                        'teacherDisplayName' => $class->teacher_display_name ?: ($class->relationLoaded('teacher') ? $class->teacher?->name : null),
                        'status' => $class->pivot->status ?? 'active',
                        'enrolledAt' => self::toIsoString($class->pivot->enrolled_at),
                        'academicYear' => $class->pivot->academic_year,
                        'termLabel' => $class->pivot->term_label,
                        'academicYearId' => $class->pivot->academic_year_id,
                        'termId' => $class->pivot->term_id,
                        'enrollmentStatus' => $class->pivot->enrollment_status ?? $class->pivot->status ?? 'active',
                        'enrollmentStartedAt' => self::toIsoString($class->pivot->enrollment_started_at),
                        'enrollmentEndedAt' => self::toIsoString($class->pivot->enrollment_ended_at),
                        'updatedAt' => self::toIsoString($class->pivot->updated_at),
                    ];
                })
                ->all();
        }, []);

        return [
            'id' => $this->id,
            'publicId' => $this->public_id,
            'studentCode' => $this->student_code,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'fullName' => trim($this->first_name.' '.$this->last_name),
            'gender' => $this->gender,
            'dateOfBirth' => $this->date_of_birth?->toDateString(),
            // Prefer the normalized guardian snapshot so the compatibility
            // columns do not override active relationships.
            'guardianName' => $guardianSnapshot['guardianName'] ?? $this->guardian_name,
            'guardianPhone' => $guardianSnapshot['guardianPhone'] ?? $this->guardian_phone,
            'guardianSource' => $guardianSnapshot['source'] ?? 'legacy',
            'address' => $this->address,
            'status' => $this->status,
            'studentType' => $this->student_type,
            'avatarUrl' => ImageStorage::url($this->avatar),
            // Active class assignments power the current roster count, while the
            // full classAssignments payload preserves historical transfers and
            // deactivated links for the assignment workflow page.
            'classesCount' => $this->whenLoaded('classes', fn () => $this->classes->filter(static fn ($class) => ($class->pivot->status ?? 'active') === 'active')->count(), 0),
            'classes' => $activeClassAssignments,
            'classAssignments' => $allClassAssignments,
            'createdAt' => self::toIsoString($this->created_at),
            'updatedAt' => self::toIsoString($this->updated_at),
            'deletedAt' => self::toIsoString($this->deleted_at),
        ];
    }
}