<?php

namespace App\Http\Controllers\Api\GuardianPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardianPortal\ActivateGuardianPortalInvitationRequest;
use App\Support\PreschoolGuardianInvitationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class GuardianPortalAuthController extends Controller
{
    /**
     * Legacy compatibility only: guardian portal activation is disabled so
     * guardian records remain data-only and do not bootstrap auth identities.
     */
    public function activate(ActivateGuardianPortalInvitationRequest $request, PreschoolGuardianInvitationService $service): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Guardian portal activation is disabled.',
            'data' => null,
        ], Response::HTTP_GONE);
    }
}
