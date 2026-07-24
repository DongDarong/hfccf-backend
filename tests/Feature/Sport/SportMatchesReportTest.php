<?php

namespace Tests\Feature\Sport;

use App\Models\SportDivision;
use App\Models\SportTournament;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SportReportTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportMatchesReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_matches_report_uses_seeded_rows_and_filters(): void
    {
        $this->seed(SportReportTestingSeeder::class);
        $admin = User::query()->where('email', 'qa.sport.reports.admin@local.invalid')->firstOrFail();
        $division = SportDivision::query()->where('name', 'QA-U19')->firstOrFail();
        $tournament = SportTournament::query()->where('tournament_code', 'QA-CUP-2026-U19')->firstOrFail();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/sport/reports/matches?'.http_build_query([
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-20',
            'division_id' => $division->id,
            'tournament_id' => $tournament->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.summary.total_matches', 3)
            ->assertJsonPath('data.summary.completed_matches', 3)
            ->assertJsonPath('data.summary.scheduled_matches', 0)
            ->assertJsonPath('data.summary.total_teams', 2);

        $this->assertCount(3, $response->json('data.matches'));
        $this->assertStringNotContainsString('Team A', $response->getContent());
        $this->assertStringContainsString('QA-U19', $response->getContent());
    }

    public function test_matches_report_pdf_returns_a_backend_generated_attachment(): void
    {
        $this->seed(SportReportTestingSeeder::class);
        $admin = User::query()->where('email', 'qa.sport.reports.admin@local.invalid')->firstOrFail();
        Sanctum::actingAs($admin);
        config()->set('services.preschool_pdf.browser_binary', PHP_BINARY);

        Process::fake(function ($process) {
            $pdfArgument = collect($process->command)->first(fn ($argument) => str_starts_with($argument, '--print-to-pdf='));
            if (is_string($pdfArgument)) {
                File::put(substr($pdfArgument, strlen('--print-to-pdf=')), "%PDF-1.4\nxref\nstartxref\n%%EOF");
            }

            return Process::result('', '', 0);
        })->preventStrayProcesses();

        $response = $this->get('/api/sport/reports/matches/download?date_from=2026-07-01&date_to=2026-07-31');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="SportReport_matches_'.now()->toDateString().'.pdf"');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        Process::assertRan(fn ($process) => collect($process->command)->contains(fn ($argument) => str_starts_with($argument, '--print-to-pdf=')));
        $this->assertSame([], File::files(storage_path('app/tmp/sport-matches-report')));
    }
}
