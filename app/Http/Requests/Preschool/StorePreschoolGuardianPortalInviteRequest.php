<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class StorePreschoolGuardianPortalInviteRequest extends FormRequest
{
    /**
     * Keep the invitation payload small so admin screens can reuse the same
     * portal contract without inventing separate invite-only forms.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role_code, ['superadmin', 'adminpreschool'], true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email', 'max:191'],
        ];
    }
}
