<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolGuardianPortalInviteRequest;
use App\Http\Resources\Preschool\PreschoolGuardianPortalAccountResource;
use App\Models\PreschoolGuardian;
use App\Models\PreschoolGuardianPortalAccount;
use App\Support\PreschoolGuardianInvitationService;
use App\Support\PreschoolGuardianPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolGuardianPortalController extends Controller
{
    /**
     * Admin portal management stays here so Preschool staff can invite and
     * revoke guardian access without touching the guardian data records.
     */
    public function index(Request $request, PreschoolGuardianPortalService $service): JsonResponse
    {
        $service->ensureAdminAccess($request->user());

        $paginator = $service->listAccounts($request->user(), $request->query());

        return response()->json([
            'success' => true,
            'message' => 'Guardian portal accounts retrieved successfully.',
            'data' => [
                'items' => PreschoolGuardianPortalAccountResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                ],
            ],
        ], Response::HTTP_OK);
    }

    public function invite(StorePreschoolGuardianPortalInviteRequest $request, PreschoolGuardian $guardian, PreschoolGuardianInvitationService $service): JsonResponse
    {
        $payload = $service->invite($request->user(), $guardian, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Guardian portal invitation created successfully.',
            'data' => [
                'account' => PreschoolGuardianPortalAccountResource::make($payload['account'])->resolve($request),
                'activationToken' => $payload['activationToken'],
                'activationUrl' => $payload['activationUrl'],
            ],
        ], Response::HTTP_CREATED);
    }

    public function revoke(Request $request, PreschoolGuardianPortalAccount $account, PreschoolGuardianInvitationService $service): JsonResponse
    {
        $updated = $service->revoke($request->user(), $account);

        return response()->json([
            'success' => true,
            'message' => 'Guardian portal access revoked successfully.',
            'data' => [
                'account' => PreschoolGuardianPortalAccountResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }
}
