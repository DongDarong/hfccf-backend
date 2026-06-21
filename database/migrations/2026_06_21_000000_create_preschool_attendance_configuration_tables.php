<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_attendance_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('late_threshold_minutes')->default(15);
            $table->unsignedSmallInteger('half_day_threshold_minutes')->default(180);
            $table->unsignedSmallInteger('absence_alert_days')->default(3);
            $table->boolean('guardian_alert_enabled')->default(true);
            $table->boolean('teacher_alert_enabled')->default(true);
            $table->boolean('admin_alert_enabled')->default(true);
            $table->boolean('monday_enabled')->default(true);
            $table->boolean('tuesday_enabled')->default(true);
            $table->boolean('wednesday_enabled')->default(true);
            $table->boolean('thursday_enabled')->default(true);
            $table->boolean('friday_enabled')->default(true);
            $table->boolean('saturday_enabled')->default(false);
            $table->boolean('sunday_enabled')->default(false);
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('preschool_school_calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')
                ->constrained('preschool_academic_years')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->enum('type', ['holiday', 'closure', 'teacher_training', 'examination', 'special_event']);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['academic_year_id', 'type', 'status'], 'preschool_school_calendar_events_year_type_status_index');
            $table->index(['start_date', 'end_date'], 'preschool_school_calendar_events_dates_index');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_school_calendar_events');
        Schema::dropIfExists('preschool_attendance_settings');
    }
};
