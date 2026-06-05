<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsam_form_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();

            // Self-referencing for versioning chain
            $table->unsignedBigInteger('parent_template_id')->nullable();

            $table->string('name', 255);
            $table->string('name_kh', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('description_kh')->nullable();
            $table->enum('category', ['annual_assessment', 'intake', 'follow_up', 'special'])
                ->default('annual_assessment');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->unsignedSmallInteger('version_number')->default(1);
            $table->text('version_notes')->nullable();

            // Fully configurable scoring and risk — stored as JSON so admins can adjust without migrations
            $table->json('scoring_config')->nullable();
            $table->json('risk_config')->nullable();
            $table->json('settings')->nullable();

            // users.id is string(16)
            $table->string('published_by', 16)->nullable();
            $table->string('created_by', 16)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_template_id')
                ->references('id')->on('dsam_form_templates')
                ->nullOnDelete();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'status']);
            $table->index('academic_year_id');
            $table->index('parent_template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsam_form_templates');
    }
};
