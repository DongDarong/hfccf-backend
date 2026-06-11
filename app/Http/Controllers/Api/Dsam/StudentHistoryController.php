<?php

namespace App\Http\Controllers\Api\Dsam;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dsam\StudentHistoryResource;
use App\Models\PreschoolStudent;
use App\Models\StudentHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentHistoryController extends Controller
{
    private const ACCESS_ROLES = ['superadmin', 'adminpreschool', 'teacherpreschool', 'evaluator'];
    private const ADMIN_ROLES  = ['superadmin', 'adminpreschool'];

    public function index(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ACCESS_ROLES)) {
            return $guard;
        }

        $histories = $student->histories()
            ->with(['academicYear', 'school', 'recordedBy'])
            ->get();

        return $this->ok(StudentHistoryResource::collection($histories));
    }

    public function store(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'school_id'        => ['sometimes', 'nullable', 'integer', 'exists:schools,id'],
            'grade'            => ['sometimes', 'nullable', 'string', 'max:20'],
            'class_name'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'status'           => ['required', 'in:active,graduated,dropped,transferred,suspended'],
            'notes'            => ['sometimes', 'nullable', 'string'],
        ]);

        // Enforce unique constraint — update if year entry already exists
        $history = StudentHistory::updateOrCreate(
            ['student_id' => $student->id, 'academic_year_id' => $validated['academic_year_id']],
            [
                ...$validated,
                'student_id'  => $student->id,
                'recorded_by' => $request->user()->id,
            ],
        );

        return $this->created(
            new StudentHistoryResource($history->load(['academicYear', 'school'])),
            'History record saved.',
        );
    }

    public function update(Request $request, PreschoolStudent $student, StudentHistory $history): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($history->student_id !== $student->id) {
            return $this->error('History record does not belong to this student.', 404);
        }

        $validated = $request->validate([
            'school_id'  => ['sometimes', 'nullable', 'integer', 'exists:schools,id'],
            'grade'      => ['sometimes', 'nullable', 'string', 'max:20'],
            'class_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status'     => ['sometimes', 'in:active,graduated,dropped,transferred,suspended'],
            'notes'      => ['sometimes', 'nullable', 'string'],
        ]);

        $history->update($validated);

        return $this->ok(
            new StudentHistoryResource($history->fresh()->load(['academicYear', 'school'])),
            'History updated.',
        );
    }
}
