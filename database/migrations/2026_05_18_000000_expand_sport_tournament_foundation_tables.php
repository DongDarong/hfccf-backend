<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sport_tournaments', function (Blueprint $table): void {
            $table->string('slug', 64)->nullable()->unique()->after('tournament_code');
            $table->string('visibility', 32)->default('private')->after('status');
            $table->timestamp('registration_open_at')->nullable()->after('visibility');
            $table->timestamp('registration_close_at')->nullable()->after('registration_open_at');
            $table->string('logo_path')->nullable()->after('description');
            $table->string('banner_path')->nullable()->after('logo_path');
            $table->string('location')->nullable()->after('banner_path');
            $table->string('organizer')->nullable()->after('location');
            $table->json('rules')->nullable()->after('organizer');
            $table->json('settings')->nullable()->after('rules');
            $table->string('created_by_user_id', 32)->nullable()->index()->after('settings');
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('sport_tournament_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_id')->constrained('sport_tournaments')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->unsignedInteger('position')->default(1);
            $table->unsignedInteger('qualification_slots')->default(0);
            $table->string('status', 32)->default('draft');
            $table->timestamp('finalized_at')->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tournament_id', 'code']);
            $table->index(['tournament_id', 'position']);
            $table->index(['tournament_id', 'status']);
        });

        Schema::create('sport_tournament_group_teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_id')->constrained('sport_tournaments')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('sport_tournament_groups')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('sport_teams')->cascadeOnDelete();
            $table->unsignedInteger('seed')->nullable();
            $table->unsignedInteger('pot')->nullable();
            $table->unsignedInteger('position')->nullable();
            $table->string('status', 32)->default('assigned');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'team_id']);
            $table->index(['tournament_id', 'group_id']);
            $table->index(['tournament_id', 'team_id']);
        });

        Schema::create('sport_tournament_knockout_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_id')->constrained('sport_tournaments')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->unsignedInteger('position')->default(1);
            $table->unsignedInteger('bracket_size')->default(0);
            $table->string('status', 32)->default('draft');
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tournament_id', 'code']);
            $table->index(['tournament_id', 'position']);
            $table->index(['tournament_id', 'status']);
        });

        Schema::table('sport_matches', function (Blueprint $table): void {
            $table->foreignId('group_id')
                ->nullable()
                ->after('tournament_id')
                ->constrained('sport_tournament_groups')
                ->nullOnDelete();
            $table->foreignId('knockout_round_id')
                ->nullable()
                ->after('group_id')
                ->constrained('sport_tournament_knockout_rounds')
                ->nullOnDelete();
            $table->string('round_name', 64)->nullable()->after('competition_type');
            $table->unsignedInteger('matchday')->default(0)->after('scheduled_at');
            $table->unsignedInteger('extra_time_home_score')->default(0)->after('away_score');
            $table->unsignedInteger('extra_time_away_score')->default(0)->after('extra_time_home_score');
            $table->unsignedInteger('penalty_home_score')->default(0)->after('extra_time_away_score');
            $table->unsignedInteger('penalty_away_score')->default(0)->after('penalty_home_score');
            $table->foreignId('winner_team_id')
                ->nullable()
                ->after('penalty_away_score')
                ->constrained('sport_teams')
                ->nullOnDelete();
            $table->json('metadata')->nullable()->after('notes');
        });

        Schema::table('sport_match_events', function (Blueprint $table): void {
            $table->foreignId('tournament_id')
                ->nullable()
                ->after('id')
                ->constrained('sport_tournaments')
                ->cascadeOnDelete();
            $table->foreignId('assist_player_id')
                ->nullable()
                ->after('player_id')
                ->constrained('sport_players')
                ->nullOnDelete();
            $table->foreignId('player_in_id')
                ->nullable()
                ->after('assist_player_id')
                ->constrained('sport_players')
                ->nullOnDelete();
            $table->foreignId('player_out_id')
                ->nullable()
                ->after('player_in_id')
                ->constrained('sport_players')
                ->nullOnDelete();
            $table->unsignedSmallInteger('stoppage_minute')->nullable()->after('minute');
            $table->string('side', 16)->nullable()->after('stoppage_minute');
            $table->text('description')->nullable()->after('side');
        });
    }

    public function down(): void
    {
        Schema::table('sport_match_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tournament_id');
            $table->dropConstrainedForeignId('assist_player_id');
            $table->dropConstrainedForeignId('player_in_id');
            $table->dropConstrainedForeignId('player_out_id');
            $table->dropColumn(['stoppage_minute', 'side', 'description']);
        });

        Schema::table('sport_matches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('winner_team_id');
            $table->dropColumn([
                'group_id',
                'knockout_round_id',
                'round_name',
                'matchday',
                'extra_time_home_score',
                'extra_time_away_score',
                'penalty_home_score',
                'penalty_away_score',
                'metadata',
            ]);
        });

        Schema::dropIfExists('sport_tournament_knockout_rounds');
        Schema::dropIfExists('sport_tournament_group_teams');
        Schema::dropIfExists('sport_tournament_groups');

        Schema::table('sport_tournaments', function (Blueprint $table): void {
            $table->dropForeign(['created_by_user_id']);
            $table->dropColumn([
                'slug',
                'visibility',
                'registration_open_at',
                'registration_close_at',
                'logo_path',
                'banner_path',
                'location',
                'organizer',
                'rules',
                'settings',
                'created_by_user_id',
            ]);
        });
    }
};
