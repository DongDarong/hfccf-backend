<?php

namespace App\Support;

use App\Models\SportMatch;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\User;
use App\Services\SportActivityRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SportCoachRequestService
{
    public function __construct(
        private readonly SportCoachAssignmentService $assignmentService,
        private readonly SportPlayerMembershipService $membershipService,
        private readonly SportActivityRecorder $activityRecorder,
    ) {}

    public function createPendingPlayerRequest(User $coach, SportTeam $team, array $data, ?UploadedFile $photo = null): SportPlayer
    {
        if (! $this->assignmentService->coachCanManageTeam($coach, $team)) {
            throw new \RuntimeException('Coach cannot manage the selected team.');
        }

        $player = DB::transaction(function () use ($coach, $team, $data, $photo): SportPlayer {
            $player = SportPlayer::query()->create([
                'player_code' => $data['player_code'] ?? strtoupper('player-'.Str::random(8)),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'jersey_number' => $data['jersey_number'] ?? null,
                'position' => $data['position'] ?? null,
                'team_id' => $team->id,
                'division' => $data['division'] ?? $team->division,
                'gender' => $data['gender'] ?? null,
                'age' => $data['age'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'phone' => $data['phone'] ?? null,
                'photo' => $photo ? ImageStorage::store($photo, 'sport/players') : null,
                'height_cm' => $data['height_cm'] ?? null,
                'weight_kg' => $data['weight_kg'] ?? null,
                'preferred_foot' => $data['preferred_foot'] ?? null,
                'blood_type' => $data['blood_type'] ?? null,
                'village' => $data['village'] ?? null,
                'commune' => $data['commune'] ?? null,
                'district' => $data['district'] ?? null,
                'province' => $data['province'] ?? null,
                'current_school' => $data['current_school'] ?? null,
                'grade_year' => $data['grade_year'] ?? null,
                'primary_position' => $data['primary_position'] ?? null,
                'registration_status' => $data['registration_status'] ?? 'pending',
                'approval_status' => SportPlayerApprovalStatus::PENDING,
                'roster_status' => SportPlayerRosterStatus::INACTIVE,
                'created_by_user_id' => $coach->id,
                'approved_by_user_id' => null,
                'approved_at' => null,
                'rejection_reason' => null,
                'matches_played' => (int) ($data['matches_played'] ?? 0),
                'goals_scored' => (int) ($data['goals_scored'] ?? 0),
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            $this->membershipService->createPendingMembership($player, $team, $coach);

            return $player->refresh()->loadMissing(['team']);
        });

        $this->activityRecorder->playerRequestCreated($player, $coach);

        return $player;
    }

    public function createPendingMatchRequest(User $coach, SportTeam $team, SportTeam $opponent, array $data): SportMatch
    {
        if (! $this->assignmentService->coachCanManageTeam($coach, $team)) {
            throw new \RuntimeException('Coach cannot manage the selected team.');
        }

        $match = DB::transaction(function () use ($coach, $team, $opponent, $data): SportMatch {
            $match = SportMatch::query()->create([
                'match_code' => $data['match_code'] ?? strtoupper('match-'.Str::random(8)),
                'home_team_id' => $team->id,
                'away_team_id' => $opponent->id,
                'competition_type' => $data['competition_type'] ?? 'friendly',
                'match_type' => $data['match_type'] ?? 'friendly',
                'tournament_name' => $data['tournament_name'] ?? null,
                'venue' => $data['venue'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'status' => 'draft',
                'approval_status' => 'pending',
                'requested_by_role' => $coach->role_code,
                'current_period' => null,
                'home_score' => 0,
                'away_score' => 0,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $coach->id,
                'approved_by_user_id' => null,
                'approved_at' => null,
                'rejection_reason' => null,
            ]);

            return $match->refresh()->loadMissing(['homeTeam', 'awayTeam']);
        });

        $this->activityRecorder->matchRequestCreated($match, $coach);

        return $match;
    }
}
