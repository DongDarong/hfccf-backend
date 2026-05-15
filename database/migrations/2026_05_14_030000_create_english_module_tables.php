<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('english_classes', function (Blueprint $table): void {
            $table->id();
            $table->string('class_code', 32)->unique();
            $table->string('name', 150);
            $table->string('level', 100);
            $table->string('teacher_user_id', 16)->nullable();
            $table->string('schedule', 191)->nullable();
            $table->string('room', 100)->nullable();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();

            $table->index('class_code', 'english_classes_class_code_index');
            $table->index('teacher_user_id', 'english_classes_teacher_user_id_index');
            $table->index('status', 'english_classes_status_index');
            $table->index('deleted_at', 'english_classes_deleted_at_index');

            $table->foreign('teacher_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('english_students', function (Blueprint $table): void {
            $table->id();
            $table->string('student_code', 32)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('guardian_name', 150)->nullable();
            $table->string('guardian_phone', 32)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 32)->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();

            $table->index('student_code', 'english_students_student_code_index');
            $table->index('status', 'english_students_status_index');
            $table->index('deleted_at', 'english_students_deleted_at_index');
        });

        Schema::create('english_class_students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')
                ->constrained('english_classes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('student_id')
                ->constrained('english_students')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('enrolled_at')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['class_id', 'student_id'], 'english_class_students_unique');
            $table->index('status', 'english_class_students_status_index');
        });

        Schema::create('english_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')
                ->constrained('english_classes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('assigned_by_user_id', 16);
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('task_status', ['draft', 'assigned', 'submitted', 'reviewed', 'completed'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index('class_id', 'english_tasks_class_id_index');
            $table->index('assigned_by_user_id', 'english_tasks_assigned_by_user_id_index');
            $table->index('task_status', 'english_tasks_task_status_index');

            $table->foreign('assigned_by_user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('english_task_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')
                ->constrained('english_tasks')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('student_id')
                ->constrained('english_students')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->text('submission_text')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->enum('submission_status', ['pending', 'submitted', 'late', 'reviewed'])->default('pending');
            $table->decimal('score', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->string('reviewed_by_user_id', 16)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'student_id'], 'english_task_submissions_unique');
            $table->index('submission_status', 'english_task_submissions_status_index');
            $table->index('reviewed_by_user_id', 'english_task_submissions_reviewed_by_user_id_index');

            $table->foreign('reviewed_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('english_task_submissions');
        Schema::dropIfExists('english_tasks');
        Schema::dropIfExists('english_class_students');
        Schema::dropIfExists('english_students');
        Schema::dropIfExists('english_classes');
    }
};
