<?php

namespace Database\Seeders;

use App\Models\CoachTeamAssignment;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\User;
use App\Support\SportCoachAssignmentService;
use App\Support\SportPlayerMembershipService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SportSystemTestSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('SportSystemTestSeeder is restricted to local/testing environments.');
        }

        DB::transaction(function (): void {
            $admin = $this->upsertTestUser(
                id: 'qa_sp_admin',
                firstName: 'QA Sport',
                lastName: 'Admin',
                email: 'qa.sport.admin@local.invalid',
                role: 'adminsport',
                status: 'active',
            );
            $existingCoach = User::query()
                ->where('role_code', 'coach')
                ->where('status', 'active')
                ->whereNotLike('email', 'qa.sport.%')
                ->firstOrFail();

            $coaches = [$existingCoach];
            foreach ([1, 2, 3] as $index) {
                $coaches[] = $this->upsertTestUser(
                    id: "qa_sp_c{$index}",
                    firstName: 'QA Coach',
                    lastName: "{$index}",
                    email: "qa.sport.coach.{$index}@local.invalid",
                    role: 'coach',
                    status: 'active',
                );
            }

            $inactiveCoach = $this->upsertTestUser(
                id: 'qa_sp_c_inactive',
                firstName: 'QA Coach',
                lastName: 'Inactive',
                email: 'qa.sport.coach.inactive@local.invalid',
                role: 'coach',
                status: 'inactive',
            );

            $teams = [];
            $teamDefinitions = [
                ['code' => 'QA-SP-TEAM-EN', 'name' => 'QA Riverside United', 'short' => 'Riverside', 'status' => 'active'],
                ['code' => 'QA-SP-TEAM-KH', 'name' => 'ក្រុមកីឡា QA អង្គរ', 'short' => 'អង្គរ QA', 'status' => 'active'],
                ['code' => 'QA-SP-TEAM-FULL', 'name' => 'QA Phnom Penh Academy', 'short' => 'PP Academy', 'status' => 'active'],
                ['code' => 'QA-SP-TEAM-OPEN', 'name' => 'QA Mekong Juniors', 'short' => 'Mekong', 'status' => 'active'],
                ['code' => 'QA-SP-TEAM-INACTIVE', 'name' => 'QA Archived Lions', 'short' => 'Archived Lions', 'status' => 'inactive'],
            ];

            foreach ($teamDefinitions as $definition) {
                DB::table('sport_teams')->updateOrInsert(
                    ['team_code' => $definition['code']],
                    [
                        'name' => $definition['name'],
                        'short_name' => $definition['short'],
                        'status' => $definition['status'],
                        'division' => 'QA Sport Testing',
                        'venue' => 'QA Local Training Ground',
                        'description' => 'QA-SP deterministic local test record.',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
                $teams[$definition['code']] = SportTeam::query()->where('team_code', $definition['code'])->firstOrFail();
            }

            $assignmentService = app(SportCoachAssignmentService::class);
            $assignmentService->assignTeamToCoach($teams['QA-SP-TEAM-EN'], $existingCoach, $admin);
            $assignmentService->assignTeamToCoach($teams['QA-SP-TEAM-KH'], $coaches[1], $admin);
            $assignmentService->assignTeamToCoach($teams['QA-SP-TEAM-FULL'], $coaches[2], $admin);

            $historical = CoachTeamAssignment::query()->firstOrNew([
                'coach_user_id' => $coaches[3]->id,
                'team_id' => $teams['QA-SP-TEAM-OPEN']->id,
            ]);
            $historical->forceFill([
                'assigned_by_user_id' => $admin->id,
                'status' => 'inactive',
                'assigned_at' => Carbon::parse('2026-01-10'),
                'ended_at' => Carbon::parse('2026-02-10'),
            ])->save();

            $inactiveAssignment = CoachTeamAssignment::query()->firstOrNew([
                'coach_user_id' => $inactiveCoach->id,
                'team_id' => $teams['QA-SP-TEAM-INACTIVE']->id,
            ]);
            $inactiveAssignment->forceFill([
                'assigned_by_user_id' => $admin->id,
                'status' => 'inactive',
                'assigned_at' => Carbon::parse('2025-01-10'),
                'ended_at' => Carbon::parse('2025-12-10'),
            ])->save();

            $membershipService = app(SportPlayerMembershipService::class);
            $players = [];
            for ($index = 1; $index <= 30; $index++) {
                $code = sprintf('QA-SP-PLAYER-%02d', $index);
                $player = SportPlayer::query()->firstOrNew(['player_code' => $code]);
                $khmer = $index % 2 === 0;
                $player->forceFill([
                    'first_name' => $khmer ? 'សុវណ្ណ' : 'QA Player',
                    'last_name' => $khmer ? "កីឡាករ {$index}" : "Test {$index}",
                    'jersey_number' => $index,
                    'position' => ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'][$index % 4],
                    'division' => 'QA Sport Testing',
                    'gender' => $index % 2 === 0 ? 'female' : 'male',
                    'age' => 14 + ($index % 7),
                    'date_of_birth' => Carbon::parse('2010-01-01')->subYears($index % 7)->toDateString(),
                    'current_school' => 'QA Local School',
                    'grade_year' => 'Grade '.(8 + ($index % 4)),
                    'registration_status' => $index % 5 === 0 ? 'pending' : 'registered',
                    'approval_status' => $index === 29 ? 'pending' : ($index === 30 ? 'rejected' : 'approved'),
                    'status' => $index === 30 ? 'inactive' : 'active',
                    'created_by_user_id' => $admin->id,
                    'approved_by_user_id' => $index === 29 || $index === 30 ? null : $admin->id,
                    'approved_at' => $index === 29 || $index === 30 ? null : now(),
                    'rejection_reason' => $index === 30 ? 'QA rejected scenario.' : null,
                    'notes' => 'QA-SP deterministic local test record.',
                ])->save();
                $players[] = $player->refresh();
            }

            foreach ($players as $index => $player) {
                if ($index < 8) {
                    $membershipService->activateMembership($player, $teams['QA-SP-TEAM-FULL'], $admin, true);
                } elseif ($index < 14) {
                    $membershipService->activateMembership($player, $teams['QA-SP-TEAM-EN'], $admin, true);
                } elseif ($index < 18) {
                    $membershipService->activateMembership($player, $teams['QA-SP-TEAM-KH'], $admin, true);
                } elseif ($index < 22) {
                    $membershipService->activateMembership($player, $teams['QA-SP-TEAM-OPEN'], $admin, true);
                } elseif ($index < 25) {
                    $membershipService->createPendingMembership($player, $teams['QA-SP-TEAM-OPEN'], $admin);
                }
            }

            $this->upsertAttendance($players, $teams, $existingCoach, $admin);
            $this->upsertEquipment($teams, $existingCoach, $admin);
            $this->upsertMatches($teams, $admin);
            $this->upsertTournaments($teams, $admin);
        });

        $this->command?->info('SportSystemTestSeeder completed for QA-SP marked local records.');
    }

    private function upsertTestUser(string $id, string $firstName, string $lastName, string $email, string $role, string $status): User
    {
        DB::table('users')->updateOrInsert(
            ['id' => $id],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'username' => Str::lower(str_replace(' ', '.', trim("{$firstName}.{$lastName}"))),
                'email' => $email,
                'role_code' => $role,
                'department_code' => 'sports',
                'status' => $status,
                'password' => Hash::make((string) env('SPORT_TEST_PASSWORD', Str::random(48))),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return User::query()->findOrFail($id);
    }

    private function upsertAttendance(array $players, array $teams, User $coach, User $admin): void
    {
        $rows = [
            [$players[0], $teams['QA-SP-TEAM-EN'], 'present', '2026-07-10'],
            [$players[1], $teams['QA-SP-TEAM-EN'], 'late', '2026-07-10'],
            [$players[2], $teams['QA-SP-TEAM-EN'], 'absent', '2026-07-10'],
            [$players[3], $teams['QA-SP-TEAM-EN'], 'excused', '2026-07-10'],
            [$players[4], $teams['QA-SP-TEAM-EN'], 'present', now()->toDateString()],
        ];

        foreach ($rows as [$player, $team, $status, $date]) {
            DB::table('sport_attendance_records')->updateOrInsert(
                ['attendance_type' => 'player', 'team_id' => $team->id, 'player_id' => $player->id, 'attendance_date' => $date],
                [
                    'coach_user_id' => $coach->id,
                    'recorded_by_user_id' => $admin->id,
                    'status' => $status,
                    'subject_key' => "QA-SP-{$player->player_code}-{$date}",
                    'note' => 'QA-SP deterministic local test record.',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    private function upsertEquipment(array $teams, User $coach, User $admin): void
    {
        $items = [
            ['QA-SP-EQUIP-BALL', 'QA Footballs', 'balls', 20, 20, 'available'],
            ['QA-SP-EQUIP-CONES', 'QA Training Cones', 'training', 40, 35, 'assigned'],
            ['QA-SP-EQUIP-BIBS', 'QA Bibs', 'training', 24, 0, 'maintenance'],
            ['QA-SP-EQUIP-NET', 'QA Goal Nets', 'goals', 4, 0, 'damaged'],
            ['QA-SP-EQUIP-FIRSTAID', 'QA First Aid Kit', 'safety', 2, 2, 'available'],
            ['QA-SP-EQUIP-WHISTLE', 'QA Whistles', 'officials', 8, 8, 'available'],
            ['QA-SP-EQUIP-PUMP', 'QA Ball Pumps', 'maintenance', 3, 1, 'lost'],
        ];

        foreach ($items as [$code, $name, $category, $total, $available, $status]) {
            DB::table('sport_equipment_items')->updateOrInsert(
                ['equipment_code' => $code],
                [
                    'name' => $name,
                    'category' => $category,
                    'unit' => 'unit',
                    'total_quantity' => $total,
                    'available_quantity' => $available,
                    'minimum_stock_level' => 2,
                    'storage_location' => 'QA Sports Store',
                    'status' => $status,
                    'description' => 'QA-SP deterministic local test record.',
                    'created_by_user_id' => $admin->id,
                    'updated_by_user_id' => $admin->id,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        $itemId = DB::table('sport_equipment_items')->where('equipment_code', 'QA-SP-EQUIP-BALL')->value('id');
        DB::table('sport_equipment_requests')->updateOrInsert(
            ['request_code' => 'QA-SP-REQUEST-PENDING'],
            [
                'equipment_item_id' => $itemId,
                'coach_user_id' => $coach->id,
                'team_id' => $teams['QA-SP-TEAM-EN']->id,
                'requested_quantity' => 4,
                'approved_quantity' => null,
                'issued_quantity' => 0,
                'returned_quantity' => 0,
                'damaged_quantity' => 0,
                'missing_quantity' => 0,
                'purpose' => 'QA training session request.',
                'required_date' => '2026-08-01',
                'expected_return_date' => '2026-08-02',
                'status' => 'pending',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function upsertMatches(array $teams, User $admin): void
    {
        $matchRows = [
            ['QA-SP-MATCH-DRAFT', $teams['QA-SP-TEAM-EN'], $teams['QA-SP-TEAM-KH'], 'draft', 'pending', '2026-08-05 09:00:00'],
            ['QA-SP-MATCH-APPROVED', $teams['QA-SP-TEAM-FULL'], $teams['QA-SP-TEAM-OPEN'], 'scheduled', 'approved', '2026-08-06 09:00:00'],
            ['QA-SP-MATCH-COMPLETED', $teams['QA-SP-TEAM-EN'], $teams['QA-SP-TEAM-FULL'], 'completed', 'approved', '2026-07-01 09:00:00'],
        ];

        foreach ($matchRows as [$code, $home, $away, $status, $approval, $scheduled]) {
            DB::table('sport_matches')->updateOrInsert(
                ['match_code' => $code],
                [
                    'home_team_id' => $home->id,
                    'away_team_id' => $away->id,
                    'competition_type' => 'QA League',
                    'match_type' => 'league',
                    'tournament_name' => 'QA Sport System Cup',
                    'venue' => 'QA Local Stadium',
                    'scheduled_at' => $scheduled,
                    'status' => $status,
                    'approval_status' => $approval,
                    'approved_by_user_id' => $approval === 'approved' ? $admin->id : null,
                    'approved_at' => $approval === 'approved' ? now() : null,
                    'home_score' => $status === 'completed' ? 2 : 0,
                    'away_score' => $status === 'completed' ? 1 : 0,
                    'completed_at' => $status === 'completed' ? '2026-07-01 11:00:00' : null,
                    'created_by_user_id' => $admin->id,
                    'requested_by_role' => 'adminsport',
                    'notes' => 'QA-SP deterministic local test record.',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    private function upsertTournaments(array $teams, User $admin): void
    {
        $tournaments = [
            ['QA-SP-TOURN-DRAFT', 'QA Draft Cup', 'draft', '2026-09-01', '2026-09-30'],
            ['QA-SP-TOURN-REG', 'ពានរង្វាន់ QA បើកចុះឈ្មោះ', 'registration_open', '2026-08-01', '2026-08-31'],
            ['QA-SP-TOURN-ACTIVE', 'QA Active League', 'active', '2026-07-01', '2026-07-31'],
            ['QA-SP-TOURN-DONE', 'QA Completed Championship', 'completed', '2026-05-01', '2026-05-31'],
        ];

        foreach ($tournaments as [$code, $name, $status, $starts, $ends]) {
            DB::table('sport_tournaments')->updateOrInsert(
                ['tournament_code' => $code],
                [
                    'slug' => Str::slug($code),
                    'name' => $name,
                    'season' => '2026',
                    'tournament_type' => 'league',
                    'status' => $status,
                    'visibility' => 'public',
                    'registration_open_at' => $starts,
                    'registration_close_at' => $ends,
                    'starts_at' => $starts,
                    'ends_at' => $ends,
                    'description' => 'QA-SP deterministic local test record.',
                    'location' => 'QA Local Stadium',
                    'organizer' => 'HFCCF QA Sports',
                    'rules' => json_encode(['teamsPerGroup' => 4]),
                    'settings' => json_encode(['qaMarker' => true]),
                    'created_by_user_id' => $admin->id,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        $activeId = DB::table('sport_tournaments')->where('tournament_code', 'QA-SP-TOURN-ACTIVE')->value('id');
        foreach ([$teams['QA-SP-TEAM-EN'], $teams['QA-SP-TEAM-KH'], $teams['QA-SP-TEAM-FULL']] as $team) {
            DB::table('sport_tournament_teams')->updateOrInsert(
                ['tournament_id' => $activeId, 'team_id' => $team->id],
                ['joined_at' => '2026-06-15 09:00:00', 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }
}
