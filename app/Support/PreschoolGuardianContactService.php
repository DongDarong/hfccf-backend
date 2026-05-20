<?php

namespace App\Support;

use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
use App\Models\User;
use Illuminate\Support\Collection;

final class PreschoolGuardianContactService
{
    /**
     * Emergency contacts only expose active relationships so the classroom
     * team sees the live pickup order without historical noise.
     */
    public function forStudent(User $user, PreschoolStudent $student): Collection
    {
        app(PreschoolGuardianService::class)->ensureUserCanAccessStudent($user, $student);

        return PreschoolStudentGuardian::query()
            ->with(['guardian'])
            ->where('student_id', $student->id)
            ->where('status', PreschoolGuardianStatus::ACTIVE)
            ->whereHas('guardian', static function ($query): void {
                $query->where('status', PreschoolGuardianStatus::ACTIVE)
                    ->whereNull('deleted_at');
            })
            ->orderByRaw('CASE WHEN is_primary = 1 THEN 0 ELSE 1 END')
            ->orderByRaw('COALESCE(emergency_priority, 999999) ASC')
            ->orderBy('created_at')
            ->get();
    }
}
