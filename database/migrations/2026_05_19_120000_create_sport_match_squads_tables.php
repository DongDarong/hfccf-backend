<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_match_squads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->constrained('sport_matches')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('sport_teams')->cascadeOnDelete();
            $table->string('selected_by_user_id', 32)->nullable()->index();
            $table->string('status', 24)->default('draft')->index();
            $table->dateTime('locked_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->string('approved_by_user_id', 32)->nullable()->index();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'team_id']);
            $table->foreign('selected_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('sport_match_squad_players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('squad_id')->constrained('sport_match_squads')->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('sport_matches')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('sport_teams')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('sport_players')->cascadeOnDelete();
            $table->string('player_name_snapshot');
            $table->unsignedInteger('jersey_number_snapshot')->nullable();
            $table->string('position_snapshot')->nullable();
            $table->string('role', 24)->default('reserve')->index();
            $table->string('eligibility_status', 24)->default('eligible')->index();
            $table->boolean('is_eligible')->default(true);
            $table->text('reason')->nullable();
            $table->dateTime('selected_at')->nullable();
            $table->timestamps();

            $table->unique(['squad_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_match_squad_players');
        Schema::dropIfExists('sport_match_squads');
    }
};
