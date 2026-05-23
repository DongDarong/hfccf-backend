<?php

namespace App\Services;

use App\Models\AssessmentPrintTemplate;
use App\Models\AssessmentSubmission;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssessmentPrintRenderService
{
    public function buildContext(AssessmentSubmission $submission, AssessmentPrintTemplate $template): array
    {
        $submission->loadMissing([
            'template.sections.questions.options',
            'template.sections.questions.matrixRows',
            'student',
            'answers.question.options',
            'answers.question.section',
            'scores.riskLevel',
            'riskLevel',
            'approver',
            'reviewer',
            'assessor',
        ]);

        $sectionScores = [];
        foreach ($submission->scores as $score) {
            $scopeKey = $score->scope === 'section' && $score->scope_id ? (string) $score->scope_id : $score->scope;
            $sectionScores[$scopeKey] = [
                'raw_score'      => (float) $score->raw_score,
                'weighted_score' => (float) $score->weighted_score,
                'max_score'      => (float) $score->max_score,
                'percentage'     => (float) $score->percentage,
                'risk_level'     => $score->riskLevel?->label,
            ];
        }

        $answerRows = $submission->answers
            ->sortBy(fn ($answer) => [$answer->question?->section?->sort_order ?? 0, $answer->question?->sort_order ?? 0, $answer->repeat_index ?? 0])
            ->values();

        $answersByQuestionId = [];
        $answersByCode = [];

        foreach ($answerRows as $answer) {
            $rendered = $this->renderAnswerValue($answer->answer_value);
            $answersByQuestionId[(string) $answer->question_id] = $rendered;
            if ($answer->question_code) {
                $answersByCode[(string) $answer->question_code] = $rendered;
            }
        }

        $studentName = trim(implode(' ', array_filter([
            $submission->student?->first_name,
            $submission->student?->last_name,
        ])));

        $riskLevel = $submission->riskLevel?->label
            ?? $submission->scores->first()?->riskLevel?->label
            ?? 'Unknown';

        $score = $submission->scores->first();

        return [
            'generated_at' => now()->toIso8601String(),
            'template' => [
                'id'          => $template->id,
                'uuid'        => $template->uuid,
                'name'        => $template->name,
                'name_kh'     => $template->name_kh,
                'format'      => $template->format,
                'page_size'   => $template->page_size,
                'orientation' => $template->orientation,
                'font_family' => $template->font_family,
                'font_size'   => $template->font_size,
                'margin_top'  => (int) $template->margin_top,
                'margin_right'=> (int) $template->margin_right,
                'margin_bottom'=> (int) $template->margin_bottom,
                'margin_left' => (int) $template->margin_left,
                'show_logo'   => (bool) $template->show_logo,
                'show_qr_code'=> (bool) $template->show_qr_code,
                'show_watermark' => (bool) $template->show_watermark,
                'watermark_text' => $template->watermark_text,
                'header_html' => $template->header_html,
                'footer_html' => $template->footer_html,
                'styles'      => $template->styles,
            ],
            'submission' => [
                'id'            => $submission->id,
                'uuid'          => $submission->uuid,
                'status'        => $submission->status,
                'submitted_at'  => $submission->submitted_at?->toIso8601String(),
                'reviewed_at'   => $submission->reviewed_at?->toIso8601String(),
                'approved_at'   => $submission->approved_at?->toIso8601String(),
                'rejected_at'   => $submission->rejected_at?->toIso8601String(),
                'completed_at'  => $submission->completed_at?->toIso8601String(),
            ],
            'student' => [
                'id'             => $submission->student?->id,
                'student_code'   => $submission->student?->student_code,
                'full_name'      => $studentName,
                'first_name'     => $submission->student?->first_name,
                'last_name'      => $submission->student?->last_name,
                'gender'         => $submission->student?->gender,
                'date_of_birth'  => $submission->student?->date_of_birth?->toDateString(),
                'guardian_name'  => $submission->student?->guardian_name,
                'guardian_phone' => $submission->student?->guardian_phone,
                'address'        => $submission->student?->address,
                'avatar'         => $submission->student?->avatar,
            ],
            'assessment' => [
                'form_name'   => $submission->template?->name,
                'form_name_kh'=> $submission->template?->name_kh,
                'code'        => $submission->template?->code,
                'module'      => $submission->template?->module,
                'version'     => $submission->version?->version_number,
            ],
            'scores' => [
                'total_score'  => (float) ($submission->total_score ?? $score?->raw_score ?? 0),
                'max_score'    => (float) ($submission->max_score ?? $score?->max_score ?? 0),
                'percentage'   => (float) ($submission->score_percent ?? $score?->percentage ?? 0),
                'risk_level'   => $riskLevel,
                'risk_color'   => $submission->riskLevel?->color_code ?? $score?->riskLevel?->color_code,
                'section_scores'=> $sectionScores,
                'manual_override' => (bool) $submission->risk_override,
                'risk_note'    => $submission->risk_note,
            ],
            'staff' => [
                'assessor' => $submission->assessor?->name ?? $submission->assessor?->full_name ?? $submission->assessor?->username,
                'reviewer' => $submission->reviewer?->name ?? $submission->reviewer?->full_name ?? $submission->reviewer?->username,
                'approver' => $submission->approver?->name ?? $submission->approver?->full_name ?? $submission->approver?->username,
            ],
            'answers' => [
                'rows'             => $answerRows->map(fn ($answer) => [
                    'id'           => $answer->id,
                    'question_id'   => $answer->question_id,
                    'question_code' => $answer->question_code,
                    'section'       => $answer->question?->section?->title,
                    'section_kh'    => $answer->question?->section?->title_kh,
                    'question'      => $answer->question?->label ?? $answer->question?->question_text,
                    'question_kh'   => $answer->question?->label_kh,
                    'answer'        => $this->renderAnswerValue($answer->answer_value),
                    'score_value'   => $answer->score_value,
                    'repeat_index'  => $answer->repeat_index,
                    'print_visible' => (bool) ($answer->question?->print_visible ?? true),
                ])->values()->all(),
                'by_question_id'   => $answersByQuestionId,
                'by_code'          => $answersByCode,
            ],
        ];
    }

    public function renderHtml(AssessmentSubmission $submission, AssessmentPrintTemplate $template): string
    {
        $context = $this->buildContext($submission, $template);
        return $this->renderDocument($template, $context);
    }

    /**
     * Render a preview document from unsaved template data and optional
     * preview values. This keeps the designer aligned with the same rendering
     * rules used by persisted print templates.
     *
     * @return array{html:string,context:array,template:array}
     */
    public function renderPreviewHtml(array $templateData, array $previewData = []): array
    {
        $template = $this->templateFromArray($templateData);
        $context = $this->buildPreviewContext($template, $previewData);

        return [
            'html' => $this->renderDocument($template, $context),
            'context' => $context,
            'template' => $this->templateSnapshot($template),
        ];
    }

    /**
     * Build a preview context for controllers or tests that need the rendered
     * HTML alongside the contextual data.
     */
    public function buildPreviewData(array $templateData, array $previewData = []): array
    {
        return $this->renderPreviewHtml($templateData, $previewData);
    }

    public function renderArtifact(AssessmentSubmission $submission, AssessmentPrintTemplate $template): array
    {
        $html = $this->renderHtml($submission, $template);
        $basePath = 'assessment-prints/'.$submission->uuid.'/'.($template->uuid ?? Str::uuid()->toString());
        $filename = Str::slug($template->name ?: 'assessment-print');

        return match ($template->format) {
            'html' => [
                'path' => $basePath.'/'.$filename.'.html',
                'content' => $html,
                'mime' => 'text/html; charset=UTF-8',
                'title' => $template->name,
            ],
            'excel' => [
                'path' => $basePath.'/'.$filename.'.xlsx',
                'content' => $this->buildXlsx($submission, $template, $html),
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'title' => $template->name,
            ],
            default => [
                'path' => $basePath.'/'.$filename.'.pdf',
                'content' => $this->buildPdf($html),
                'mime' => 'application/pdf',
                'title' => $template->name,
            ],
        };
    }

    private function renderDocument(AssessmentPrintTemplate $template, array $context): string
    {
        $blocks = $this->normalizeBlocks($template->blocks, $context);
        $styles = $this->renderStyles($template, $context);
        $body = '';

        foreach ($blocks as $block) {
            $body .= $this->renderBlock($block, $context);
        }

        $header = $this->sanitizeHtml($this->replacePlaceholders((string) ($template->header_html ?? ''), $context));
        $footer = $this->sanitizeHtml($this->replacePlaceholders((string) ($template->footer_html ?? ''), $context));

        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
            .'<title>'.e($template->name).'</title>'
            .$styles
            .'</head><body>'
            .'<div class="assessment-print-page">'
            .$header
            .$body
            .$footer
            .'</div>'
            .'</body></html>';
    }

    private function templateFromArray(array $templateData): AssessmentPrintTemplate
    {
        $template = new AssessmentPrintTemplate();
        $template->forceFill($templateData);

        return $template;
    }

    private function templateSnapshot(AssessmentPrintTemplate $template): array
    {
        return [
            'id'             => $template->id,
            'uuid'           => $template->uuid,
            'form_template_id' => $template->form_template_id,
            'name'           => $template->name,
            'name_kh'        => $template->name_kh,
            'format'         => $template->format,
            'page_size'      => $template->page_size,
            'orientation'    => $template->orientation,
            'margin_top'     => (int) $template->margin_top,
            'margin_right'   => (int) $template->margin_right,
            'margin_bottom'  => (int) $template->margin_bottom,
            'margin_left'    => (int) $template->margin_left,
            'font_family'    => $template->font_family,
            'font_size'      => $template->font_size,
            'header_html'    => $template->header_html,
            'footer_html'    => $template->footer_html,
            'watermark_text' => $template->watermark_text,
            'show_logo'      => (bool) $template->show_logo,
            'logo_path'      => $template->logo_path,
            'show_qr_code'   => (bool) $template->show_qr_code,
            'show_watermark' => (bool) $template->show_watermark,
            'blocks'         => $template->blocks,
            'styles'         => $template->styles,
            'is_default'     => (bool) $template->is_default,
            'status'         => $template->status,
        ];
    }

    private function buildPreviewContext(AssessmentPrintTemplate $template, array $previewData): array
    {
        $studentName = trim((string) ($previewData['student_name'] ?? ''));
        if ($studentName === '') {
            $studentName = trim(implode(' ', array_filter([
                (string) ($previewData['first_name'] ?? ''),
                (string) ($previewData['last_name'] ?? ''),
            ])));
        }
        $sectionScore = (float) ($previewData['section_score'] ?? 0);
        $totalScore = (float) ($previewData['total_score'] ?? 0);
        $riskLevel = (string) ($previewData['risk_level'] ?? 'Unknown');
        $assessmentDate = (string) ($previewData['assessment_date'] ?? now()->toDateString());
        $generatedAt = (string) ($previewData['generated_at'] ?? now()->toIso8601String());

        $answerRows = collect($previewData['answers'] ?? [])->values()->map(function ($row, $index) {
            return [
                'id' => $index + 1,
                'question_id' => $row['question_id'] ?? null,
                'question_code' => $row['question_code'] ?? null,
                'section' => $row['section'] ?? null,
                'section_kh' => $row['section_kh'] ?? null,
                'question' => $row['question'] ?? null,
                'question_kh' => $row['question_kh'] ?? null,
                'answer' => $row['answer'] ?? '',
                'score_value' => $row['score_value'] ?? null,
                'repeat_index' => $row['repeat_index'] ?? 0,
                'print_visible' => (bool) ($row['print_visible'] ?? true),
            ];
        })->all();

        return [
            'generated_at' => $generatedAt,
            'template' => [
                'id' => $template->id,
                'uuid' => $template->uuid,
                'name' => $template->name,
                'name_kh' => $template->name_kh,
                'format' => $template->format,
                'page_size' => $template->page_size,
                'orientation' => $template->orientation,
                'font_family' => $template->font_family,
                'font_size' => $template->font_size,
                'margin_top' => (int) $template->margin_top,
                'margin_right' => (int) $template->margin_right,
                'margin_bottom' => (int) $template->margin_bottom,
                'margin_left' => (int) $template->margin_left,
                'show_logo' => (bool) $template->show_logo,
                'show_qr_code' => (bool) $template->show_qr_code,
                'show_watermark' => (bool) $template->show_watermark,
                'watermark_text' => $template->watermark_text,
                'header_html' => $template->header_html,
                'footer_html' => $template->footer_html,
                'styles' => $template->styles,
                'logo_path' => $template->logo_path,
            ],
            'submission' => [
                'id' => $previewData['submission_id'] ?? null,
                'uuid' => $previewData['submission_uuid'] ?? null,
                'status' => $previewData['status'] ?? 'draft',
                'submitted_at' => $assessmentDate,
                'reviewed_at' => null,
                'approved_at' => null,
                'rejected_at' => null,
                'completed_at' => null,
            ],
            'student' => [
                'id' => $previewData['student_id'] ?? null,
                'student_code' => (string) ($previewData['student_code'] ?? ''),
                'full_name' => $studentName,
                'first_name' => (string) ($previewData['first_name'] ?? ''),
                'last_name' => (string) ($previewData['last_name'] ?? ''),
                'gender' => (string) ($previewData['gender'] ?? ''),
                'date_of_birth' => (string) ($previewData['date_of_birth'] ?? ''),
                'guardian_name' => (string) ($previewData['guardian_name'] ?? ''),
                'guardian_phone' => (string) ($previewData['guardian_phone'] ?? ''),
                'address' => (string) ($previewData['address'] ?? ''),
                'school' => (string) ($previewData['school'] ?? ''),
                'grade' => (string) ($previewData['grade'] ?? ''),
                'avatar' => $previewData['avatar'] ?? null,
            ],
            'assessment' => [
                'form_name' => (string) ($previewData['form_name'] ?? $template->name ?? ''),
                'form_name_kh' => (string) ($previewData['form_name_kh'] ?? $template->name_kh ?? ''),
                'code' => (string) ($previewData['form_code'] ?? ''),
                'module' => (string) ($previewData['module'] ?? ''),
                'version' => $previewData['version'] ?? null,
            ],
            'scores' => [
                'total_score' => $totalScore,
                'max_score' => (float) ($previewData['max_score'] ?? 100),
                'percentage' => (float) ($previewData['percentage'] ?? 0),
                'risk_level' => $riskLevel,
                'risk_color' => (string) ($previewData['risk_color'] ?? '#475569'),
                'section_score' => $sectionScore,
                'section_scores' => [
                    'section_score' => $sectionScore,
                ],
                'manual_override' => (bool) ($previewData['manual_override'] ?? false),
                'risk_note' => (string) ($previewData['risk_note'] ?? ''),
            ],
            'staff' => [
                'assessor' => (string) ($previewData['assessor'] ?? ''),
                'reviewer' => (string) ($previewData['reviewer'] ?? ''),
                'approver' => (string) ($previewData['approver'] ?? ''),
            ],
            'answers' => [
                'rows' => $answerRows,
                'by_question_id' => [],
                'by_code' => [],
            ],
        ];
    }

    private function normalizeBlocks(mixed $blocks, array $context): array
    {
        if (! is_array($blocks) || $blocks === []) {
            return $this->defaultBlocks($context);
        }

        return array_values(array_map(function ($block) {
            if (! is_array($block)) {
                return [
                    'type' => 'custom_html',
                    'content' => (string) $block,
                ];
            }

            if (empty($block['type'])) {
                $block['type'] = 'custom_html';
            }

            return $block;
        }, $blocks));
    }

    private function defaultBlocks(array $context): array
    {
        return [
            ['type' => 'header'],
            ['type' => 'student_info'],
            ['type' => 'answers_table'],
            ['type' => 'score_summary'],
            ['type' => 'risk_badge'],
            ['type' => 'signature_box'],
            ['type' => 'footer'],
        ];
    }

    private function renderBlock(array $block, array $context): string
    {
        $type = strtolower((string) ($block['type'] ?? 'custom_html'));
        $style = $this->styleString((string) Arr::get($block, 'style', ''));
        $title = $this->replacePlaceholders((string) Arr::get($block, 'title', ''), $context);

        return match ($type) {
            'page_break' => '<div class="page-break"></div>',
            'header' => $this->renderHeaderBlock($block, $context),
            'student_info' => $this->renderStudentInfoBlock($block, $context),
            'answers_table' => $this->renderAnswersTableBlock($block, $context),
            'score_summary' => $this->renderScoreSummaryBlock($block, $context),
            'risk_badge' => $this->renderRiskBadgeBlock($block, $context),
            'signature_box' => $this->renderSignatureBlock($block, $context),
            'footer' => $this->renderFooterBlock($block, $context),
            'custom_html' => '<section class="print-block custom-html" style="'.$style.'">'.$this->sanitizeHtml($this->replacePlaceholders((string) Arr::get($block, 'content', ''), $context)).'</section>',
            default => '<section class="print-block" style="'.$style.'">'.e($title).'</section>',
        };
    }

    private function renderHeaderBlock(array $block, array $context): string
    {
        $logo = '';
        if (! empty($context['template']['show_logo']) && ! empty($context['template']['logo_path'])) {
            $logo = $this->renderLogo($context['template']['logo_path']);
        }

        $title = $this->replacePlaceholders((string) Arr::get($block, 'title', $context['assessment']['form_name']), $context);
        $subtitle = trim(implode(' · ', array_filter([
            $context['assessment']['form_name_kh'] ?? null,
            $context['assessment']['code'] ?? null,
            $context['student']['student_code'] ?? null,
        ])));

        return '<section class="print-block print-header">'
            .($logo ? '<div class="print-logo">'.$logo.'</div>' : '')
            .'<div class="print-header-text">'
            .'<h1>'.e($title).'</h1>'
            .($subtitle !== '' ? '<p>'.e($subtitle).'</p>' : '')
            .'</div>'
            .'</section>';
    }

    private function renderStudentInfoBlock(array $block, array $context): string
    {
        $fields = Arr::get($block, 'fields', [
            ['label' => 'Student Name', 'value' => '{{student_name}}'],
            ['label' => 'Gender', 'value' => '{{gender}}'],
            ['label' => 'Date of Birth', 'value' => '{{date_of_birth}}'],
            ['label' => 'Guardian', 'value' => '{{guardian_name}}'],
            ['label' => 'Phone', 'value' => '{{guardian_phone}}'],
            ['label' => 'Address', 'value' => '{{address}}'],
        ]);

        $rows = '';
        foreach ((array) $fields as $field) {
            $label = e((string) Arr::get($field, 'label', ''));
            $value = e($this->replacePlaceholders((string) Arr::get($field, 'value', ''), $context));
            $rows .= '<tr><th>'.$label.'</th><td>'.$value.'</td></tr>';
        }

        return '<section class="print-block print-student-info">'
            .'<h2>'.e($this->replacePlaceholders((string) Arr::get($block, 'title', 'Student Information'), $context)).'</h2>'
            .'<table class="print-table">'.$rows.'</table>'
            .'</section>';
    }

    private function renderAnswersTableBlock(array $block, array $context): string
    {
        $rows = '';
        foreach ($context['answers']['rows'] as $answer) {
            if (! ($answer['print_visible'] ?? true)) {
                continue;
            }

            $rows .= '<tr>'
                .'<td>'.e((string) ($answer['section_kh'] ?? $answer['section'] ?? '-')).'</td>'
                .'<td>'.e((string) ($answer['question_kh'] ?? $answer['question'] ?? '-')).'</td>'
                .'<td>'.e((string) ($answer['answer'] ?? '-')).'</td>'
                .'<td class="text-right">'.e((string) ($answer['score_value'] ?? '-')).'</td>'
                .'</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="4" class="muted">No answer data available.</td></tr>';
        }

        return '<section class="print-block print-answers">'
            .'<h2>'.e($this->replacePlaceholders((string) Arr::get($block, 'title', 'Assessment Answers'), $context)).'</h2>'
            .'<table class="print-table">'
            .'<thead><tr><th>Section</th><th>Question</th><th>Answer</th><th>Score</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table>'
            .'</section>';
    }

    private function renderScoreSummaryBlock(array $block, array $context): string
    {
        $items = [
            ['label' => 'Total Score', 'value' => $context['scores']['total_score']],
            ['label' => 'Max Score', 'value' => $context['scores']['max_score']],
            ['label' => 'Percentage', 'value' => $context['scores']['percentage'].'%'],
            ['label' => 'Risk Level', 'value' => $context['scores']['risk_level']],
        ];

        $html = '';
        foreach ($items as $item) {
            $html .= '<div class="score-card"><span>'.e($item['label']).'</span><strong>'.e((string) $item['value']).'</strong></div>';
        }

        return '<section class="print-block print-score-summary">'
            .'<h2>'.e($this->replacePlaceholders((string) Arr::get($block, 'title', 'Score Summary'), $context)).'</h2>'
            .'<div class="score-grid">'.$html.'</div>'
            .'</section>';
    }

    private function renderRiskBadgeBlock(array $block, array $context): string
    {
        $color = $context['scores']['risk_color'] ?: '#4b5563';
        $label = $context['scores']['risk_level'] ?: 'Unknown';

        return '<section class="print-block print-risk-badge">'
            .'<div class="risk-badge" style="border-color:'.e($color).';color:'.e($color).';">'
            .e($label)
            .'</div>'
            .'</section>';
    }

    private function renderSignatureBlock(array $block, array $context): string
    {
        $labels = Arr::get($block, 'labels', [
            ['label' => 'Prepared by', 'value' => '{{assessor}}'],
            ['label' => 'Reviewed by', 'value' => '{{reviewer}}'],
            ['label' => 'Approved by', 'value' => '{{approver}}'],
        ]);

        $items = '';
        foreach ((array) $labels as $item) {
            $items .= '<div class="signature-line"><span>'.e((string) Arr::get($item, 'label', '')).'</span><strong>'.e($this->replacePlaceholders((string) Arr::get($item, 'value', ''), $context)).'</strong></div>';
        }

        return '<section class="print-block print-signatures">'
            .'<h2>'.e($this->replacePlaceholders((string) Arr::get($block, 'title', 'Approvals & Signatures'), $context)).'</h2>'
            .'<div class="signature-grid">'.$items.'</div>'
            .'</section>';
    }

    private function renderFooterBlock(array $block, array $context): string
    {
        $content = Arr::get($block, 'content', 'Generated at {{generated_at}}');
        return '<section class="print-block print-footer">'. $this->sanitizeHtml($this->replacePlaceholders((string) $content, $context)).'</section>';
    }

    private function renderStyles(AssessmentPrintTemplate $template, array $context): string
    {
        $pageWidth = $template->orientation === 'landscape' ? '297mm' : '210mm';
        $pageHeight = $template->orientation === 'landscape' ? '210mm' : '297mm';
        $marginTop = (int) $template->margin_top.'mm';
        $marginRight = (int) $template->margin_right.'mm';
        $marginBottom = (int) $template->margin_bottom.'mm';
        $marginLeft = (int) $template->margin_left.'mm';
        $fontFamily = $template->font_family ?: 'Khmer OS, Khmer, Arial, sans-serif';
        $fontSize = max(8, (int) $template->font_size);
        $customStyles = trim((string) ($template->styles ?? ''));

        return '<style>'
            .'@page { size: '.$pageWidth.' '.$pageHeight.'; margin: '.$marginTop.' '.$marginRight.' '.$marginBottom.' '.$marginLeft.'; }'
            .'body { font-family: '.e($fontFamily).'; font-size: '.$fontSize.'pt; color: #111827; background: #f8fafc; }'
            .'.assessment-print-page { max-width: '.$pageWidth.'; margin: 0 auto; background: #fff; padding: 0; }'
            .'.print-block { margin-bottom: 16px; break-inside: avoid; page-break-inside: avoid; }'
            .'.print-header { display: flex; align-items: center; gap: 16px; border-bottom: 2px solid #111827; padding-bottom: 12px; }'
            .'.print-logo img { max-height: 72px; max-width: 180px; object-fit: contain; }'
            .'.print-header-text h1, .print-block h2 { margin: 0 0 6px; font-size: 18pt; }'
            .'.print-header-text p { margin: 0; color: #475569; }'
            .'.print-table { width: 100%; border-collapse: collapse; }'
            .'.print-table th, .print-table td { border: 1px solid #cbd5e1; padding: 8px 10px; vertical-align: top; }'
            .'.print-table th { width: 24%; background: #f1f5f9; text-align: left; }'
            .'.print-table .text-right { text-align: right; }'
            .'.score-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }'
            .'.score-card { border: 1px solid #cbd5e1; border-radius: 10px; padding: 12px; background: #f8fafc; }'
            .'.score-card span { display: block; font-size: 10pt; color: #64748b; margin-bottom: 4px; }'
            .'.score-card strong { font-size: 18pt; }'
            .'.risk-badge { display: inline-flex; padding: 10px 16px; border: 2px solid #475569; border-radius: 999px; font-weight: 700; font-size: 12pt; }'
            .'.signature-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }'
            .'.signature-line { min-height: 72px; border-bottom: 1px solid #94a3b8; padding: 8px 0; display: flex; flex-direction: column; justify-content: flex-end; }'
            .'.signature-line span { color: #64748b; font-size: 10pt; }'
            .'.page-break { page-break-after: always; break-after: page; height: 0; }'
            .'.print-footer { border-top: 1px solid #e2e8f0; padding-top: 10px; color: #64748b; font-size: 10pt; }'
            .'.muted { color: #64748b; }'
            .($customStyles !== '' ? $customStyles : '')
            .'</style>';
    }

    private function replacePlaceholders(string $value, array $context): string
    {
        $placeholders = [
            '{{student_name}}' => $context['student']['full_name'] ?? '',
            '{{student_code}}' => $context['student']['student_code'] ?? '',
            '{{gender}}' => $context['student']['gender'] ?? '',
            '{{date_of_birth}}' => $context['student']['date_of_birth'] ?? '',
            '{{guardian_name}}' => $context['student']['guardian_name'] ?? '',
            '{{guardian_phone}}' => $context['student']['guardian_phone'] ?? '',
            '{{address}}' => $context['student']['address'] ?? '',
            '{{school}}' => $context['student']['school'] ?? '',
            '{{grade}}' => $context['student']['grade'] ?? '',
            '{{section_score}}' => (string) ($context['scores']['section_score'] ?? $context['scores']['section_scores']['section_score'] ?? ''),
            '{{total_score}}' => (string) ($context['scores']['total_score'] ?? ''),
            '{{risk_level}}' => (string) ($context['scores']['risk_level'] ?? ''),
            '{{assessment_date}}' => $context['submission']['submitted_at'] ?? $context['generated_at'],
            '{{generated_at}}' => $context['generated_at'] ?? '',
            '{{assessor}}' => $context['staff']['assessor'] ?? '',
            '{{reviewer}}' => $context['staff']['reviewer'] ?? '',
            '{{approver}}' => $context['staff']['approver'] ?? '',
            '{{form_name}}' => $context['assessment']['form_name'] ?? '',
            '{{form_name_kh}}' => $context['assessment']['form_name_kh'] ?? '',
            '{{template_name}}' => $context['template']['name'] ?? '',
        ];

        return strtr($value, $placeholders);
    }

    private function renderAnswerValue(mixed $value): string
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    $parts[] = implode(': ', array_filter([
                        (string) ($item['label'] ?? $item['name'] ?? ''),
                        (string) ($item['value'] ?? $item['score'] ?? ''),
                    ]));
                    continue;
                }
                $parts[] = (string) $item;
            }

            return trim(implode(', ', array_filter($parts, static fn ($item) => $item !== '')));
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    }

    private function sanitizeHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/is', '', $html) ?? $html;

        return $html;
    }

    private function styleString(string $style): string
    {
        $style = trim($style);
        if ($style === '') {
            return '';
        }

        return rtrim($style, ';').';';
    }

    private function renderLogo(string $path): string
    {
        if (preg_match('/^https?:\/\//i', $path)) {
            return '<img src="'.e($path).'" alt="Logo">';
        }

        $normalized = ltrim($path, '/\\');
        $diskPath = Storage::disk('local')->path($normalized);
        if (! file_exists($diskPath)) {
            return '';
        }

        $mime = function_exists('mime_content_type') ? mime_content_type($diskPath) : 'application/octet-stream';
        $content = file_get_contents($diskPath);
        if ($content === false) {
            return '';
        }

        return '<img src="data:'.e($mime).';base64,'.base64_encode($content).'" alt="Logo">';
    }

    private function buildXlsx(AssessmentSubmission $submission, AssessmentPrintTemplate $template, string $html): string
    {
        $context = $this->buildContext($submission, $template);
        $rows = [
            ['Section', 'Field', 'Value'],
            ['Template', 'Name', (string) ($context['template']['name'] ?? '')],
            ['Student', 'Name', (string) ($context['student']['full_name'] ?? '')],
            ['Student', 'Code', (string) ($context['student']['student_code'] ?? '')],
            ['Scores', 'Total', (string) ($context['scores']['total_score'] ?? '')],
            ['Scores', 'Risk Level', (string) ($context['scores']['risk_level'] ?? '')],
        ];

        foreach ($context['answers']['rows'] as $answer) {
            if (! ($answer['print_visible'] ?? true)) {
                continue;
            }

            $rows[] = [
                (string) ($answer['section'] ?? ''),
                (string) ($answer['question'] ?? ''),
                (string) ($answer['answer'] ?? ''),
            ];
        }

        $xmlRows = [];
        foreach ($rows as $index => $row) {
            $cells = [];
            foreach ($row as $columnIndex => $value) {
                $cellRef = $this->columnLetter($columnIndex + 1).($index + 1);
                $cells[] = sprintf(
                    '<c r="%s" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
                    $cellRef,
                    htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8'),
                );
            }

            $xmlRows[] = '<row r="'.($index + 1).'">'.implode('', $cells).'</row>';
        }

        return $this->buildZipArchive([
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Print" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $xmlRows).'</sheetData></worksheet>',
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        ]);
    }

    private function buildPdf(string $html): string
    {
        $binary = $this->findEdgeBinary();
        if ($binary) {
            $pdf = $this->renderPdfWithEdge($binary, $html);
            if ($pdf !== null) {
                return $pdf;
            }
        }

        return $this->fallbackPdf($html);
    }

    private function findEdgeBinary(): ?string
    {
        $override = env('ASSESSMENT_EDGE_BINARY');
        if (is_string($override) && $override !== '' && file_exists($override)) {
            return $override;
        }

        $candidates = [
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application',
            'C:\\Program Files\\Microsoft\\Edge\\Application',
        ];

        foreach ($candidates as $basePath) {
            if (file_exists($basePath.'\\msedge.exe')) {
                return $basePath.'\\msedge.exe';
            }

            $versions = glob($basePath.'\\*', GLOB_ONLYDIR) ?: [];
            rsort($versions);
            foreach ($versions as $versionPath) {
                $candidate = $versionPath.'\\msedge.exe';
                if (file_exists($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function renderPdfWithEdge(string $binary, string $html): ?string
    {
        $tmpDir = storage_path('app/assessment-prints/tmp');
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $token = Str::uuid()->toString();
        $input = $tmpDir.DIRECTORY_SEPARATOR.$token.'.html';
        $output = $tmpDir.DIRECTORY_SEPARATOR.$token.'.pdf';
        file_put_contents($input, $html);

        $command = escapeshellarg($binary)
            .' --headless --disable-gpu --no-sandbox --allow-file-access-from-files --print-to-pdf-no-header'
            .' --print-to-pdf='.escapeshellarg($output)
            .' '.escapeshellarg($input);

        $outputLines = [];
        $exitCode = 1;
        @exec($command.' 2>&1', $outputLines, $exitCode);
        $pdf = (file_exists($output) && filesize($output) > 0) ? file_get_contents($output) : null;

        @unlink($input);
        @unlink($output);

        return $exitCode === 0 && is_string($pdf) ? $pdf : null;
    }

    private function fallbackPdf(string $html): string
    {
        $plain = trim(html_entity_decode(strip_tags(preg_replace('/<\/(p|div|li|tr|h\d)>/i', "\n", $html) ?? $html), ENT_QUOTES, 'UTF-8'));
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/', $plain) ?: []), static fn ($line) => $line !== ''));
        if ($lines === []) {
            $lines = ['Assessment Print'];
        }

        return $this->createPdfFromLines($lines);
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
        $buffer .= "xref\n";
        $buffer .= '0 '.(count($objects) + 1)."\n";
        $buffer .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $buffer .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        $buffer .= "trailer\n";
        $buffer .= '<< /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\n";
        $buffer .= "startxref\n";
        $buffer .= $xref."\n";
        $buffer .= "%%EOF";

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
}
