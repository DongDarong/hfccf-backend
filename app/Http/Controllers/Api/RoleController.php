<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    public function permissions(Request $request, string $role): JsonResponse
    {
        $model = Role::query()
            ->with(['permissions' => fn ($query) => $query->orderBy('permissions.code')])
            ->where('code', $role)
            ->first();

        if (! $model) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role permissions retrieved successfully.',
            'data' => [
                'role' => $model->code,
                'permissions' => $model->permissions->map(static function ($permission): array {
                    return [
                        'code' => $permission->code,
                        'name' => $permission->name,
                    ];
                })->values()->all(),
            ],
        ], Response::HTTP_OK);
    }
}
