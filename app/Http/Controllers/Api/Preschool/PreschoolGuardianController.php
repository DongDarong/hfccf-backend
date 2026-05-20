<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolGuardianRequest;
use App\Http\Requests\Preschool\UpdatePreschoolGuardianRequest;
use App\Http\Resources\Preschool\PreschoolGuardianResource;
use App\Models\PreschoolGuardian;
use App\Models\User;
use App\Support\PreschoolGuardianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolGuardianController extends Controller
{
    /**
     * Guardian records stay admin-managed so the UI can reuse the same
     * relationship across siblings without exposing editable contact data to
     * teachers.
     */
    public function index(Request $request, PreschoolGuardianService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $paginator = $service->listGuardians($request->user(), $request->query());

        return response()->json([
            'success' => true,
            'message' => 'Preschool guardians retrieved successfully.',
            'data' => [
                'items' => PreschoolGuardianResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolGuardianRequest $request, PreschoolGuardianService $service): JsonResponse
    {
        $guardian = $service->createGuardian($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Preschool guardian created successfully.',
            'data' => [
                'guardian' => PreschoolGuardianResource::make($guardian)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $guardian): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $guardianModel = PreschoolGuardian::query()->withCount([
            'studentGuardians as relationships_count',
            'activeStudentGuardians as active_relationships_count',
        ])->find($guardian);
        if (! $guardianModel) {
            return response()->json([
                'success' => false,
                'message' => 'Guardian not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool guardian retrieved successfully.',
            'data' => [
                'guardian' => PreschoolGuardianResource::make($guardianModel)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolGuardianRequest $request, string $guardian, PreschoolGuardianService $service): JsonResponse
    {
        $guardianModel = PreschoolGuardian::query()->find($guardian);
        if (! $guardianModel) {
            return response()->json([
                'success' => false,
                'message' => 'Guardian not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $updated = $service->updateGuardian($request->user(), $guardianModel, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Preschool guardian updated successfully.',
            'data' => [
                'guardian' => PreschoolGuardianResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, string $guardian, PreschoolGuardianService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $guardianModel = PreschoolGuardian::query()->find($guardian);
        if (! $guardianModel) {
            return response()->json([
                'success' => false,
                'message' => 'Guardian not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $updated = $service->archiveGuardian($request->user(), $guardianModel);

        return response()->json([
            'success' => true,
            'message' => 'Preschool guardian archived successfully.',
            'data' => [
                'guardian' => PreschoolGuardianResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
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

    private function paginationShape($paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
        ];
    }
}
