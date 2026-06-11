<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SportDivision extends Model
{
    protected $table = 'sport_divisions';

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function teams(): HasMany
    {
        return $this->hasMany(SportTeam::class, 'division_id');
    }
}
