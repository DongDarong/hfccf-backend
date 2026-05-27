<?php

namespace App\Http\Requests\GuardianPortal;

use Illuminate\Foundation\Http\FormRequest;

class ActivateGuardianPortalInvitationRequest extends FormRequest
{
    /**
     * Invitation activation is public, but the token and password are still
     * validated here so the portal never exposes an unsafe login state.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
