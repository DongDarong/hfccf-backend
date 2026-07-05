<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class CambodiaLocationLookup
{
    /**
     * Find a location record by exact code first, then by numeric-equivalent
     * code so leading-zero variants work on MySQL/MariaDB.
     *
     * @template TModel of Model
     *
     * @param class-string<TModel> $modelClass
     *
     * @return TModel|null
     */
    public static function findByCodeOrNumericCode(string $modelClass, string $code): ?Model
    {
        $normalized = trim($code);

        if ($normalized === '') {
            return null;
        }

        return $modelClass::query()
            ->where('code', $normalized)
            ->orWhereRaw('CAST(code AS UNSIGNED) = ?', [(int) $normalized])
            ->first();
    }
}
