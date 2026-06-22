<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CambodiaProvince extends Model
{
    protected $table = 'cambodia_provinces';

    protected $fillable = [
        'code',
        'name_kh',
        'name_en',
    ];

    public function districts(): HasMany
    {
        return $this->hasMany(CambodiaDistrict::class, 'province_id');
    }

    public function communes(): HasMany
    {
        return $this->hasMany(CambodiaCommune::class, 'province_id');
    }

    public function villages(): HasMany
    {
        return $this->hasMany(CambodiaVillage::class, 'province_id');
    }
}
