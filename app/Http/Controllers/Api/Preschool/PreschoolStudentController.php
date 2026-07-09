<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolStudentRequest;
use App\Http\Requests\Preschool\UpdatePreschoolStudentRequest;
use App\Http\Resources\Preschool\PreschoolStudentResource;
use App\Models\PreschoolClassStudent;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Support\CambodiaLocationContract;
use App\Support\ImageStorage;
use App\Support\PreschoolAcademicLifecycleService;
use App\Support\PreschoolLifecycleGuardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PreschoolStudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeStudentViewer($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:32'],
            'class_id' => ['sometimes', 'nullable', 'integer'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $gender = trim((string) ($validated['gender'] ?? ''));
        $classId = trim((string) ($validated['class_id'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $query = PreschoolStudent::query()->with(array_merge(['classes'], $this->studentLocationRelations()));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('student_code', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('latin_name', 'like', $like)
                    ->orWhere('place_of_birth', 'like', $like)
                    ->orWhere('nationality', 'like', $like)
                    ->orWhere('ethnicity', 'like', $like)
                    ->orWhere('guardian_name', 'like', $like)
                    ->orWhere('guardian_phone', 'like', $like)
                    ->orWhere('address', 'like', $like)
                    ->orWhere('status', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($gender !== '') {
            $query->where('gender', $gender);
        }

        if ($classId !== '') {
            $query->whereHas('classes', static function (Builder $builder) use ($classId, $request): void {
                $builder->where('preschool_classes.id', $classId);

                if ($request->user()?->role_code === 'teacher-preschool') {
                    $builder->where('teacher_user_id', $request->user()->id);
                }
            });
        } elseif ($request->user()?->role_code === 'teacher-preschool') {
            $query->whereHas('classes', static function (Builder $builder) use ($request): void {
                $builder->where('teacher_user_id', $request->user()->id);
            });
        }

        $sortColumn = match ($sortBy) {
            'student_code' => 'student_code',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'status' => 'status',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => 'Preschool students retrieved successfully.',
            'data' => [
                'items' => PreschoolStudentResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolStudentRequest $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $data = $request->validated();
        if (! empty($data['class_ids'] ?? [])) {
            if ($response = app(PreschoolLifecycleGuardService::class)->assignmentWriteLock($request->user(), $data)) {
                return $response;
            }
        }
        $student = PreschoolStudent::query()->create([
            'student_code' => $data['student_code'] ?? PreschoolStudent::nextStudentCode(),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'latin_name' => $data['latin_name'] ?? null,
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'place_of_birth' => $data['place_of_birth'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'ethnicity' => $data['ethnicity'] ?? null,
            'birth_province_id' => $data['birth_province_id'] ?? null,
            'birth_district_id' => $data['birth_district_id'] ?? null,
            'birth_commune_id' => $data['birth_commune_id'] ?? null,
            'birth_village_id' => $data['birth_village_id'] ?? null,
            'residence_province_id' => $data['residence_province_id'] ?? null,
            'residence_district_id' => $data['residence_district_id'] ?? null,
            'residence_commune_id' => $data['residence_commune_id'] ?? null,
            'residence_village_id' => $data['residence_village_id'] ?? null,
            'guardian_name' => $data['guardian_name'] ?? null,
            'guardian_phone' => $data['guardian_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => $data['status'],
            'student_type' => $data['student_type'] ?? 'paying',
            'avatar' => ImageStorage::store($request->file('avatar'), 'preschool/students'),
        ]);

        if (blank($student->address)) {
            $student->address = CambodiaLocationContract::composeHierarchyDisplay(
                $student->residenceProvince,
                $student->residenceDistrict,
                $student->residenceCommune,
                $student->residenceVillage,
                null,
                'kh',
            );
        }

        if (blank($student->place_of_birth)) {
            $student->place_of_birth = CambodiaLocationContract::composeHierarchyDisplay(
                $student->birthProvince,
                $student->birthDistrict,
                $student->birthCommune,
                $student->birthVillage,
                null,
                'kh',
            );
        }

        $student->save();

        $this->syncStudentClasses($student, $data['class_ids'] ?? []);
        $student->load(array_merge(['classes'], $this->studentLocationRelations()));

        return response()->json([
            'success' => true,
            'message' => 'Preschool student created successfully.',
            'data' => [
                'student' => PreschoolStudentResource::make($student)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $student = PreschoolStudent::query()->with(array_merge(['classes'], $this->studentLocationRelations()))->find($id);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool student retrieved successfully.',
            'data' => [
                'student' => PreschoolStudentResource::make($student)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolStudentRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $student = PreschoolStudent::query()->find($id);
        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();
        if (array_key_exists('class_ids', $data)) {
            if ($response = app(PreschoolLifecycleGuardService::class)->assignmentWriteLock($request->user(), $data)) {
                return $response;
            }
        }

        foreach ([
            'student_code',
            'first_name',
            'last_name',
            'latin_name',
            'gender',
            'date_of_birth',
            'place_of_birth',
            'nationality',
            'ethnicity',
            'birth_province_id',
            'birth_district_id',
            'birth_commune_id',
            'birth_village_id',
            'residence_province_id',
            'residence_district_id',
            'residence_commune_id',
            'residence_village_id',
            'guardian_name',
            'guardian_phone',
            'address',
            'status',
            'student_type',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $student->{$field} = $data[$field];
            }
        }

        $hasResidenceUpdate = array_key_exists('residence_province_id', $data)
            || array_key_exists('residence_district_id', $data)
            || array_key_exists('residence_commune_id', $data)
            || array_key_exists('residence_village_id', $data);

        $replaceAvatar = $request->hasFile('avatar');
        $removeAvatar = (bool) ($data['remove_avatar'] ?? false);

        if ($replaceAvatar) {
            ImageStorage::delete($student->avatar);
            $student->avatar = ImageStorage::store($request->file('avatar'), 'preschool/students');
        } elseif ($removeAvatar) {
            ImageStorage::delete($student->avatar);
            $student->avatar = null;
        }

        if ($hasResidenceUpdate && ! array_key_exists('address', $data)) {
            $student->address = CambodiaLocationContract::composeHierarchyDisplay(
                $student->residenceProvince,
                $student->residenceDistrict,
                $student->residenceCommune,
                $student->residenceVillage,
                null,
                'kh',
            );
        }

        if (($hasResidenceUpdate || array_key_exists('birth_province_id', $data) || array_key_exists('birth_district_id', $data) || array_key_exists('birth_commune_id', $data) || array_key_exists('birth_village_id', $data)) && ! array_key_exists('place_of_birth', $data)) {
            $student->place_of_birth = $student->place_of_birth ?: CambodiaLocationContract::composeHierarchyDisplay(
                $student->birthProvince,
                $student->birthDistrict,
                $student->birthCommune,
                $student->birthVillage,
                null,
                'kh',
            );
        }

        $student->save();
        $this->syncStudentClasses($student, $data['class_ids'] ?? null);
        $student->load(array_merge(['classes'], $this->studentLocationRelations()));

        return response()->json([
            'success' => true,
            'message' => 'Preschool student updated successfully.',
            'data' => [
                'student' => PreschoolStudentResource::make($student)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $student = PreschoolStudent::query()->find($id);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $student->delete();

        return response()->json([
            'success' => true,
            'message' => 'Preschool student deleted successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    private function academicContext(): array
    {
        return app(PreschoolAcademicLifecycleService::class)->currentContext();
    }

    private function syncStudentClasses(PreschoolStudent $student, ?array $classIds): void
    {
        $academicContext = $this->academicContext();

        if ($classIds === null) {
            // Keep history rows intact when the caller is not changing class
            // membership. The inactive pivots remain available for assignment
            // and transfer history while the active roster stays unchanged.

            return;
        }

        $targetClassIds = collect($classIds)
            ->filter()
            ->map(static fn ($classId) => trim((string) $classId))
            ->filter()
            ->unique()
            ->values();

        $now = now();

        foreach ($targetClassIds as $classId) {
            $existingAssignment = DB::table('preschool_class_students')
                ->where('class_id', $classId)
                ->where('student_id', $student->id)
                ->first();

            $assignmentData = [
                'class_id' => $classId,
                'student_id' => $student->id,
                'enrolled_at' => $existingAssignment && ($existingAssignment->status ?? null) === 'active'
                    ? $existingAssignment->enrolled_at
                    : $now,
                'academic_year' => $academicContext['academic_year'],
                'term_label' => $academicContext['term_label'],
                'academic_year_id' => $academicContext['academic_year_id'] ?? null,
                'term_id' => $academicContext['term_id'] ?? null,
                'enrollment_status' => 'active',
                'enrollment_started_at' => $existingAssignment->enrollment_started_at ?? $now,
                'enrollment_ended_at' => null,
                'status' => 'active',
                'updated_at' => $now,
            ];

            if ($existingAssignment) {
                DB::table('preschool_class_students')
                    ->where('class_id', $classId)
                    ->where('student_id', $student->id)
                    ->update($assignmentData);

                continue;
            }

            $assignmentData['created_at'] = $now;

            DB::table('preschool_class_students')->insert($assignmentData);
        }

        $inactiveQuery = DB::table('preschool_class_students')
            ->where('student_id', $student->id);

        if ($targetClassIds->isNotEmpty()) {
            $inactiveQuery->whereNotIn('class_id', $targetClassIds->all());
        }

        $inactiveQuery->update([
            'status' => 'inactive',
            'enrollment_status' => 'inactive',
            'enrollment_ended_at' => now(),
            'updated_at' => now(),
        ]);

        $student->load('classes');
        foreach ($student->classes as $class) {
            $class->students_count = DB::table('preschool_class_students')
                ->where('class_id', $class->id)
                ->where('status', 'active')
                ->count();
            $class->save();
        }
    }

    /**
     * @return array<int, string>
     */
    private function studentLocationRelations(): array
    {
        return [
            'birthProvince',
            'birthDistrict',
            'birthCommune',
            'birthVillage',
            'residenceProvince',
            'residenceDistrict',
            'residenceCommune',
            'residenceVillage',
        ];
    }

    private function authorizeAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }

    private function authorizeStudentViewer(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }

    private function paginationShape($paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
        ];
    }
}
