<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_classes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 191);
            $table->string('teacher_user_id', 16)->nullable();
            $table->string('teacher_display_name', 191)->nullable();
            $table->string('level', 100);
            $table->string('schedule', 191)->nullable();
            $table->unsignedInteger('students_count')->default(0);
            $table->enum('status', ['active', 'pending', 'closed', 'archived'])->default('active');
            $table->string('room', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'level'], 'preschool_classes_status_level_index');
            $table->index('teacher_user_id', 'preschool_classes_teacher_user_id_index');

            $table->foreign('teacher_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('preschool_students', function (Blueprint $table): void {
            $table->id();
            $table->string('student_code', 50)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('guardian_name', 191)->nullable();
            $table->string('guardian_phone', 32)->nullable();
            $table->string('address', 255)->nullable();
            $table->enum('status', ['active', 'pending', 'inactive', 'graduated'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'last_name'], 'preschool_students_status_last_name_index');
        });

        Schema::create('preschool_class_students', function (Blueprint $table): void {
            $table->foreignId('class_id')
                ->constrained('preschool_classes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('student_id')
                ->constrained('preschool_students')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('enrolled_at')->nullable();
            $table->enum('status', ['active', 'inactive', 'completed'])->default('active');
            $table->timestamps();

            $table->unique(['class_id', 'student_id'], 'preschool_class_students_unique');
            $table->index(['student_id', 'status'], 'preschool_class_students_student_status_index');
        });

        Schema::create('preschool_attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')
                ->constrained('preschool_classes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('student_id')
                ->constrained('preschool_students')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('recorded_by_user_id', 16);
            $table->date('attendance_date');
            $table->enum('status', ['present', 'absent', 'late', 'excused']);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['attendance_date', 'status'], 'preschool_attendance_date_status_index');
            $table->index('recorded_by_user_id', 'preschool_attendance_recorded_by_index');

            $table->foreign('recorded_by_user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('preschool_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('preschool_students')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('class_id')
                ->constrained('preschool_classes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('payment_reference', 100)->unique();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->enum('payment_method', ['cash', 'mobile_payment', 'bank_transfer', 'card', 'other'])->default('cash');
            $table->enum('payment_status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->date('due_date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['payment_status', 'due_date'], 'preschool_payments_status_due_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_payments');
        Schema::dropIfExists('preschool_attendance_records');
        Schema::dropIfExists('preschool_class_students');
        Schema::dropIfExists('preschool_students');
        Schema::dropIfExists('preschool_classes');
    }
};
