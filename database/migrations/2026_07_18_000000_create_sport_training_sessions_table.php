<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_training_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('session_code', 32)->unique();
            $table->foreignId('team_id')->constrained('sport_teams')->cascadeOnDelete();
            $table->string('coach_user_id', 32)->nullable();
            $table->foreign('coach_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('title');
            $table->string('training_type', 32)->default('technical');
            $table->text('focus')->nullable();
            $table->string('venue')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('intensity', 16)->default('medium');
            $table->string('status', 16)->default('scheduled');
            $table->text('notes')->nullable();
            $table->string('created_by_user_id', 32)->nullable();
            $table->string('updated_by_user_id', 32)->nullable();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['team_id', 'starts_at']);
            $table->index(['coach_user_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_training_sessions');
    }
};
