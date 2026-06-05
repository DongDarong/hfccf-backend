<?php

namespace App\Http\Controllers\Api\Dsam;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    private const ADMIN_ROLES = ['superadmin', 'adminpreschool'];

    public function index(Request $request): JsonResponse
    {
        if ($guard = $this->requireAuth($request->user())) {
            return $guard;
        }

        $validated = $request->validate([
            'organization_id' => ['sometimes', 'integer', 'exists:organizations,id'],
        ]);

        $query = AcademicYear::query()->orderByDesc('start_date');

        if (! empty($validated['organization_id'])) {
            $query->where('organization_id', $validated['organization_id']);
        } elseif ($request->user()->organization_id) {
            $query->where('organization_id', $request->user()->organization_id);
        }

        return $this->ok($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'name'            => ['required', 'string', 'max:20'],
            'start_date'      => ['required', 'date'],
            'end_date'        => ['required', 'date', 'after:start_date'],
            'is_current'      => ['sometimes', 'boolean'],
        ]);

        $year = AcademicYear::create($validated);

        if (! empty($validated['is_current'])) {
            $year->setCurrent();
        }

        return $this->created($year->fresh(), 'Academic year created.');
    }

    public function show(Request $request, AcademicYear $academicYear): JsonResponse
    {
        if ($guard = $this->requireAuth($request->user())) {
            return $guard;
        }

        return $this->ok($academicYear);
    }

    public function update(Request $request, AcademicYear $academicYear): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        $validated = $request->validate([
            'name'       => ['sometimes', 'string', 'max:20'],
            'start_date' => ['sometimes', 'date'],
            'end_date'   => ['sometimes', 'date'],
        ]);

        $academicYear->update($validated);

        return $this->ok($academicYear->fresh(), 'Academic year updated.');
    }

    public function destroy(Request $request, AcademicYear $academicYear): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($academicYear->formSubmissions()->exists()) {
            return $this->error('Cannot delete an academic year that has submissions.');
        }

        $academicYear->delete();

        return $this->noContent('Academic year deleted.');
    }

    public function setCurrent(Request $request, AcademicYear $academicYear): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        $academicYear->setCurrent();

        return $this->ok($academicYear->fresh(), 'Academic year set as current.');
    }
}
