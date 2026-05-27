<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSportMatchStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return (bool) $user && in_array($user->role_code, ['superadmin', 'adminsport'], true);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:draft,scheduled,live,halftime,completed,postponed,cancelled'],
            'current_period' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
