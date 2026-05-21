<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\ArchiveDuplicateCandidateRequest;
use App\Http\Requests\Preschool\ArchiveOrphanGuardianRequest;
use App\Http\Requests\Preschool\ClearInvalidEmergencyContactRequest;
use App\Http\Requests\Preschool\ClearInvalidPrimaryRequest;
use App\Http\Requests\Preschool\MarkGuardianIssueReviewedRequest;
use App\Http\Requests\Preschool\ReconcileLegacyGuardianFieldsRequest;
use App\Http\Requests\Preschool\SetPrimaryGuardianRemediationRequest;
use App\Http\Resources\Preschool\PreschoolGuardianRemediationLogResource;
use App\Models\User;
use App\Support\PreschoolGuardianRemediationAuditService;
use App\Support\PreschoolGuardianRemediationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolGuardianRemediationController extends Controller
{
    /**
     * Remediation endpoints stay admin-only and fully logged so every manual
     * data correction carries a before/after audit trail with a responsible user.
     */
    public function logs(
        Request $request,
        PreschoolGuardianRemediationAuditService $audit,
    ): JsonResponse {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $paginator = $audit->paginatedLogs($request->only([
            'issue_type', 'action', 'student_id', 'guardian_id',
            'performed_by_user_id', 'per_page',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Remediation logs retrieved successfully.',
            'data' => PreschoolGuardianRemediationLogResource::collection($paginator),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function markReviewed(
        MarkGuardianIssueReviewedRequest $request,
        PreschoolGuardianRemediationService $service,
    ): JsonResponse {
        $result = $service->markIssueReviewed($request->user(), $request->validated());

        return response()->json($result, Response::HTTP_OK);
    }

    public function setPrimary(
        SetPrimaryGuardianRemediationRequest $request,
        PreschoolGuardianRemediationService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $result = $service->setPrimaryGuardian(
            user: $request->user(),
            studentId: (int) $validated['student_id'],
            relationshipId: (int) $validated['relationship_id'],
            notes: $validated['notes'] ?? null,
        );

        return response()->json($result, Response::HTTP_OK);
    }

    public function clearInvalidPrimary(
        ClearInvalidPrimaryRequest $request,
        PreschoolGuardianRemediationService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $result = $service->clearInvalidPrimary(
            user: $request->user(),
            relationshipId: (int) $validated['relationship_id'],
            notes: $validated['notes'] ?? null,
        );

        return response()->json($result, Response::HTTP_OK);
    }

    public function clearInvalidEmergencyContact(
        ClearInvalidEmergencyContactRequest $request,
        PreschoolGuardianRemediationService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $result = $service->clearInvalidEmergencyContact(
            user: $request->user(),
            relationshipId: (int) $validated['relationship_id'],
            notes: $validated['notes'] ?? null,
        );

        return response()->json($result, Response::HTTP_OK);
    }

    public function reconcileLegacyFields(
        ReconcileLegacyGuardianFieldsRequest $request,
        PreschoolGuardianRemediationService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $result = $service->reconcileLegacyFields(
            user: $request->user(),
            studentId: (int) $validated['student_id'],
            notes: $validated['notes'] ?? null,
        );

        return response()->json($result, Response::HTTP_OK);
    }

    public function archiveDuplicateCandidate(
        ArchiveDuplicateCandidateRequest $request,
        PreschoolGuardianRemediationService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $result = $service->archiveDuplicateCandidate(
            user: $request->user(),
            relationshipId: (int) $validated['relationship_id'],
            notes: $validated['notes'] ?? null,
        );

        return response()->json($result, Response::HTTP_OK);
    }

    public function archiveOrphanGuardian(
        ArchiveOrphanGuardianRequest $request,
        PreschoolGuardianRemediationService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $result = $service->archiveOrphanGuardian(
            user: $request->user(),
            guardianId: (int) $validated['guardian_id'],
            notes: $validated['notes'] ?? null,
        );

        return response()->json($result, Response::HTTP_OK);
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
