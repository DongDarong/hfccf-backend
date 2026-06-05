<?php

namespace App\Http\Controllers\Api\Dsam;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    private const ADMIN_ROLES = ['superadmin', 'adminpreschool'];

    public function index(Request $request): JsonResponse
    {
        if ($guard = $this->requireAuth($request->user())) {
            return $guard;
        }

        $validated = $request->validate([
            'organization_id' => ['sometimes', 'integer', 'exists:organizations,id'],
            'search'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'province'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page'        => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = School::query()->withoutTrashed();

        $orgId = $validated['organization_id'] ?? $request->user()->organization_id;
        if ($orgId) {
            $query->where('organization_id', $orgId);
        }
        if (! empty($validated['search'])) {
            $query->where('name', 'like', '%'.$validated['search'].'%');
        }
        if (! empty($validated['province'])) {
            $query->where('province', $validated['province']);
        }

        $paginator = $query->orderBy('name')->paginate($validated['per_page'] ?? 20);

        return $this->ok(SchoolResource::collection($paginator->items()), null, $this->paginationMeta($paginator));
    }

    public function store(Request $request): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'name'            => ['required', 'string', 'max:255'],
            'name_kh'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'province'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'district'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'commune'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'address'         => ['sometimes', 'nullable', 'string'],
            'principal_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone'           => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        return $this->created(new SchoolResource(School::create($validated)), 'School created.');
    }

    public function show(Request $request, School $school): JsonResponse
    {
        if ($guard = $this->requireAuth($request->user())) {
            return $guard;
        }

        return $this->ok(new SchoolResource($school->load('organization')));
    }

    public function update(Request $request, School $school): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        $validated = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'name_kh'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'province'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'district'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'commune'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'address'        => ['sometimes', 'nullable', 'string'],
            'principal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone'          => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active'      => ['sometimes', 'boolean'],
        ]);

        $school->update($validated);

        return $this->ok(new SchoolResource($school->fresh()), 'School updated.');
    }

    public function destroy(Request $request, School $school): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        $school->delete();

        return $this->noContent('School deleted.');
    }
}
