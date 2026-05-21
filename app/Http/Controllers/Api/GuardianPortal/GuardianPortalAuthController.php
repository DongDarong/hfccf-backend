<?php

namespace App\Http\Controllers\Api\GuardianPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardianPortal\ActivateGuardianPortalInvitationRequest;
use App\Http\Resources\Auth\UserResource;
use App\Support\PreschoolGuardianInvitationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class GuardianPortalAuthController extends Controller
{
    /**
     * Invitation activation is public because the guardian portal must be
     * able to bootstrap a login without exposing the full Preschool UI.
     */
    public function activate(ActivateGuardianPortalInvitationRequest $request, PreschoolGuardianInvitationService $service): JsonResponse
    {
        $payload = $service->activate($request->validated('token'), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Guardian portal invitation activated successfully.',
            'data' => [
                'token' => $payload['token'],
                'user' => UserResource::make($payload['user'])->resolve($request),
                'account' => [
                    'id' => $payload['account']->id,
                    'guardianId' => $payload['account']->guardian_id,
                    'status' => $payload['account']->status,
                    'email' => $payload['account']->email,
                ],
            ],
        ], Response::HTTP_OK);
    }
}
