<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scholarship_students', function (Blueprint $table): void {
            $table->id();
            $table->string('student_code', 50)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('school_name', 191);
            $table->string('grade_level', 100);
            $table->string('guardian_name', 191);
            $table->string('guardian_phone', 32);
            $table->string('address', 255);
            $table->enum('status', ['active', 'pending', 'inactive', 'graduated', 'archived'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'scholarship_students_status_index');
            $table->index('grade_level', 'scholarship_students_grade_level_index');
            $table->index('created_at', 'scholarship_students_created_at_index');
        });

        Schema::create('scholarship_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('scholarship_students')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('application_code', 50)->unique();
            $table->string('scholarship_type', 100);
            $table->decimal('requested_amount', 12, 2);
            $table->string('academic_year', 20);
            $table->date('submission_date');
            $table->enum('application_status', ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'archived'])->default('draft');
            $table->string('assigned_reviewer_user_id', 16)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('application_status', 'scholarship_applications_status_index');
            $table->index('academic_year', 'scholarship_applications_academic_year_index');
            $table->index('assigned_reviewer_user_id', 'scholarship_applications_assigned_reviewer_index');
            $table->index('submission_date', 'scholarship_applications_submission_date_index');
            $table->index('created_at', 'scholarship_applications_created_at_index');

            $table->foreign('assigned_reviewer_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('scholarship_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('scholarship_applications')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('reviewer_user_id', 16);
            $table->unsignedTinyInteger('score')->nullable();
            $table->enum('recommendation', ['approve', 'review', 'reject']);
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->index('recommendation', 'scholarship_reviews_recommendation_index');
            $table->index('reviewed_at', 'scholarship_reviews_reviewed_at_index');
            $table->index('reviewer_user_id', 'scholarship_reviews_reviewer_index');

            $table->foreign('reviewer_user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('scholarship_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('scholarship_applications')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32);
            $table->string('changed_by_user_id', 16);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['application_id', 'created_at'], 'scholarship_status_histories_application_created_index');
            $table->index('changed_by_user_id', 'scholarship_status_histories_changed_by_index');

            $table->foreign('changed_by_user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scholarship_status_histories');
        Schema::dropIfExists('scholarship_reviews');
        Schema::dropIfExists('scholarship_applications');
        Schema::dropIfExists('scholarship_students');
    }
};
