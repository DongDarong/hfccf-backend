<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_enrollment_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('application_code', 50)->unique();

            // Student identity (captured at application time, before a student record exists)
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('khmer_name', 200)->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth', 200)->nullable();
            $table->string('nationality', 100)->nullable()->default('Cambodian');
            $table->string('avatar', 500)->nullable();

            // Academic request
            $table->unsignedBigInteger('requested_academic_year_id')->nullable();
            $table->unsignedBigInteger('requested_term_id')->nullable();
            $table->string('requested_level', 100)->nullable();
            $table->unsignedBigInteger('preferred_class_id')->nullable();
            $table->date('requested_start_date')->nullable();

            // Primary guardian snapshot (denormalised for speed; full record in guardian tables after enroll)
            $table->string('guardian_name', 200)->nullable();
            $table->string('guardian_relationship', 100)->nullable();
            $table->string('guardian_phone', 50)->nullable();
            $table->string('guardian_email', 200)->nullable();
            $table->string('guardian_address', 500)->nullable();
            $table->boolean('guardian_can_pickup')->default(true);
            $table->boolean('guardian_is_emergency')->default(true);

            // Workflow status
            // draft → submitted → under_review → approved / waitlisted / rejected → enrolled / cancelled
            $table->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'waitlisted', 'rejected', 'enrolled', 'cancelled'])
                ->default('draft');
            $table->date('application_date')->nullable();
            $table->string('source', 100)->nullable()->default('walk_in');

            // Admin tracking
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('waitlist_reason')->nullable();

            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('enrolled_by_user_id')->nullable();
            $table->timestamp('enrolled_at')->nullable();

            // Link to created student after enrollment
            $table->unsignedBigInteger('enrolled_student_id')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status'], 'enrollment_status_index');
            $table->index(['application_date'], 'enrollment_date_index');
            $table->index(['requested_academic_year_id', 'requested_term_id'], 'enrollment_academic_index');
        });

        Schema::create('preschool_enrollment_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->string('document_type', 100);
            // birth_certificate, family_book, vaccination_card, parent_id, photo, consent_form
            $table->boolean('is_required')->default(true);
            $table->boolean('is_received')->default(false);
            $table->date('received_date')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('application_id')
                ->references('id')
                ->on('preschool_enrollment_applications')
                ->cascadeOnDelete();

            $table->unique(['application_id', 'document_type'], 'enrollment_doc_unique');
        });

        // Immutable log of every status transition for an application.
        // Separate from lifecycle_audit_logs so enrollment history is queryable independently.
        Schema::create('preschool_enrollment_decision_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->string('action', 100); // submitted, review_started, approved, rejected, etc.
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_role', 100)->nullable();
            $table->text('note')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('recorded_at')->useCurrent();

            $table->foreign('application_id')
                ->references('id')
                ->on('preschool_enrollment_applications')
                ->cascadeOnDelete();

            $table->index(['application_id', 'recorded_at'], 'enrollment_log_app_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_enrollment_decision_logs');
        Schema::dropIfExists('preschool_enrollment_documents');
        Schema::dropIfExists('preschool_enrollment_applications');
    }
};
