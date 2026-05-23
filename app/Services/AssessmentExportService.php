<?php

namespace App\Services;

use App\Models\AssessmentExportLog;
use App\Models\AssessmentPrintTemplate;
use App\Models\AssessmentSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AssessmentExportService
{
    public function __construct(
        private AssessmentLifecycleService $lifecycle,
        private AssessmentPrintRenderService $printRenderer,
    ) {}

    public function generate(AssessmentExportLog $exportLog): AssessmentExportLog
    {
        try {
            $exportLog = $this->lifecycle->markExportProcessing($exportLog);
            $artifact = $exportLog->print_template_id
                ? $this->renderPrintArtifact($exportLog)
                : $this->renderReportArtifact($exportLog, $this->buildPayload($exportLog));

            Storage::disk('local')->put($artifact['path'], $artifact['content']);

            return $this->lifecycle->markExportCompleted(
                $exportLog,
                $artifact['path'],
                strlen($artifact['content']),
                [
                    'format' => $exportLog->export_type,
                    'title'  => $artifact['title'],
                    'rows'   => $artifact['rows'] ?? null,
                ],
            );
        } catch (\Throwable $exception) {
            $this->lifecycle->markExportFailed($exportLog, $exception->getMessage(), [
                'format' => $exportLog->export_type,
            ]);

            throw $exception;
        }
    }

    public function output(AssessmentExportLog $exportLog): string
    {
        $contents = Storage::disk('local')->get($exportLog->file_path);

        return is_string($contents) ? $contents : '';
    }

    /**
     * @return array{title: string, path: string, content: string, rows?: int}
     */
    private function renderReportArtifact(AssessmentExportLog $exportLog, array $payload): array
    {
        $basePath = 'assessment-exports/'.$exportLog->uuid;
        $title = 'Assessment Export';

        return match ($exportLog->export_type) {
            'excel' => [
                'title' => $title,
                'path' => $basePath.'/assessment-export.xlsx',
                'content' => $this->buildXlsx($payload),
                'rows' => count($payload['rows']),
            ],
            'zip' => [
                'title' => $title,
                'path' => $basePath.'/assessment-export.zip',
                'content' => $this->buildZip($exportLog, $payload),
                'rows' => count($payload['rows']),
            ],
            default => [
                'title' => $title,
                'path' => $basePath.'/assessment-export.pdf',
                'content' => $this->buildPdf($payload),
                'rows' => count($payload['rows']),
            ],
        };
    }

    private function renderPrintArtifact(AssessmentExportLog $exportLog): array
    {
        $submission = $this->resolvePrintSubmission($exportLog);
        $template = AssessmentPrintTemplate::query()->findOrFail($exportLog->print_template_id);

        $artifact = $this->printRenderer->renderArtifact($submission, $template);

        return [
            'title' => $artifact['title'] ?? $template->name,
            'path' => 'assessment-exports/'.$exportLog->uuid.'/'.basename($artifact['path']),
            'content' => $artifact['content'],
            'rows' => 1,
        ];
    }

    private function buildPayload(AssessmentExportLog $exportLog): array
    {
        $dashboard = [
            'total_assessments' => DB::table('assessment_submissions')->count(),
            'average_score' => round((float) (DB::table('assessment_submission_scores')->avg('percentage') ?? 0), 2),
            'completion_rate' => $this->completionRate(),
        ];

        $riskDistribution = DB::table('assessment_submission_scores')
            ->join('assessment_risk_levels', 'assessment_submission_scores.risk_level_id', '=', 'assessment_risk_levels.id')
            ->select('assessment_risk_levels.label as level_name', 'assessment_risk_levels.color_code as color', DB::raw('COUNT(*) as count'))
            ->groupBy('assessment_risk_levels.id', 'assessment_risk_levels.label', 'assessment_risk_levels.color_code')
            ->orderBy('assessment_risk_levels.sort_order')
            ->get()
            ->map(fn ($row) => [
                'level_name' => $row->level_name,
                'color' => $row->color,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $submissionTrend = DB::table('assessment_submissions')
            ->whereNotNull('submitted_at')
            ->selectRaw($this->monthExpression().' as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $submissions = [];

        if ($exportLog->scope !== 'report' && ! empty($exportLog->submission_ids)) {
            $submissions = AssessmentSubmission::query()
                ->with(['template', 'student', 'riskLevel', 'scores'])
                ->whereIn('id', $exportLog->submission_ids)
                ->orderBy('submitted_at', 'desc')
                ->get()
                ->map(fn (AssessmentSubmission $submission) => [
                    'id' => $submission->id,
                    'student' => $submission->student?->full_name,
                    'form' => $submission->template?->name,
                    'status' => $submission->status,
                    'submitted_at' => $submission->submitted_at?->toIso8601String(),
                    'total_score' => $submission->scores->first()?->raw_score,
                    'risk_level' => $submission->riskLevel?->label,
                ])
                ->values()
                ->all();
        }

        $rows = [
            ['Section', 'Metric', 'Value'],
            ['Dashboard', 'Total Assessments', (string) $dashboard['total_assessments']],
            ['Dashboard', 'Average Score', (string) $dashboard['average_score']],
            ['Dashboard', 'Completion Rate', $dashboard['completion_rate'].'%'],
        ];

        foreach ($riskDistribution as $row) {
            $rows[] = ['Risk Distribution', $row['level_name'] ?? '', (string) ($row['count'] ?? 0)];
        }

        foreach ($submissionTrend as $row) {
            $rows[] = ['Trend', $row['month'] ?? '', (string) ($row['count'] ?? 0)];
        }

        foreach ($submissions as $submission) {
            $rows[] = [
                'Submission',
                (string) ($submission['student'] ?? 'Student'),
                implode(' | ', array_filter([
                    'Form: '.($submission['form'] ?? '-'),
                    'Status: '.($submission['status'] ?? '-'),
                    'Score: '.($submission['total_score'] ?? '-'),
                    'Risk: '.($submission['risk_level'] ?? '-'),
                ], static fn ($value) => $value !== null && $value !== '')),
            ];
        }

        return [
            'dashboard' => $dashboard,
            'riskDistribution' => $riskDistribution,
            'submissionTrend' => $submissionTrend,
            'submissions' => $submissions,
            'rows' => $rows,
            'export' => [
                'uuid' => $exportLog->uuid,
                'type' => $exportLog->export_type,
                'scope' => $exportLog->scope,
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function resolvePrintSubmission(AssessmentExportLog $exportLog): AssessmentSubmission
    {
        $submissionId = $exportLog->submission_ids[0] ?? null;

        if (! $submissionId) {
            throw new \RuntimeException('No submission selected for print export.');
        }

        return AssessmentSubmission::query()
            ->with(['template.sections.questions.options', 'template.sections.questions.matrixRows', 'student', 'answers.question.section', 'answers.question.options', 'scores.riskLevel', 'riskLevel', 'assessor', 'reviewer', 'approver', 'version'])
            ->findOrFail($submissionId);
    }

    private function buildPdf(array $payload): string
    {
        $lines = [];
        $lines[] = 'Assessment Export';
        $lines[] = 'Generated at: '.$payload['export']['generated_at'];
        $lines[] = '';
        $lines[] = 'Dashboard';
        $lines[] = 'Total Assessments: '.$payload['dashboard']['total_assessments'];
        $lines[] = 'Average Score: '.$payload['dashboard']['average_score'];
        $lines[] = 'Completion Rate: '.$payload['dashboard']['completion_rate'].'%';
        $lines[] = '';
        $lines[] = 'Risk Distribution';

        foreach ($payload['riskDistribution'] as $row) {
            $lines[] = sprintf('%s: %s', $row['level_name'] ?? '-', $row['count'] ?? 0);
        }

        $lines[] = '';
        $lines[] = 'Submission Trend';

        foreach ($payload['submissionTrend'] as $row) {
            $lines[] = sprintf('%s: %s', $row['month'] ?? '-', $row['count'] ?? 0);
        }

        if (! empty($payload['submissions'])) {
            $lines[] = '';
            $lines[] = 'Submissions';
            foreach ($payload['submissions'] as $submission) {
                $lines[] = sprintf(
                    '#%s %s | %s | %s | %s',
                    $submission['id'],
                    $submission['student'] ?? '-',
                    $submission['form'] ?? '-',
                    $submission['status'] ?? '-',
                    $submission['risk_level'] ?? '-',
                );
            }
        }

        return $this->createPdfFromLines($lines);
    }

    private function buildXlsx(array $payload): string
    {
        $rows = $payload['rows'];
        $sheetRows = [];
        foreach ($rows as $row) {
            $sheetRows[] = $row;
        }

        $xmlRows = [];
        foreach ($sheetRows as $index => $row) {
            $cells = [];
            foreach ($row as $columnIndex => $value) {
                $cellRef = $this->columnLetter($columnIndex + 1).($index + 1);
                $value = (string) $value;
                $cells[] = sprintf(
                    '<c r="%s" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
                    $cellRef,
                    htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8'),
                );
            }

            $xmlRows[] = '<row r="'.($index + 1).'">'.implode('', $cells).'</row>';
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.implode('', $xmlRows).'</sheetData>'
            .'</worksheet>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Assessment Export" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';

        $stylesXml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            .'</styleSheet>';

        $contentTypesXml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';

        $rootRels = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';

        return $this->buildZipArchive([
            '[Content_Types].xml' => $contentTypesXml,
            '_rels/.rels' => $rootRels,
            'xl/workbook.xml' => $workbookXml,
            'xl/_rels/workbook.xml.rels' => $workbookRels,
            'xl/styles.xml' => $stylesXml,
            'xl/worksheets/sheet1.xml' => $sheetXml,
        ]);
    }

    private function buildZip(AssessmentExportLog $exportLog, array $payload): string
    {
        return $this->buildZipArchive([
            'report.json' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}',
            'report.pdf' => $this->buildPdf($payload),
            'report.xlsx' => $this->buildXlsx($payload),
            'manifest.json' => json_encode([
            'export_uuid' => $exportLog->uuid,
            'export_type' => $exportLog->export_type,
            'scope' => $exportLog->scope,
            'generated_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}',
        ]);
    }

    private function createPdfFromLines(array $lines): string
    {
        $pages = [];
        $chunks = array_chunk($lines, 42);
        foreach ($chunks as $chunk) {
            $pages[] = $this->buildPdfPage($chunk);
        }

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $objectOffset = 3;
        $fontObjectNumber = 3 + (count($pages) * 2);
        foreach ($pages as $index => $page) {
            $kids[] = ($objectOffset + $index * 2) . ' 0 R';
        }
        $objects[] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($pages).' >>';

        foreach ($pages as $index => $page) {
            $contentObject = (string) ($objectOffset + $index * 2 + 1);
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontObjectNumber} 0 R >> >> /Contents {$contentObject} 0 R >>";
            $objects[] = '<< /Length '.strlen($page).' >>'."\nstream\n{$page}\nendstream";
        }

        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $buffer = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $object) {
            $offsets[] = strlen($buffer);
            $buffer .= ($number + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($buffer);
        $buffer .= 'xref' . "\n";
        $buffer .= '0 '.(count($objects) + 1)."\n";
        $buffer .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $buffer .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        $buffer .= 'trailer' . "\n";
        $buffer .= '<< /Size '.(count($objects) + 1).' /Root 1 0 R >>' . "\n";
        $buffer .= 'startxref' . "\n";
        $buffer .= $xref . "\n";
        $buffer .= '%%EOF';

        return $buffer;
    }

    private function buildPdfPage(array $lines): string
    {
        $content = "BT\n/F1 12 Tf\n50 790 Td\n";
        $first = true;

        foreach ($lines as $line) {
            $escaped = $this->escapePdfText((string) $line);
            if ($first) {
                $content .= "({$escaped}) Tj\n";
                $first = false;
            } else {
                $content .= "T*\n({$escaped}) Tj\n";
            }
        }

        $content .= "ET";

        return $content;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function columnLetter(int $column): string
    {
        $letters = '';
        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $letters = chr(65 + $remainder).$letters;
            $column = intdiv($column - 1, 26);
        }

        return $letters;
    }

    private function monthExpression(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', submitted_at)"
            : 'DATE_FORMAT(submitted_at, "%Y-%m")';
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function buildZipArchive(array $entries): string
    {
        $files = [];
        $offset = 0;

        foreach ($entries as $name => $content) {
            $binary = (string) $content;
            $compressed = function_exists('gzdeflate') ? gzdeflate($binary, 9) : false;
            if ($compressed === false) {
                $compressed = $binary;
                $method = 0;
            } else {
                $method = 8;
            }

            $crc = sprintf('%u', crc32($binary));
            $compressedSize = strlen($compressed);
            $uncompressedSize = strlen($binary);

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                $method,
                0,
                0,
                (int) $crc,
                $compressedSize,
                $uncompressedSize,
                strlen($name),
                0,
            ).$name.$compressed;

            $files[] = [
                'name' => $name,
                'method' => $method,
                'crc' => (int) $crc,
                'compressed_size' => $compressedSize,
                'uncompressed_size' => $uncompressedSize,
                'offset' => $offset,
                'data' => $localHeader,
            ];

            $offset += strlen($localHeader);
        }

        $centralDirectory = '';
        foreach ($files as $file) {
            $centralDirectory .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                $file['method'],
                0,
                0,
                $file['crc'],
                $file['compressed_size'],
                $file['uncompressed_size'],
                strlen($file['name']),
                0,
                0,
                0,
                0,
                0,
                $file['offset'],
            ).$file['name'];
        }

        $centralDirectorySize = strlen($centralDirectory);
        $centralDirectoryOffset = $offset;

        $zip = '';
        foreach ($files as $file) {
            $zip .= $file['data'];
        }
        $zip .= $centralDirectory;
        $zip .= pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            count($files),
            count($files),
            $centralDirectorySize,
            $centralDirectoryOffset,
            0,
        );

        return $zip;
    }

    private function completionRate(): float
    {
        $total = DB::table('assessment_submissions')->count();
        if ($total === 0) {
            return 0.0;
        }

        $completed = DB::table('assessment_submissions')
            ->whereIn('status', ['approved', 'rejected'])
            ->count();

        return round(($completed / $total) * 100, 2);
    }
}
