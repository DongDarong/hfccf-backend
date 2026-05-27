<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guardian relationships stay normalized so Preschool can preserve
     * historical contacts without turning parents or guardians into users.
     */
    public function up(): void
    {
        Schema::create('preschool_guardians', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name', 191);
            $table->string('phone', 32);
            $table->string('secondary_phone', 32)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('occupation', 191)->nullable();
            $table->string('national_id', 100)->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->text('notes')->nullable();
            $table->string('created_by_user_id', 16)->nullable()->index();
            $table->string('updated_by_user_id', 16)->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'full_name'], 'preschool_guardians_status_name_index');
            $table->index(['phone', 'status'], 'preschool_guardians_phone_status_index');
        });

        Schema::table('preschool_guardians', function (Blueprint $table): void {
            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('updated_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('preschool_student_guardians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('preschool_students')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('guardian_id')
                ->constrained('preschool_guardians')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('relationship_type', 32);
            $table->boolean('is_primary')->default(false);
            $table->boolean('can_pickup')->default(false);
            $table->unsignedInteger('emergency_priority')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->date('starts_at')->nullable()->index();
            $table->date('ends_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('created_by_user_id', 16)->nullable()->index();
            $table->string('updated_by_user_id', 16)->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'status'], 'preschool_student_guardians_student_status_index');
            $table->index(['guardian_id', 'status'], 'preschool_student_guardians_guardian_status_index');
            $table->index(['relationship_type', 'status'], 'preschool_student_guardians_type_status_index');
            $table->index(['student_id', 'is_primary', 'status'], 'preschool_student_guardians_primary_index');
            $table->index(['student_id', 'emergency_priority'], 'preschool_student_guardians_priority_index');
            $table->unique(['student_id', 'guardian_id', 'status'], 'preschool_student_guardians_unique_active_pair');
        });

        Schema::table('preschool_student_guardians', function (Blueprint $table): void {
            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('updated_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_student_guardians');
        Schema::dropIfExists('preschool_guardians');
    }
};
