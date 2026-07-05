<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolClassLevelRequest;
use App\Http\Requests\Preschool\UpdatePreschoolClassLevelRequest;
use App\Http\Resources\Preschool\PreschoolClassLevelResource;
use App\Models\PreschoolClassLevel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolClassLevelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $classLevels = PreschoolClassLevel::query()
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Preschool class levels retrieved successfully.',
            'data' => [
                'items' => PreschoolClassLevelResource::collection($classLevels)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolClassLevelRequest $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $data = $request->validated();
        $classLevel = PreschoolClassLevel::query()->create([
            'name_en' => $data['name_en'],
            'name_kh' => $data['name_kh'] !== '' ? $data['name_kh'] : null,
            'code' => $data['code'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool class level created successfully.',
            'data' => [
                'classLevel' => PreschoolClassLevelResource::make($classLevel)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(UpdatePreschoolClassLevelRequest $request, PreschoolClassLevel $classLevel): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $data = $request->validated();

        foreach (['name_en', 'name_kh', 'code', 'sort_order', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $classLevel->{$field} = $field === 'name_kh' && $data[$field] === ''
                    ? null
                    : $data[$field];
            }
        }

        $classLevel->save();

        return response()->json([
            'success' => true,
            'message' => 'Preschool class level updated successfully.',
            'data' => [
                'classLevel' => PreschoolClassLevelResource::make($classLevel)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function deactivate(Request $request, PreschoolClassLevel $classLevel): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $classLevel->is_active = false;
        $classLevel->save();

        return response()->json([
            'success' => true,
            'message' => 'Preschool class level deactivated successfully.',
            'data' => [
                'classLevel' => PreschoolClassLevelResource::make($classLevel)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function restore(Request $request, PreschoolClassLevel $classLevel): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $classLevel->is_active = true;
        $classLevel->save();

        return response()->json([
            'success' => true,
            'message' => 'Preschool class level restored successfully.',
            'data' => [
                'classLevel' => PreschoolClassLevelResource::make($classLevel)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeAny(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }

    private function authorizeAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}
