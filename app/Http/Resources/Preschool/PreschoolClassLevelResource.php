<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolClassLevel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolClassLevel */
class PreschoolClassLevelResource extends JsonResource
{
    private static function toIsoString(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toISOString();
        }

        return Carbon::parse((string) $value)->toISOString();
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nameEn' => $this->name_en,
            'nameKh' => $this->name_kh,
            'code' => $this->code,
            'sortOrder' => $this->sort_order,
            'isActive' => (bool) $this->is_active,
            'status' => $this->is_active ? 'active' : 'inactive',
            'createdAt' => self::toIsoString($this->created_at),
            'updatedAt' => self::toIsoString($this->updated_at),
            'deletedAt' => self::toIsoString($this->deleted_at),
        ];
    }
}
