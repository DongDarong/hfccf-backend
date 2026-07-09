<?php

namespace App\Support;

use App\Models\CambodiaCommune;
use App\Models\CambodiaDistrict;
use App\Models\CambodiaProvince;
use App\Models\CambodiaVillage;
use Illuminate\Support\Arr;

final class CambodiaLocationContract
{
    public static function locationToArray(?object $location): ?array
    {
        if ($location === null) {
            return null;
        }

        $payload = [
            'id' => $location->id ?? null,
            'code' => $location->code ?? null,
            'nameKh' => $location->name_kh ?? null,
            'nameEn' => $location->name_en ?? null,
        ];

        if (property_exists($location, 'province_id')) {
            $payload['provinceId'] = $location->province_id;
        }

        if (property_exists($location, 'district_id')) {
            $payload['districtId'] = $location->district_id;
        }

        if (property_exists($location, 'commune_id')) {
            $payload['communeId'] = $location->commune_id;
        }

        return $payload;
    }

    public static function displayName(?object $location, string $locale = 'kh'): ?string
    {
        if ($location === null) {
            return null;
        }

        $nextLocale = strtolower($locale) === 'en' ? 'en' : 'kh';

        if ($nextLocale === 'en') {
            return self::normalizeText($location->name_en ?? $location->name_kh ?? $location->code ?? null);
        }

        return self::normalizeText($location->name_kh ?? $location->name_en ?? $location->code ?? null);
    }

    public static function composeHierarchyDisplay(
        ?object $province,
        ?object $district,
        ?object $commune,
        ?object $village,
        ?string $legacyText = null,
        string $locale = 'kh'
    ): ?string {
        $parts = array_values(array_filter([
            self::displayName($village, $locale),
            self::displayName($commune, $locale),
            self::displayName($district, $locale),
            self::displayName($province, $locale),
        ], static fn (?string $value): bool => $value !== null && $value !== ''));

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        $legacy = self::normalizeText($legacyText);

        return $legacy !== '' ? $legacy : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function hierarchyErrors(array $data, string $prefix): array
    {
        $provinceId = self::nullableInteger(Arr::get($data, "{$prefix}_province_id"));
        $districtId = self::nullableInteger(Arr::get($data, "{$prefix}_district_id"));
        $communeId = self::nullableInteger(Arr::get($data, "{$prefix}_commune_id"));
        $villageId = self::nullableInteger(Arr::get($data, "{$prefix}_village_id"));

        $errors = [];

        if ($districtId !== null && $provinceId === null) {
            $errors["{$prefix}_province_id"] = 'A province must be selected before choosing a district.';
        }

        if ($communeId !== null && $districtId === null) {
            $errors["{$prefix}_district_id"] = 'A district must be selected before choosing a commune.';
        }

        if ($villageId !== null && $communeId === null) {
            $errors["{$prefix}_commune_id"] = 'A commune must be selected before choosing a village.';
        }

        $province = $provinceId !== null ? CambodiaProvince::query()->find($provinceId) : null;
        $district = $districtId !== null ? CambodiaDistrict::query()->find($districtId) : null;
        $commune = $communeId !== null ? CambodiaCommune::query()->find($communeId) : null;
        $village = $villageId !== null ? CambodiaVillage::query()->find($villageId) : null;

        if ($districtId !== null && $district === null) {
            $errors["{$prefix}_district_id"] = 'The selected district is invalid.';
        } elseif ($province !== null && $district !== null && (int) $district->province_id !== (int) $province->id) {
            $errors["{$prefix}_district_id"] = 'The selected district does not belong to the selected province.';
        }

        if ($communeId !== null && $commune === null) {
            $errors["{$prefix}_commune_id"] = 'The selected commune is invalid.';
        } elseif ($district !== null && $commune !== null && (int) $commune->district_id !== (int) $district->id) {
            $errors["{$prefix}_commune_id"] = 'The selected commune does not belong to the selected district.';
        } elseif ($province !== null && $commune !== null && (int) $commune->province_id !== (int) $province->id) {
            $errors["{$prefix}_commune_id"] = 'The selected commune does not belong to the selected province.';
        }

        if ($villageId !== null && $village === null) {
            $errors["{$prefix}_village_id"] = 'The selected village is invalid.';
        } elseif ($commune !== null && $village !== null && (int) $village->commune_id !== (int) $commune->id) {
            $errors["{$prefix}_village_id"] = 'The selected village does not belong to the selected commune.';
        } elseif ($district !== null && $village !== null && (int) $village->district_id !== (int) $district->id) {
            $errors["{$prefix}_village_id"] = 'The selected village does not belong to the selected district.';
        } elseif ($province !== null && $village !== null && (int) $village->province_id !== (int) $province->id) {
            $errors["{$prefix}_village_id"] = 'The selected village does not belong to the selected province.';
        }

        return $errors;
    }

    private static function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private static function normalizeText(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }
}
