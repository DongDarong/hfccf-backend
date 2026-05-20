<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolStudentGuardianRequest;
use App\Http\Requests\Preschool\UpdatePreschoolStudentGuardianRequest;
use App\Http\Resources\Preschool\PreschoolStudentGuardianResource;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
use App\Models\User;
use App\Support\PreschoolGuardianContactService;
use App\Support\PreschoolStudentGuardianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolStudentGuardianController extends Controller
{
    /**
     * Student guardian links stay separate from guardian master data so the
     * module can preserve history while teachers only read assigned students.
     */
    public function index(Request $request, PreschoolStudent $student, PreschoolStudentGuardianService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        $items = $service->listStudentGuardians($request->user(), $student);

        return response()->json([
            'success' => true,
            'message' => 'Preschool student guardians retrieved successfully.',
            'data' => [
                'items' => PreschoolStudentGuardianResource::collection($items)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolStudentGuardianRequest $request, PreschoolStudent $student, PreschoolStudentGuardianService $service): JsonResponse
    {
        $relationship = $service->linkGuardian($request->user(), $student, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Preschool student guardian linked successfully.',
            'data' => [
                'relationship' => PreschoolStudentGuardianResource::make($relationship)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(UpdatePreschoolStudentGuardianRequest $request, PreschoolStudentGuardian $relationship, PreschoolStudentGuardianService $service): JsonResponse
    {
        $updated = $service->updateRelationship($request->user(), $relationship, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Preschool student guardian updated successfully.',
            'data' => [
                'relationship' => PreschoolStudentGuardianResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, PreschoolStudentGuardian $relationship, PreschoolStudentGuardianService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $updated = $service->archiveRelationship($request->user(), $relationship);

        return response()->json([
            'success' => true,
            'message' => 'Preschool student guardian archived successfully.',
            'data' => [
                'relationship' => PreschoolStudentGuardianResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function emergencyContacts(Request $request, PreschoolStudent $student, PreschoolGuardianContactService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        $contacts = $service->forStudent($request->user(), $student);

        return response()->json([
            'success' => true,
            'message' => 'Preschool emergency contacts retrieved successfully.',
            'data' => [
                'items' => PreschoolStudentGuardianResource::collection($contacts)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeViewer(?User $user): ?JsonResponse
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
