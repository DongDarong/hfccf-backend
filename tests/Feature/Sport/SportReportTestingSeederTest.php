<?php

namespace Tests\Feature\Sport;

use App\Models\SportDivision;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\SportMatch;
use App\Models\SportMatchEvent;
use App\Models\SportTournament;
use App\Models\SportAttendanceRecord;
use App\Models\SportStanding;
use App\Models\User;
use Database\Seeders\SportReportTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportReportTestingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_runs_successfully()
    {
        $this->seed(SportReportTestingSeeder::class);

        // If we get here without exception, seeder ran successfully
        $this->assertTrue(true);
    }

    public function test_divisions_are_created()
    {
        $this->seed(SportReportTestingSeeder::class);

        $divisions = SportDivision::where('name', 'LIKE', 'QA-%')->get();
        $this->assertCount(2, $divisions);
        $this->assertTrue($divisions->pluck('name')->contains('QA-U19'));
        $this->assertTrue($divisions->pluck('name')->contains('QA-U16'));
    }

    public function test_teams_are_created()
    {
        $this->seed(SportReportTestingSeeder::class);

        $teams = SportTeam::where('team_code', 'LIKE', 'QA-U%')->get();
        $this->assertCount(4, $teams);

        $this->assertTrue($teams->pluck('team_code')->contains('QA-U19-MK'));
        $this->assertTrue($teams->pluck('team_code')->contains('QA-U19-HW'));
        $this->assertTrue($teams->pluck('team_code')->contains('QA-U16-BE'));
        $this->assertTrue($teams->pluck('team_code')->contains('QA-U16-JS'));
    }

    public function test_players_are_created()
    {
        $this->seed(SportReportTestingSeeder::class);

        $players = SportPlayer::where('player_code', 'LIKE', 'QA-%')->get();
        $this->assertGreaterThanOrEqual(24, $players->count());
        $this->assertLessThanOrEqual(30, $players->count()); // Could have some variance
    }

    public function test_player_team_memberships_are_active()
    {
        $this->seed(SportReportTestingSeeder::class);

        $players = SportPlayer::where('player_code', 'LIKE', 'QA-U19%')->get();
        foreach ($players as $player) {
            $activeMembership = $player->activeMembership()->first();
            $this->assertNotNull($activeMembership, "Player {$player->player_code} should have active membership");
            $this->assertEquals('active', $activeMembership->status);
        }
    }

    public function test_coaches_are_assigned_to_teams()
    {
        $this->seed(SportReportTestingSeeder::class);

        $teams = SportTeam::where('team_code', 'LIKE', 'QA-U%')->get();
        foreach ($teams as $team) {
            $this->assertNotNull($team->coach_user_id, "Team {$team->team_code} should have assigned coach");
            $this->assertNotNull($team->coach_display_name, "Team {$team->team_code} should have coach display name");
        }
    }

    public function test_tournaments_are_created()
    {
        $this->seed(SportReportTestingSeeder::class);

        $tournaments = SportTournament::where('tournament_code', 'LIKE', 'QA-CUP%')->get();
        $this->assertCount(2, $tournaments);

        $this->assertTrue($tournaments->pluck('tournament_code')->contains('QA-CUP-2026-U19'));
        $this->assertTrue($tournaments->pluck('tournament_code')->contains('QA-CUP-2026-U16'));
    }

    public function test_teams_registered_in_tournaments()
    {
        $this->seed(SportReportTestingSeeder::class);

        $u19Tournament = SportTournament::where('tournament_code', 'QA-CUP-2026-U19')->first();
        $u19Teams = $u19Tournament->teams()->get();
        $this->assertCount(2, $u19Teams);

        $u16Tournament = SportTournament::where('tournament_code', 'QA-CUP-2026-U16')->first();
        $u16Teams = $u16Tournament->teams()->get();
        $this->assertCount(2, $u16Teams);
    }

    public function test_matches_are_created()
    {
        $this->seed(SportReportTestingSeeder::class);

        $matches = SportMatch::where('match_code', 'LIKE', 'QA-MATCH%')->get();
        $this->assertGreaterThanOrEqual(8, $matches->count());

        // Check completed matches
        $completed = $matches->where('status', 'completed');
        $this->assertGreaterThan(0, $completed->count());

        // Check scheduled matches
        $scheduled = $matches->where('status', 'scheduled');
        $this->assertGreaterThan(0, $scheduled->count());
    }

    public function test_match_events_exist_for_completed_matches()
    {
        $this->seed(SportReportTestingSeeder::class);

        $completedMatches = SportMatch::where('match_code', 'LIKE', 'QA-MATCH%')
            ->where('status', 'completed')
            ->get();

        foreach ($completedMatches as $match) {
            $events = $match->events()->get();
            $this->assertGreaterThan(0, $events->count(), "Match {$match->match_code} should have events");

            // Check for goal events
            $goals = $events->where('event_type', 'goal');
            $this->assertGreaterThan(0, $goals->count(), "Match {$match->match_code} should have goal events");
        }
    }

    public function test_attendance_records_exist()
    {
        $this->seed(SportReportTestingSeeder::class);

        $attendance = SportAttendanceRecord::where('subject_key', 'LIKE', 'QA-%')->get();
        $this->assertGreater(0, $attendance->count());

        // Check for mixed attendance statuses
        $statuses = $attendance->pluck('status')->unique();
        $this->assertTrue($statuses->contains('present'));
        $this->assertTrue($statuses->contains('absent') || $statuses->contains('late') || $statuses->contains('excused'));
    }

    public function test_standings_are_calculated()
    {
        $this->seed(SportReportTestingSeeder::class);

        $standings = SportStanding::all();
        $this->assertGreater(0, $standings->count());

        // Check standing calculations
        foreach ($standings as $standing) {
            $this->assertNotNull($standing->tournament_id);
            $this->assertNotNull($standing->team_id);
            $this->assertTrue($standing->played >= 0);
            $this->assertTrue($standing->points >= 0);

            // Verify calculated points match wins and draws
            $expectedPoints = ($standing->wins * 3) + $standing->draws;
            $this->assertEquals($expectedPoints, $standing->points,
                "Standing points for team {$standing->team_id} should match calculation");
        }
    }

    public function test_seeder_is_idempotent()
    {
        $this->seed(SportReportTestingSeeder::class);

        $teamCountBefore = SportTeam::where('team_code', 'LIKE', 'QA-U%')->count();
        $playerCountBefore = SportPlayer::where('player_code', 'LIKE', 'QA-%')->count();
        $matchCountBefore = SportMatch::where('match_code', 'LIKE', 'QA-MATCH%')->count();

        // Run seeder again
        $this->seed(SportReportTestingSeeder::class);

        $teamCountAfter = SportTeam::where('team_code', 'LIKE', 'QA-U%')->count();
        $playerCountAfter = SportPlayer::where('player_code', 'LIKE', 'QA-%')->count();
        $matchCountAfter = SportMatch::where('match_code', 'LIKE', 'QA-MATCH%')->count();

        $this->assertEquals($teamCountBefore, $teamCountAfter, 'Team count should not increase on second run');
        $this->assertEquals($playerCountBefore, $playerCountAfter, 'Player count should not increase on second run');
        $this->assertEquals($matchCountBefore, $matchCountAfter, 'Match count should not increase on second run');
    }

    public function test_u19_and_u16_divisions_are_separate()
    {
        $this->seed(SportReportTestingSeeder::class);

        $u19Teams = SportTeam::whereHas('divisionRelation', fn($q) => $q->where('name', 'QA-U19'))->get();
        $u16Teams = SportTeam::whereHas('divisionRelation', fn($q) => $q->where('name', 'QA-U16'))->get();

        $this->assertCount(2, $u19Teams);
        $this->assertCount(2, $u16Teams);

        $u19Players = SportPlayer::where('division', 'LIKE', '%U19%')->count();
        $u16Players = SportPlayer::where('division', 'LIKE', '%U16%')->count();

        $this->assertGreater(0, $u19Players);
        $this->assertGreater(0, $u16Players);
    }

    public function test_match_squads_exist_for_completed_matches()
    {
        $this->seed(SportReportTestingSeeder::class);

        $completedMatches = SportMatch::where('match_code', 'LIKE', 'QA-MATCH%')
            ->where('status', 'completed')
            ->get();

        foreach ($completedMatches as $match) {
            $squads = $match->squads()->get();
            $this->assertGreaterThanOrEqual(2, $squads->count(),
                "Completed match {$match->match_code} should have squads for both teams");
        }
    }
}
