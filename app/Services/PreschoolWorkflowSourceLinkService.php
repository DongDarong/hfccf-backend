<?php

namespace App\Services;

use App\Models\AssessmentFormTemplate;
use App\Models\PreschoolAttendanceSession;
use App\Models\PreschoolAutomationTask;
use App\Models\PreschoolEnrollmentApplication;
use App\Models\PreschoolGuardianCommunication;
use App\Models\PreschoolHealthAlert;
use App\Models\PreschoolInvoice;
use App\Models\PreschoolNotification;
use App\Models\PreschoolWorkflowInstance;
use Illuminate\Support\Str;

class PreschoolWorkflowSourceLinkService
{
    /**
     * Resolve a readable source summary and route hint for a workflow source.
     *
     * @return array{
     *     sourceType: string,
     *     sourceId: ?string,
     *     sourceLabel: ?string,
     *     sourceRouteName: ?string,
     *     sourceRouteParams: ?array<string, mixed>,
     *     sourceExists: bool
     * }
     */
    public function resolveSource(string $sourceType, mixed $sourceId = null, ?string $sourceLabel = null): array
    {
        $normalizedType = $this->normalizeSourceType($sourceType);
        $resolvedSourceId = $this->nullableString($sourceId);

        return match ($normalizedType) {
            'preschool_enrollment_application' => $this->resolveEnrollmentApplication($resolvedSourceId, $sourceLabel),
            'preschool_health_alert' => $this->resolveHealthAlert($resolvedSourceId, $sourceLabel),
            'preschool_invoice' => $this->resolveInvoice($resolvedSourceId, $sourceLabel),
            'preschool_assessment_template' => $this->resolveAssessmentTemplate($resolvedSourceId, $sourceLabel),
            'preschool_guardian_communication' => $this->resolveGuardianCommunication($resolvedSourceId, $sourceLabel),
            'preschool_automation_task' => $this->resolveAutomationTask($resolvedSourceId, $sourceLabel),
            'preschool_attendance_alert' => $this->resolveAttendanceAlert($resolvedSourceId, $sourceLabel),
            'preschool_attendance_communication' => $this->resolveGuardianCommunication($resolvedSourceId, $sourceLabel),
            'preschool_attendance_session' => $this->resolveAttendanceSession($resolvedSourceId, $sourceLabel),
            default => $this->resolveFallback($normalizedType, $resolvedSourceId, $sourceLabel),
        };
    }

