<?php

namespace App\Http\Controllers\Api\Dsam;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    private const ADMIN_ROLES = ['superadmin'];

    public function index(Request $request): JsonResponse
    {
        if ($guard = $this->requireAuth($request->user())) {
            return $guard;
        }

        $validated = $request->validate([
            'search'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'type'     => ['sometimes', 'nullable', 'string', 'in:ngo,school,social_org'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Organization::query()->withoutTrashed();

        if (! empty($validated['search'])) {
            $query->where('name', 'like', '%'.$validated['search'].'%');
        }
        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        $paginator = $query->orderBy('name')->paginate($validated['per_page'] ?? 20);

        return $this->ok(
            $paginator->items(),
            null,
            $this->paginationMeta($paginator),
        );
    }

    public function store(Request $request): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'name_kh'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'type'     => ['required', 'in:ngo,school,social_org'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'address'  => ['sometimes', 'nullable', 'string'],
            'email'    => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone'    => ['sometimes', 'nullable', 'string', 'max:50'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ]);

        $org = Organization::create($validated);

        return $this->created($org, 'Organization created.');
    }

    public function show(Request $request, Organization $organization): JsonResponse
    {
        if ($guard = $this->requireAuth($request->user())) {
            return $guard;
        }

        $organization->load(['schools', 'academicYears']);

        return $this->ok($organization);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        $validated = $request->validate([
            'name'     => ['sometimes', 'string', 'max:255'],
            'name_kh'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'type'     => ['sometimes', 'in:ngo,school,social_org'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'address'  => ['sometimes', 'nullable', 'string'],
            'email'    => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone'    => ['sometimes', 'nullable', 'string', 'max:50'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $organization->update($validated);

        return $this->ok($organization->fresh(), 'Organization updated.');
    }

    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        $organization->delete();

        return $this->noContent('Organization deleted.');
    }
}
