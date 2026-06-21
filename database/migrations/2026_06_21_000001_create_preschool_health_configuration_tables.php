<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_health_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('critical_alert_enabled')->default(true);
            $table->boolean('guardian_notification_enabled')->default(true);
            $table->boolean('teacher_notification_enabled')->default(true);
            $table->boolean('admin_notification_enabled')->default(true);
            $table->boolean('medication_reminder_enabled')->default(true);
            $table->boolean('vaccination_reminder_enabled')->default(true);
            $table->unsignedSmallInteger('overdue_vaccination_alert_days')->default(30);
            $table->unsignedSmallInteger('medication_reminder_minutes_before')->default(30);
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('preschool_health_severity_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 64)->unique();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->string('color', 32)->nullable();
            $table->boolean('requires_acknowledgment')->default(false);
            $table->boolean('triggers_notification')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order'], 'preschool_health_severity_levels_active_sort_index');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('preschool_health_incident_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 64)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('default_severity_code', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order'], 'preschool_health_incident_categories_active_sort_index');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('preschool_vaccination_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 64)->nullable()->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('recommended_age_months')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order'], 'preschool_vaccination_categories_active_sort_index');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('preschool_health_check_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 64)->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order'], 'preschool_health_check_categories_active_sort_index');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_health_check_categories');
        Schema::dropIfExists('preschool_vaccination_categories');
        Schema::dropIfExists('preschool_health_incident_categories');
        Schema::dropIfExists('preschool_health_severity_levels');
        Schema::dropIfExists('preschool_health_settings');
    }
};
