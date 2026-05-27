<?php

namespace App\Http\Resources\Preschool;

use App\Http\Resources\UserResource;
use App\Models\PreschoolGuardianPortalAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Portal accounts are rendered separately from guardian records so the admin
 * UI can manage invite status without exposing login internals.
 *
 * @mixin PreschoolGuardianPortalAccount
 */
class PreschoolGuardianPortalAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        return [
            'id' => $this->id,
            'guardianId' => $this->guardian_id,
            'userId' => $this->user_id,
            'email' => $this->email,
            'status' => $this->status,
            'isActive' => $this->status === 'active',
            'invitedAt' => $this->invited_at?->toISOString(),
            'activatedAt' => $this->activated_at?->toISOString(),
            'revokedAt' => $this->revoked_at?->toISOString(),
            'lastLoginAt' => $this->last_login_at?->toISOString(),
            'activationExpiresAt' => $metadata['activation_token_expires_at'] ?? null,
            'guardian' => $this->whenLoaded('guardian', function (): array {
                return [
                    'id' => $this->guardian?->id,
                    'fullName' => $this->guardian?->full_name,
                    'phone' => $this->guardian?->phone,
                    'email' => $this->guardian?->email,
                    'status' => $this->guardian?->status,
                ];
            }),
            'user' => $this->whenLoaded('user', function () use ($request): ?array {
                return $this->user ? UserResource::make($this->user)->resolve($request) : null;
            }),
            'invitedBy' => $this->whenLoaded('invitedBy', function (): ?array {
                return $this->invitedBy ? [
                    'id' => $this->invitedBy->id,
                    'fullName' => trim($this->invitedBy->first_name.' '.$this->invitedBy->last_name),
                ] : null;
            }),
        ];
    }
}
