<?php

use App\Http\Controllers\Api\Preschool\PreschoolWorkflowApprovalController;
use App\Http\Controllers\Api\Preschool\PreschoolWorkflowController;
use App\Http\Controllers\Api\Preschool\PreschoolWorkflowDefinitionController;
use Illuminate\Support\Facades\Route;

Route::prefix('workflows')->group(function (): void {
    Route::get('definitions', [PreschoolWorkflowDefinitionController::class, 'index']);
    Route::get('', [PreschoolWorkflowController::class, 'index']);
    Route::get('summary', [PreschoolWorkflowController::class, 'summary']);
    Route::get('approvals', [PreschoolWorkflowApprovalController::class, 'index']);
    Route::post('{workflow}/approvals', [PreschoolWorkflowApprovalController::class, 'store']);
    Route::get('{workflow}/timeline', [PreschoolWorkflowController::class, 'timeline']);
    Route::get('{workflow}', [PreschoolWorkflowController::class, 'show']);
    Route::post('', [PreschoolWorkflowController::class, 'store']);
    Route::patch('{workflow}/assign', [PreschoolWorkflowController::class, 'assign']);
    Route::patch('{workflow}/transition', [PreschoolWorkflowController::class, 'transition']);
    Route::patch('{workflow}/complete', [PreschoolWorkflowController::class, 'complete']);
    Route::patch('{workflow}/cancel', [PreschoolWorkflowController::class, 'cancel']);
    Route::patch('{workflow}/escalate', [PreschoolWorkflowController::class, 'escalate']);
    Route::patch('approvals/{approval}/approve', [PreschoolWorkflowApprovalController::class, 'approve']);
    Route::patch('approvals/{approval}/reject', [PreschoolWorkflowApprovalController::class, 'reject']);
    Route::patch('approvals/{approval}/return', [PreschoolWorkflowApprovalController::class, 'returnApproval']);
    Route::patch('approvals/{approval}/cancel', [PreschoolWorkflowApprovalController::class, 'cancel']);
});
