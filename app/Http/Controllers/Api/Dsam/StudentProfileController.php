<?php

namespace App\Http\Controllers\Api\Dsam;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dsam\StudentProfileResource;
use App\Models\PreschoolStudent;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentProfileController extends Controller
{
    private const ACCESS_ROLES = ['superadmin', 'adminpreschool', 'teacherpreschool', 'evaluator'];

    public function show(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ACCESS_ROLES)) {
            return $guard;
        }

        $profile = $student->profile ?? new StudentProfile(['student_id' => $student->id]);

        return $this->ok(new StudentProfileResource($profile));
    }

    public function upsert(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ACCESS_ROLES)) {
            return $guard;
        }

        $validated = $request->validate([
            // Father
            'father_name'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'father_dob'         => ['sometimes', 'nullable', 'date'],
            'father_occupation'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'father_income'      => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'father_phone'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'father_status'      => ['sometimes', 'nullable', 'in:alive,deceased,unknown,separated'],
            // Mother
            'mother_name'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'mother_dob'         => ['sometimes', 'nullable', 'date'],
            'mother_occupation'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'mother_income'      => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'mother_phone'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'mother_status'      => ['sometimes', 'nullable', 'in:alive,deceased,unknown,separated'],
            // Guardian
            'guardian_name'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'guardian_relation'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'guardian_phone'     => ['sometimes', 'nullable', 'string', 'max:50'],
            // Household
            'num_siblings'       => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'birth_order'        => ['sometimes', 'nullable', 'integer', 'min:1', 'max:20'],
            'household_size'     => ['sometimes', 'nullable', 'integer', 'min:1', 'max:30'],
            'monthly_income'     => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'income_sources'     => ['sometimes', 'nullable', 'array'],
            'income_sources.*'   => ['string', 'max:100'],
            // Housing
            'housing_type'       => ['sometimes', 'nullable', 'in:owned,rented,relatives,shelter,no_home'],
            'has_electricity'    => ['sometimes', 'boolean'],
            'has_clean_water'    => ['sometimes', 'boolean'],
            'has_toilet'         => ['sometimes', 'boolean'],
            // Education
            'distance_to_school' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'transport_mode'     => ['sometimes', 'nullable', 'string', 'max:100'],
            // Health
            'health_status'      => ['sometimes', 'nullable', 'in:good,fair,poor'],
            'disabilities'       => ['sometimes', 'nullable', 'array'],
            'has_health_insurance'=> ['sometimes', 'boolean'],
            'vaccination_status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes'              => ['sometimes', 'nullable', 'string'],
        ]);

        $profile = StudentProfile::updateOrCreate(
            ['student_id' => $student->id],
            $validated,
        );

        return $this->ok(new StudentProfileResource($profile), 'Profile saved.');
    }
}
