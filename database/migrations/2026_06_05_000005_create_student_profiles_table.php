<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')
                ->unique()
                ->constrained('preschool_students')
                ->cascadeOnDelete();

            // Father
            $table->string('father_name', 255)->nullable();
            $table->date('father_dob')->nullable();
            $table->string('father_occupation', 255)->nullable();
            $table->decimal('father_income', 12, 2)->nullable();
            $table->string('father_phone', 50)->nullable();
            $table->enum('father_status', ['alive', 'deceased', 'unknown', 'separated'])->nullable();

            // Mother
            $table->string('mother_name', 255)->nullable();
            $table->date('mother_dob')->nullable();
            $table->string('mother_occupation', 255)->nullable();
            $table->decimal('mother_income', 12, 2)->nullable();
            $table->string('mother_phone', 50)->nullable();
            $table->enum('mother_status', ['alive', 'deceased', 'unknown', 'separated'])->nullable();

            // Guardian (if different from parents)
            $table->string('guardian_name', 255)->nullable();
            $table->string('guardian_relation', 100)->nullable();
            $table->string('guardian_phone', 50)->nullable();

            // Household
            $table->unsignedTinyInteger('num_siblings')->default(0);
            $table->unsignedTinyInteger('birth_order')->nullable();
            $table->unsignedTinyInteger('household_size')->nullable();
            $table->decimal('monthly_income', 12, 2)->nullable();
            $table->json('income_sources')->nullable();     // ["farming","remittance"]

            // Housing
            $table->enum('housing_type', ['owned', 'rented', 'relatives', 'shelter', 'no_home'])->nullable();
            $table->boolean('has_electricity')->default(false);
            $table->boolean('has_clean_water')->default(false);
            $table->boolean('has_toilet')->default(false);

            // Education
            $table->decimal('distance_to_school', 5, 2)->nullable();   // km
            $table->string('transport_mode', 100)->nullable();

            // Health
            $table->enum('health_status', ['good', 'fair', 'poor'])->nullable();
            $table->json('disabilities')->nullable();
            $table->boolean('has_health_insurance')->default(false);
            $table->string('vaccination_status', 100)->nullable();

            $table->text('notes')->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
