<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('preschool_academic_years')) {
            Schema::create('preschool_academic_years', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('label', 191);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->enum('status', ['active', 'closed', 'archived'])->default('active');
                $table->boolean('is_current')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['status', 'is_current'], 'preschool_academic_years_status_current_index');
            });
        }

        if (! Schema::hasTable('preschool_terms')) {
            Schema::create('preschool_terms', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('academic_year_id')
                    ->constrained('preschool_academic_years')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
                $table->string('code', 50)->unique();
                $table->string('name', 191);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->enum('status', ['active', 'closed', 'archived'])->default('active');
                $table->boolean('is_current')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['academic_year_id', 'status', 'is_current'], 'preschool_terms_year_status_current_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('preschool_terms')) {
            Schema::dropIfExists('preschool_terms');
        }

        if (Schema::hasTable('preschool_academic_years')) {
            Schema::dropIfExists('preschool_academic_years');
        }
    }
};
