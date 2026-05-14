<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_tournaments', function (Blueprint $table): void {
            $table->id();
            $table->string('tournament_code', 32)->unique();
            $table->string('name');
            $table->string('season', 64)->nullable();
            $table->string('tournament_type', 32)->default('league');
            $table->string('status', 32)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sport_tournament_teams', function (Blueprint $table): void {
            $table->foreignId('tournament_id')->constrained('sport_tournaments')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('sport_teams')->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'team_id']);
        });

        Schema::create('sport_standings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_id')->constrained('sport_tournaments')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('sport_teams')->cascadeOnDelete();
            $table->unsignedInteger('played')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('draws')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->unsignedInteger('goals_for')->default(0);
            $table->unsignedInteger('goals_against')->default(0);
            $table->integer('goal_difference')->default(0);
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('rank_position')->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'team_id']);
            $table->index(['tournament_id', 'rank_position']);
        });

        Schema::table('sport_matches', function (Blueprint $table): void {
            $table->foreignId('tournament_id')
                ->nullable()
                ->after('away_team_id')
                ->constrained('sport_tournaments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sport_matches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tournament_id');
        });

        Schema::dropIfExists('sport_standings');
        Schema::dropIfExists('sport_tournament_teams');
        Schema::dropIfExists('sport_tournaments');
    }
};
