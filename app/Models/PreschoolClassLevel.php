<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Preschool class levels are the configurable level catalog used by the
 * preschool class editor and enrollment forms.
 */
class PreschoolClassLevel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_kh',
        'code',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function classes(): HasMany
    {
        return $this->hasMany(PreschoolClass::class, 'class_level_id');
    }
}
