<?php

namespace App\Services\Sport;

use App\Models\Organization;
use App\Models\SportDivision;
use App\Models\SportMatch;
use App\Models\SportTeam;
use App\Models\SportTournament;
use App\Support\ImageStorage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SportMatchesReportService
{
    public function report(array $filters): array
    {
        $matches = SportMatch::query()
            ->with(['homeTeam.divisionRelation', 'awayTeam.divisionRelation', 'tournament'])
            ->when($filters['date_from'] ?? null, fn ($builder, $date) => $builder->whereDate('scheduled_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($builder, $date) => $builder->whereDate('scheduled_at', '<=', $date))
            ->when($filters['tournament_id'] ?? null, fn ($builder, $id) => $builder->where('tournament_id', $id))
            ->when($filters['team_id'] ?? null, function ($builder, $id): void {
                $builder->where(fn ($teamQuery) => $teamQuery->where('home_team_id', $id)->orWhere('away_team_id', $id));
            })
            ->when($filters['division_id'] ?? null, function ($builder, $id): void {
                $builder->where(function ($divisionQuery) use ($id): void {
                    $divisionQuery->whereHas('homeTeam', fn ($teamQuery) => $teamQuery->where('division_id', $id))
                        ->orWhereHas('awayTeam', fn ($teamQuery) => $teamQuery->where('division_id', $id));
                });
            })
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

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
            'summary' => [
                'total_matches' => $matches->count(),
                'completed_matches' => $matches->where('status', 'completed')->count(),
                'scheduled_matches' => $matches->where('status', 'scheduled')->count(),
                'total_teams' => $matches->flatMap(fn (SportMatch $match) => [$match->home_team_id, $match->away_team_id])->filter()->unique()->count(),
            ],
            'matches' => $matches->map(fn (SportMatch $match): array => $this->mapMatch($match))->values()->all(),
        ];
    }

    public function exportPdf(array $filters): array
    {
        $report = $this->report($filters);
        $html = view('pdf.sport-matches-report', [
            'report' => $report,
            'organization' => $this->organization(),
            'generatedAt' => now(),
        ])->render();
        $content = $this->renderPdf($html);

        if ($content === '' || ! str_starts_with($content, '%PDF')) {
            throw new \RuntimeException('Sport Matches PDF rendering produced invalid content.');
        }

        return ['filename' => 'SportReport_matches_'.now()->toDateString().'.pdf', 'content' => $content];
    }

    public function exportExcel(array $filters): array
    {
        $report = $this->report($filters);

        $filename = 'SportReport_matches_'.now()->toDateString().'.xlsx';
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
        $summarySheet->setCellValue('A1', 'Matches Report');
        $summarySheet->setCellValue('A2', 'Generated: '.now()->toDateTimeString());
        $summarySheet->setCellValue('A4', 'Filters');
        $summarySheet->setCellValue('A5', 'Division: '.$report['filter_labels']['division']);
        $summarySheet->setCellValue('A6', 'Team: '.$report['filter_labels']['team']);
        $summarySheet->setCellValue('A7', 'Tournament: '.$report['filter_labels']['tournament']);

        $summarySheet->setCellValue('A9', 'Summary');
        $summarySheet->setCellValue('A10', 'Total Matches: '.$report['summary']['total_matches']);
        $summarySheet->setCellValue('A11', 'Completed Matches: '.$report['summary']['completed_matches']);
        $summarySheet->setCellValue('A12', 'Scheduled Matches: '.$report['summary']['scheduled_matches']);
        $summarySheet->setCellValue('A13', 'Total Teams: '.$report['summary']['total_teams']);

        // Matches sheet
        $matchesSheet = $spreadsheet->createSheet();
        $matchesSheet->setTitle('Matches');

        $colHeaders = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $headers = ['Date', 'Tournament', 'Division', 'Home Team', 'Away Team', 'Score', 'Venue', 'Status'];

        foreach ($headers as $col => $header) {
            $matchesSheet->setCellValue($colHeaders[$col].'1', $header);
        }

        $row = 2;
        foreach ($report['matches'] as $match) {
            $matchesSheet->setCellValue('A'.$row, $match['date']);
            $matchesSheet->setCellValue('B'.$row, $match['tournamentName']);
            $matchesSheet->setCellValue('C'.$row, $match['divisionName']);
            $matchesSheet->setCellValue('D'.$row, $match['homeTeamName']);
            $matchesSheet->setCellValue('E'.$row, $match['awayTeamName']);
            $matchesSheet->setCellValue('F'.$row, $match['score']);
            $matchesSheet->setCellValue('G'.$row, $match['venue']);
            $matchesSheet->setCellValue('H'.$row, $match['status']);

            $row++;
        }

        // Auto-size columns
        foreach ($colHeaders as $col) {
            $matchesSheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'matches_');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);
        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return $content;
    }

    private function mapMatch(SportMatch $match): array
    {
        $division = $match->homeTeam?->divisionRelation ?: $match->awayTeam?->divisionRelation;

        return [
            'id' => $match->id,
            'homeTeamId' => $match->home_team_id,
            'awayTeamId' => $match->away_team_id,
            'tournamentId' => $match->tournament_id,
            'homeTeamName' => $match->homeTeam?->name,
            'awayTeamName' => $match->awayTeam?->name,
            'tournamentName' => $match->tournament?->name,
            'divisionId' => $division?->id,
            'divisionName' => $division?->name,
            'homeScore' => (int) $match->home_score,
            'awayScore' => (int) $match->away_score,
            'score' => sprintf('%d - %d', (int) $match->home_score, (int) $match->away_score),
            'date' => $match->scheduled_at?->toDateString(),
            'scheduledAt' => $match->scheduled_at?->toISOString(),
            'venue' => $match->venue,
            'status' => $match->status,
        ];
    }

    private function organization(): array
    {
        $organization = Organization::query()->where('is_active', true)->first();
        return ['kh_name' => $organization?->kh_name ?: 'អង្គការ HFCCF', 'en_name' => $organization?->name ?: config('app.name', 'HFCCF'), 'logo_data_uri' => $this->organizationLogoDataUri($organization?->logo)];
    }

    private function organizationLogoDataUri(?string $logoPath): ?string
    {
        $path = ImageStorage::resolvePath($logoPath);
        if ($path !== null) {
            foreach (ImageStorage::deleteDisks() as $disk) {
                try {
                    $storage = Storage::disk($disk);
                    if ($storage->exists($path)) {
                        return 'data:'.($storage->mimeType($path) ?: 'image/png').';base64,'.base64_encode($storage->get($path));
                    }
                } catch (Throwable $exception) {
                    Log::warning('Sport Matches PDF image lookup failed.', ['path' => $path, 'exception' => $exception::class]);
                }
            }
        }
        $fallback = public_path('images'.DIRECTORY_SEPARATOR.'hfccf-logo.png');
        return File::exists($fallback) ? 'data:'.(File::mimeType($fallback) ?: 'image/png').';base64,'.base64_encode(File::get($fallback)) : null;
    }

    private function renderPdf(string $html): string
    {
        $browser = $this->resolveBrowser();
        $directory = storage_path('app/tmp/sport-matches-report');
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
                throw new \RuntimeException('Sport Matches PDF rendering is temporarily unavailable.');
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
        throw new \RuntimeException('No supported browser binary found for Sport Matches PDF.');
    }
}
