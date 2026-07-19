<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_equipment_assignments', function (Blueprint $table): void {
            $table->id();
            $table->string('assignment_code', 32)->unique();
            $table->foreignId('equipment_request_id')->unique()->constrained('sport_equipment_requests')->restrictOnDelete();
            $table->foreignId('equipment_item_id')->constrained('sport_equipment_items')->restrictOnDelete();
            $table->foreignId('team_id')->constrained('sport_teams')->restrictOnDelete();
            $table->string('coach_user_id', 32)->index();
            $table->unsignedInteger('assigned_quantity');
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->unsignedInteger('missing_quantity')->default(0);
            $table->string('status', 16)->default('assigned')->index();
            $table->dateTime('assigned_at')->index();
            $table->dateTime('expected_return_at')->nullable()->index();
            $table->dateTime('returned_at')->nullable()->index();
            $table->string('assigned_by_user_id', 32)->nullable()->index();
            $table->string('returned_by_user_id', 32)->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('coach_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('assigned_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('returned_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['team_id', 'status']);
            $table->index(['equipment_item_id', 'status']);
            $table->index(['coach_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_equipment_assignments');
    }
};
