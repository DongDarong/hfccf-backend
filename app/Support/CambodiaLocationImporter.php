<?php

namespace App\Support;

use App\Models\CambodiaCommune;
use App\Models\CambodiaDistrict;
use App\Models\CambodiaProvince;
use App\Models\CambodiaVillage;
use RuntimeException;

/**
 * Cambodia administrative master data importer.
 *
 * This shared system dataset is consumed by Preschool, English, Sport,
 * Scholarship, and future modules through backend APIs only.
 * Official source: Cambodia administrative location CSV dataset.
 */
class CambodiaLocationImporter
{
    private const PROVINCE_FILE = 'Cambodia Province List 2025.csv';

    private const DISTRICT_FILE = 'Cambodia District List 2025.csv';

    private const COMMUNE_FILE = 'Cambodia Commune List 2025.csv';

    private const VILLAGE_FILE = 'Cambodia Villages List 2025.csv';

    public function resolveSourcePath(string $filename): string
    {
        $path = database_path('data/cambodia/'.$filename);

        if (is_file($path)) {
            return $path;
        }

        throw new RuntimeException('Unable to locate Cambodia location CSV: '.$filename);
    }

    public function import(): array
    {
        $paths = [
            'provinces' => $this->resolveSourcePath(self::PROVINCE_FILE),
            'districts' => $this->resolveSourcePath(self::DISTRICT_FILE),
            'communes' => $this->resolveSourcePath(self::COMMUNE_FILE),
            'villages' => $this->resolveSourcePath(self::VILLAGE_FILE),
        ];

        $provinceRows = $this->readCsv($paths['provinces']);
        $districtRows = $this->readCsv($paths['districts']);
        $communeRows = $this->readCsv($paths['communes']);
        $villageRows = $this->readCsv($paths['villages']);

        $now = now()->toDateTimeString();

        $this->upsertProvinces($provinceRows, $now);
        $provinceMap = $this->buildCodeMap(CambodiaProvince::query()->get(['id', 'code']));

        $districtImport = $this->upsertDistricts($districtRows, $provinceMap, $now);
        $districtMap = $this->buildCodeMap(CambodiaDistrict::query()->get(['id', 'code']));

        $communeImport = $this->upsertCommunes($communeRows, $provinceMap, $districtMap, $now);
        $communeMap = $this->buildCodeMap(CambodiaCommune::query()->get(['id', 'code']));

        $villageImport = $this->upsertVillages($villageRows, $provinceMap, $districtMap, $communeMap, $now);

        return [
            'paths' => $paths,
            'counts' => [
                'provinces' => count($provinceRows),
                'districts' => count($districtRows),
                'communes' => count($communeRows),
                'villages' => count($villageRows),
            ],
            'imported' => [
                'provinces' => count($provinceRows),
                'districts' => $districtImport['imported'],
                'communes' => $communeImport['imported'],
                'villages' => $villageImport['imported'],
            ],
            'missing_parents' => [
                'districts' => $districtImport['missing_parents'],
                'communes' => $communeImport['missing_parents'],
                'villages' => $villageImport['missing_parents'],
            ],
        ];
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV file: '.$path);
        }

