<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CambodiaVillage extends Model
{
    protected $table = 'cambodia_villages';

    protected $fillable = [
        'province_id',
        'district_id',
        'commune_id',
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

    public function commune(): BelongsTo
    {
        return $this->belongsTo(CambodiaCommune::class, 'commune_id');
    }
}
