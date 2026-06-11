<?php

namespace App\Models;

use App\Models\Dsam\FormTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'name_kh',
        'type',
        'logo',
        'province',
        'address',
        'email',
        'phone',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings'  => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $org): void {
            if (blank($org->uuid)) {
                $org->uuid = (string) Str::uuid();
            }
        });
    }

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function currentAcademicYear(): ?AcademicYear
    {
        return $this->academicYears()->where('is_current', true)->first();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function formTemplates(): HasMany
    {
        return $this->hasMany(FormTemplate::class);
    }
}
