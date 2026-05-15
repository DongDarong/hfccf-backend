<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_teams', function (Blueprint $table): void {
            $table->id();
            $table->string('team_code', 32)->unique();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('coach_user_id', 32)->nullable()->index();
            $table->string('coach_display_name')->nullable();
            $table->string('division')->nullable();
            $table->string('captain_name')->nullable();
            $table->unsignedInteger('players_count')->default(0);
            $table->unsignedInteger('matches_count')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('draws')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->unsignedInteger('points')->default(0);
            $table->string('venue')->nullable();
            $table->string('logo')->nullable();
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('coach_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('sport_players', function (Blueprint $table): void {
            $table->id();
            $table->string('player_code', 32)->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->unsignedInteger('jersey_number')->nullable();
            $table->string('position')->nullable();
            $table->foreignId('team_id')->nullable()->constrained('sport_teams')->nullOnDelete();
            $table->string('division')->nullable();
            $table->string('gender')->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone')->nullable();
            $table->string('photo')->nullable();
            $table->unsignedInteger('height_cm')->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->string('preferred_foot')->nullable();
            $table->string('blood_type')->nullable();
            $table->string('village')->nullable();
            $table->string('commune')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('current_school')->nullable();
            $table->string('grade_year')->nullable();
            $table->string('primary_position')->nullable();
            $table->string('registration_status')->default('registered');
            $table->unsignedInteger('matches_played')->default(0);
            $table->unsignedInteger('goals_scored')->default(0);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sport_matches', function (Blueprint $table): void {
            $table->id();
            $table->string('match_code', 32)->unique();
            $table->foreignId('home_team_id')->constrained('sport_teams')->cascadeOnDelete();
            $table->foreignId('away_team_id')->constrained('sport_teams')->cascadeOnDelete();
            $table->string('competition_type')->nullable();
            $table->string('tournament_name')->nullable();
            $table->string('venue')->nullable();
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('current_period')->nullable();
            $table->unsignedInteger('home_score')->default(0);
            $table->unsignedInteger('away_score')->default(0);
            $table->text('notes')->nullable();
            $table->string('created_by_user_id', 32)->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('sport_match_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->constrained('sport_matches')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('sport_teams')->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('sport_players')->nullOnDelete();
            $table->string('event_type', 32)->index();
            $table->unsignedSmallInteger('minute')->default(0);
            $table->unsignedSmallInteger('extra_time_minute')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by_user_id', 32)->nullable()->index();
            $table->timestamps();

            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_match_events');
        Schema::dropIfExists('sport_matches');
        Schema::dropIfExists('sport_players');
        Schema::dropIfExists('sport_teams');
    }
};
