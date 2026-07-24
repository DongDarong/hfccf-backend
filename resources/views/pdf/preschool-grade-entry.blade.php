@php
    $khmerMonths = [
        1 => 'មករា',
        2 => 'កុម្ភៈ',
        3 => 'មីនា',
        4 => 'មេសា',
        5 => 'ឧសភា',
        6 => 'មិថុនា',
        7 => 'កក្កដា',
        8 => 'សីហា',
        9 => 'កញ្ញា',
        10 => 'តុលា',
        11 => 'វិច្ឆិកា',
        12 => 'ធ្នូ',
    ];

    $genderLabel = static function ($value): string {
        return match (strtolower((string) $value)) {
            'male' => 'ប្រុស',
            'female' => 'ស្រី',
            'other' => 'ផ្សេងៗ',
            default => trim((string) $value) !== '' ? (string) $value : '—',
        };
    };

    $formatValue = static fn ($value): string => trim((string) ($value ?? '')) !== '' ? (string) $value : '—';
    $formatScore = static fn ($value): string => $value === null || $value === '' ? '—' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp
<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <title>បញ្ជីពិន្ទុសិស្សប្រចាំខែ</title>
    <style>
        @font-face {
            font-family: 'Noto Sans Khmer';
            src: url('{{ $fontRegularPath }}') format('truetype');
            font-weight: 400;
        }

        @font-face {
            font-family: 'Noto Sans Khmer';
            src: url('{{ $fontBoldPath }}') format('truetype');
            font-weight: 700;
        }

        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 277mm;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            color: #111111;
            font-family: 'Noto Sans Khmer', sans-serif;
            font-size: 10pt;
            line-height: 1.45;
        }

        .national-header {
            text-align: center;
            font-weight: 700;
            font-size: 14pt;
            line-height: 1.5;
            margin-bottom: 2mm;
        }

        .top-row {
            display: grid;
            grid-template-columns: 58mm 1fr 58mm;
            align-items: start;
            margin-bottom: 2mm;
        }

        .organization-block {
            text-align: center;
            font-size: 9pt;
        }

        .logo-box {
            width: 42mm;
            height: 24mm;
            margin: 1mm auto 0;
        }

        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .document-title {
            text-align: center;
            font-weight: 700;
            font-size: 15pt;
            line-height: 1.6;
            padding-top: 12mm;
        }

        .metadata {
            display: flex;
            justify-content: center;
            gap: 12mm;
            margin: 2mm 0 4mm;
            font-size: 10pt;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 0.35mm solid #111111;
            padding: 1.2mm 1.4mm;
            vertical-align: middle;
            word-break: break-word;
        }

        th {
            text-align: center;
            font-weight: 700;
            background: #ffffff;
        }

        .number-cell,
        .gender-cell,
        .date-cell,
        .score-cell,
        .rating-cell {
            text-align: center;
        }

        .footer {
            margin-top: auto;
            padding-top: 12mm;
            padding-right: 6mm;
            text-align: right;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .formal-signature-table {
            width: 122mm;
            margin-left: auto;
            border: 0;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11.5pt;
            line-height: 1.75;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .formal-signature-table td {
            border: 0;
            padding: 0;
            vertical-align: middle;
            word-break: normal;
            white-space: nowrap;
        }

        .col-label-1 {
            width: 25mm;
        }

        .col-space-1 {
            width: 14mm;
        }

        .col-label-2 {
            width: 8mm;
        }

        .col-space-2 {
            width: 14mm;
        }

        .col-label-3 {
            width: 10mm;
        }

        .col-space-3 {
            width: 14mm;
        }

        .col-buddhist-label {
            width: 37mm;
        }

        .label-cell,
        .location-label {
            text-align: right;
        }

        .blank-cell {
            text-align: left;
        }

        .buddhist-cell {
            text-align: right;
        }

        .signature-label-cell {
            padding-top: 5mm !important;
            text-align: center;
            font-weight: 700;
        }

        .signature-writing-cell {
            height: 24mm;
        }
    </style>
</head>
<body>
    <div class="national-header">
        <div>ព្រះរាជាណាចក្រកម្ពុជា</div>
        <div>ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
    </div>

    <div class="top-row">
        <div class="organization-block">
            <div class="logo-box">
                @if (! empty($organization['logo_data_uri']))
                    <img src="{{ $organization['logo_data_uri'] }}" alt="">
                @endif
            </div>
        </div>
        <div class="document-title">បញ្ជីពិន្ទុសិស្សប្រចាំខែ</div>
        <div></div>
    </div>

    <div class="metadata">
        <div>ថ្នាក់៖ {{ $class?->name ?? '—' }}</div>
        <div>ឆ្នាំសិក្សា៖ {{ $academicYear?->label ?? $academicYear?->code ?? '—' }}</div>
        <div>ខែ៖ {{ $khmerMonths[(int) $month] ?? $month }} ឆ្នាំ៖ {{ $year }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 9mm;">ល.រ</th>
                <th style="width: 26mm;">អត្តលេខសិស្ស</th>
                <th>គោត្តនាម-នាម</th>
                <th style="width: 16mm;">ភេទ</th>
                <th style="width: 30mm;">ថ្ងៃខែឆ្នាំកំណើត</th>
                <th style="width: 34mm;">ថ្នាក់</th>
                <th style="width: 18mm;">ពិន្ទុ</th>
                <th style="width: 18mm;">និទ្ទេស</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $row)
                <tr>
                    <td class="number-cell">{{ $row['number'] }}</td>
                    <td>{{ $formatValue($row['student_code']) }}</td>
                    <td>{{ $formatValue($row['student_name']) }}</td>
                    <td class="gender-cell">{{ $genderLabel($row['gender']) }}</td>
                    <td class="date-cell">{{ $formatValue($row['date_of_birth']) }}</td>
                    <td>{{ $formatValue($row['class_name'] ?? $class?->name) }}</td>
                    <td class="score-cell">{{ $formatScore($row['score']) }}</td>
                    <td class="rating-cell">{{ $formatValue($row['rating']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center;">មិនមានទិន្នន័យសិស្ស</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <table class="formal-signature-table">
            <colgroup>
                <col class="col-label-1">
                <col class="col-space-1">
                <col class="col-label-2">
                <col class="col-space-2">
                <col class="col-label-3">
                <col class="col-space-3">
                <col class="col-buddhist-label">
            </colgroup>
            <tbody>
                <tr>
                    <td class="label-cell">ថ្ងៃ</td>
                    <td class="blank-cell"></td>
                    <td class="label-cell">ខែ</td>
                    <td class="blank-cell"></td>

                    <td class="label-cell">ឆ្នាំ</td>
                    <td class="blank-cell"></td>
                    <td class="buddhist-cell">ពុទ្ធសករាជ ២៥៧</td>
                </tr>
                <tr>
                    <td class="blank-cell"></td>
                    <td class="location-label" colspan="2">បាត់ដំបង ថ្ងៃទី</td>
                    <td class="blank-cell"></td>
                    <td class="label-cell">ខែ</td>

                    <td class="label-cell">ឆ្នាំ</td>
                    <td class="blank-cell"></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="8" class="signature-label-cell">ហត្ថលេខា និងឈ្មោះ</td>
                </tr>
                <tr>
                    <td colspan="8" class="signature-writing-cell"></td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
