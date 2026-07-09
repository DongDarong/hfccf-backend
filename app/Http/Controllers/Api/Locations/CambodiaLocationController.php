<?php

namespace App\Http\Controllers\Api\Locations;

use App\Http\Controllers\Controller;
use App\Models\CambodiaCommune;
use App\Models\CambodiaDistrict;
use App\Models\CambodiaProvince;
use App\Models\CambodiaVillage;
use App\Support\CambodiaLocationLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CambodiaLocationController extends Controller
{
    public function provinces(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->formatLocations(
                CambodiaProvince::query()->orderBy('code')->get(),
            ),
        ], Response::HTTP_OK);
    }

    public function districts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'province_code' => ['required', 'string', 'max:16'],
        ]);

        $province = $this->findProvinceByCode($validated['province_code']);

        if (! $province) {
            return $this->notFound('Province not found.');
        }

        return response()->json([
            'data' => $this->formatLocations(
                $province->districts()->orderBy('code')->get(),
            ),
        ], Response::HTTP_OK);
    }

    public function communes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'district_code' => ['required', 'string', 'max:16'],
        ]);

        $district = $this->findDistrictByCode($validated['district_code']);

        if (! $district) {
            return $this->notFound('District not found.');
        }

        return response()->json([
            'data' => $this->formatLocations(
                $district->communes()->orderBy('code')->get(),
            ),
        ], Response::HTTP_OK);
    }

    public function villages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commune_code' => ['required', 'string', 'max:16'],
        ]);

        $commune = $this->findCommuneByCode($validated['commune_code']);

        if (! $commune) {
            return $this->notFound('Commune not found.');
        }

        return response()->json([
            'data' => $this->formatLocations(
                $commune->villages()->orderBy('code')->get(),
            ),
        ], Response::HTTP_OK);
    }

    private function findProvinceByCode(string $code): ?CambodiaProvince
    {
        return CambodiaLocationLookup::findByCodeOrNumericCode(CambodiaProvince::class, $code);
    }

    private function findDistrictByCode(string $code): ?CambodiaDistrict
    {
        return CambodiaLocationLookup::findByCodeOrNumericCode(CambodiaDistrict::class, $code);
    }

    private function findCommuneByCode(string $code): ?CambodiaCommune
    {
        return CambodiaLocationLookup::findByCodeOrNumericCode(CambodiaCommune::class, $code);
    }

    private function formatLocations($items): array
    {
        return $items->map(static fn ($item): array => array_filter([
            'id' => $item->id,
            'code' => $item->code,
            'name_kh' => $item->name_kh,
            'name_en' => $item->name_en,
            'province_id' => $item->province_id ?? null,
            'district_id' => $item->district_id ?? null,
            'commune_id' => $item->commune_id ?? null,
        ], static fn ($value): bool => $value !== null))->values()->all();
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => [],
        ], Response::HTTP_NOT_FOUND);
    }
}
