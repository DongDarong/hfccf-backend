<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreschoolSettingsBackbone extends Model
{
    protected $table = 'preschool_settings_backbone';

    protected $fillable = [
        'key',
        'payload',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
