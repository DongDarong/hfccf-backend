<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fullName = trim($this->first_name.' '.$this->last_name);

        return [
            'id' => $this->id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'name' => $fullName !== '' ? $fullName : $this->username,
            'fullName' => $fullName !== '' ? $fullName : $this->username,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role_code,
            'scope' => $this->role?->scope,
            'domain' => $this->role?->domain_code,
            'departmentCode' => $this->department_code,
            'department' => $this->department?->name,
            'bio' => $this->bio,
            'status' => $this->status,
            'avatar' => $this->avatar,
            'createdAt' => $this->created_at?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updated_at?->format('Y-m-d H:i:s'),
            'lastLoginAt' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'permissions' => $this->resolvedPermissionCodes(),
        ];
    }
}
