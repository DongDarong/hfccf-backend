<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Governance cases are workflow records, not immutable history. The tables
     * below keep review ownership, escalation, evidence, and status changes
     * separate from snapshots so administrators can manage institutional risks
     * without touching frozen report data.
     */
    public function up(): void
    {
        if (! Schema::hasTable('preschool_governance_cases')) {
            Schema::create('preschool_governance_cases', function (Blueprint $table): void {
                $table->id();
                $table->string('case_key', 32)->unique();
                $table->string('title', 191);
                $table->text('summary')->nullable();
                $table->string('source_type', 64);
                $table->string('source_reference', 191)->nullable();
                $table->json('source_context')->nullable();
                $table->string('severity', 16)->default('medium');
                $table->unsignedTinyInteger('risk_score')->default(0);
                $table->string('status', 32)->default('open');
                $table->boolean('is_urgent')->default(false);
                $table->text('urgent_reason')->nullable();
                $table->string('owner_user_id', 16)->nullable();
                $table->string('reviewer_user_id', 16)->nullable();
                $table->string('escalation_officer_user_id', 16)->nullable();
                $table->date('due_date')->nullable();
                $table->foreignId('academic_year_id')->nullable()->constrained('preschool_academic_years')->nullOnDelete();
                $table->foreignId('term_id')->nullable()->constrained('preschool_terms')->nullOnDelete();
                $table->foreignId('report_period_id')->nullable()->constrained('preschool_report_periods')->nullOnDelete();
                $table->foreignId('class_id')->nullable()->constrained('preschool_classes')->nullOnDelete();
                $table->foreignId('student_id')->nullable()->constrained('preschool_students')->nullOnDelete();
                $table->string('created_by', 16);
                $table->string('resolved_by', 16)->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->string('closed_by', 16)->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->text('resolution_note')->nullable();
                $table->text('latest_note')->nullable();
                $table->timestamps();

                $table->index('case_key', 'preschool_governance_cases_case_key_index');
                $table->index('source_type', 'preschool_governance_cases_source_type_index');
                $table->index('source_reference', 'preschool_governance_cases_source_reference_index');
                $table->index('severity', 'preschool_governance_cases_severity_index');
                $table->index('status', 'preschool_governance_cases_status_index');
                $table->index('risk_score', 'preschool_governance_cases_risk_score_index');
                $table->index('is_urgent', 'preschool_governance_cases_is_urgent_index');
                $table->index('owner_user_id', 'preschool_governance_cases_owner_user_id_index');
                $table->index('reviewer_user_id', 'preschool_governance_cases_reviewer_user_id_index');
                $table->index('escalation_officer_user_id', 'preschool_governance_cases_escalation_officer_user_id_index');
                $table->index('due_date', 'preschool_governance_cases_due_date_index');
                $table->index(['academic_year_id', 'term_id', 'report_period_id'], 'preschool_governance_cases_academic_context_index');
                $table->index(['class_id', 'student_id'], 'preschool_governance_cases_entity_index');
                $table->index('created_at', 'preschool_governance_cases_created_at_index');
                $table->index('updated_at', 'preschool_governance_cases_updated_at_index');

                $table->foreign('owner_user_id', 'preschool_governance_cases_owner_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();

                $table->foreign('reviewer_user_id', 'preschool_governance_cases_reviewer_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();

                $table->foreign('escalation_officer_user_id', 'preschool_governance_cases_escalation_officer_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();

                $table->foreign('created_by', 'preschool_governance_cases_created_by_foreign')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnUpdate();

                $table->foreign('resolved_by', 'preschool_governance_cases_resolved_by_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();

                $table->foreign('closed_by', 'preschool_governance_cases_closed_by_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('preschool_governance_case_events')) {
            Schema::create('preschool_governance_case_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('governance_case_id')->constrained('preschool_governance_cases')->cascadeOnDelete();
                $table->string('event_type', 64);
                $table->string('actor_user_id', 16)->nullable();
                $table->string('actor_role', 64)->nullable();
                $table->string('previous_status', 32)->nullable();
                $table->string('new_status', 32)->nullable();
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('governance_case_id', 'preschool_governance_case_events_case_id_index');
                $table->index('event_type', 'preschool_governance_case_events_event_type_index');
                $table->index('actor_user_id', 'preschool_governance_case_events_actor_user_id_index');
                $table->index('created_at', 'preschool_governance_case_events_created_at_index');

                $table->foreign('actor_user_id', 'preschool_governance_case_events_actor_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('preschool_governance_case_evidence')) {
            Schema::create('preschool_governance_case_evidence', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('governance_case_id')->constrained('preschool_governance_cases')->cascadeOnDelete();
                $table->string('evidence_type', 64);
                $table->string('evidence_reference', 191)->nullable();
                $table->string('evidence_label', 191)->nullable();
                $table->text('evidence_description')->nullable();
                $table->json('metadata')->nullable();
                $table->string('created_by', 16)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('governance_case_id', 'preschool_governance_case_evidence_case_id_index');
                $table->index('evidence_type', 'preschool_governance_case_evidence_type_index');
                $table->index('evidence_reference', 'preschool_governance_case_evidence_reference_index');
                $table->index('created_by', 'preschool_governance_case_evidence_created_by_index');
                $table->index('created_at', 'preschool_governance_case_evidence_created_at_index');

                $table->foreign('created_by', 'preschool_governance_case_evidence_created_by_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_governance_case_evidence');
        Schema::dropIfExists('preschool_governance_case_events');
        Schema::dropIfExists('preschool_governance_cases');
    }
};
