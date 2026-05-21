<?php

namespace App\Models;

use App\Support\ImageStorage;
use App\Traits\HasAuditFields;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'id',
    'first_name',
    'last_name',
    'username',
    'email',
    'phone',
    'role_code',
    'department_code',
    'bio',
    'status',
    'avatar',
    'password',
    'email_verified_at',
    'last_login_at',
    'remember_token',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasAuditFields, HasFactory, Notifiable, SoftDeletes;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->resolveAvatarUrl($value),
        );
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_code', 'code');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_code', 'code');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'user_permissions',
            'user_id',
            'permission_code',
            'id',
            'code',
        );
    }

    public function coachedSportTeams(): HasMany
    {
        return $this->hasMany(SportTeam::class, 'coach_user_id', 'id');
    }

    public function coachTeamAssignments(): HasMany
    {
        return $this->hasMany(CoachTeamAssignment::class, 'coach_user_id', 'id');
    }

    public function activeCoachTeamAssignments(): HasMany
    {
        return $this->hasMany(CoachTeamAssignment::class, 'coach_user_id', 'id')->where('status', 'active');
    }

    /**
     * Preschool timetable lookups stay teacher-scoped through the shared User
     * model because teacher-preschool accounts are real system users.
     */
    public function preschoolScheduleEntries(): HasMany
    {
        return $this->hasMany(PreschoolScheduleEntry::class, 'teacher_user_id', 'id');
    }

    /**
     * Guardian accounts stay linked to the shared User model so portal access
     * can be revoked without turning every guardian record into a login.
     */
    public function guardianPortalAccount(): HasMany
    {
        return $this->hasMany(PreschoolGuardianPortalAccount::class, 'user_id', 'id');
    }

    private function resolveAvatarUrl(mixed $value): ?string
    {
        return ImageStorage::url($value);
    }

    protected static function booted(): void
    {
        static::deleted(function (User $user) {
            // Only archive if it's a permanent deletion (forceDelete)
            // or if the user wants every soft-delete archived too.
            // Given the request, we'll archive on any deletion event.
            DeletedUser::create([
                'original_id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'role_code' => $user->role_code,
                'department_code' => $user->department_code,
                'bio' => $user->bio,
                'status' => $user->status,
                'avatar' => $user->avatar,
                'email_verified_at' => $user->email_verified_at,
                'last_login_at' => $user->last_login_at,
                'user_created_at' => $user->created_at,
                'user_updated_at' => $user->updated_at,
                'deleted_at' => now(),
                'deleted_by' => auth()->id(),
                'original_data' => $user->makeHidden(['password', 'remember_token'])->toArray(),
            ]);
        });
    }
}
