<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('template_id')
                ->constrained('assessment_form_templates')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('version_id')
                ->constrained('assessment_form_versions')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('student_id')
                ->constrained('preschool_students')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('assessor_id', 16);
            $table->string('reviewer_id', 16)->nullable();
            $table->string('approver_id', 16)->nullable();
            $table->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'archived'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->json('location_data')->nullable();
            $table->json('device_info')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->decimal('total_score', 10, 2)->nullable();
            $table->decimal('max_score', 10, 2)->nullable();
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('risk_level_id')->nullable();
            $table->boolean('risk_override')->default(false);
            $table->text('risk_note')->nullable();
            $table->json('meta')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('student_id', 'assessment_submissions_student_id_index');
            $table->index('template_id', 'assessment_submissions_template_id_index');
            $table->index('status', 'assessment_submissions_status_index');
            $table->index('assessor_id', 'assessment_submissions_assessor_id_index');
            $table->index('submitted_at', 'assessment_submissions_submitted_at_index');
            $table->index(['student_id', 'template_id'], 'assessment_submissions_student_template_index');
            $table->index(['student_id', 'status'], 'assessment_submissions_student_status_index');

            $table->foreign('assessor_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('reviewer_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('approver_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('risk_level_id')
                ->references('id')
                ->on('assessment_risk_levels')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('assessment_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')
                ->constrained('assessment_submissions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('question_id')
                ->constrained('assessment_questions')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('question_code', 64)->nullable();
            $table->tinyInteger('repeat_index')->default(0);
            $table->mediumText('answer_text')->nullable();
            $table->date('answer_date')->nullable();
            $table->decimal('answer_number', 14, 4)->nullable();
            $table->json('answer_options')->nullable();
            $table->json('answer_matrix')->nullable();
            $table->string('answer_file', 512)->nullable();
            $table->json('answer_gps')->nullable();
            $table->decimal('score_value', 8, 2)->nullable();
            $table->boolean('is_skipped')->default(false);
            $table->timestamps();

            $table->unique(['submission_id', 'question_id', 'repeat_index'], 'assessment_answers_submission_question_repeat_unique');
            $table->index('submission_id', 'assessment_answers_submission_id_index');
            $table->index('question_id', 'assessment_answers_question_id_index');
        });

        Schema::create('assessment_submission_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')
                ->constrained('assessment_submissions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->enum('scope', ['section', 'template']);
            $table->unsignedBigInteger('scope_id');
            $table->decimal('raw_score', 10, 2)->default(0);
            $table->decimal('max_score', 10, 2)->default(0);
            $table->decimal('weighted_score', 10, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->unsignedBigInteger('risk_level_id')->nullable();
            $table->decimal('override_score', 10, 2)->nullable();
            $table->string('override_by', 16)->nullable();
            $table->text('override_note')->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'scope'], 'assessment_submission_scores_submission_scope_index');

            $table->foreign('risk_level_id')
                ->references('id')
                ->on('assessment_risk_levels')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('override_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('assessment_submission_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')
                ->constrained('assessment_submissions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('changed_by', 16);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('submission_id', 'assessment_submission_history_submission_id_index');

            $table->foreign('changed_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('assessment_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')
                ->constrained('assessment_submissions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedBigInteger('question_id')->nullable();
            $table->string('file_name', 255);
            $table->string('file_path', 512);
            $table->string('file_type', 64)->nullable();
            $table->integer('file_size')->nullable();
            $table->string('disk', 32)->default('r2');
            $table->string('uploaded_by', 16);
            $table->softDeletes();
            $table->timestamps();

            $table->index('submission_id', 'assessment_attachments_submission_id_index');
            $table->index('question_id', 'assessment_attachments_question_id_index');

            $table->foreign('question_id')
                ->references('id')
                ->on('assessment_questions')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('uploaded_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attachments');
        Schema::dropIfExists('assessment_submission_history');
        Schema::dropIfExists('assessment_submission_scores');
        Schema::dropIfExists('assessment_answers');
        Schema::dropIfExists('assessment_submissions');
    }
};
