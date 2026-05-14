<?php

namespace Tests\Feature;

use App\Models\SportMatch;
use App\Models\SportTeam;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_adminsport_can_manage_coaches(): void
    {
        $admin = $this->actingAsRole('adminsport', 'usr_900', 'sport.admin900@hfccf.org');

        $create = $this->postJson('/api/sport/coaches', [
            'name' => 'Coach Alpha',
            'email' => 'coach.alpha@hfccf.org',
            'phone' => '+855 12 900 900',
            'status' => 'active',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.coach.role', 'coach');

        $coachId = $create->json('data.coach.id');

        $this->putJson('/api/sport/coaches/'.$coachId, [
            'name' => 'Coach Alpha Updated',
            'status' => 'pending',
        ])
            ->assertOk()
            ->assertJsonPath('data.coach.status', 'pending');

        $team = SportTeam::query()->create([
            'team_code' => 'TEAM-COACH-900',
            'name' => 'Coach Linked Team',
            'coach_user_id' => $coachId,
            'coach_display_name' => 'Coach Alpha Updated',
            'status' => 'active',
        ]);

        $this->deleteJson('/api/sport/coaches/'.$coachId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('users', [
            'id' => $coachId,
        ]);

        $this->assertDatabaseHas('sport_teams', [
            'id' => $team->id,
            'coach_user_id' => null,
            'coach_display_name' => 'Coach Alpha Updated',
        ]);

    }

    public function test_adminsport_can_manage_teams(): void
    {
        $this->actingAsRole('adminsport', 'usr_910', 'sport.admin910@hfccf.org');
        $coach = $this->createCoachUser('usr_911', 'Coach Team');

        $create = $this->postJson('/api/sport/teams', [
            'team_code' => 'TEAM-910',
            'name' => 'Phoenix FC',
            'short_name' => 'Phoenix',
            'coach_user_id' => $coach->id,
            'coach_display_name' => trim($coach->first_name.' '.$coach->last_name),
            'division' => 'Senior',
            'captain_name' => 'Captain One',
            'players_count' => 0,
            'matches_count' => 0,
            'wins' => 3,
            'draws' => 1,
            'losses' => 0,
            'status' => 'active',
            'venue' => 'Main Stadium',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.team.teamCode', 'TEAM-910')
            ->assertJsonPath('data.team.coachUserId', $coach->id);

        $teamId = $create->json('data.team.id');

        $this->putJson('/api/sport/teams/'.$teamId, [
            'name' => 'Phoenix United',
            'status' => 'pending',
        ])
            ->assertOk()
            ->assertJsonPath('data.team.name', 'Phoenix United')
            ->assertJsonPath('data.team.status', 'pending');

        $list = $this->getJson('/api/sport/teams?page=1&per_page=10');
        $list
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'items',
                    'pagination' => ['page', 'perPage', 'total', 'totalPages'],
                ],
            ]);

        $this->deleteJson('/api/sport/teams/'.$teamId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('sport_teams', [
            'id' => $teamId,
        ]);
    }

    public function test_adminsport_can_manage_players(): void
    {
        $this->actingAsRole('adminsport', 'usr_920', 'sport.admin920@hfccf.org');
        $team = $this->createTeam('TEAM-920', 'Lions FC');

        $create = $this->postJson('/api/sport/players', [
            'name' => 'Sok Dara',
            'player_code' => 'PLY-920',
            'team' => $team->name,
            'division' => 'Senior',
            'gender' => 'male',
            'status' => 'active',
            'matches_played' => 2,
            'goals_scored' => 1,
            'primary_position' => 'Forward',
            'registration_status' => 'registered',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.player.playerCode', 'PLY-920')
            ->assertJsonPath('data.player.teamId', $team->id);

        $playerId = $create->json('data.player.id');

        $this->putJson('/api/sport/players/'.$playerId, [
            'name' => 'Sok Dara Updated',
            'status' => 'pending',
            'goals_scored' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('data.player.status', 'pending')
            ->assertJsonPath('data.player.goalsScored', 3);

        $this->deleteJson('/api/sport/players/'.$playerId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('sport_players', [
            'id' => $playerId,
        ]);
    }

    public function test_adminsport_can_manage_matches_and_events(): void
    {
        $this->actingAsRole('adminsport', 'usr_930', 'sport.admin930@hfccf.org');
        $homeTeam = $this->createTeam('TEAM-930-A', 'Home FC');
        $awayTeam = $this->createTeam('TEAM-930-B', 'Away FC');

        $matchResponse = $this->postJson('/api/sport/matches', [
            'match_code' => 'MATCH-930',
            'home_team' => $homeTeam->name,
            'away_team' => $awayTeam->name,
            'competition_type' => 'friendly',
            'tournament_name' => 'Foundation Cup',
            'scheduled_at' => '2026-05-14 15:00:00',
            'venue' => 'Main Stadium',
            'status' => 'scheduled',
        ]);

        $matchResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.match.matchCode', 'MATCH-930')
            ->assertJsonPath('data.match.homeTeamId', $homeTeam->id)
            ->assertJsonPath('data.match.awayTeamId', $awayTeam->id);

        $matchId = $matchResponse->json('data.match.id');

        $live = $this->patchJson('/api/sport/matches/'.$matchId.'/status', [
            'status' => 'live',
            'current_period' => 'first_half',
        ]);

        $live
            ->assertOk()
            ->assertJsonPath('data.match.status', 'live');

        $this->assertNotNull($live->json('data.match.startedAt'));

        $goal = $this->postJson('/api/sport/matches/'.$matchId.'/events', [
            'team_id' => $homeTeam->id,
            'event_type' => 'goal',
            'minute' => 11,
            'metadata' => ['assist_player_id' => null],
        ]);

        $goal
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event.eventType', 'goal');

        $match = SportMatch::query()->findOrFail($matchId);
        $this->assertSame(1, (int) $match->home_score);
        $this->assertSame(0, (int) $match->away_score);

        $eventId = $goal->json('data.event.id');

        $this->deleteJson('/api/sport/events/'.$eventId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $match->refresh();
        $this->assertSame(0, (int) $match->home_score);
        $this->assertSame(0, (int) $match->away_score);
    }

    public function test_score_recalculates_for_event_mutations(): void
    {
        $this->actingAsRole('adminsport', 'usr_931', 'sport.admin931@hfccf.org');
        $homeTeam = $this->createTeam('TEAM-931-A', 'Mutation Home');
        $awayTeam = $this->createTeam('TEAM-931-B', 'Mutation Away');
        $match = $this->createMatch($homeTeam, $awayTeam, 'live');

        $goal = $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $homeTeam->id,
            'event_type' => 'goal',
            'minute' => 11,
        ]);

        $goal->assertCreated()->assertJsonPath('data.event.eventType', 'goal');

        $eventId = $goal->json('data.event.id');

        $this->putJson('/api/sport/events/'.$eventId, [
            'minute' => 12,
            'event_type' => 'goal',
        ])
            ->assertOk()
            ->assertJsonPath('data.event.minute', 12)
            ->assertJsonPath('data.event.eventType', 'goal');

        $match->refresh();
        $this->assertSame(1, (int) $match->home_score);
        $this->assertSame(0, (int) $match->away_score);

        $this->putJson('/api/sport/events/'.$eventId, [
            'team_id' => $awayTeam->id,
            'event_type' => 'goal',
            'minute' => 13,
        ])
            ->assertOk()
            ->assertJsonPath('data.event.teamId', $awayTeam->id)
            ->assertJsonPath('data.event.eventType', 'goal');

        $match->refresh();
        $this->assertSame(0, (int) $match->home_score);
        $this->assertSame(1, (int) $match->away_score);

        $this->putJson('/api/sport/events/'.$eventId, [
            'team_id' => $awayTeam->id,
            'event_type' => 'own_goal',
            'minute' => 14,
        ])
            ->assertOk()
            ->assertJsonPath('data.event.eventType', 'own_goal');

        $match->refresh();
        $this->assertSame(1, (int) $match->home_score);
        $this->assertSame(0, (int) $match->away_score);

        $this->putJson('/api/sport/events/'.$eventId, [
            'team_id' => $awayTeam->id,
            'event_type' => 'goal',
            'minute' => 15,
        ])
            ->assertOk()
            ->assertJsonPath('data.event.eventType', 'goal');

        $match->refresh();
        $this->assertSame(0, (int) $match->home_score);
        $this->assertSame(1, (int) $match->away_score);

        $penalty = $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $homeTeam->id,
            'event_type' => 'penalty_goal',
            'minute' => 22,
        ]);

        $penalty->assertCreated()->assertJsonPath('data.event.eventType', 'penalty_goal');

        $match->refresh();
        $this->assertSame(1, (int) $match->home_score);
        $this->assertSame(1, (int) $match->away_score);

        $this->deleteJson('/api/sport/events/'.$penalty->json('data.event.id'))
            ->assertOk()
            ->assertJsonPath('success', true);

        $match->refresh();
        $this->assertSame(0, (int) $match->home_score);
        $this->assertSame(1, (int) $match->away_score);

        $this->deleteJson('/api/sport/events/'.$eventId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $match->refresh();
        $this->assertSame(0, (int) $match->home_score);
        $this->assertSame(0, (int) $match->away_score);
    }

    public function test_timeline_events_are_ordered_by_minute_and_extra_time(): void
    {
        $this->actingAsRole('adminsport', 'usr_932', 'sport.admin932@hfccf.org');
        $homeTeam = $this->createTeam('TEAM-932-A', 'Timeline Home');
        $awayTeam = $this->createTeam('TEAM-932-B', 'Timeline Away');
        $match = $this->createMatch($homeTeam, $awayTeam, 'live');

        $sameMinuteFirst = $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $homeTeam->id,
            'event_type' => 'goal',
            'minute' => 12,
        ]);
        $sameMinuteSecond = $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $awayTeam->id,
            'event_type' => 'substitution',
            'minute' => 12,
        ]);
        $fortyFive = $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $homeTeam->id,
            'event_type' => 'goal',
            'minute' => 45,
        ]);
        $fortyFivePlusOne = $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $awayTeam->id,
            'event_type' => 'goal',
            'minute' => 45,
            'extra_time_minute' => 1,
        ]);
        $ninetyPlusOne = $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $homeTeam->id,
            'event_type' => 'goal',
            'minute' => 90,
            'extra_time_minute' => 1,
        ]);
        $ninetyPlusFive = $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $awayTeam->id,
            'event_type' => 'goal',
            'minute' => 90,
            'extra_time_minute' => 5,
        ]);

        $response = $this->getJson('/api/sport/matches/'.$match->id.'/events')
            ->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data.items');

        $this->assertCount(6, $items);
        $this->assertSame(12, (int) $items[0]['minute']);
        $this->assertSame($sameMinuteFirst->json('data.event.id'), $items[0]['id']);
        $this->assertSame(12, (int) $items[1]['minute']);
        $this->assertSame($sameMinuteSecond->json('data.event.id'), $items[1]['id']);
        $this->assertSame(45, (int) $items[2]['minute']);
        $this->assertNull($items[2]['extraTimeMinute']);
        $this->assertSame($fortyFive->json('data.event.id'), $items[2]['id']);
        $this->assertSame(45, (int) $items[3]['minute']);
        $this->assertSame(1, (int) $items[3]['extraTimeMinute']);
        $this->assertSame($fortyFivePlusOne->json('data.event.id'), $items[3]['id']);
        $this->assertSame(90, (int) $items[4]['minute']);
        $this->assertSame(1, (int) $items[4]['extraTimeMinute']);
        $this->assertSame($ninetyPlusOne->json('data.event.id'), $items[4]['id']);
        $this->assertSame(90, (int) $items[5]['minute']);
        $this->assertSame(5, (int) $items[5]['extraTimeMinute']);
        $this->assertSame($ninetyPlusFive->json('data.event.id'), $items[5]['id']);
    }

    public function test_completed_match_can_reopen_and_edit_events(): void
    {
        $this->actingAsRole('adminsport', 'usr_933', 'sport.admin933@hfccf.org');
        $homeTeam = $this->createTeam('TEAM-933-A', 'Reopen Home');
        $awayTeam = $this->createTeam('TEAM-933-B', 'Reopen Away');
        $match = $this->createMatch($homeTeam, $awayTeam, 'live');

        $goal = $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $homeTeam->id,
            'event_type' => 'goal',
            'minute' => 10,
        ]);

        $goal->assertCreated();
        $eventId = $goal->json('data.event.id');

        $this->patchJson('/api/sport/matches/'.$match->id.'/status', [
            'status' => 'completed',
            'current_period' => 'final',
        ])->assertOk();

        $reopen = $this->patchJson('/api/sport/matches/'.$match->id.'/status', [
            'status' => 'live',
            'current_period' => 'second_half',
        ]);

        $reopen
            ->assertOk()
            ->assertJsonPath('data.match.status', 'live');

        $this->putJson('/api/sport/events/'.$eventId, [
            'minute' => 11,
            'event_type' => 'goal',
        ])
            ->assertOk()
            ->assertJsonPath('data.event.minute', 11);

        $match->refresh();
        $this->assertSame(1, (int) $match->home_score);
        $this->assertSame(0, (int) $match->away_score);
    }

    public function test_coach_cannot_mutate_sport_events(): void
    {
        $this->actingAsRole('coach', 'usr_934', 'coach.sport934@hfccf.org');
        $homeTeam = $this->createTeam('TEAM-934-A', 'Coach Event Home');
        $awayTeam = $this->createTeam('TEAM-934-B', 'Coach Event Away');
        $match = $this->createMatch($homeTeam, $awayTeam, 'live');

        $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $homeTeam->id,
            'event_type' => 'goal',
            'minute' => 8,
        ])->assertForbidden();
    }

    public function test_own_goals_are_counted_for_the_opponent_team(): void
    {
        $this->actingAsRole('adminsport', 'usr_940', 'sport.admin940@hfccf.org');
        $homeTeam = $this->createTeam('TEAM-940-A', 'Home Side');
        $awayTeam = $this->createTeam('TEAM-940-B', 'Away Side');

        $match = $this->createMatch($homeTeam, $awayTeam, 'live');

        $response = $this->postJson('/api/sport/matches/'.$match->id.'/events', [
            'team_id' => $homeTeam->id,
            'event_type' => 'own_goal',
            'minute' => 28,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.event.eventType', 'own_goal');

        $match->refresh();
        $this->assertSame(0, (int) $match->home_score);
        $this->assertSame(1, (int) $match->away_score);
    }

    public function test_match_lifecycle_transitions_are_validated(): void
    {
        $this->actingAsRole('adminsport', 'usr_950', 'sport.admin950@hfccf.org');
        $homeTeam = $this->createTeam('TEAM-950-A', 'Lifecycle Home');
        $awayTeam = $this->createTeam('TEAM-950-B', 'Lifecycle Away');

        $matchResponse = $this->postJson('/api/sport/matches', [
            'home_team' => $homeTeam->name,
            'away_team' => $awayTeam->name,
            'status' => 'scheduled',
        ]);

        $matchId = $matchResponse->json('data.match.id');

        $this->patchJson('/api/sport/matches/'.$matchId.'/status', [
            'status' => 'draft',
        ])->assertUnprocessable();

        $this->patchJson('/api/sport/matches/'.$matchId.'/status', [
            'status' => 'live',
            'current_period' => 'first_half',
        ])->assertOk();

        $this->patchJson('/api/sport/matches/'.$matchId.'/status', [
            'status' => 'halftime',
            'current_period' => 'halftime',
        ])->assertOk();

        $completed = $this->patchJson('/api/sport/matches/'.$matchId.'/status', [
            'status' => 'completed',
            'current_period' => 'final',
        ]);

        $completed
            ->assertOk()
            ->assertJsonPath('data.match.status', 'completed');

        $this->assertNotNull($completed->json('data.match.completedAt'));

        $this->patchJson('/api/sport/matches/'.$matchId.'/status', [
            'status' => 'live',
            'current_period' => 'second_half',
        ])->assertOk()
            ->assertJsonPath('data.match.status', 'live');
    }

    public function test_coach_is_restricted_to_own_teams_and_matches(): void
    {
        $coach = $this->actingAsRole('coach', 'usr_960', 'coach.sport960@hfccf.org');
        $otherCoach = $this->createCoachUser('usr_961', 'Other Coach');

        $coachTeam = $this->createTeam('TEAM-960-A', 'Coach Team', $coach->id, 'Coach Sport');
        $otherTeam = $this->createTeam('TEAM-960-B', 'Other Team', $otherCoach->id, 'Other Sport');

        $ownMatch = $this->createMatch($coachTeam, $otherTeam, 'scheduled');
        $otherMatch = $this->createMatch($otherTeam, $otherTeam, 'scheduled', false);

        $this->getJson('/api/sport/coach/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/sport/coach/teams')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['teamCode' => 'TEAM-960-A'])
            ->assertJsonMissing(['teamCode' => 'TEAM-960-B']);

        $this->getJson('/api/sport/coach/matches')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['matchCode' => $ownMatch->match_code])
            ->assertJsonMissing(['matchCode' => $otherMatch->match_code]);

        $this->getJson('/api/sport/teams')
            ->assertForbidden();
    }

    public function test_unauthorized_users_are_blocked(): void
    {
        $this->getJson('/api/sport/dashboard')
            ->assertUnauthorized();
    }

    private function actingAsRole(string $roleCode, string $id, string $email): User
    {
        $user = $this->createUser($roleCode, $id, $email);
        Sanctum::actingAs($user);

        return $user;
    }

    private function createUser(string $roleCode, string $id, string $email): User
    {
        $department = match ($roleCode) {
            'coach', 'adminsport' => 'sports',
            default => 'sports',
        };

        return User::query()->create([
            'id' => $id,
            'first_name' => ucfirst($roleCode),
            'last_name' => 'User',
            'username' => ucfirst($roleCode).' User',
            'email' => $email,
            'phone' => '+855 12 600 600',
            'role_code' => $roleCode,
            'department_code' => $department,
            'status' => 'active',
            'password' => 'secret-pass',
        ]);
    }

    private function createCoachUser(string $id, string $name): User
    {
        [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, 'User');

        return User::query()->create([
            'id' => $id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => trim($name),
            'email' => strtolower(str_replace(' ', '.', $name)).'@hfccf.org',
            'phone' => '+855 12 611 611',
            'role_code' => 'coach',
            'department_code' => 'sports',
            'status' => 'active',
            'password' => 'secret-pass',
        ]);
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

    private function createMatch(SportTeam $homeTeam, SportTeam $awayTeam, string $status = 'scheduled', bool $useDistinctTeams = true): SportMatch
    {
        if (! $useDistinctTeams) {
            $awayTeam = $homeTeam;
        }

        return SportMatch::query()->create([
            'match_code' => 'MATCH-'.strtoupper(substr((string) microtime(true), -6)),
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'status' => $status,
            'scheduled_at' => now()->addDay(),
            'created_by_user_id' => auth()->id(),
        ]);
    }
}
