<?php

namespace App\Services;

use App\Models\PreschoolWorkflowApproval;
use App\Models\PreschoolWorkflowInstance;
use App\Models\User;

class PreschoolWorkflowApprovalService
{
    public function __construct(
        private readonly PreschoolWorkflowService $workflowService,
    ) {
    }

    public function listApprovals(?User $viewer, array $filters = []): array
    {
        return $this->workflowService->listApprovals($viewer, $filters);
    }

    public function requestApproval(PreschoolWorkflowInstance $instance, array $data, ?User $actor = null): PreschoolWorkflowApproval
    {
        return $this->workflowService->requestApproval($instance, $data, $actor);
    }

    public function approve(PreschoolWorkflowApproval $approval, array $data = [], ?User $actor = null): PreschoolWorkflowApproval
    {
        return $this->workflowService->approve($approval, $data, $actor);
    }

    public function reject(PreschoolWorkflowApproval $approval, array $data = [], ?User $actor = null): PreschoolWorkflowApproval
    {
        return $this->workflowService->reject($approval, $data, $actor);
    }

    public function returnApproval(PreschoolWorkflowApproval $approval, array $data = [], ?User $actor = null): PreschoolWorkflowApproval
    {
        return $this->workflowService->returnApproval($approval, $data, $actor);
    }

    public function cancel(PreschoolWorkflowApproval $approval, array $data = [], ?User $actor = null): PreschoolWorkflowApproval
    {
        return $this->workflowService->cancelApproval($approval, $data, $actor);
    }
}
