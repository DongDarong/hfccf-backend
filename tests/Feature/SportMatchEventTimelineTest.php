<?php

namespace Tests\Feature;

use App\Models\SportMatch;
use App\Models\SportMatchEvent;
use App\Models\SportMatchSquad;
use App\Models\SportMatchSquadPlayer;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportMatchEventTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_coach_can_record_snapshot_based_event_for_assigned_match_team(): void
    {
        $coach = $this->actingAsRole('coach', 'usr_evt_001', 'coach.events001@hfccf.org');
        $team = $this->createTeam('TEAM-EVT-1', 'Event Team One', $coach->id, 'Coach Event');
        $awayTeam = $this->createTeam('TEAM-EVT-2', 'Event Team Two');
        $match = $this->createMatch($team, $awayTeam, 'live');
        $squad = SportMatchSquad::query()->create([
            'match_id' => $match->id,
            'team_id' => $team->id,
            'status' => 'draft',
        ]);
        $squadPlayer = $this->createSquadPlayer($match, $team, $squad);

        $response = $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $team->id,
            'squad_player_id' => $squadPlayer->id,
            'event_type' => 'goal',
            'minute' => 12,
            'period' => 'first_half',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event.squadPlayerId', $squadPlayer->id)
            ->assertJsonPath('data.event.playerNameSnapshot', 'Event One Player')
            ->assertJsonPath('data.event.eventType', 'goal');
    }

    public function test_non_squad_players_are_blocked_from_match_events(): void
    {
        $this->actingAsRole('adminsport', 'usr_evt_002', 'sport.events002@hfccf.org');
        $team = $this->createTeam('TEAM-EVT-3', 'Event Team Three');
        $awayTeam = $this->createTeam('TEAM-EVT-4', 'Event Team Four');
        $match = $this->createMatch($team, $awayTeam, 'live');
        $player = $this->createPlayer($team, 'Event', 'Legacy');

        $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $team->id,
            'player_id' => $player->id,
            'event_type' => 'goal',
            'minute' => 14,
        ])->assertUnprocessable();
    }

    public function test_completed_matches_block_event_edits_until_reopened(): void
    {
        $this->actingAsRole('adminsport', 'usr_evt_003', 'sport.events003@hfccf.org');
        $team = $this->createTeam('TEAM-EVT-5', 'Event Team Five');
        $awayTeam = $this->createTeam('TEAM-EVT-6', 'Event Team Six');
        $match = $this->createMatch($team, $awayTeam, 'completed');

        $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $team->id,
            'event_type' => 'goal',
            'minute' => 10,
        ])->assertUnprocessable();

        $this->patchJson('/api/sport/matches/'.$match->id.'/status', [
            'status' => 'live',
            'current_period' => 'second_half',
        ])->assertOk();

        $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $team->id,
            'event_type' => 'goal',
            'minute' => 11,
        ])->assertCreated();
    }

    public function test_timeline_order_is_stable_and_red_cards_block_follow_up_events(): void
    {
        $this->actingAsRole('adminsport', 'usr_evt_004', 'sport.events004@hfccf.org');
        $team = $this->createTeam('TEAM-EVT-7', 'Event Team Seven');
        $awayTeam = $this->createTeam('TEAM-EVT-8', 'Event Team Eight');
        $match = $this->createMatch($team, $awayTeam, 'live');
        $squad = SportMatchSquad::query()->create([
            'match_id' => $match->id,
            'team_id' => $team->id,
            'status' => 'draft',
        ]);
        $squadPlayer = $this->createSquadPlayer($match, $team, $squad);
        $otherSquadPlayer = $this->createSquadPlayer($match, $team, $squad, 'Event One Reserve');

        $first = $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $team->id,
            'squad_player_id' => $squadPlayer->id,
            'event_type' => 'red_card',
            'minute' => 45,
        ])->assertCreated();

        $second = $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $team->id,
            'squad_player_id' => $otherSquadPlayer->id,
            'event_type' => 'goal',
            'minute' => 45,
            'extra_time_minute' => 1,
        ])->assertCreated();

        $timeline = $this->getJson('/api/sport/matches/'.$match->id.'/events')
            ->assertOk()
            ->json('data.items');

        $this->assertSame($first->json('data.event.id'), $timeline[0]['id']);
        $this->assertSame($second->json('data.event.id'), $timeline[1]['id']);

        $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $team->id,
            'squad_player_id' => $squadPlayer->id,
            'event_type' => 'goal',
            'minute' => 46,
        ])->assertUnprocessable();
    }

    public function test_snapshot_fields_persist_after_player_updates(): void
    {
        $this->actingAsRole('adminsport', 'usr_evt_005', 'sport.events005@hfccf.org');
        $team = $this->createTeam('TEAM-EVT-9', 'Event Team Nine');
        $awayTeam = $this->createTeam('TEAM-EVT-10', 'Event Team Ten');
        $match = $this->createMatch($team, $awayTeam, 'live');
        $squad = SportMatchSquad::query()->create([
            'match_id' => $match->id,
            'team_id' => $team->id,
            'status' => 'draft',
        ]);
        $squadPlayer = $this->createSquadPlayer($match, $team, $squad, 'Event Snapshot Player');

        $response = $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $team->id,
            'squad_player_id' => $squadPlayer->id,
            'event_type' => 'goal',
            'minute' => 18,
        ])->assertCreated();

        $eventId = $response->json('data.event.id');

        $player = SportPlayer::query()->findOrFail($squadPlayer->player_id);
        $player->forceFill([
            'first_name' => 'Changed',
            'last_name' => 'Name',
            'jersey_number' => 99,
            'primary_position' => 'Midfielder',
        ])->save();

        $event = SportMatchEvent::query()->findOrFail($eventId);
        $this->assertSame('Event Snapshot Player', $event->player_name_snapshot);
        $this->assertSame(7, (int) $event->jersey_number_snapshot);
        $this->assertSame('Forward', $event->position_snapshot);
    }

    private function actingAsRole(string $roleCode, string $id, string $email): User
    {
        $user = User::query()->create([
            'id' => $id,
            'first_name' => ucfirst($roleCode),
            'last_name' => 'User',
            'username' => ucfirst($roleCode).' User',
            'email' => $email,
            'phone' => '+855 12 620 620',
            'role_code' => $roleCode,
            'department_code' => 'sports',
            'status' => 'active',
            'password' => 'secret-pass',
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createTeam(string $code, string $name, ?string $coachUserId = null, ?string $coachDisplayName = null): SportTeam
    {
        return SportTeam::query()->create([
            'team_code' => $code,
            'name' => $name,
            'coach_user_id' => $coachUserId,
            'coach_display_name' => $coachDisplayName,
            'status' => 'active',
        ]);
    }

    private function createMatch(SportTeam $homeTeam, SportTeam $awayTeam, string $status = 'scheduled'): SportMatch
    {
        return SportMatch::query()->create([
            'match_code' => 'MATCH-EVT-'.strtoupper(substr((string) microtime(true), -6)),
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'status' => $status,
            'scheduled_at' => now()->addDay(),
            'created_by_user_id' => auth()->id(),
        ]);
    }

    private function createPlayer(SportTeam $team, string $firstName, string $lastName): SportPlayer
    {
        return SportPlayer::query()->create([
            'player_code' => 'PLY-EVT-'.strtoupper(substr((string) microtime(true), -6)),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'team_id' => $team->id,
            'primary_position' => 'Forward',
            'jersey_number' => 11,
            'approval_status' => 'approved',
            'roster_status' => 'active',
            'status' => 'active',
        ]);
    }

    private function createSquadPlayer(SportMatch $match, SportTeam $team, SportMatchSquad $squad, string $playerName = 'Event One Player'): SportMatchSquadPlayer
    {
        $player = $this->createPlayer($team, ...array_pad(explode(' ', $playerName, 2), 2, 'Player'));

        return SportMatchSquadPlayer::query()->create([
            'squad_id' => $squad->id,
            'match_id' => $match->id,
            'team_id' => $team->id,
            'player_id' => $player->id,
            'player_name_snapshot' => $playerName,
            'jersey_number_snapshot' => 7,
            'position_snapshot' => 'Forward',
            'role' => 'starter',
            'eligibility_status' => 'eligible',
            'is_eligible' => true,
            'selected_at' => now(),
        ]);
    }
}
