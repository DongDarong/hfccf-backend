<?php

namespace App\Services\Sport;

use App\Models\SportDivision;
use App\Models\SportTeam;
use App\Models\SportTournament;
use App\Support\SportTournament\TournamentStatisticsService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SportPlayerStatisticsReportService
{
    public function __construct(
        private readonly TournamentStatisticsService $statisticsService,
    ) {}

    /**
     * Generate canonical player statistics report data.
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
        $tournaments = SportTournament::query()
            ->with(['teams', 'matches.events.player', 'matches.events.assistPlayer'])
            ->when($filters['date_from'] ?? null, fn ($builder, $date) => $builder->whereDate('starts_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($builder, $date) => $builder->whereDate('ends_at', '<=', $date))
            ->when($filters['tournament_id'] ?? null, fn ($builder, $id) => $builder->where('id', $id))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        $allPlayers = [];
        $summary = [
            'total_players' => 0,
            'players_with_appearances' => 0,
            'total_goals' => 0,
            'total_assists' => 0,
            'total_yellow_cards' => 0,
            'total_red_cards' => 0,
        ];

        foreach ($tournaments as $tournament) {
            $tournamentStats = $this->statisticsService->calculate($tournament);
            $playerStats = $tournamentStats['playerStats'] ?? [];

            foreach ($playerStats as $playerStat) {
                $playerId = (int) ($playerStat['player_id'] ?? 0);
                $teamId = (int) ($playerStat['team_id'] ?? 0);

                // Skip if player is null (soft-deleted)
                if ($playerId === 0) {
                    continue;
                }

                // Filter by team if specified
                if (($filters['team_id'] ?? null) && $teamId !== (int) $filters['team_id']) {
                    continue;
                }

                // Filter by division if specified
                if ($filters['division_id'] ?? null) {
                    $team = SportTeam::find($teamId);
                    if (! $team || $team->division_id !== (int) $filters['division_id']) {
                        continue;
                    }
                }

                // Use player_id as key to avoid duplicates across tournaments
                if (! isset($allPlayers[$playerId])) {
                    $allPlayers[$playerId] = [
                        'player_id' => $playerId,
                        'player_code' => $playerStat['player_code'] ?? '',
                        'player_name' => $playerStat['player_name'] ?? '',
                        'team_id' => $teamId,
                        'team_name' => $playerStat['team_name'] ?? '',
                        'playing_position' => $playerStat['position_snapshot'] ?? $playerStat['position'] ?? '',
                        'appearances' => 0,
                        'goals' => 0,
                        'assists' => 0,
                        'yellow_cards' => 0,
                        'red_cards' => 0,
                        'penalty_goals' => 0,
                        'own_goals' => 0,
                        'penalty_misses' => 0,
                        'discipline_points' => 0,
                    ];
                }

                // Aggregate stats across tournaments
                $allPlayers[$playerId]['appearances'] += $playerStat['appearances'] ?? 0;
                $allPlayers[$playerId]['goals'] += $playerStat['goals'] ?? 0;
                $allPlayers[$playerId]['assists'] += $playerStat['assists'] ?? 0;
                $allPlayers[$playerId]['yellow_cards'] += $playerStat['yellow_cards'] ?? 0;
                $allPlayers[$playerId]['red_cards'] += $playerStat['red_cards'] ?? 0;
                $allPlayers[$playerId]['penalty_goals'] += $playerStat['penalty_goals'] ?? 0;
                $allPlayers[$playerId]['own_goals'] += $playerStat['own_goals'] ?? 0;
                $allPlayers[$playerId]['penalty_misses'] += $playerStat['penalty_misses'] ?? 0;
                $allPlayers[$playerId]['discipline_points'] = ($allPlayers[$playerId]['yellow_cards'] * 1) + ($allPlayers[$playerId]['red_cards'] * 3);
            }
        }

        // Calculate summary
        foreach ($allPlayers as $player) {
            $summary['total_players']++;
            if ($player['appearances'] > 0) {
                $summary['players_with_appearances']++;
            }
            $summary['total_goals'] += $player['goals'];
            $summary['total_assists'] += $player['assists'];
            $summary['total_yellow_cards'] += $player['yellow_cards'];
            $summary['total_red_cards'] += $player['red_cards'];
        }

        // Rank players by goals, then assists, then name
        $ranked = collect($allPlayers)
            ->sort(function (array $left, array $right): int {
                if ($left['goals'] !== $right['goals']) {
                    return $right['goals'] <=> $left['goals'];
                }

                if ($left['assists'] !== $right['assists']) {
                    return $right['assists'] <=> $left['assists'];
                }

                return strcasecmp((string) $left['player_name'], (string) $right['player_name']);
            })
            ->values()
            ->all();

        // Add rank_position
        foreach ($ranked as $index => &$player) {
            $player['rank_position'] = $index + 1;
        }
        unset($player);

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
            'players' => $ranked,
        ];
    }

    public function exportPdf(array $filters): array
    {
        $report = $this->report($filters);
        $html = view('pdf.sport-player-statistics-report', [
            'report' => $report,
            'organization' => $this->organization(),
            'generatedAt' => now(),
        ])->render();
        $content = $this->renderPdf($html);

        if ($content === '' || ! str_starts_with($content, '%PDF')) {
            throw new \RuntimeException('Sport Player Statistics PDF rendering produced invalid content.');
        }

        return ['filename' => 'SportReport_player-statistics_'.now()->toDateString().'.pdf', 'content' => $content];
    }

    public function exportExcel(array $filters): array
    {
        $report = $this->report($filters);

        $filename = 'SportReport_player-statistics_'.now()->toDateString().'.xlsx';
        $content = $this->createExcelContent($report);

        return ['filename' => $filename, 'content' => $content];
    }

    private function createExcelContent(array $report): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        // Summary sheet
        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Summary');
        $summarySheet->setCellValue('A1', 'Player Statistics Report');
        $summarySheet->setCellValue('A2', 'Generated: '.now()->toDateTimeString());
        $summarySheet->setCellValue('A4', 'Filters');
        $summarySheet->setCellValue('A5', 'Division: '.$report['filter_labels']['division']);
        $summarySheet->setCellValue('A6', 'Team: '.$report['filter_labels']['team']);
        $summarySheet->setCellValue('A7', 'Tournament: '.$report['filter_labels']['tournament']);

        $summarySheet->setCellValue('A9', 'Summary');
        $summarySheet->setCellValue('A10', 'Total Players: '.$report['summary']['total_players']);
        $summarySheet->setCellValue('A11', 'Players with Appearances: '.$report['summary']['players_with_appearances']);
        $summarySheet->setCellValue('A12', 'Total Goals: '.$report['summary']['total_goals']);
        $summarySheet->setCellValue('A13', 'Total Assists: '.$report['summary']['total_assists']);
        $summarySheet->setCellValue('A14', 'Total Yellow Cards: '.$report['summary']['total_yellow_cards']);
        $summarySheet->setCellValue('A15', 'Total Red Cards: '.$report['summary']['total_red_cards']);

        // Players sheet
        $playersSheet = $spreadsheet->createSheet();
        $playersSheet->setTitle('Players');

        $colHeaders = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'];
        $headers = ['Position', 'Player', 'Team', 'Appearances', 'Goals', 'Assists', 'Yellow Cards', 'Red Cards', 'Penalty Goals', 'Own Goals', 'Discipline Pts'];

        foreach ($headers as $col => $header) {
            $playersSheet->setCellValue($colHeaders[$col].'1', $header);
        }

        $row = 2;
        foreach ($report['players'] as $player) {
            $playersSheet->setCellValue('A'.$row, $player['rank_position']);
            $playersSheet->setCellValue('B'.$row, $player['player_name']);
            $playersSheet->setCellValue('C'.$row, $player['team_name']);
            $playersSheet->setCellValue('D'.$row, $player['appearances']);
            $playersSheet->setCellValue('E'.$row, $player['goals']);
            $playersSheet->setCellValue('F'.$row, $player['assists']);
            $playersSheet->setCellValue('G'.$row, $player['yellow_cards']);
            $playersSheet->setCellValue('H'.$row, $player['red_cards']);
            $playersSheet->setCellValue('I'.$row, $player['penalty_goals']);
            $playersSheet->setCellValue('J'.$row, $player['own_goals']);
            $playersSheet->setCellValue('K'.$row, $player['discipline_points']);

            $row++;
        }

        // Auto-size columns
        foreach ($colHeaders as $col) {
            $playersSheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'player_stats_');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);
        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return $content;
    }

    private function renderPdf(string $html): string
    {
        $browser = $this->resolveBrowser();
        $directory = storage_path('app/tmp/sport-player-statistics-report');
        File::ensureDirectoryExists($directory);
        $token = (string) Str::uuid();
        $htmlPath = $directory.DIRECTORY_SEPARATOR.$token.'.html';
        $pdfPath = $directory.DIRECTORY_SEPARATOR.$token.'.pdf';
        $profilePath = $directory.DIRECTORY_SEPARATOR.$token.'-profile';
        try {
            File::put($htmlPath, $html);
            File::ensureDirectoryExists($profilePath);
            $result = Process::timeout(120)->run([$browser, '--headless=new', '--disable-gpu', '--allow-file-access-from-files', '--no-pdf-header-footer', '--user-data-dir='.$profilePath, '--print-to-pdf='.$pdfPath, $this->filePathToUri($htmlPath)]);
            if ($result->failed() || ! File::exists($pdfPath)) {
                throw new \RuntimeException('Sport Player Statistics PDF rendering is temporarily unavailable.');
            }
            return (string) File::get($pdfPath);
        } finally {
            File::delete([$htmlPath, $pdfPath]);
            File::deleteDirectory($profilePath);
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
            if (is_string($candidate) && $candidate !== '' && File::exists($candidate)) return $candidate;
        }
        throw new \RuntimeException('No supported browser binary found for Sport Player Statistics PDF.');
    }

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
                    Log::warning('Sport Player Statistics PDF image lookup failed.', ['path' => $path, 'exception' => $exception::class]);
                }
            }
        }
        $fallback = public_path('images'.DIRECTORY_SEPARATOR.'hfccf-logo.png');
        return File::exists($fallback) ? 'data:'.((File::mimeType($fallback) ?: 'image/png')).';base64,'.base64_encode(File::get($fallback)) : null;
    }
}
