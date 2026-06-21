<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_preferences', function (Blueprint $table): void {
            $table->id();
            $table->string('timezone', 64)->default('Asia/Phnom_Penh');
            $table->string('default_language', 16)->default('en');
            $table->string('date_format', 32)->default('Y-m-d');
            $table->string('time_format', 32)->default('H:i');

            $table->unsignedSmallInteger('minimum_enrollment_age_months')->default(24);
            $table->unsignedSmallInteger('maximum_enrollment_age_months')->default(60);
            $table->boolean('auto_approve_enrollment')->default(false);

            $table->string('student_code_prefix', 32)->default('PS');
            $table->string('student_code_year_format', 32)->default('YYYY');
            $table->unsignedSmallInteger('student_code_sequence_length')->default(4);

            $table->unsignedSmallInteger('default_class_capacity')->default(18);
            $table->unsignedSmallInteger('teacher_student_ratio')->default(10);
            $table->boolean('waitlist_enabled')->default(true);

            $table->unsignedSmallInteger('minimum_guardians')->default(1);
            $table->unsignedSmallInteger('maximum_guardians')->default(2);
            $table->boolean('primary_guardian_required')->default(true);
            $table->boolean('pickup_authorization_required')->default(true);

            $table->boolean('attendance_alert_enabled')->default(true);
            $table->boolean('assessment_alert_enabled')->default(true);
            $table->boolean('health_alert_enabled')->default(true);
            $table->boolean('enrollment_notification_enabled')->default(true);

            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_preferences');
    }
};
