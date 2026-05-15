<?php

namespace App\Http\Resources\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fullName = trim($this->first_name.' '.$this->last_name);
        $permissionCodes = $this->relationLoaded('permissions')
            ? $this->permissions->pluck('code')->unique()->values()->all()
            : $this->permissions()->orderBy('permissions.code')->pluck('permissions.code')->all();

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
            'status' => $this->status,
            'avatar' => $this->avatar,
            'departmentCode' => $this->department_code,
            'department' => $this->department?->name,
            'bio' => $this->bio,
            'updatedAt' => $this->updated_at?->format('Y-m-d H:i:s'),
            'permissions' => $permissionCodes,
            'createdAt' => $this->created_at?->toISOString(),
            'lastLoginAt' => $this->last_login_at?->toISOString(),
        ];
    }
}
