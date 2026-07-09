<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolEnrollmentApplicationRequest;
use App\Http\Requests\Preschool\UpdatePreschoolEnrollmentApplicationRequest;
use App\Http\Resources\Preschool\PreschoolEnrollmentResource;
use App\Models\PreschoolAcademicTerm;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolClass;
use App\Models\PreschoolEnrollmentApplication;
use App\Models\PreschoolEnrollmentDocument;
use App\Models\PreschoolLifecycleAuditLog;
use App\Models\User;
use App\Services\PreschoolGuardianCommunicationService;
use App\Support\PreschoolEnrollmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PreschoolEnrollmentController extends Controller
{
    public function __construct(private readonly PreschoolEnrollmentService $enrollment) {}

    // ── List ────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $query = PreschoolEnrollmentApplication::query()
            ->with(array_merge(
                ['requestedAcademicYear', 'requestedTerm', 'preferredClass'],
                $this->applicationLocationRelations(),
            ));

        $this->applyFilters($request, $query);

        $paginator = $query
            ->orderByDesc('updated_at')
            ->paginate(
                min(max((int) $request->query('per_page', 20), 1), 100),
                ['*'], 'page',
                max((int) $request->query('page', 1), 1)
            );

        return response()->json([
            'success' => true,
            'message' => 'Enrollment applications retrieved.',
            'data' => [
                'items' => PreschoolEnrollmentResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    // ── Summary counts per status ────────────────────────────────────────────

    public function summary(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $counts = PreschoolEnrollmentApplication::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $total = array_sum($counts);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment summary retrieved.',
            'data' => [
                'summary' => [
                    'total' => $total,
                    'draft' => (int) ($counts['draft'] ?? 0),
                    'submitted' => (int) ($counts['submitted'] ?? 0),
                    'underReview' => (int) ($counts['under_review'] ?? 0),
                    'approved' => (int) ($counts['approved'] ?? 0),
                    'waitlisted' => (int) ($counts['waitlisted'] ?? 0),
                    'rejected' => (int) ($counts['rejected'] ?? 0),
                    'enrolled' => (int) ($counts['enrolled'] ?? 0),
                    'cancelled' => (int) ($counts['cancelled'] ?? 0),
                ],
            ],
        ]);
    }

    // ── Create draft application ─────────────────────────────────────────────

    public function store(StorePreschoolEnrollmentApplicationRequest $request): JsonResponse
    {
        $data = $this->normalizeIdentityPayload($request->validated());

        $application = PreschoolEnrollmentApplication::create([
            ...$data,
            'application_code' => $this->enrollment->generateApplicationCode(),
            'status' => 'draft',
            'application_date' => $data['application_date'] ?? today()->toDateString(),
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);

        $this->enrollment->seedDocumentChecklist($application);
        $this->enrollment->logDecision($application, 'created', null, 'draft', $request->user(), 'Application created.');
        $this->writeAuditLog('preschool_enrollment.created', $application, $request->user());

        $application->load(array_merge(
            ['requestedAcademicYear', 'requestedTerm', 'preferredClass', 'documents', 'decisionLogs.actor'],
            $this->applicationLocationRelations(),
        ));

        return response()->json([
            'success' => true,
            'message' => 'Enrollment application created.',
            'data' => ['application' => PreschoolEnrollmentResource::make($application)->resolve($request)],
        ], Response::HTTP_CREATED);
    }

    // ── Show single application ──────────────────────────────────────────────

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $application = $this->findOr404($id);
        if ($application instanceof JsonResponse) {
            return $application;
        }

        $application->load(array_merge(
            [
                'requestedAcademicYear',
                'requestedTerm',
                'preferredClass',
                'reviewedBy',
                'approvedBy',
                'enrolledBy',
                'enrolledStudent',
                'documents',
                'decisionLogs.actor',
            ],
            $this->applicationLocationRelations(),
        ));

        return response()->json([
            'success' => true,
            'message' => 'Application retrieved.',
            'data' => ['application' => PreschoolEnrollmentResource::make($application)->resolve($request)],
        ]);
    }

    // ── Patch application data (only while not in a terminal state) ──────────

    public function update(UpdatePreschoolEnrollmentApplicationRequest $request, string $id): JsonResponse
    {
        $application = $this->findOr404($id);
        if ($application instanceof JsonResponse) {
            return $application;
        }

        if ($application->isTerminal()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit a terminal application.',
                'data' => null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $this->normalizeIdentityPayload(
            $request->validated(),
            $application->latin_name,
            $application->khmer_name,
        );

        $application->fill([...$data, 'updated_by_user_id' => $request->user()->id]);
        $application->save();
        $application->load(array_merge(
            ['requestedAcademicYear', 'requestedTerm', 'preferredClass', 'documents', 'decisionLogs.actor'],
            $this->applicationLocationRelations(),
        ));

        return response()->json([
            'success' => true,
            'message' => 'Application updated.',
            'data' => ['application' => PreschoolEnrollmentResource::make($application)->resolve($request)],
        ]);
    }

    // ── Workflow actions ─────────────────────────────────────────────────────

    public function submit(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $application = $this->findOr404($id);
        if ($application instanceof JsonResponse) {
            return $application;
        }

        $application->loadMissing($this->applicationLocationRelations());

        if ($response = $this->enrollment->assertStatus($application, ['draft'])) {
            return $response;
        }

        $prev = $application->status;
        $application->status = 'submitted';
        $application->application_date = $application->application_date ?? today();
        $application->updated_by_user_id = $request->user()->id;
        $application->save();

        $this->enrollment->logDecision($application, 'submitted', $prev, 'submitted', $request->user());
        $this->writeAuditLog('preschool_enrollment.submitted', $application, $request->user());
        $this->enrollment->startEnrollmentWorkflow($application, $request->user());

        return $this->applicationResponse($request, $application, 'Application submitted for review.');
    }

    public function review(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $application = $this->findOr404($id);
        if ($application instanceof JsonResponse) {
            return $application;
        }

        if ($response = $this->enrollment->assertStatus($application, ['submitted', 'waitlisted'])) {
            return $response;
        }

        $prev = $application->status;
        $application->status = 'under_review';
        $application->reviewed_by_user_id = $request->user()->id;
        $application->reviewed_at = now();
        $application->updated_by_user_id = $request->user()->id;
        $application->save();

        $this->enrollment->logDecision($application, 'review_started', $prev, 'under_review', $request->user());
        $this->writeAuditLog('preschool_enrollment.review_started', $application, $request->user());
        $this->enrollment->startEnrollmentWorkflow($application, $request->user());

        return $this->applicationResponse($request, $application, 'Application is now under review.');
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $application = $this->findOr404($id);
        if ($application instanceof JsonResponse) {
            return $application;
        }

        if ($response = $this->enrollment->assertStatus($application, ['under_review', 'waitlisted'])) {
            return $response;
        }

        $data = $request->validate(['note' => ['nullable', 'string']]);

        $prev = $application->status;
        $application->status = 'approved';
        $application->approved_by_user_id = $request->user()->id;
        $application->approved_at = now();
        $application->updated_by_user_id = $request->user()->id;
        $application->save();

        $this->enrollment->logDecision($application, 'approved', $prev, 'approved', $request->user(), $data['note'] ?? null);
        $this->writeAuditLog('preschool_enrollment.approved', $application, $request->user());
        app(PreschoolGuardianCommunicationService::class)->syncEnrollmentDecision($application, $request->user(), 'approved');

        return $this->applicationResponse($request, $application, 'Application approved.');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $application = $this->findOr404($id);
        if ($application instanceof JsonResponse) {
            return $application;
        }

        if ($response = $this->enrollment->assertStatus($application, ['submitted', 'under_review', 'waitlisted'])) {
            return $response;
        }

        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);

        $prev = $application->status;
        $application->status = 'rejected';
        $application->rejection_reason = $data['rejection_reason'];
        $application->updated_by_user_id = $request->user()->id;
        $application->save();

        $this->enrollment->logDecision($application, 'rejected', $prev, 'rejected', $request->user(), $data['rejection_reason']);
        $this->writeAuditLog('preschool_enrollment.rejected', $application, $request->user());
        app(PreschoolGuardianCommunicationService::class)->syncEnrollmentDecision($application, $request->user(), 'rejected');

        return $this->applicationResponse($request, $application, 'Application rejected.');
    }

    public function waitlist(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $application = $this->findOr404($id);
        if ($application instanceof JsonResponse) {
            return $application;
        }

        if ($response = $this->enrollment->assertStatus($application, ['submitted', 'under_review'])) {
            return $response;
        }

        $data = $request->validate(['waitlist_reason' => ['nullable', 'string', 'max:1000']]);

        $prev = $application->status;
        $application->status = 'waitlisted';
        $application->waitlist_reason = $data['waitlist_reason'] ?? null;
        $application->updated_by_user_id = $request->user()->id;
        $application->save();

        $this->enrollment->logDecision($application, 'waitlisted', $prev, 'waitlisted', $request->user(), $data['waitlist_reason'] ?? null);
        $this->writeAuditLog('preschool_enrollment.waitlisted', $application, $request->user());
        app(PreschoolGuardianCommunicationService::class)->syncEnrollmentDecision($application, $request->user(), 'waitlisted');

        return $this->applicationResponse($request, $application, 'Application placed on waitlist.');
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $application = $this->findOr404($id);
        if ($application instanceof JsonResponse) {
            return $application;
        }

        // Enrolled applications cannot be cancelled — contact student management
        if ($response = $this->enrollment->assertStatus($application, ['draft', 'submitted', 'under_review', 'approved', 'waitlisted'])) {
            return $response;
        }

        $prev = $application->status;
        $application->status = 'cancelled';
        $application->updated_by_user_id = $request->user()->id;
        $application->save();

        $this->enrollment->logDecision($application, 'cancelled', $prev, 'cancelled', $request->user(), $request->input('note'));
        $this->writeAuditLog('preschool_enrollment.cancelled', $application, $request->user());

        return $this->applicationResponse($request, $application, 'Application cancelled.');
    }

    // Enroll: only approved applications may create a student record.
    public function enroll(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $application = $this->findOr404($id);
        if ($application instanceof JsonResponse) {
            return $application;
        }

        if ($response = $this->enrollment->assertStatus($application, ['approved'])) {
            return $response;
        }

        $data = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:preschool_classes,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['nullable', 'integer', 'exists:preschool_terms,id'],
            'enrollment_start_date' => ['nullable', 'date'],
        ]);

        // Prevent enrolment into archived years, closed terms, or inactive classes
        if (!empty($data['academic_year_id'])) {
            $year = PreschoolAcademicYear::find($data['academic_year_id']);
            if ($year && $year->status === 'archived') {
                return response()->json([
                    'success' => false, 'message' => 'Cannot enroll into an archived academic year.', 'data' => null,
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (!empty($data['term_id'])) {
            $term = PreschoolAcademicTerm::find($data['term_id']);
            if ($term && in_array($term->status, ['closed', 'archived'], true)) {
                return response()->json([
                    'success' => false, 'message' => 'Cannot enroll into a closed or archived term.', 'data' => null,
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (!empty($data['class_id'])) {
            $class = PreschoolClass::find($data['class_id']);
            if ($class && $class->status !== 'active') {
                return response()->json([
                    'success' => false, 'message' => 'Cannot enroll into an inactive class.', 'data' => null,
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // enrollAsStudent() runs all writes inside a DB::transaction() and
        // dispatches PaymentCreatedOnEnrollment post-commit when a class was set.
        $student = $this->enrollment->enrollAsStudent(
            $application,
            $request->user(),
            $data['class_id'] ?? null,
            $data['academic_year_id'] ?? null,
            $data['term_id'] ?? null
        );

        // Read the auto-created payment from the service property so the
        // response can include it without an extra DB query.
        $payment = $this->enrollment->lastCreatedPayment;

        $application->status = 'enrolled';
        $application->enrolled_by_user_id = $request->user()->id;
        $application->enrolled_at = now();
        $application->enrolled_student_id = $student->id;
        $application->updated_by_user_id = $request->user()->id;
        $application->save();

        $this->enrollment->logDecision($application, 'enrolled', 'approved', 'enrolled', $request->user(), "Student #{$student->student_code} created.");
        $this->writeAuditLog('preschool_enrollment.enrolled', $application, $request->user(), ['student_id' => $student->id, 'student_code' => $student->student_code]);
        app(PreschoolGuardianCommunicationService::class)->syncEnrollmentDecision($application, $request->user(), 'approved');

        // Load application relations for the resource response
        $application->load([
            'requestedAcademicYear', 'requestedTerm', 'preferredClass',
            'reviewedBy', 'approvedBy', 'enrolledBy', 'enrolledStudent',
            'documents', 'decisionLogs.actor',
            ...$this->applicationLocationRelations(),
        ]);

        // Return a custom response that includes the created payment alongside
        // the application so the frontend can display the payment confirmation.
        return response()->json([
            'success' => true,
            'message' => "Application enrolled. Student {$student->student_code} created.",
            'data'    => [
                'application' => PreschoolEnrollmentResource::make($application)->resolve($request),
                // Null when no class was assigned; frontend must handle both cases.
                'payment'     => $payment ? [
                    'id'             => $payment->id,
                    'amount'         => $payment->amount,
                    'currency'       => $payment->currency ?? 'USD',
                    'paymentStatus'  => $payment->payment_status,
                    'description'    => $payment->description,
                    'dueDate'        => $payment->due_date?->toDateString(),
                    'termId'         => $payment->term_id,
                    'academicYearId' => $payment->academic_year_id,
                ] : null,
            ],
        ]);
    }

    // ── Document checklist update ────────────────────────────────────────────

    public function updateDocument(Request $request, string $id, string $documentId): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $document = PreschoolEnrollmentDocument::query()
            ->where('id', $documentId)
            ->where('application_id', $id)
            ->first();

        if (!$document) {
            return response()->json(['success' => false, 'message' => 'Document not found.', 'data' => null], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validate([
            'is_received' => ['sometimes', 'boolean'],
            'received_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $document->fill($data)->save();

        return response()->json([
            'success' => true,
            'message' => 'Document updated.',
            'data' => [
                'document' => [
                    'id' => $document->id,
                    'documentType' => $document->document_type,
                    'isRequired' => $document->is_required,
                    'isReceived' => $document->is_received,
                    'receivedDate' => $document->received_date?->toDateString(),
                    'note' => $document->note,
                ],
            ],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function findOr404(string $id): PreschoolEnrollmentApplication|JsonResponse
    {
        $app = PreschoolEnrollmentApplication::query()->find($id);

        if (!$app) {
            return response()->json(['success' => false, 'message' => 'Application not found.', 'data' => null], Response::HTTP_NOT_FOUND);
        }

        return $app;
    }

    private function applyFilters(Request $request, Builder $query): void
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
        $level = trim((string) $request->query('level', ''));
        $yearId = trim((string) $request->query('academic_year_id', ''));

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $like = '%' . $search . '%';
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('khmer_name', 'like', $like)
                    ->orWhere('latin_name', 'like', $like)
                    ->orWhere('application_code', 'like', $like)
                    ->orWhere('place_of_birth', 'like', $like)
                    ->orWhere('nationality', 'like', $like)
                    ->orWhere('ethnicity', 'like', $like)
                    ->orWhere('guardian_name', 'like', $like)
                    ->orWhere('guardian_phone', 'like', $like);
            });
        }

        if ($level !== '') {
            $query->where('requested_level', $level);
        }

        if ($yearId !== '') {
            $query->where('requested_academic_year_id', $yearId);
        }
    }

    private function applicationResponse(Request $request, PreschoolEnrollmentApplication $application, string $message): JsonResponse
    {
        $application->load([
            'requestedAcademicYear', 'requestedTerm', 'preferredClass',
            'reviewedBy', 'approvedBy', 'enrolledBy', 'enrolledStudent',
            'documents', 'decisionLogs.actor',
            ...$this->applicationLocationRelations(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['application' => PreschoolEnrollmentResource::make($application)->resolve($request)],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function applicationLocationRelations(): array
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

    /**
     * Keep the legacy `khmer_name` column and the new canonical `latin_name`
     * column in sync for compatibility with existing clients.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeIdentityPayload(
        array $data,
        ?string $existingLatinName = null,
        ?string $existingLegacyName = null,
    ): array
    {
        $hasLatinName = array_key_exists('latin_name', $data);
        $hasLegacyName = array_key_exists('khmer_name', $data);

        if (! $hasLatinName && ! $hasLegacyName) {
            return $data;
        }

        $latinName = $hasLatinName ? trim((string) ($data['latin_name'] ?? '')) : '';
        $legacyName = $hasLegacyName ? trim((string) ($data['khmer_name'] ?? '')) : '';

        if ($latinName !== '') {
            $legacyName = $latinName;
        } elseif ($legacyName !== '') {
            $latinName = $legacyName;
        }

        $data['latin_name'] = $latinName !== '' ? $latinName : null;
        $data['khmer_name'] = $legacyName !== '' ? $legacyName : null;

        return $data;
    }

    private function authorizeAdmin(?User $user): ?JsonResponse
    {
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
    }

    private function writeAuditLog(string $actionType, PreschoolEnrollmentApplication $application, User $actor, array $extra = []): void
    {
        try {
            PreschoolLifecycleAuditLog::create([
                'actor_user_id' => $actor->id,
                'actor_role' => $actor->role_code,
                'action_type' => $actionType,
                'entity_type' => 'PreschoolEnrollmentApplication',
                'entity_id' => (string) $application->id,
                'new_state' => array_merge(['status' => $application->status, 'application_code' => $application->application_code], $extra),
                'request_context' => ['application_code' => $application->application_code],
            ]);
        } catch (\Throwable) {
            // Audit failure must never block the primary enrollment workflow
        }
    }
}
