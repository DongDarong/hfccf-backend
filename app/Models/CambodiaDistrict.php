<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CambodiaDistrict extends Model
{
    protected $table = 'cambodia_districts';

    protected $fillable = [
        'province_id',
        'code',
        'name_kh',
        'name_en',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(CambodiaProvince::class, 'province_id');
    }

    public function communes(): HasMany
    {
        return $this->hasMany(CambodiaCommune::class, 'district_id');
    }

    public function villages(): HasMany
    {
        return $this->hasMany(CambodiaVillage::class, 'district_id');
    }
}
