<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\PreschoolClass;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAllergy;
use App\Models\PreschoolStudentHealthCheckLog;
use App\Models\PreschoolStudentHealthContact;
use App\Models\PreschoolStudentHealthIncident;
use App\Models\PreschoolStudentMedicalProfile;
use App\Models\PreschoolStudentMedicationRecord;
use App\Models\PreschoolStudentVaccinationRecord;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PreschoolStudentHealthController extends Controller
{
    public function summary(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user(), $student)) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool health summary retrieved successfully.',
            'data' => $this->buildSummary($student),
        ], Response::HTTP_OK);
    }

    public function medicalProfile(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user(), $student)) {
            return $response;
        }

        $profile = $student->medicalProfile()->first();

        return response()->json([
            'success' => true,
            'message' => 'Preschool medical profile retrieved successfully.',
            'data' => [
                'medicalProfile' => $profile,
                'legacyProfile' => $student->profile,
            ],
        ], Response::HTTP_OK);
    }

    public function upsertMedicalProfile(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'blood_type' => ['sometimes', 'nullable', 'string', 'max:10'],
            'chronic_conditions' => ['sometimes', 'nullable', 'array'],
            'current_conditions' => ['sometimes', 'nullable', 'array'],
            'medical_notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
        ]);

        $profile = PreschoolStudentMedicalProfile::query()->updateOrCreate(
            ['student_id' => $student->id],
            [
                'blood_type' => $data['blood_type'] ?? null,
                'chronic_conditions' => $data['chronic_conditions'] ?? null,
                'current_conditions' => $data['current_conditions'] ?? null,
                'medical_notes' => $data['medical_notes'] ?? null,
                'status' => $data['status'] ?? 'active',
                'created_by_user_id' => $request->user()?->id,
                'updated_by_user_id' => $request->user()?->id,
            ],
        );

        $profile->load(['student', 'createdBy', 'updatedBy']);

        return response()->json([
            'success' => true,
            'message' => 'Preschool medical profile saved successfully.',
            'data' => [
                'medicalProfile' => $profile,
            ],
        ], Response::HTTP_OK);
    }

    public function allergies(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user(), $student)) {
            return $response;
        }

        $items = $student->allergies()
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'moderate' THEN 3 ELSE 4 END")
            ->orderBy('allergy_name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Preschool allergies retrieved successfully.',
            'data' => [
                'items' => $items,
            ],
        ], Response::HTTP_OK);
    }

    public function storeAllergy(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'allergy_name' => ['required', 'string', 'max:255'],
            'allergy_type' => ['required', 'string', 'max:100'],
            'severity' => ['required', Rule::in(['mild', 'moderate', 'high', 'critical'])],
            'reaction' => ['sometimes', 'nullable', 'string', 'max:255'],
            'action_taken' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'resolved', 'inactive'])],
        ]);

        $allergy = $student->allergies()->create([
            ...$data,
            'status' => $data['status'] ?? 'active',
            'created_by_user_id' => $request->user()?->id,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool allergy created successfully.',
            'data' => [
                'allergy' => $allergy,
            ],
        ], Response::HTTP_CREATED);
    }

    public function updateAllergy(Request $request, PreschoolStudent $student, string $allergy): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $record = $student->allergies()->whereKey($allergy)->first();
        if (! $record) {
            return $this->notFound('Allergy not found.');
        }

        $data = $request->validate([
            'allergy_name' => ['sometimes', 'required', 'string', 'max:255'],
            'allergy_type' => ['sometimes', 'required', 'string', 'max:100'],
            'severity' => ['sometimes', 'required', Rule::in(['mild', 'moderate', 'high', 'critical'])],
            'reaction' => ['sometimes', 'nullable', 'string', 'max:255'],
            'action_taken' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'resolved', 'inactive'])],
        ]);

        $record->fill($data);
        $record->updated_by_user_id = $request->user()?->id;
        $record->save();

        return response()->json([
            'success' => true,
            'message' => 'Preschool allergy updated successfully.',
            'data' => [
                'allergy' => $record,
            ],
        ], Response::HTTP_OK);
    }

    public function destroyAllergy(Request $request, PreschoolStudent $student, string $allergy): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $record = $student->allergies()->whereKey($allergy)->first();
        if (! $record) {
            return $this->notFound('Allergy not found.');
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Preschool allergy deleted successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    public function vaccinations(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user(), $student)) {
            return $response;
        }

        $items = $student->vaccinationRecords()
            ->orderByDesc('vaccination_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Preschool vaccination records retrieved successfully.',
            'data' => [
                'items' => $items,
            ],
        ], Response::HTTP_OK);
    }

    public function storeVaccination(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'vaccine_name' => ['required', 'string', 'max:255'],
            'vaccination_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['pending', 'completed', 'overdue', 'unknown'])],
            'dose_number' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $record = $student->vaccinationRecords()->create([
            ...$data,
            'created_by_user_id' => $request->user()?->id,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool vaccination record created successfully.',
            'data' => [
                'vaccination' => $record,
            ],
        ], Response::HTTP_CREATED);
    }

    public function updateVaccination(Request $request, PreschoolStudent $student, string $vaccination): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $record = $student->vaccinationRecords()->whereKey($vaccination)->first();
        if (! $record) {
            return $this->notFound('Vaccination record not found.');
        }

        $data = $request->validate([
            'vaccine_name' => ['sometimes', 'required', 'string', 'max:255'],
            'vaccination_date' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', Rule::in(['pending', 'completed', 'overdue', 'unknown'])],
            'dose_number' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $record->fill($data);
        $record->updated_by_user_id = $request->user()?->id;
        $record->save();

        return response()->json([
            'success' => true,
            'message' => 'Preschool vaccination record updated successfully.',
            'data' => [
                'vaccination' => $record,
            ],
        ], Response::HTTP_OK);
    }

    public function destroyVaccination(Request $request, PreschoolStudent $student, string $vaccination): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $record = $student->vaccinationRecords()->whereKey($vaccination)->first();
        if (! $record) {
            return $this->notFound('Vaccination record not found.');
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Preschool vaccination record deleted successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    public function medications(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user(), $student)) {
            return $response;
        }

        $items = $student->medicationRecords()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Preschool medication records retrieved successfully.',
            'data' => [
                'items' => $items,
            ],
        ], Response::HTTP_OK);
    }

    public function storeMedication(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'medication_name' => ['required', 'string', 'max:255'],
            'dosage' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'string', 'max:255'],
            'route' => ['sometimes', 'nullable', 'string', 'max:100'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive', 'stopped', 'completed'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $record = $student->medicationRecords()->create([
            ...$data,
            'status' => $data['status'] ?? 'active',
            'created_by_user_id' => $request->user()?->id,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool medication record created successfully.',
            'data' => [
                'medication' => $record,
            ],
        ], Response::HTTP_CREATED);
    }

    public function updateMedication(Request $request, PreschoolStudent $student, string $medication): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $record = $student->medicationRecords()->whereKey($medication)->first();
        if (! $record) {
            return $this->notFound('Medication record not found.');
        }

        $data = $request->validate([
            'medication_name' => ['sometimes', 'required', 'string', 'max:255'],
            'dosage' => ['sometimes', 'required', 'string', 'max:255'],
            'frequency' => ['sometimes', 'required', 'string', 'max:255'],
            'route' => ['sometimes', 'nullable', 'string', 'max:100'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive', 'stopped', 'completed'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $record->fill($data);
        $record->updated_by_user_id = $request->user()?->id;
        $record->save();

        return response()->json([
            'success' => true,
            'message' => 'Preschool medication record updated successfully.',
            'data' => [
                'medication' => $record,
            ],
        ], Response::HTTP_OK);
    }

    public function destroyMedication(Request $request, PreschoolStudent $student, string $medication): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $record = $student->medicationRecords()->whereKey($medication)->first();
        if (! $record) {
            return $this->notFound('Medication record not found.');
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Preschool medication record deleted successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    public function incidents(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user(), $student)) {
            return $response;
        }

        $items = $student->healthIncidents()
            ->orderByDesc('incident_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Preschool health incidents retrieved successfully.',
            'data' => [
                'items' => $items,
            ],
        ], Response::HTTP_OK);
    }

    public function storeIncident(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeTeacherIncidentWrite($request->user(), $student)) {
            return $response;
        }

        $data = $request->validate([
            'incident_date' => ['required', 'date'],
            'incident_type' => ['required', 'string', 'max:255'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'action_taken' => ['required', 'string'],
            'follow_up_needed' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', Rule::in(['open', 'closed', 'resolved'])],
        ]);

        $record = $student->healthIncidents()->create([
            ...$data,
            'follow_up_needed' => (bool) ($data['follow_up_needed'] ?? false),
            'status' => $data['status'] ?? 'open',
            'reported_by_user_id' => $request->user()?->id,
            'created_by_user_id' => $request->user()?->id,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool health incident logged successfully.',
            'data' => [
                'incident' => $record,
            ],
        ], Response::HTTP_CREATED);
    }

    public function updateIncident(Request $request, PreschoolStudent $student, string $incident): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $record = $student->healthIncidents()->whereKey($incident)->first();
        if (! $record) {
            return $this->notFound('Health incident not found.');
        }

        $data = $request->validate([
            'incident_date' => ['sometimes', 'required', 'date'],
            'incident_type' => ['sometimes', 'required', 'string', 'max:255'],
            'severity' => ['sometimes', 'required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'action_taken' => ['sometimes', 'required', 'string'],
            'follow_up_needed' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', Rule::in(['open', 'closed', 'resolved'])],
        ]);

        $record->fill($data);
        $record->updated_by_user_id = $request->user()?->id;
        $record->save();

        return response()->json([
            'success' => true,
            'message' => 'Preschool health incident updated successfully.',
            'data' => [
                'incident' => $record,
            ],
        ], Response::HTTP_OK);
    }

    public function destroyIncident(Request $request, PreschoolStudent $student, string $incident): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $record = $student->healthIncidents()->whereKey($incident)->first();
        if (! $record) {
            return $this->notFound('Health incident not found.');
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Preschool health incident deleted successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    public function healthContacts(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user(), $student)) {
            return $response;
        }

        $items = $student->emergencyHealthContacts()
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Preschool emergency health contacts retrieved successfully.',
            'data' => [
                'items' => $items,
            ],
        ], Response::HTTP_OK);
    }

    public function storeHealthContact(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:50'],
            'secondary_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'priority' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_primary' => ['sometimes', 'boolean'],
            'receive_alerts' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $record = $student->emergencyHealthContacts()->create([
            ...$data,
            'priority' => $data['priority'] ?? 1,
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'receive_alerts' => (bool) ($data['receive_alerts'] ?? true),
            'status' => $data['status'] ?? 'active',
            'created_by_user_id' => $request->user()?->id,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool emergency health contact created successfully.',
            'data' => [
                'contact' => $record,
            ],
        ], Response::HTTP_CREATED);
    }

    public function updateHealthContact(Request $request, PreschoolStudent $student, string $contact): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $record = $student->emergencyHealthContacts()->whereKey($contact)->first();
        if (! $record) {
            return $this->notFound('Health contact not found.');
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'relationship' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'max:50'],
            'secondary_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'priority' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_primary' => ['sometimes', 'boolean'],
            'receive_alerts' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $record->fill($data);
        $record->updated_by_user_id = $request->user()?->id;
        $record->save();

        return response()->json([
            'success' => true,
            'message' => 'Preschool emergency health contact updated successfully.',
            'data' => [
                'contact' => $record,
            ],
        ], Response::HTTP_OK);
    }

    public function destroyHealthContact(Request $request, PreschoolStudent $student, string $contact): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $record = $student->emergencyHealthContacts()->whereKey($contact)->first();
        if (! $record) {
            return $this->notFound('Health contact not found.');
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Preschool emergency health contact deleted successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    public function healthChecks(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user(), $student)) {
            return $response;
        }

        $items = $student->healthCheckLogs()
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Preschool health check logs retrieved successfully.',
            'data' => [
                'items' => $items,
            ],
        ], Response::HTTP_OK);
    }

    public function storeHealthCheck(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeTeacherIncidentWrite($request->user(), $student)) {
            return $response;
        }

        $data = $request->validate([
            'checked_at' => ['required', 'date'],
            'temperature_celsius' => ['sometimes', 'nullable', 'numeric'],
            'weight_kg' => ['sometimes', 'nullable', 'numeric'],
            'height_cm' => ['sometimes', 'nullable', 'numeric'],
            'symptoms' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remarks' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', Rule::in(['recorded', 'reviewed', 'follow_up'])],
        ]);

        $record = $student->healthCheckLogs()->create([
            ...$data,
            'status' => $data['status'] ?? 'recorded',
            'logged_by_user_id' => $request->user()?->id,
            'created_by_user_id' => $request->user()?->id,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool health check logged successfully.',
            'data' => [
                'healthCheck' => $record,
            ],
        ], Response::HTTP_CREATED);
    }

    public function destroyHealthCheck(Request $request, PreschoolStudent $student, string $check): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $record = $student->healthCheckLogs()->whereKey($check)->first();
        if (! $record) {
            return $this->notFound('Health check log not found.');
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Preschool health check deleted successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }
    private function buildSummary(PreschoolStudent $student): array
    {
        $student->loadMissing([
            'profile',
            'medicalProfile',
            'allergies',
            'vaccinationRecords',
            'medicationRecords',
            'healthIncidents',
            'emergencyHealthContacts',
            'healthCheckLogs',
            'classes',
        ]);

        $primaryEmergencyContact = $student->emergencyHealthContacts->firstWhere('is_primary', true) ?? $student->emergencyHealthContacts->first();
        $highSeverityIncidents = $student->healthIncidents->whereIn('severity', ['high', 'critical'])->count();

        return [
            'student' => [
                'id' => $student->id,
                'publicId' => $student->public_id,
                'studentCode' => $student->student_code,
                'fullName' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                'studentType' => $student->student_type,
                'classes' => $student->classes->map(static function (PreschoolClass $class): array {
                    return [
                        'id' => $class->id,
                        'code' => $class->code,
                        'name' => $class->name,
                        'teacherUserId' => $class->teacher_user_id,
                    ];
                })->values()->all(),
                'legacyHealthStatus' => $student->profile?->health_status,
                'legacyVaccinationStatus' => $student->profile?->vaccination_status,
            ],
            'medicalProfile' => $student->medicalProfile,
            'legacyProfile' => $student->profile,
            'counts' => [
                'allergies' => $student->allergies->count(),
                'vaccinations' => $student->vaccinationRecords->count(),
                'medications' => $student->medicationRecords->count(),
                'incidents' => $student->healthIncidents->count(),
                'highSeverityIncidents' => $highSeverityIncidents,
                'emergencyContacts' => $student->emergencyHealthContacts->count(),
                'healthChecks' => $student->healthCheckLogs->count(),
            ],
            'primaryEmergencyContact' => $primaryEmergencyContact,
            'allergies' => $student->allergies->take(5)->values(),
            'vaccinations' => $student->vaccinationRecords->take(5)->values(),
            'medications' => $student->medicationRecords->take(5)->values(),
            'incidents' => $student->healthIncidents->take(5)->values(),
            'emergencyContacts' => $student->emergencyHealthContacts->take(5)->values(),
            'healthChecks' => $student->healthCheckLogs->take(5)->values(),
        ];
    }

    private function authorizeViewer(?User $user, PreschoolStudent $student): ?JsonResponse
    {
        if (! $user) {
            return $this->unauthorized();
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        if ($user->role_code === 'teacher-preschool' && $this->teacherCanAccessStudent($user, $student)) {
            return null;
        }

        return $this->forbidden();
    }

    private function authorizeAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return $this->unauthorized();
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        return $this->forbidden();
    }

    private function authorizeTeacherIncidentWrite(?User $user, PreschoolStudent $student): ?JsonResponse
    {
        if (! $user) {
            return $this->unauthorized();
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        if ($user->role_code === 'teacher-preschool' && $this->teacherCanAccessStudent($user, $student)) {
            return null;
        }

        return $this->forbidden();
    }

    private function teacherCanAccessStudent(User $user, PreschoolStudent $student): bool
    {
        return PreschoolClass::query()
            ->where('teacher_user_id', $user->id)
            ->whereHas('students', static function (Builder $query) use ($student): void {
                $query->where('preschool_students.id', $student->id);
            })
            ->exists();
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], Response::HTTP_NOT_FOUND);
    }

    protected function unauthorized(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
            'data' => null,
        ], Response::HTTP_UNAUTHORIZED);
    }

    protected function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}