        $headers = null;
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(
                    static fn ($header) => preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $header)),
                    $row,
                );
                continue;
            }

            if ($row === [null] || $row === false) {
                continue;
            }

            $row = array_pad($row, count($headers), '');
            $rows[] = array_combine(
                $headers,
                array_map(
                    static fn ($value) => is_string($value) ? trim($value) : $value,
                    $row,
                ),
            );
        }

        fclose($handle);

        return $rows;
    }

    private function upsertProvinces(array $rows, string $now): void
    {
        $payload = array_map(static function (array $row) use ($now): array {
            return [
                'code' => trim((string) ($row['province_code'] ?? '')),
                'name_kh' => trim((string) ($row['province_kh'] ?? '')),
                'name_en' => trim((string) ($row['province_en'] ?? '')),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $rows);

        foreach (array_chunk($payload, 500) as $chunk) {
            CambodiaProvince::query()->upsert($chunk, ['code'], ['name_kh', 'name_en', 'updated_at']);
        }
    }

    private function upsertDistricts(array $rows, array $provinceMap, string $now): array
    {
        $payload = [];
        $missingParents = [];

        foreach ($rows as $row) {
            $provinceCode = trim((string) ($row['province_code'] ?? ''));
            $provinceId = $provinceMap[$provinceCode] ?? null;

            if (! $provinceId) {
                $missingParents[] = [
                    'district_code' => trim((string) ($row['district_code'] ?? '')),
                    'province_code' => $provinceCode,
                ];
                continue;
            }

            $payload[] = [
                'province_id' => $provinceId,
                'code' => trim((string) ($row['district_code'] ?? '')),
                'name_kh' => trim((string) ($row['district_kh'] ?? '')),
                'name_en' => trim((string) ($row['district_en'] ?? '')),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($payload, 500) as $chunk) {
            CambodiaDistrict::query()->upsert($chunk, ['code'], ['province_id', 'name_kh', 'name_en', 'updated_at']);
        }

        return [
            'imported' => count($payload),
            'missing_parents' => $missingParents,
        ];
    }

    private function upsertCommunes(array $rows, array $provinceMap, array $districtMap, string $now): array
    {
        $payload = [];
        $missingParents = [];

        foreach ($rows as $row) {
            $provinceCode = trim((string) ($row['province_code'] ?? ''));
            $districtCode = trim((string) ($row['district_code'] ?? ''));
            $provinceId = $provinceMap[$provinceCode] ?? null;
            $districtId = $districtMap[$districtCode] ?? null;

            if (! $provinceId || ! $districtId) {
                $missingParents[] = [
                    'commune_code' => trim((string) ($row['commune_code'] ?? '')),
                    'province_code' => $provinceCode,
                    'district_code' => $districtCode,
                ];
                continue;
            }

            $payload[] = [
                'province_id' => $provinceId,
                'district_id' => $districtId,
                'code' => trim((string) ($row['commune_code'] ?? '')),
                'name_kh' => trim((string) ($row['commune_kh'] ?? '')),
                'name_en' => trim((string) ($row['commune_en'] ?? '')),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($payload, 500) as $chunk) {
            CambodiaCommune::query()->upsert($chunk, ['code'], ['province_id', 'district_id', 'name_kh', 'name_en', 'updated_at']);
        }

        return [
            'imported' => count($payload),
            'missing_parents' => $missingParents,
        ];
    }

    private function upsertVillages(array $rows, array $provinceMap, array $districtMap, array $communeMap, string $now): array
    {
        $payload = [];
        $missingParents = [];

        foreach ($rows as $row) {
            $provinceCode = trim((string) ($row['province_code'] ?? ''));
            $districtCode = trim((string) ($row['district_code'] ?? ''));
            $communeCode = trim((string) ($row['commune_code'] ?? ''));
            $provinceId = $provinceMap[$provinceCode] ?? null;
            $districtId = $districtMap[$districtCode] ?? null;
            $communeId = $communeMap[$communeCode] ?? null;

            if (! $provinceId || ! $districtId) {
                $missingParents[] = [
                    'village_code' => trim((string) ($row['village_code'] ?? '')),
                    'province_code' => $provinceCode,
                    'district_code' => $districtCode,
                    'commune_code' => $communeCode,
                ];
                continue;
            }

            if (! $communeId) {
                $missingParents[] = [
                    'village_code' => trim((string) ($row['village_code'] ?? '')),
                    'province_code' => $provinceCode,
                    'district_code' => $districtCode,
                    'commune_code' => $communeCode,
                ];
            }

            $payload[] = [
                'province_id' => $provinceId,
                'district_id' => $districtId,
                'commune_id' => $communeId,
                'code' => trim((string) ($row['village_code'] ?? '')),
                'name_kh' => trim((string) ($row['village_kh'] ?? '')),
                'name_en' => trim((string) ($row['village_en'] ?? '')),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        CambodiaVillage::query()->delete();

        foreach (array_chunk($payload, 500) as $chunk) {
            CambodiaVillage::query()->insert($chunk);
        }

        return [
            'imported' => count($payload),
            'missing_parents' => $missingParents,
        ];
    }

    private function buildCodeMap($records): array
    {
        $map = [];

        foreach ($records as $record) {
            $code = trim((string) $record->code);
            if ($code === '') {
                continue;
            }

            $map[$code] = $record->id;

            $normalized = ltrim($code, '0');
            if ($normalized !== '' && ! isset($map[$normalized])) {
                $map[$normalized] = $record->id;
            }
        }

        return $map;
    }
}
