<?php

namespace App\Support;

class SportMedia
{
    public static function resolveUrl(mixed $value): ?string
    {
        return ImageStorage::url($value);
    }
}
