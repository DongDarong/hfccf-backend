<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_student_medical_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('preschool_students')->cascadeOnDelete();
            $table->string('blood_type', 10)->nullable();
            $table->json('chronic_conditions')->nullable();
            $table->json('current_conditions')->nullable();
            $table->text('medical_notes')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('student_id', 'preschool_student_medical_profiles_student_id_unique');
        });

        Schema::create('preschool_student_allergies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('preschool_students')->cascadeOnDelete();
            $table->string('allergy_name');
            $table->string('allergy_type', 100);
            $table->enum('severity', ['mild', 'moderate', 'high', 'critical'])->default('mild');
            $table->string('reaction')->nullable();
            $table->text('action_taken')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'resolved', 'inactive'])->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'severity', 'status'], 'preschool_student_allergies_student_severity_status_index');
        });

        Schema::create('preschool_student_vaccination_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('preschool_students')->cascadeOnDelete();
            $table->string('vaccine_name');
            $table->date('vaccination_date')->nullable();
            $table->enum('status', ['pending', 'completed', 'overdue', 'unknown'])->default('unknown');
            $table->unsignedInteger('dose_number')->nullable();
            $table->string('provider')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'status'], 'preschool_student_vaccination_records_student_status_index');
        });

        Schema::create('preschool_student_medication_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('preschool_students')->cascadeOnDelete();
            $table->string('medication_name');
            $table->string('dosage');
            $table->string('frequency');
            $table->string('route')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'stopped', 'completed'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'status'], 'preschool_student_medication_records_student_status_index');
        });

        Schema::create('preschool_student_health_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('preschool_students')->cascadeOnDelete();
            $table->dateTime('incident_date');
            $table->string('incident_type');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->text('action_taken');
            $table->boolean('follow_up_needed')->default(false);
            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'closed', 'resolved'])->default('open');
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'severity', 'status'], 'preschool_student_health_incidents_student_severity_status_index');
        });

        Schema::create('preschool_student_health_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('preschool_students')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship');
            $table->string('phone');
            $table->string('secondary_phone')->nullable();
            $table->unsignedInteger('priority')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->boolean('receive_alerts')->default(true);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'priority'], 'preschool_student_health_contacts_student_priority_index');
        });

        Schema::create('preschool_student_health_check_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('preschool_students')->cascadeOnDelete();
            $table->dateTime('checked_at');
            $table->decimal('temperature_celsius', 4, 1)->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('height_cm', 6, 2)->nullable();
            $table->string('symptoms')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['recorded', 'reviewed', 'follow_up'])->default('recorded');
            $table->foreignId('logged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'checked_at'], 'preschool_student_health_check_logs_student_checked_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_student_health_check_logs');
        Schema::dropIfExists('preschool_student_health_contacts');
        Schema::dropIfExists('preschool_student_health_incidents');
        Schema::dropIfExists('preschool_student_medication_records');
        Schema::dropIfExists('preschool_student_vaccination_records');
        Schema::dropIfExists('preschool_student_allergies');
        Schema::dropIfExists('preschool_student_medical_profiles');
    }
};
