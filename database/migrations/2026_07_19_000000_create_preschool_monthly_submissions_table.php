<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the monthly submission aggregate table.
     *
     * PreschoolMonthlySubmission is the parent workflow entity that aggregates
     * teacher submissions for a specific (class, category, month). It stores
     * submission, review, return, and finalization metadata so individual
     * PreschoolStudentAssessment records remain focused on scores and observations.
     *
     * Uniqueness ensures only one submission per class+category+month.
     * Status transitions: DRAFT → SUBMITTED → RETURNED → SUBMITTED → FINALIZED → ARCHIVED
     */
    public function up(): void
    {
        Schema::create('preschool_monthly_submissions', function (Blueprint $table): void {
            $table->id();

            // Submission Identity (Uniqueness Constraint)
            $table->foreignId('academic_year_id')
                ->constrained('preschool_academic_years')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('class_id')
                ->constrained('preschool_classes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('assessment_category_id')
                ->constrained('preschool_assessment_categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->date('submission_month')
                ->comment('First day of the month, e.g., 2026-07-01');

            // Submission Timeline
            $table->timestamp('submitted_at')->nullable()
                ->comment('When teacher clicked Submit');
            $table->string('submitted_by_user_id', 16)->nullable()
                ->comment('Teacher who submitted (usually same as creator)');

            // Review Timeline
            $table->timestamp('reviewed_at')->nullable()
                ->comment('When admin opened for review');
            $table->string('reviewed_by_user_id', 16)->nullable()
                ->comment('Admin who reviewed');
            $table->text('review_comment')->nullable()
                ->comment('Admin notes during review');

            // Return Timeline (if rejected)
            $table->timestamp('returned_at')->nullable()
                ->comment('When admin returned for revision');
            $table->string('returned_by_user_id', 16)->nullable()
                ->comment('Admin who returned');
            $table->text('return_reason')->nullable()
                ->comment('Why submission was returned');

            // Finalization (Approval)
            $table->timestamp('finalized_at')->nullable()
                ->comment('When locked as official');
            $table->string('finalized_by_user_id', 16)->nullable()
                ->comment('Admin who finalized');

            // Status & Audit
            $table->enum('status', ['draft', 'submitted', 'returned', 'finalized', 'archived'])
                ->default('draft');
            $table->timestamp('locked_at')->nullable()
                ->comment('When no further edits allowed (= finalized_at)');

            // Grading Scale Snapshot
            $table->json('grading_scale_snapshot')->nullable()
                ->comment('JSON snapshot of grading scales at finalization');

            // Timestamps & Soft Delete
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(
                ['academic_year_id', 'class_id', 'assessment_category_id', 'submission_month'],
                'unique_monthly_submission'
            );
            $table->index(['class_id', 'submission_month'], 'idx_class_month');
            $table->index(['status', 'submitted_at'], 'idx_status_submitted');
            $table->index(['assessment_category_id', 'status'], 'idx_category_status');
            $table->index(['academic_year_id', 'status'], 'idx_year_status');
            $table->index(['submitted_by_user_id', 'status'], 'idx_submitted_by_status');
            $table->index(['reviewed_by_user_id', 'status'], 'idx_reviewed_by_status');

            // Foreign Keys for User IDs
            $table->foreign('submitted_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('reviewed_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('returned_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('finalized_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_monthly_submissions');
    }
};
