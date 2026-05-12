<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $permissionCodes = $this->relationLoaded('permissions')
            ? $this->permissions->pluck('code')->unique()->values()->all()
            : $this->permissions()->orderBy('permissions.code')->pluck('permissions.code')->all();

        return [
            'id' => $this->id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'email' => $this->email,
            'role' => $this->role_code,
            'permissions' => $permissionCodes,
            'departmentCode' => $this->department_code,
            'createdAt' => $this->created_at?->toISOString(),
            'lastLoginAt' => $this->last_login_at?->toISOString(),
        ];
    }
}

