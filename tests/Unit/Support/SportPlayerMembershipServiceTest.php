<?php

namespace Tests\Unit\Support;

use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Support\SportPlayerMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportPlayerMembershipServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_cannot_join_two_active_teams(): void
    {
        $service = app(SportPlayerMembershipService::class);
        $player = $this->createPlayer();
        $teamA = $this->createTeam('TEAM-U-1', 'United A');
        $teamB = $this->createTeam('TEAM-U-2', 'United B');

        $service->activateMembership($player, $teamA, null, false);

        $this->assertTrue($service->playerCanJoinTeam($player, $teamA));
        $this->assertFalse($service->playerCanJoinTeam($player, $teamB));
    }

    public function test_pending_membership_can_be_promoted_to_active_membership(): void
    {
        $service = app(SportPlayerMembershipService::class);
        $player = $this->createPlayer();
        $team = $this->createTeam('TEAM-U-3', 'United C');

        $service->createPendingMembership($player, $team, null);
        $service->activateMembership($player, $team, null, false);

        $this->assertDatabaseHas('sport_player_team_memberships', [
            'player_id' => $player->id,
            'team_id' => $team->id,
            'status' => 'active',
        ]);
    }

    private function createPlayer(): SportPlayer
    {
        return SportPlayer::query()->create([
            'player_code' => 'PLY-'.fake()->unique()->numerify('###'),
            'first_name' => 'Test',
            'last_name' => 'Player',
            'status' => 'active',
            'approval_status' => 'approved',
            'registration_status' => 'registered',
        ]);
    }

    private function createTeam(string $code, string $name): SportTeam
    {
        return SportTeam::query()->create([
            'team_code' => $code,
            'name' => $name,
            'status' => 'active',
        ]);
    }
}
