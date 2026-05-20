<?php

namespace App\Support;

use App\Models\PreschoolClass;
use App\Models\PreschoolGuardian;
use App\Models\PreschoolStudent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class PreschoolGuardianService
{
    /**
     * Keep guardian access centralized so the Preschool module can reuse the
     * same teacher-scoped visibility rules for guardians and contacts.
     */
    public function listGuardians(User $user, array $filters = []): LengthAwarePaginator
    {
        $this->ensureAdminAccess($user);

        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 100);
        $status = trim((string) ($filters['status'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $sortBy = (string) ($filters['sort_by'] ?? 'full_name');
        $sortDirection = strtolower((string) ($filters['sort_direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = PreschoolGuardian::query()
            ->withCount([
                'studentGuardians as relationships_count',
                'activeStudentGuardians as active_relationships_count' => static function (Builder $builder): void {
                    $builder->where('status', 'active');
                },
            ]);

        if ($search !== '') {
            $query->where(static function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('full_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('secondary_phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('status', 'like', $like);
            });
        }

        if ($status !== '' && in_array($status, PreschoolGuardianStatus::values(), true)) {
            $query->where('status', $status);
        }

        $sortColumn = match ($sortBy) {
            'phone' => 'phone',
            'status' => 'status',
            'created_at' => 'created_at',
            default => 'full_name',
        };

        return $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function createGuardian(User $user, array $data): PreschoolGuardian
    {
        $this->ensureAdminAccess($user);

        $guardian = PreschoolGuardian::query()->create([
            'full_name' => trim((string) $data['full_name']),
            'phone' => trim((string) $data['phone']),
            'secondary_phone' => $this->nullableText($data['secondary_phone'] ?? null),
            'email' => $this->nullableText($data['email'] ?? null),
            'address' => $this->nullableText($data['address'] ?? null),
            'occupation' => $this->nullableText($data['occupation'] ?? null),
            'national_id' => $this->nullableText($data['national_id'] ?? null),
            'status' => $data['status'] ?? PreschoolGuardianStatus::ACTIVE,
            'notes' => $this->nullableText($data['notes'] ?? null),
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        return $guardian->fresh();
    }

    public function updateGuardian(User $user, PreschoolGuardian $guardian, array $data): PreschoolGuardian
    {
        $this->ensureAdminAccess($user);

        foreach (['full_name', 'phone', 'secondary_phone', 'email', 'address', 'occupation', 'national_id', 'status', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                if (in_array($field, ['full_name', 'phone'], true)) {
                    $guardian->{$field} = trim((string) $data[$field]);

                    continue;
                }

                if ($field === 'status') {
                    $guardian->status = trim((string) $data[$field]) ?: PreschoolGuardianStatus::ACTIVE;

                    continue;
                }

                $guardian->{$field} = $this->nullableText($data[$field]);
            }
        }

        $guardian->updated_by_user_id = $user->id;
        $guardian->save();

        return $guardian->fresh();
    }

    public function archiveGuardian(User $user, PreschoolGuardian $guardian): PreschoolGuardian
    {
        $this->ensureAdminAccess($user);

        return DB::transaction(function () use ($user, $guardian): PreschoolGuardian {
            $guardian->status = PreschoolGuardianStatus::ARCHIVED;
            $guardian->updated_by_user_id = $user->id;
            $guardian->save();

            $guardian->studentGuardians()
                ->where('status', PreschoolGuardianStatus::ACTIVE)
                ->update([
                    'status' => PreschoolGuardianStatus::ARCHIVED,
                    'is_primary' => false,
                    'can_pickup' => false,
                    'ends_at' => now()->toDateString(),
                    'updated_by_user_id' => $user->id,
                    'updated_at' => now(),
                ]);

            return $guardian->fresh();
        });
    }

    public function ensureUserCanAccessStudent(?User $user, PreschoolStudent $student): void
    {
        abort_unless($user, Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return;
        }

        abort_unless($user->role_code === 'teacher-preschool', Response::HTTP_FORBIDDEN, 'Forbidden.');

        $classIds = PreschoolClass::query()
            ->where('teacher_user_id', $user->id)
            ->pluck('id')
            ->all();

        abort_if($classIds === [], Response::HTTP_FORBIDDEN, 'Forbidden.');

        abort_if(
            ! $student->classes()
                ->whereIn('preschool_classes.id', $classIds)
                ->wherePivot('status', 'active')
                ->exists(),
            Response::HTTP_FORBIDDEN,
            'Forbidden.',
        );
    }

    public function activeClassIdsForTeacher(User $user): Collection
    {
        return PreschoolClass::query()
            ->where('teacher_user_id', $user->id)
            ->pluck('id');
    }

    private function ensureAdminAccess(?User $user): void
    {
        abort_unless($user, Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        abort_unless(in_array($user->role_code, ['superadmin', 'adminpreschool'], true), Response::HTTP_FORBIDDEN, 'Forbidden.');
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
