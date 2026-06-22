<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CambodiaCommune extends Model
{
    protected $table = 'cambodia_communes';

    protected $fillable = [
        'province_id',
        'district_id',
        'code',
        'name_kh',
        'name_en',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(CambodiaProvince::class, 'province_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(CambodiaDistrict::class, 'district_id');
    }

    public function villages(): HasMany
    {
        return $this->hasMany(CambodiaVillage::class, 'commune_id');
    }
}