    /**
     * Resolve a workflow instance linked to a source entity, if one exists.
     *
     * @return array{
     *     workflowInstanceId: int|string|null,
     *     workflowStatus: string|null,
     *     workflowRoute: ?string,
     *     workflowActionParams: array<string, mixed>
     * }
     */
    public function resolveWorkflowLink(string $sourceType, mixed $sourceId = null): array
    {
        $normalizedType = $this->normalizeSourceType($sourceType);
        $resolvedSourceId = $this->nullableString($sourceId);
        $sourceTypes = $this->sourceTypeVariants($sourceType, $normalizedType);

        if ($resolvedSourceId === null) {
            return [
                'workflowInstanceId' => null,
                'workflowStatus' => null,
                'workflowRoute' => null,
                'workflowActionParams' => [],
            ];
        }

        $workflow = PreschoolWorkflowInstance::query()
            ->select(['id', 'status'])
            ->whereIn('source_type', $sourceTypes)
            ->where('source_id', $resolvedSourceId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if (! $workflow) {
            return [
                'workflowInstanceId' => null,
                'workflowStatus' => null,
                'workflowRoute' => null,
                'workflowActionParams' => [],
            ];
        }

        return [
            'workflowInstanceId' => $workflow->id,
            'workflowStatus' => $workflow->status,
            'workflowRoute' => 'dashboard-preschool-admin-workflow-details',
            'workflowActionParams' => ['id' => $workflow->id],
        ];
    }

    /**
     * @return array{
     *     sourceType: string,
     *     sourceId: ?string,
     *     sourceLabel: ?string,
     *     sourceRouteName: ?string,
     *     sourceRouteParams: ?array<string, mixed>,
     *     sourceExists: bool
     * }
     */
    private function resolveEnrollmentApplication(?string $sourceId, ?string $sourceLabel): array
    {
        $application = $sourceId !== null
            ? PreschoolEnrollmentApplication::query()
                ->whereKey($sourceId)
                ->orWhere('application_code', $sourceId)
                ->first()
            : null;

        return [
            'sourceType' => 'preschool_enrollment_application',
            'sourceId' => $sourceId,
            'sourceLabel' => $this->fallbackLabel($sourceLabel, $application?->application_code, 'Enrollment application', $sourceId),
            'sourceRouteName' => 'dashboard-preschool-admin-enrollments',
            'sourceRouteParams' => [],
            'sourceExists' => $application !== null,
        ];
    }

    /**
     * @return array{
     *     sourceType: string,
     *     sourceId: ?string,
     *     sourceLabel: ?string,
     *     sourceRouteName: ?string,
     *     sourceRouteParams: ?array<string, mixed>,
     *     sourceExists: bool
     * }
     */
    private function resolveHealthAlert(?string $sourceId, ?string $sourceLabel): array
    {
        $alert = $sourceId !== null
            ? PreschoolHealthAlert::query()->with('student')->find($sourceId)
            : null;

        $routeParams = $alert?->student_id ? ['id' => $alert->student_id] : [];

        return [
            'sourceType' => 'preschool_health_alert',
            'sourceId' => $sourceId,
            'sourceLabel' => $this->fallbackLabel(
                $sourceLabel,
                $alert?->title,
                $alert?->student ? trim(($alert->student->first_name ?? '').' '.($alert->student->last_name ?? '')) : null,
                'Health alert',
                $sourceId,
            ),
            'sourceRouteName' => $routeParams !== [] ? 'dashboard-preschool-admin-health-student' : 'dashboard-preschool-admin-health',
            'sourceRouteParams' => $routeParams,
            'sourceExists' => $alert !== null,
        ];
    }

    /**
     * @return array{
     *     sourceType: string,
     *     sourceId: ?string,
     *     sourceLabel: ?string,
     *     sourceRouteName: ?string,
     *     sourceRouteParams: ?array<string, mixed>,
     *     sourceExists: bool
     * }
     */
    private function resolveInvoice(?string $sourceId, ?string $sourceLabel): array
    {
        $invoice = $sourceId !== null
            ? PreschoolInvoice::query()
                ->whereKey($sourceId)
                ->orWhere('invoice_number', $sourceId)
                ->first()
            : null;

        return [
            'sourceType' => 'preschool_invoice',
            'sourceId' => $sourceId,
            'sourceLabel' => $this->fallbackLabel($sourceLabel, $invoice?->invoice_number, 'Invoice', $sourceId),
            'sourceRouteName' => 'dashboard-preschool-admin-invoice-detail',
            'sourceRouteParams' => $invoice?->id !== null ? ['id' => $invoice->id] : [],
            'sourceExists' => $invoice !== null,
        ];
    }

    /**
     * @return array{
     *     sourceType: string,
     *     sourceId: ?string,
     *     sourceLabel: ?string,
     *     sourceRouteName: ?string,
     *     sourceRouteParams: ?array<string, mixed>,
     *     sourceExists: bool
     * }
     */
    private function resolveAssessmentTemplate(?string $sourceId, ?string $sourceLabel): array
    {
        $template = $sourceId !== null
            ? AssessmentFormTemplate::query()
                ->whereKey($sourceId)
                ->orWhere('uuid', $sourceId)
                ->orWhere('code', $sourceId)
                ->first()
            : null;

        return [
            'sourceType' => 'preschool_assessment_template',
            'sourceId' => $sourceId,
            'sourceLabel' => $this->fallbackLabel($sourceLabel, $template?->name, $template?->code, 'Assessment template', $sourceId),
            'sourceRouteName' => 'preschool-assessment-dashboard',
            'sourceRouteParams' => [],
            'sourceExists' => $template !== null,
        ];
    }

    /**
     * @return array{
     *     sourceType: string,
     *     sourceId: ?string,
     *     sourceLabel: ?string,
     *     sourceRouteName: ?string,
     *     sourceRouteParams: ?array<string, mixed>,
     *     sourceExists: bool
     * }
     */
    private function resolveGuardianCommunication(?string $sourceId, ?string $sourceLabel): array
    {
        $communication = $sourceId !== null
            ? PreschoolGuardianCommunication::query()->find($sourceId)
            : null;

        return [
            'sourceType' => 'preschool_guardian_communication',
            'sourceId' => $sourceId,
            'sourceLabel' => $this->fallbackLabel($sourceLabel, $communication?->subject, $communication?->message, 'Guardian communication', $sourceId),
            'sourceRouteName' => 'dashboard-preschool-admin-guardian-communications',
            'sourceRouteParams' => [],
            'sourceExists' => $communication !== null,
        ];
    }

    /**
     * @return array{
     *     sourceType: string,
     *     sourceId: ?string,
     *     sourceLabel: ?string,
     *     sourceRouteName: ?string,
     *     sourceRouteParams: ?array<string, mixed>,
     *     sourceExists: bool
     * }
     */
    private function resolveAutomationTask(?string $sourceId, ?string $sourceLabel): array
    {
        $task = $sourceId !== null
            ? PreschoolAutomationTask::query()->find($sourceId)
            : null;

        return [
            'sourceType' => 'preschool_automation_task',
            'sourceId' => $sourceId,
            'sourceLabel' => $this->fallbackLabel($sourceLabel, $task?->title, 'Automation task', $sourceId),
            'sourceRouteName' => 'dashboard-preschool-admin-notifications',
            'sourceRouteParams' => [],
            'sourceExists' => $task !== null,
        ];
    }

    /**
     * @return array{
     *     sourceType: string,
     *     sourceId: ?string,
     *     sourceLabel: ?string,
     *     sourceRouteName: ?string,
     *     sourceRouteParams: ?array<string, mixed>,
     *     sourceExists: bool
     * }
     */
    private function resolveAttendanceAlert(?string $sourceId, ?string $sourceLabel): array
    {
        return [
            'sourceType' => 'preschool_attendance_alert',
            'sourceId' => $sourceId,
            'sourceLabel' => $this->fallbackLabel($sourceLabel, 'Attendance alert', $sourceId),
            'sourceRouteName' => 'dashboard-preschool-admin-attendance-alerts',
            'sourceRouteParams' => [],
            'sourceExists' => false,
        ];
    }

    /**
     * @return array{
     *     sourceType: string,
     *     sourceId: ?string,
     *     sourceLabel: ?string,
     *     sourceRouteName: ?string,
     *     sourceRouteParams: ?array<string, mixed>,
     *     sourceExists: bool
     * }
     */
    private function resolveAttendanceSession(?string $sourceId, ?string $sourceLabel): array
    {
        $session = $sourceId !== null
            ? PreschoolAttendanceSession::query()->find($sourceId)
            : null;

        return [
            'sourceType' => 'preschool_attendance_session',
            'sourceId' => $sourceId,
            'sourceLabel' => $this->fallbackLabel($sourceLabel, $session?->className, 'Attendance session', $sourceId),
            'sourceRouteName' => 'dashboard-preschool-admin-attendance-session-details',
            'sourceRouteParams' => $session?->id !== null ? ['id' => $session->id] : [],
            'sourceExists' => $session !== null,
        ];
    }

    /**
     * @return array{
     *     sourceType: string,
     *     sourceId: ?string,
     *     sourceLabel: ?string,
     *     sourceRouteName: ?string,
     *     sourceRouteParams: ?array<string, mixed>,
     *     sourceExists: bool
     * }
     */
    private function resolveFallback(string $sourceType, ?string $sourceId, ?string $sourceLabel): array
    {
        return [
            'sourceType' => $sourceType,
            'sourceId' => $sourceId,
            'sourceLabel' => $this->fallbackLabel($sourceLabel, $this->humanizeSourceType($sourceType), $sourceId),
            'sourceRouteName' => null,
            'sourceRouteParams' => null,
            'sourceExists' => false,
        ];
    }

    private function normalizeSourceType(string $sourceType): string
    {
        $value = strtolower(trim($sourceType));

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/^preschool_/', '', $value) ?? $value;

        return match ($value) {
            'enrollment_application', 'enrollment_applications' => 'preschool_enrollment_application',
            'health_alert', 'health_alerts' => 'preschool_health_alert',
            'invoice', 'invoices' => 'preschool_invoice',
            'assessment_template', 'assessment_templates', 'assessment' => 'preschool_assessment_template',
            'guardian_communication', 'guardian_communications', 'communication' => 'preschool_guardian_communication',
            'automation_task', 'automation_tasks', 'task' => 'preschool_automation_task',
            'attendance_alert', 'attendance_alerts' => 'preschool_attendance_alert',
            'attendance_communication', 'attendance_communications' => 'preschool_attendance_communication',
            'attendance_session', 'attendance_sessions' => 'preschool_attendance_session',
            default => $value,
        };
    }

    /**
     * @return array<int, string>
     */
    private function sourceTypeVariants(string $rawSourceType, string $normalizedSourceType): array
    {
        $variants = array_filter([
            strtolower(trim($rawSourceType)),
            $normalizedSourceType,
            preg_replace('/^preschool_/', '', strtolower(trim($rawSourceType))) ?: null,
        ]);

        return array_values(array_unique($variants));
    }

    private function fallbackLabel(?string ...$values): ?string
    {
        foreach ($values as $value) {
            $normalized = $this->nullableString($value);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function humanizeSourceType(string $sourceType): string
    {
        return Str::headline(str_replace(['preschool_', '_'], ['', ' '], $sourceType));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
