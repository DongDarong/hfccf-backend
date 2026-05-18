<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_team_assignments', function (Blueprint $table): void {
            $table->id();
            $table->string('coach_user_id', 32)->index();
            $table->foreignId('team_id')->constrained('sport_teams')->cascadeOnDelete();
            $table->string('assigned_by_user_id', 32)->index();
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->foreign('coach_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['coach_user_id', 'status'], 'coach_team_assignments_coach_status_index');
            $table->index(['team_id', 'status'], 'coach_team_assignments_team_status_index');
        });

        Schema::table('sport_players', function (Blueprint $table): void {
            $table->string('approval_status', 16)->default('approved')->after('registration_status')->index();
            $table->string('created_by_user_id', 32)->nullable()->after('approval_status')->index();
            $table->string('approved_by_user_id', 32)->nullable()->after('created_by_user_id')->index();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->text('rejection_reason')->nullable()->after('approved_at');

            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('sport_player_team_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('sport_teams')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('sport_players')->cascadeOnDelete();
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->string('created_by_user_id', 32)->nullable()->index();
            $table->timestamps();

            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['player_id', 'status'], 'sport_player_team_memberships_player_status_index');
            $table->index(['team_id', 'status'], 'sport_player_team_memberships_team_status_index');
        });

        Schema::table('sport_matches', function (Blueprint $table): void {
            $table->string('match_type', 32)->nullable()->after('competition_type')->index();
            $table->string('approval_status', 16)->default('approved')->after('status')->index();
            $table->string('approved_by_user_id', 32)->nullable()->after('approval_status')->index();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->string('requested_by_role', 32)->nullable()->after('created_by_user_id');

            $table->foreign('approved_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sport_matches', function (Blueprint $table): void {
            $table->dropForeign(['approved_by_user_id']);
            $table->dropColumn([
                'match_type',
                'approval_status',
                'approved_by_user_id',
                'approved_at',
                'rejection_reason',
                'requested_by_role',
            ]);
        });

        Schema::dropIfExists('sport_player_team_memberships');

        Schema::table('sport_players', function (Blueprint $table): void {
            $table->dropForeign(['created_by_user_id']);
            $table->dropForeign(['approved_by_user_id']);
            $table->dropColumn([
                'approval_status',
                'created_by_user_id',
                'approved_by_user_id',
                'approved_at',
                'rejection_reason',
            ]);
        });

        Schema::dropIfExists('coach_team_assignments');
    }
};
