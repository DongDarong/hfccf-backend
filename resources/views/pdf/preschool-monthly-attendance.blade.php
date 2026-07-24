<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <title>បញ្ជីវត្តមានសិស្សប្រចាំខែ</title>
    <style>
        @font-face {
            font-family: 'Noto Sans Khmer PDF';
            font-style: normal;
            font-weight: 400;
            src: url('{{ $fontRegularPath }}') format('truetype');
        }

        @font-face {
            font-family: 'Noto Sans Khmer PDF';
            font-style: normal;
            font-weight: 700;
            src: url('{{ $fontBoldPath }}') format('truetype');
        }

        @page {
            size: A4 landscape;
            margin: 8mm 8mm 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #000000;
            font-family: 'Noto Sans Khmer PDF', sans-serif;
            font-size: 9pt;
            line-height: 1.22;
            background: #ffffff;
        }

        .document {
            width: 100%;
        }

        .top-band {
            position: relative;
            min-height: 28mm;
            margin-bottom: 0.8mm;
        }

        .national-heading {
            text-align: center;
            font-weight: 700;
            line-height: 1.35;
            padding-top: 0;
        }

        .national-heading p {
            margin: 0;
        }

        .national-title {
            font-size: 13.5pt;
        }

        .national-motto {
            font-size: 12.5pt;
        }

        .organization-block {
            position: absolute;
            top: 0;
            left: 0;
            width: 48mm;
            text-align: center;
        }

        .organization-name {
            margin: 0 0 1mm;
            font-size: 8pt;
            font-weight: 700;
            line-height: 1.35;
        }

        .logo-box {
            width: 44mm;
            height: 26mm;
            margin: 0 auto;
        }

        .logo-box img {
            width: 100%;
            height: 100%;
            max-width: 44mm;
            max-height: 26mm;
            object-fit: contain;
        }

        .logo-fallback {
            width: 44mm;
            height: 26mm;
            border: 1px solid #444444;
            font-size: 12pt;
            font-weight: 700;
            line-height: 26mm;
            text-align: center;
        }

        .title-block {
            margin: 0 auto 1mm;
            text-align: center;
            font-weight: 700;
        }

        .document-title {
            margin: 0;
            font-size: 12pt;
            line-height: 1.32;
        }

        .document-subtitle {
            margin: 0;
            font-size: 10.5pt;
            line-height: 1.25;
        }

        .register-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7.8pt;
            line-height: 1.08;
            page-break-inside: auto;
        }

        .register-table thead {
            display: table-header-group;
        }

        .register-table th,
        .register-table td {
            border: 1px solid #000000;
            padding: 0.45mm 0.45mm;
            vertical-align: middle;
            background: #ffffff;
        }

        .register-table th {
            font-weight: 700;
            text-align: center;
        }

        .register-table tbody tr {
            height: 5.3mm;
            page-break-inside: avoid;
        }

        .col-no {
            width: 8mm;
            text-align: center;
        }

        .col-name {
            width: 44mm;
            text-align: left;
            word-break: break-word;
        }

        .col-gender {
            width: 12mm;
            text-align: center;
        }

        .col-dob {
            width: 23mm;
            text-align: center;
            white-space: nowrap;
        }

        .day-col {
            width: 5.4mm;
            text-align: center;
        }

        .status-mark {
            text-align: center;
            font-weight: 700;
        }

        .summary-line {
            margin-top: 0;
            font-size: 8.3pt;
            line-height: 1.22;
            white-space: nowrap;
        }

        .summary-line span {
            display: inline-block;
            margin-right: 3mm;
        }

        .legend {
            margin-top: 0.6mm;
            font-size: 8pt;
            line-height: 1.18;
        }

        .register-footer {
            margin-top: 1mm;
            display: table;
            width: 100%;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .footer-left,
        .footer-right {
            display: table-cell;
            vertical-align: top;
        }

        .footer-left {
            width: 64%;
            text-align: left;
        }

        .footer-right {
            width: 36%;
            font-size: 8.3pt;
            text-align: right;
            line-height: 1.55;
        }

        .footer-date-line {
            white-space: nowrap;
        }

        .blank-space {
            display: inline-block;
            vertical-align: baseline;
        }

        .blank-day {
            width: 12mm;
        }

        .blank-month {
            width: 12mm;
        }

        .blank-year {
            width: 13mm;
        }

        .blank-buddhist-year {
            width: 7mm;
        }

        .signature-label {
            margin-top: 1.2mm;
            font-weight: 700;
        }

        .signature-space {
            height: 6mm;
            margin-top: 4mm;
        }
    </style>
</head>
<body>
@php
    $display = static fn ($value): string => filled($value) ? (string) $value : '';
    $dateValue = static fn ($value): string => $value ? \Carbon\Carbon::parse($value)->format('Y-m-d') : '';
    $khGender = static function ($value): string {
        return match (strtolower(trim((string) $value))) {
            'male' => 'ប្រុស',
            'female' => 'ស្រី',
            'other' => 'ផ្សេងៗ',
            default => filled($value) ? (string) $value : '',
        };
    };
    $khMonth = [
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
    ][$month] ?? (string) $month;
    $visibleRows = max(20, count($students));
    $totalStudentCount = $totalStudentCount ?? count($students);
    $femaleStudentCount = $femaleStudentCount ?? collect($students)->filter(
        static fn ($row): bool => strtolower(trim((string) $row['student']->gender)) === 'female',
    )->count();
    $organizationName = trim((string) ($organization['kh_name'] ?? ''));
    $showOrganizationName = $organizationName !== '' && $organizationName !== 'អង្គការ HFCCF';
@endphp
<div class="document">
    <div class="top-band">
        <div class="organization-block">
            @if ($showOrganizationName)
                <p class="organization-name">{{ $organizationName }}</p>
            @endif
            <div class="logo-box">
                @if (! empty($organization['logo_data_uri']))
                    <img src="{{ $organization['logo_data_uri'] }}" alt="HFCCF">
                @else
                    <div class="logo-fallback">HFCCF</div>
                @endif
            </div>
        </div>

        <div class="national-heading">
            <p class="national-title">ព្រះរាជាណាចក្រកម្ពុជា</p>
            <p class="national-motto">ជាតិ សាសនា ព្រះមហាក្សត្រ</p>
        </div>
    </div>

    <div class="title-block">
        <p class="document-title">បញ្ជីវត្តមានសិស្សថ្នាក់ {{ $class->name }} ប្រចាំខែ {{ $khMonth }}</p>
        <p class="document-subtitle">ឆ្នាំសិក្សា {{ $academicYear->label }}</p>
    </div>

    <table class="register-table">
        <thead>
            <tr>
                <th class="col-no">ល.រ</th>
                <th class="col-name">គោត្តនាម-នាម</th>
                <th class="col-gender">ភេទ</th>
                <th class="col-dob">ថ្ងៃខែឆ្នាំកំណើត</th>
                @foreach ($days as $day)
                    <th class="day-col">{{ $day->format('j') }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @for ($index = 0; $index < $visibleRows; $index++)
                @php
                    $row = $students[$index] ?? null;
                    $student = $row['student'] ?? null;
                @endphp
                <tr>
                    <td class="col-no">{{ $index + 1 }}</td>
                    <td class="col-name">{{ $student ? $display(trim($student->first_name.' '.$student->last_name)) : '' }}</td>
                    <td class="col-gender">{{ $student ? $khGender($student->gender) : '' }}</td>
                    <td class="col-dob">{{ $student ? $dateValue($student->date_of_birth) : '' }}</td>
                    @foreach ($days as $dayIndex => $day)
                        <td class="day-col status-mark">{{ $row['daily'][$dayIndex] ?? '' }}</td>
                    @endforeach
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="register-footer">
        <div class="footer-left">
            <div class="summary-line">
                <span>ចំនួនសិស្សសរុប៖ {{ $totalStudentCount }} នាក់</span>
                <span>ចំនួនសិស្សស្រី៖ {{ $femaleStudentCount }} នាក់</span>
                <span>វត្តមានសរុប៖ {{ $summary['present'] }}</span>
                <span>អវត្តមានសរុប៖ {{ $summary['absent'] }}</span>
                <span>មកយឺតសរុប៖ {{ $summary['late'] }}</span>
                <span>មានច្បាប់សរុប៖ {{ $summary['excused'] }}</span>
            </div>

            <div class="legend">
                P = វត្តមាន &nbsp;&nbsp;
                A = អវត្តមាន &nbsp;&nbsp;
                L = មកយឺត &nbsp;&nbsp;
                E = មានច្បាប់
            </div>
        </div>

        <div class="footer-right">
            <div class="footer-date-line">
                ថ្ងៃ <span class="blank-space blank-day"></span>
                ខែ <span class="blank-space blank-month"></span>
                ឆ្នាំ <span class="blank-space blank-year"></span>
                ពុទ្ធសករាជ ២៥៧<span class="blank-space blank-buddhist-year"></span>
            </div>
            <div class="footer-date-line">
                បាត់ដំបង ថ្ងៃទី <span class="blank-space blank-day"></span>
                ខែ <span class="blank-space blank-month"></span>
                ឆ្នាំ <span class="blank-space blank-year"></span>
            </div>
            <div class="signature-space"></div>
        </div>
    </div>
</div>
</body>
</html>
