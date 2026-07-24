<?php

namespace App\Services\Sport;

use App\Models\SportDivision;
use App\Models\SportMatch;
use App\Models\SportTeam;
use App\Models\SportTournament;
use App\Support\SportTournament\TournamentStandingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SportStandingsReportService
{
    public function __construct(
        private readonly TournamentStandingsService $standingsService,
    ) {}

    /**
     * Generate canonical standings report data.
     *
     * @param  array{
     *   date_from?: string,
     *   date_to?: string,
     *   tournament_id?: int,
     *   team_id?: int,
     *   division_id?: int
     * }  $filters
     */
    public function report(array $filters): array
    {
        // Get tournaments within date range
        $tournaments = SportTournament::query()
            ->with(['teams', 'matches.homeTeam', 'matches.awayTeam', 'groups.groupTeams.team'])
            ->when($filters['date_from'] ?? null, fn ($builder, $date) => $builder->whereDate('starts_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($builder, $date) => $builder->whereDate('ends_at', '<=', $date))
            ->when($filters['tournament_id'] ?? null, fn ($builder, $id) => $builder->where('id', $id))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        // Calculate standings for each tournament
        $allStandings = [];
        $summary = [
            'total_teams' => 0,
            'tournaments_with_standings' => 0,
            'total_groups' => 0,
        ];

        foreach ($tournaments as $tournament) {
            $tournamentStandings = $this->standingsService->calculate($tournament);

            // Flatten group standings into a single array with context
            foreach ($tournamentStandings['groups'] ?? [] as $groupData) {
                $summary['total_groups']++;

                foreach ($groupData['standings'] as $standing) {
                    // Filter by team if specified
                    if (($filters['team_id'] ?? null) && $standing['team_id'] !== (int) $filters['team_id']) {
                        continue;
                    }

                    // Filter by division if specified
                    if ($filters['division_id'] ?? null) {
                        $team = SportTeam::find($standing['team_id']);
                        if (! $team || $team->division_id !== (int) $filters['division_id']) {
                            continue;
                        }
                    }

                    $allStandings[] = array_merge($standing, [
                        'tournament_id' => $tournament->id,
                        'tournament_name' => $tournament->name,
                    ]);

                    $summary['total_teams']++;
                }
            }

            // Add overall standings if no groups
            if (empty($tournamentStandings['groups'])) {
                foreach ($tournamentStandings['overall'] ?? [] as $standing) {
                    // Filter by team if specified
                    if (($filters['team_id'] ?? null) && $standing['team_id'] !== (int) $filters['team_id']) {
                        continue;
                    }

                    // Filter by division if specified
                    if ($filters['division_id'] ?? null) {
                        $team = SportTeam::find($standing['team_id']);
                        if (! $team || $team->division_id !== (int) $filters['division_id']) {
                            continue;
                        }
                    }

                    $allStandings[] = array_merge($standing, [
                        'tournament_id' => $tournament->id,
                        'tournament_name' => $tournament->name,
                    ]);

                    $summary['total_teams']++;
                }

                if (! empty($tournamentStandings['overall'])) {
                    $summary['tournaments_with_standings']++;
                }
            } else {
                if (! empty($tournamentStandings['groups'])) {
                    $summary['tournaments_with_standings']++;
                }
            }
        }

        return [
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'division_id' => $filters['division_id'] ?? null,
                'team_id' => $filters['team_id'] ?? null,
                'tournament_id' => $filters['tournament_id'] ?? null,
            ],
            'filter_labels' => [
                'division' => ($filters['division_id'] ?? null) ? SportDivision::find($filters['division_id'])?->name : 'All',
                'team' => ($filters['team_id'] ?? null) ? SportTeam::find($filters['team_id'])?->name : 'All',
                'tournament' => ($filters['tournament_id'] ?? null) ? SportTournament::find($filters['tournament_id'])?->name : 'All',
            ],
            'summary' => $summary,
            'standings' => $allStandings,
        ];
    }

    /**
     * Export standings report as PDF.
     *
     * @param  array{
     *   date_from?: string,
     *   date_to?: string,
     *   tournament_id?: int,
     *   team_id?: int,
     *   division_id?: int
     * }  $filters
     */
    public function exportPdf(array $filters): array
    {
        $report = $this->report($filters);
        $html = view('pdf.sport-standings-report', [
            'report' => $report,
            'organization' => $this->organization(),
            'generatedAt' => now(),
        ])->render();
        $content = $this->renderPdf($html);

        if ($content === '' || ! str_starts_with($content, '%PDF')) {
            throw new \RuntimeException('Sport Standings PDF rendering produced invalid content.');
        }

        return ['filename' => 'SportReport_standings_'.now()->toDateString().'.pdf', 'content' => $content];
    }

    /**
     * Export standings report as Excel.
     *
     * @param  array{
     *   date_from?: string,
     *   date_to?: string,
     *   tournament_id?: int,
     *   team_id?: int,
     *   division_id?: int
     * }  $filters
     */
    public function exportExcel(array $filters): array
    {
        $report = $this->report($filters);

        // Group standings by tournament for Excel sheets
        $standingsByTournament = collect($report['standings'])
            ->groupBy('tournament_id')
            ->mapWithKeys(function ($standings, $tournamentId) {
                $tournament = SportTournament::find($tournamentId);

                return [$tournament?->name ?? "Tournament {$tournamentId}" => $standings->values()->all()];
            });

        $filename = 'SportReport_standings_'.now()->toDateString().'.xlsx';
        $content = $this->createExcelContent($report, $standingsByTournament);

        return ['filename' => $filename, 'content' => $content];
    }

    /**
     * Create Excel file content for standings.
     */
    private function createExcelContent(array $report, $standingsByTournament): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        // Summary sheet
        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Summary');
        $summarySheet->setCellValue('A1', 'Standings Report');
        $summarySheet->setCellValue('A2', 'Generated: '.now()->toDateTimeString());
        $summarySheet->setCellValue('A4', 'Filters');
        $summarySheet->setCellValue('A5', 'Division: '.$report['filter_labels']['division']);
        $summarySheet->setCellValue('A6', 'Team: '.$report['filter_labels']['team']);
        $summarySheet->setCellValue('A7', 'Tournament: '.$report['filter_labels']['tournament']);

        $summarySheet->setCellValue('A9', 'Summary');
        $summarySheet->setCellValue('A10', 'Total Teams: '.$report['summary']['total_teams']);
        $summarySheet->setCellValue('A11', 'Tournaments: '.$report['summary']['tournaments_with_standings']);
        $summarySheet->setCellValue('A12', 'Groups: '.$report['summary']['total_groups']);

        // Standings sheets
        $colHeaders = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        foreach ($standingsByTournament as $tournamentName => $standings) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle(substr($tournamentName, 0, 31)); // Excel sheet name limit

            // Headers
            $headers = ['Position', 'Team', 'Played', 'Won', 'Draw', 'Lost', 'Goals For', 'Goals Against', 'Goal Difference', 'Points'];
            foreach ($headers as $col => $header) {
                $sheet->setCellValue($colHeaders[$col].'1', $header);
            }

            // Data rows
            $row = 2;
            foreach ($standings as $standing) {
                $sheet->setCellValue('A'.$row, $standing['rank_position'] ?? '');
                $sheet->setCellValue('B'.$row, $standing['team_name'] ?? '');
                $sheet->setCellValue('C'.$row, $standing['played'] ?? 0);
                $sheet->setCellValue('D'.$row, $standing['wins'] ?? 0);
                $sheet->setCellValue('E'.$row, $standing['draws'] ?? 0);
                $sheet->setCellValue('F'.$row, $standing['losses'] ?? 0);
                $sheet->setCellValue('G'.$row, $standing['goals_for'] ?? 0);
                $sheet->setCellValue('H'.$row, $standing['goals_against'] ?? 0);
                $sheet->setCellValue('I'.$row, $standing['goal_difference'] ?? 0);
                $sheet->setCellValue('J'.$row, $standing['points'] ?? 0);

                $row++;
            }

            // Auto-size columns
            foreach ($colHeaders as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        // Write to temporary file and return content
        $tempFile = tempnam(sys_get_temp_dir(), 'standing_');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);
        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return $content;
    }

    /**
     * Render HTML to PDF using browser.
     */
    private function renderPdf(string $html): string
    {
        $browser = $this->resolveBrowser();
        $directory = storage_path('app/tmp/sport-standings-report');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
        $token = (string) \Illuminate\Support\Str::uuid();
        $htmlPath = $directory.DIRECTORY_SEPARATOR.$token.'.html';
        $pdfPath = $directory.DIRECTORY_SEPARATOR.$token.'.pdf';
        $profilePath = $directory.DIRECTORY_SEPARATOR.$token.'-profile';
        try {
            \Illuminate\Support\Facades\File::put($htmlPath, $html);
            \Illuminate\Support\Facades\File::ensureDirectoryExists($profilePath);
            $result = Process::timeout(120)->run([$browser, '--headless=new', '--disable-gpu', '--allow-file-access-from-files', '--no-pdf-header-footer', '--user-data-dir='.$profilePath, '--print-to-pdf='.$pdfPath, $this->filePathToUri($htmlPath)]);
            if ($result->failed() || ! \Illuminate\Support\Facades\File::exists($pdfPath)) {
                throw new \RuntimeException('Sport Standings PDF rendering is temporarily unavailable.');
            }
            return (string) \Illuminate\Support\Facades\File::get($pdfPath);
        } finally {
            \Illuminate\Support\Facades\File::delete([$htmlPath, $pdfPath]);
            \Illuminate\Support\Facades\File::deleteDirectory($profilePath);
        }
    }

    private function filePathToUri(string $path): string
    {
        $normalized = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        if (preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return 'file:///'.rawurlencode(substr($normalized, 0, 1)).':'.str_replace('%2F', '/', rawurlencode(substr($normalized, 2)));
        }

        return 'file://'.$normalized;
    }

    private function resolveBrowser(): string
    {
        foreach ([config('services.preschool_pdf.browser_binary'), 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe', 'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe', 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', '/usr/bin/google-chrome', '/usr/bin/chromium'] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && \Illuminate\Support\Facades\File::exists($candidate)) return $candidate;
        }
        throw new \RuntimeException('No supported browser binary found for Sport Standings PDF.');
    }

    /**
     * Get organization data for reports.
     */
    private function organization(): array
    {
        try {
            $organization = \App\Models\Organization::query()->where('is_active', true)->first();
            return [
                'kh_name' => $organization?->kh_name ?: 'អង្គការ HFCCF',
                'en_name' => $organization?->name ?: config('app.name', 'HFCCF'),
                'logo_data_uri' => $this->organizationLogoDataUri($organization?->logo),
            ];
        } catch (Throwable) {
            return [
                'kh_name' => 'អង្គការ HFCCF',
                'en_name' => config('app.name', 'HFCCF'),
                'logo_data_uri' => $this->organizationLogoDataUri(null),
            ];
        }
    }

    private function organizationLogoDataUri(?string $logoPath): ?string
    {
        $path = \App\Support\ImageStorage::resolvePath($logoPath);
        if ($path !== null) {
            foreach (\App\Support\ImageStorage::deleteDisks() as $disk) {
                try {
                    $storage = Storage::disk($disk);
                    if ($storage->exists($path)) {
                        return 'data:'.($storage->mimeType($path) ?: 'image/png').';base64,'.base64_encode($storage->get($path));
                    }
                } catch (Throwable $exception) {
                    Log::warning('Sport Standings PDF image lookup failed.', ['path' => $path, 'exception' => $exception::class]);
                }
            }
        }
        $fallback = public_path('images'.DIRECTORY_SEPARATOR.'hfccf-logo.png');
        return \Illuminate\Support\Facades\File::exists($fallback) ? 'data:'.(\Illuminate\Support\Facades\File::mimeType($fallback) ?: 'image/png').';base64,'.base64_encode(\Illuminate\Support\Facades\File::get($fallback)) : null;
    }
}
