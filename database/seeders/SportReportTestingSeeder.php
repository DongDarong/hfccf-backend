<?php

namespace Database\Seeders;

use App\Models\SportDivision;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\SportMatch;
use App\Models\SportMatchEvent;
use App\Models\SportMatchSquad;
use App\Models\SportMatchSquadPlayer;
use App\Models\SportTournament;
use App\Models\SportTournamentGroup;
use App\Models\SportAttendanceRecord;
use App\Models\SportStanding;
use App\Models\User;
use App\Support\SportCoachAssignmentService;
use App\Support\SportPlayerMembershipService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SportReportTestingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('SportReportTestingSeeder is restricted to local/testing environments.');
        }

        DB::transaction(function (): void {
            // Phase 1: Create admin and coaches
            $admin = $this->upsertTestUser(
                id: 'qa_sp_admin_r',
                firstName: 'QA Sport',
                lastName: 'Admin',
                email: 'qa.sport.reports.admin@local.invalid',
                role: 'adminsport',
                status: 'active',
            );

            $coaches = [];
            $coachDefinitions = [
                ['id' => 'qa_sp_coach_d', 'first' => 'Dara', 'last' => 'Sok', 'email' => 'qa.reports.dara@local.invalid'],
                ['id' => 'qa_sp_coach_v', 'first' => 'Vannak', 'last' => 'Chhay', 'email' => 'qa.reports.vannak@local.invalid'],
                ['id' => 'qa_sp_coach_p', 'first' => 'Pisey', 'last' => 'Chan', 'email' => 'qa.reports.pisey@local.invalid'],
            ];

            foreach ($coachDefinitions as $def) {
                $coaches[$def['id']] = $this->upsertTestUser(
                    id: substr($def['id'], 0, 16),
                    firstName: $def['first'],
                    lastName: $def['last'],
                    email: $def['email'],
                    role: 'coach',
                    status: 'active',
                );
            }

            // Phase 2: Create divisions
            $divisions = [];
            foreach (['U19', 'U16'] as $divName) {
                $division = SportDivision::query()->firstOrCreate(
                    ['name' => "QA-{$divName}"],
                    [
                        'name' => "QA-{$divName}",
                        'description' => "QA Report testing data for {$divName} division",
                        'status' => 'active',
                    ],
                );
                $divisions[$divName] = $division;
            }

            // Phase 3: Create 4 teams
            $teams = [];
            $teamDefinitions = [
                [
                    'code' => 'QA-U19-MK',
                    'name' => 'QA Mekong Juniors',
                    'short' => 'Mekong',
                    'division_id' => $divisions['U19']->id,
                    'coach' => $coaches['qa_sp_coach_d'],
                ],
                [
                    'code' => 'QA-U19-HW',
                    'name' => 'QA Hope Warriors',
                    'short' => 'Hope Warriors',
                    'division_id' => $divisions['U19']->id,
                    'coach' => $coaches['qa_sp_coach_v'],
                ],
                [
                    'code' => 'QA-U16-BE',
                    'name' => 'QA Battambang Eagles',
                    'short' => 'Eagles',
                    'division_id' => $divisions['U16']->id,
                    'coach' => $coaches['qa_sp_coach_p'],
                ],
                [
                    'code' => 'QA-U16-JS',
                    'name' => 'QA Junior Stars',
                    'short' => 'Junior Stars',
                    'division_id' => $divisions['U16']->id,
                    'coach' => $coaches['qa_sp_coach_d'],
                ],
            ];

            $assignmentService = app(SportCoachAssignmentService::class);

            foreach ($teamDefinitions as $def) {
                $team = SportTeam::query()->updateOrCreate(
                    ['team_code' => $def['code']],
                    [
                        'name' => $def['name'],
                        'short_name' => $def['short'],
                        'division_id' => $def['division_id'],
                        'status' => 'active',
                        'description' => 'QA Sport report testing data',
                        'venue' => 'QA Local Stadium',
                    ],
                );
                $teams[$def['code']] = $team;

                // Assign coach
                $assignmentService->assignTeamToCoach($team, $def['coach'], $admin);
            }

            // Phase 4: Create 24 players (6 per team)
            $membershipService = app(SportPlayerMembershipService::class);
            $players = [];

            $positions = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'];
            $genders = ['male', 'female'];

            foreach ($teamDefinitions as $teamDef) {
                $teamCode = substr($teamDef['code'], 5); // Extract "U19-MK" from "QA-U19-MK"
                $divisionPrefix = $teamCode; // "U19-MK", "U19-HW", "U16-BE", "U16-JS"

                for ($i = 1; $i <= 6; $i++) {
                    $playerCode = sprintf('QA-%s-%03d', $divisionPrefix, $i);
                    $age = ($divisionPrefix === 'U19-MK' || $divisionPrefix === 'U19-HW') ? 16 + ($i % 3) : 13 + ($i % 3);
                    $dob = Carbon::now()->subYears($age)->addDays(rand(1, 365));

                    $player = SportPlayer::query()->updateOrCreate(
                        ['player_code' => $playerCode],
                        [
                            'first_name' => $i % 2 === 0 ? 'សម័យ' : 'QA Player',
                            'last_name' => $i % 2 === 0 ? "កីឡាករ {$i}" : "Test {$i}",
                            'team_id' => $teams[$teamDef['code']]->id,
                            'division' => $divisionPrefix,
                            'position' => $positions[($i - 1) % 4],
                            'jersey_number' => $i,
                            'gender' => $genders[$i % 2],
                            'age' => $age,
                            'date_of_birth' => $dob->toDateString(),
                            'status' => 'active',
                            'registration_status' => 'registered',
                            'approval_status' => 'approved',
                            'created_by_user_id' => $admin->id,
                            'approved_by_user_id' => $admin->id,
                            'approved_at' => now(),
                            'notes' => 'QA Sport report testing data',
                        ],
                    );

                    // Activate player membership
                    $membershipService->activateMembership($player, $teams[$teamDef['code']], $admin, true);
                    $players[$playerCode] = $player->refresh();
                }
            }

            // Phase 5: Create attendance records
            $this->createAttendanceData($teams, $players, $coaches['qa_sp_coach_d'], $admin);

            // Phase 6: Create tournaments
            $tournaments = $this->createTournaments($teams, $admin);

            // Phase 7: Create matches and match events
            $this->createMatches($teams, $players, $tournaments, $admin);

            // Phase 8: Calculate standings
            $this->calculateStandings($tournaments);
        });

        $this->command?->info('SportReportTestingSeeder completed for QA Sport report testing.');
    }

    /**
     * Create or update test user with deterministic ID
     */
    private function upsertTestUser(string $id, string $firstName, string $lastName, string $email, string $role, string $status): User
    {
        $username = Str::lower(str_replace(' ', '.', trim("{$firstName}.{$lastName}")));
        $password = Hash::make((string) env('SPORT_TEST_PASSWORD', Str::random(48)));
        $now = now();

        // Check if user already exists
        $user = User::query()->where('email', $email)->first();
        if ($user) {
            return $user;
        }

        // Create new user directly
        DB::table('users')->insert([
            'id' => $id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
            'email' => $email,
            'role_code' => $role,
            'department_code' => 'sports',
            'status' => $status,
            'password' => $password,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return User::query()->where('email', $email)->firstOrFail();
    }

    /**
     * Create attendance records with mixed outcomes
     */
    private function createAttendanceData(array $teams, array $players, User $coach, User $admin): void
    {
        $dates = ['2026-07-03', '2026-07-10', '2026-07-17', '2026-07-24'];
        $statuses = ['present', 'absent', 'late', 'excused'];

        foreach ($teams as $team) {
            // Get 6 players for this team
            $teamPlayers = array_filter($players, fn($p) => $p->team_id === $team->id);
            $teamPlayerList = array_slice(array_values($teamPlayers), 0, 6);

            foreach ($dates as $dateIndex => $date) {
                foreach ($teamPlayerList as $playerIndex => $player) {
                    // Distribute different statuses across players and dates
                    $statusIndex = ($playerIndex + $dateIndex) % 4;
                    $status = $statuses[$statusIndex];

                    $subjectKey = "QA-{$player->player_code}-{$date}";

                    DB::table('sport_attendance_records')->updateOrInsert(
                        [
                            'attendance_type' => 'player',
                            'team_id' => $team->id,
                            'player_id' => $player->id,
                            'attendance_date' => $date,
                        ],
                        [
                            'subject_key' => $subjectKey,
                            'coach_user_id' => $coach->id,
                            'recorded_by_user_id' => $admin->id,
                            'status' => $status,
                            'note' => 'QA Sport report testing data',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            }
        }
    }

    /**
     * Create tournaments
     */
    private function createTournaments(array $teams, User $admin): array
    {
        $tournaments = [];

        $tournamentDefs = [
            [
                'code' => 'QA-CUP-2026-U19',
                'name' => 'QA Cup 2026 - U19',
                'starts' => '2026-07-01',
                'ends' => '2026-07-31',
                'division' => 'U19',
            ],
            [
                'code' => 'QA-CUP-2026-U16',
                'name' => 'QA Junior Cup 2026 - U16',
                'starts' => '2026-07-05',
                'ends' => '2026-07-30',
                'division' => 'U16',
            ],
        ];

        foreach ($tournamentDefs as $def) {
            $tournament = SportTournament::query()->updateOrCreate(
                ['tournament_code' => $def['code']],
                [
                    'slug' => Str::slug($def['code']),
                    'name' => $def['name'],
                    'season' => '2026',
                    'tournament_type' => 'league',
                    'status' => 'active',
                    'visibility' => 'public',
                    'starts_at' => $def['starts'],
                    'ends_at' => $def['ends'],
                    'registration_open_at' => $def['starts'],
                    'registration_close_at' => $def['ends'],
                    'description' => 'QA Sport report testing data',
                    'location' => 'QA Local Stadium',
                    'organizer' => 'HFCCF QA Sports',
                    'created_by_user_id' => $admin->id,
                ],
            );

            // Register appropriate teams
            $divisionTeams = array_filter($teams, fn($t) => str_contains($t->divisionRelation?->name ?? '', $def['division']));
            foreach ($divisionTeams as $team) {
                DB::table('sport_tournament_teams')->updateOrInsert(
                    ['tournament_id' => $tournament->id, 'team_id' => $team->id],
                    ['joined_at' => now(), 'created_at' => now(), 'updated_at' => now()],
                );
            }

            $tournaments[$def['division']] = $tournament;
        }

        return $tournaments;
    }

    /**
     * Create matches with events
     */
    private function createMatches(array $teams, array $players, array $tournaments, User $admin): void
    {
        $u19Teams = array_filter($teams, fn($t) => str_contains($t->divisionRelation?->name ?? '', 'U19'));
        $u16Teams = array_filter($teams, fn($t) => str_contains($t->divisionRelation?->name ?? '', 'U16'));

        $u19TeamList = array_values($u19Teams);
        $u16TeamList = array_values($u16Teams);

        // U19 matches
        if (count($u19TeamList) >= 2) {
            $this->createMatchWithEvents(
                'QA-MATCH-U19-001',
                $u19TeamList[0],
                $u19TeamList[1],
                '2026-07-05 14:00:00',
                2, 1,
                'completed',
                $tournaments['U19'],
                $players,
                $admin,
            );

            $this->createMatchWithEvents(
                'QA-MATCH-U19-002',
                $u19TeamList[1],
                $u19TeamList[0],
                '2026-07-12 14:00:00',
                1, 1,
                'completed',
                $tournaments['U19'],
                $players,
                $admin,
            );

            $this->createMatchWithEvents(
                'QA-MATCH-U19-003',
                $u19TeamList[0],
                $u19TeamList[1],
                '2026-07-19 14:00:00',
                3, 0,
                'completed',
                $tournaments['U19'],
                $players,
                $admin,
            );

            $this->createMatchWithEvents(
                'QA-MATCH-U19-004',
                $u19TeamList[1],
                $u19TeamList[0],
                '2026-07-28 14:00:00',
                0, 0,
                'scheduled',
                $tournaments['U19'],
                $players,
                $admin,
            );
        }

        // U16 matches
        if (count($u16TeamList) >= 2) {
            $this->createMatchWithEvents(
                'QA-MATCH-U16-001',
                $u16TeamList[0],
                $u16TeamList[1],
                '2026-07-06 14:00:00',
                1, 0,
                'completed',
                $tournaments['U16'],
                $players,
                $admin,
            );

            $this->createMatchWithEvents(
                'QA-MATCH-U16-002',
                $u16TeamList[1],
                $u16TeamList[0],
                '2026-07-13 14:00:00',
                2, 2,
                'completed',
                $tournaments['U16'],
                $players,
                $admin,
            );

            $this->createMatchWithEvents(
                'QA-MATCH-U16-003',
                $u16TeamList[0],
                $u16TeamList[1],
                '2026-07-20 14:00:00',
                0, 2,
                'completed',
                $tournaments['U16'],
                $players,
                $admin,
            );

            $this->createMatchWithEvents(
                'QA-MATCH-U16-004',
                $u16TeamList[1],
                $u16TeamList[0],
                '2026-07-27 14:00:00',
                0, 0,
                'scheduled',
                $tournaments['U16'],
                $players,
                $admin,
            );
        }
    }

    /**
     * Create single match with squads and events
     */
    private function createMatchWithEvents(
        string $matchCode,
        SportTeam $homeTeam,
        SportTeam $awayTeam,
        string $scheduledAt,
        int $homeScore,
        int $awayScore,
        string $status,
        SportTournament $tournament,
        array $allPlayers,
        User $admin,
    ): void {
        $match = SportMatch::query()->updateOrCreate(
            ['match_code' => $matchCode],
            [
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'tournament_id' => $tournament->id,
                'scheduled_at' => $scheduledAt,
                'status' => $status,
                'approval_status' => 'approved',
                'approved_by_user_id' => $admin->id,
                'approved_at' => now(),
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'completed_at' => $status === 'completed' ? Carbon::parse($scheduledAt)->addHours(2) : null,
                'created_by_user_id' => $admin->id,
                'notes' => 'QA Sport report testing data',
            ],
        );

        // Create match squads only for completed matches
        if ($status === 'completed') {
            // Get players for each team
            $homeTeamPlayers = array_filter($allPlayers, fn($p) => $p->team_id === $homeTeam->id);
            $awayTeamPlayers = array_filter($allPlayers, fn($p) => $p->team_id === $awayTeam->id);

            // Home team squad
            $this->createMatchSquad($match, $homeTeam, array_slice(array_values($homeTeamPlayers), 0, 6), $admin);

            // Away team squad
            $this->createMatchSquad($match, $awayTeam, array_slice(array_values($awayTeamPlayers), 0, 6), $admin);

            // Create match events (goals, assists, cards)
            $this->createMatchEvents($match, $homeTeam, $awayTeam, $homeScore, $awayScore, $allPlayers, $admin);
        }
    }

    /**
     * Create match squad
     */
    private function createMatchSquad(SportMatch $match, SportTeam $team, array $squadPlayers, User $admin): void
    {
        $squad = SportMatchSquad::query()->updateOrCreate(
            ['match_id' => $match->id, 'team_id' => $team->id],
            [
                'status' => 'submitted',
                'selected_by_user_id' => $admin->id,
                'submitted_at' => now(),
            ],
        );

        foreach ($squadPlayers as $index => $player) {
            SportMatchSquadPlayer::query()->updateOrInsert(
                ['squad_id' => $squad->id, 'player_id' => $player->id],
                [
                    'match_id' => $match->id,
                    'team_id' => $team->id,
                    'player_name_snapshot' => "{$player->first_name} {$player->last_name}",
                    'jersey_number_snapshot' => $player->jersey_number,
                    'position_snapshot' => $player->position,
                    'role' => $index === 0 ? 'goalkeeper' : 'field_player',
                    'eligibility_status' => 'eligible',
                    'is_eligible' => true,
                    'selected_at' => now(),
                ],
            );
        }
    }

    /**
     * Create match events (goals, cards, etc.)
     */
    private function createMatchEvents(
        SportMatch $match,
        SportTeam $homeTeam,
        SportTeam $awayTeam,
        int $homeScore,
        int $awayScore,
        array $allPlayers,
        User $admin,
    ): void {
        $homeTeamPlayers = array_filter($allPlayers, fn($p) => $p->team_id === $homeTeam->id);
        $awayTeamPlayers = array_filter($allPlayers, fn($p) => $p->team_id === $awayTeam->id);

        // Home team goals
        for ($i = 0; $i < $homeScore; $i++) {
            $player = array_values($homeTeamPlayers)[$i % count($homeTeamPlayers)] ?? null;
            if ($player) {
                $minute = 15 + ($i * 20);
                SportMatchEvent::query()->updateOrInsert(
                    ['match_id' => $match->id, 'player_id' => $player->id, 'event_type' => 'goal', 'minute' => $minute],
                    [
                        'team_id' => $homeTeam->id,
                        'minute' => $minute,
                        'created_by_user_id' => $admin->id,
                    ],
                );
            }
        }

        // Away team goals
        for ($i = 0; $i < $awayScore; $i++) {
            $player = array_values($awayTeamPlayers)[$i % count($awayTeamPlayers)] ?? null;
            if ($player) {
                $minute = 25 + ($i * 20);
                SportMatchEvent::query()->updateOrInsert(
                    ['match_id' => $match->id, 'player_id' => $player->id, 'event_type' => 'goal', 'minute' => $minute],
                    [
                        'team_id' => $awayTeam->id,
                        'minute' => $minute,
                        'created_by_user_id' => $admin->id,
                    ],
                );
            }
        }

        // Add some yellow cards
        $cardPlayers = array_slice(array_values($homeTeamPlayers), 0, 2);
        foreach ($cardPlayers as $index => $player) {
            $minute = 30 + ($index * 15);
            SportMatchEvent::query()->updateOrInsert(
                ['match_id' => $match->id, 'player_id' => $player->id, 'event_type' => 'yellow_card', 'minute' => $minute],
                [
                    'team_id' => $homeTeam->id,
                    'minute' => $minute,
                    'created_by_user_id' => $admin->id,
                ],
            );
        }

        // Add one red card
        $redCardPlayer = array_values($awayTeamPlayers)[0] ?? null;
        if ($redCardPlayer) {
            SportMatchEvent::query()->updateOrInsert(
                ['match_id' => $match->id, 'player_id' => $redCardPlayer->id, 'event_type' => 'red_card', 'minute' => 75],
                [
                    'team_id' => $awayTeam->id,
                    'minute' => 75,
                    'created_by_user_id' => $admin->id,
                ],
            );
        }
    }

    /**
     * Calculate standings from matches
     */
    private function calculateStandings(array $tournaments): void
    {
        foreach ($tournaments as $tournament) {
            $teams = $tournament->teams()->get();

            foreach ($teams as $team) {
                // Count matches
                $matches = SportMatch::query()
                    ->where('tournament_id', $tournament->id)
                    ->where('status', 'completed')
                    ->where(function ($q) use ($team) {
                        $q->where('home_team_id', $team->id)
                            ->orWhere('away_team_id', $team->id);
                    })
                    ->get();

                $played = $matches->count();
                $wins = 0;
                $draws = 0;
                $losses = 0;
                $goalsFor = 0;
                $goalsAgainst = 0;

                foreach ($matches as $match) {
                    if ($match->home_team_id === $team->id) {
                        $goalsFor += $match->home_score;
                        $goalsAgainst += $match->away_score;

                        if ($match->home_score > $match->away_score) {
                            $wins++;
                        } elseif ($match->home_score === $match->away_score) {
                            $draws++;
                        } else {
                            $losses++;
                        }
                    } else {
                        $goalsFor += $match->away_score;
                        $goalsAgainst += $match->home_score;

                        if ($match->away_score > $match->home_score) {
                            $wins++;
                        } elseif ($match->away_score === $match->home_score) {
                            $draws++;
                        } else {
                            $losses++;
                        }
                    }
                }

                $points = ($wins * 3) + $draws;
                $goalDifference = $goalsFor - $goalsAgainst;

                SportStanding::query()->updateOrCreate(
                    ['tournament_id' => $tournament->id, 'team_id' => $team->id],
                    [
                        'played' => $played,
                        'wins' => $wins,
                        'draws' => $draws,
                        'losses' => $losses,
                        'goals_for' => $goalsFor,
                        'goals_against' => $goalsAgainst,
                        'goal_difference' => $goalDifference,
                        'points' => $points,
                    ],
                );
            }
        }
    }
}
