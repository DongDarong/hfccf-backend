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

    $statusLabel = static function ($value): string {
        return match (strtolower((string) $value)) {
            'draft' => 'ព្រាង',
            'submitted' => 'បានដាក់ស្នើ',
            'returned' => 'បានបញ្ជូនត្រឡប់',
            'finalized' => 'បានបញ្ចប់',
            'archived' => 'បានរក្សាទុក',
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
            size: A4 landscape;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
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
        .rating-cell,
        .status-cell {
            text-align: center;
        }

        .footer {
            margin-top: 4mm;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10mm;
            page-break-inside: avoid;
        }

        .generated-date {
            font-size: 9pt;
        }

        .signature {
            width: 52mm;
            text-align: center;
            font-size: 10pt;
            line-height: 1.9;
        }

        .signature-space {
            height: 14mm;
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
            <div>{{ $organization['kh_name'] ?? '' }}</div>
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
                <th style="width: 28mm;">ថ្ងៃខែឆ្នាំកំណើត</th>
                <th style="width: 32mm;">ថ្នាក់</th>
                <th style="width: 18mm;">ពិន្ទុ</th>
                <th style="width: 18mm;">និទ្ទេស</th>
                <th style="width: 25mm;">ស្ថានភាព</th>
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
                    <td class="status-cell">{{ $statusLabel($row['status']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center;">មិនមានទិន្នន័យសិស្ស</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="generated-date">
            កាលបរិច្ឆេទបង្កើត៖ {{ $generatedAt?->format('Y-m-d') }}
        </div>
        <div class="signature">
            <div>គ្រូបន្ទុកថ្នាក់</div>
            <div class="signature-space"></div>
            <div></div>
        </div>
    </div>
</body>
</html>
