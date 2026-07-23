<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <title>ប្រវត្តិរូបសិស្ស</title>
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
            size: A4 portrait;
            margin: 12mm 16mm 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #000000;
            font-family: 'Noto Sans Khmer PDF', sans-serif;
            font-size: 13.5pt;
            line-height: 1.62;
            background: #ffffff;
        }

        .document {
            position: relative;
            width: 100%;
            min-height: 273mm;
        }

        .national-heading {
            text-align: center;
            font-weight: 700;
            line-height: 1.45;
            margin-bottom: 17mm;
        }

        .national-heading p {
            margin: 0;
        }

        .national-title {
            font-size: 21pt;
        }

        .national-motto {
            font-size: 20pt;
        }

        .top-row {
            position: relative;
            min-height: 34mm;
            margin-bottom: 7mm;
        }

        .organization-block {
            position: absolute;
            top: 0;
            left: 0;
            width: 48mm;
            text-align: center;
        }

        .organization-name {
            margin: 0 0 3mm;
            font-size: 11.5px;
            font-weight: 700;
            line-height: 1.55;
        }

        .logo-box {
            width: 40mm;
            height: 30mm;
            margin: 0 auto;
        }

        .logo-box img {
            width: 100%;
            height: 100%;
            max-width: 40mm;
            max-height: 30mm;
            object-fit: contain;
        }

        .logo-fallback {
            width: 40mm;
            height: 30mm;
            border: 1px solid #555555;
            font-size: 14pt;
            font-weight: 700;
            line-height: 30mm;
            text-align: center;
        }

        .student-photo {
            position: absolute;
            top: 0;
            right: 0;
            width: 33mm;
            height: 42mm;
            border: 1px solid #333333;
            text-align: center;
            overflow: hidden;
        }

        .student-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .student-photo-empty {
            width: 100%;
            height: 100%;
            color: #555555;
            font-size: 10px;
            line-height: 42mm;
        }

        .profile-title {
            margin: 0;
            padding-top: 13mm;
            text-align: center;
            font-size: 19pt;
            font-weight: 700;
        }

        .section {
            margin-top: 5.5mm;
            page-break-inside: avoid;
        }

        .section-title {
            margin: 0 0 2.5mm;
            font-size: 17pt;
            font-weight: 700;
        }

        .info-lines {
            padding-left: 6mm;
        }

        .line {
            margin: 0 0 1mm;
            white-space: normal;
            word-break: break-word;
        }

        .line-group {
            margin-top: 2.2mm;
        }

        .label {
            display: inline-block;
            min-width: 36mm;
            margin-right: 2mm;
            font-size: 14pt;
            font-weight: 700;
        }

        .label-short {
            display: inline-block;
            min-width: 20mm;
            margin-right: 2mm;
            font-size: 14pt;
            font-weight: 700;
        }

        .line-pair {
            display: table;
            width: 100%;
            margin-bottom: 1mm;
        }

        .pair-cell {
            display: table-cell;
            width: 50%;
            padding-right: 6mm;
            vertical-align: top;
        }

        .khmer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3mm;
            font-size: 13pt;
        }

        .khmer-table th,
        .khmer-table td {
            border: 1px solid #333333;
            padding: 2mm 2.5mm;
            text-align: left;
            vertical-align: top;
        }

        .khmer-table th {
            font-weight: 700;
            text-align: center;
        }

        .class-photo,
        .class-photo-empty {
            width: 17mm;
            height: 22mm;
            margin: 0 auto;
            border: 1px solid #555555;
            text-align: center;
            overflow: hidden;
        }

        .class-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .class-photo-empty {
            color: #555555;
            font-size: 10px;
            line-height: 22mm;
        }

        .created-date {
            margin-top: 12mm;
            font-size: 13pt;
            text-align: right;
        }

        .class-document .profile-title {
            padding-top: 8mm;
        }
    </style>
</head>
<body>
@php
    $display = static fn ($value): string => filled($value) ? (string) $value : '—';
    $dateValue = static fn ($value): string => $value ? \Carbon\Carbon::parse($value)->format('Y-m-d') : '—';
    $khGender = static function ($value): string {
        return match (strtolower(trim((string) $value))) {
            'male' => 'ប្រុស',
            'female' => 'ស្រី',
            'other' => 'ផ្សេងៗ',
            default => filled($value) ? (string) $value : '—',
        };
    };
    $khStatus = static function ($value): string {
        return match (strtolower(trim((string) $value))) {
            'active' => 'សកម្ម',
            'inactive' => 'អសកម្ម',
            'archived' => 'បានរក្សាទុក',
            default => filled($value) ? (string) $value : '—',
        };
    };
    $fullName = isset($student) ? trim((string) $student->first_name.' '.(string) $student->last_name) : '';
    $enrollment = isset($student) ? $student->classes->first()?->pivot : null;
    $academicYearLabel = $academicYear?->label ?? ($enrollment?->academic_year ?? '—');
    $studentCode = isset($student) ? ($student->student_code ?: ($student->public_id ?: '—')) : '—';
    $guardianAvailable = isset($student) && (filled($student->guardian_name) || filled($student->guardian_phone) || filled($student->address));
@endphp

@if ($mode === 'individual' && isset($student))
    <div class="document">
        <div class="national-heading">
            <p class="national-title">ព្រះរាជាណាចក្រកម្ពុជា</p>
            <p class="national-motto">ជាតិ សាសនា ព្រះមហាក្សត្រ</p>
        </div>

        <div class="top-row">
            <div class="organization-block">
                <div class="logo-box">
                    @if (! empty($organization['logo_data_uri']))
                        <img src="{{ $organization['logo_data_uri'] }}" alt="HFCCF">
                    @else
                        <div class="logo-fallback">HFCCF</div>
                    @endif
                </div>
            </div>

            <div class="student-photo">
                @if ($studentPhoto)
                    <img src="{{ $studentPhoto }}" alt="">
                @else
                    <div class="student-photo-empty">រូបថត</div>
                @endif
            </div>

            <h1 class="profile-title">ប្រវត្តិរូបសិស្ស៖</h1>
        </div>

        <div class="section">
            <p class="section-title">ព័ត៌មានផ្ទាល់ខ្លួនសិស្ស៖</p>
            <div class="info-lines">
                <div class="line-pair">
                    <div class="pair-cell"><span class="label">គោត្តនាម-នាមៈ</span>{{ $display($fullName) }}</div>
                    <div class="pair-cell"><span class="label-short">ភេទៈ</span>{{ $khGender($student->gender) }}</div>
                </div>
                <p class="line"><span class="label">ឈ្មោះជាឡាតាំងៈ</span>{{ $display($student->latin_name) }}</p>
                <div class="line-pair">
                    <div class="pair-cell"><span class="label">ថ្ងៃខែឆ្នាំកំណើតៈ</span>{{ $dateValue($student->date_of_birth) }}</div>
                    <div class="pair-cell"><span class="label-short">សញ្ជាតិៈ</span>{{ $display($student->nationality) }}</div>
                </div>
                <p class="line"><span class="label">ជនជាតិៈ</span>{{ $display($student->ethnicity) }}</p>

                <div class="line-group">
                    <p class="line"><span class="label">អត្តលេខសិស្សៈ</span>{{ $studentCode }}</p>
                    <p class="line"><span class="label">ទីកន្លែងកំណើតៈ</span>{{ $display($student->place_of_birth) }}</p>
                    <p class="line"><span class="label">អាសយដ្ឋានៈ</span>{{ $display($student->address) }}</p>
                    <p class="line"><span class="label">កម្រិតសិក្សាៈ</span>{{ $display($class->name) }}</p>
                    <p class="line"><span class="label">ឆ្នាំសិក្សាៈ</span>{{ $display($academicYearLabel) }}</p>
                    <p class="line"><span class="label">កាលបរិច្ឆេទចុះឈ្មោះៈ</span>{{ $dateValue($enrollment?->enrolled_at) }}</p>
                    <p class="line"><span class="label">ស្ថានភាពៈ</span>{{ $khStatus($student->status) }}</p>
                </div>
            </div>
        </div>

        <div class="section">
            <p class="section-title">ព័ត៌មានអាណាព្យាបាល៖</p>
            <div class="info-lines">
                @if ($guardianAvailable)
                    <p class="line"><span class="label">គោត្តនាម-នាមៈ</span>{{ $display($student->guardian_name) }}</p>
                    <p class="line"><span class="label">អាសយដ្ឋានៈ</span>{{ $display($student->address) }}</p>
                    <p class="line"><span class="label">លេខទំនាក់ទំនងៈ</span>{{ $display($student->guardian_phone) }}</p>
                @else
                    <p class="line">មិនមានព័ត៌មានអាណាព្យាបាល។</p>
                @endif
            </div>
        </div>

        <p class="created-date">កាលបរិច្ឆេទបង្កើត៖ {{ $generatedAt->format('Y-m-d') }}</p>
    </div>

@elseif ($mode === 'class')
    <div class="document class-document">
        <div class="national-heading">
            <p class="national-title">ព្រះរាជាណាចក្រកម្ពុជា</p>
            <p class="national-motto">ជាតិ សាសនា ព្រះមហាក្សត្រ</p>
        </div>

        <div class="top-row">
            <div class="organization-block">
                <div class="logo-box">
                    @if (! empty($organization['logo_data_uri']))
                        <img src="{{ $organization['logo_data_uri'] }}" alt="HFCCF">
                    @else
                        <div class="logo-fallback">HFCCF</div>
                    @endif
                </div>
            </div>

            <h1 class="profile-title">បញ្ជីសង្ខេបប្រវត្តិរូបសិស្ស៖</h1>
        </div>

        <div class="section">
            <p class="section-title">ព័ត៌មានថ្នាក់សិក្សា៖</p>
            <div class="info-lines">
                <p class="line"><span class="label">កម្រិតសិក្សាៈ</span>{{ $display($class->name) }}</p>
                <p class="line"><span class="label">ឆ្នាំសិក្សាៈ</span>{{ $academicYear?->label ?? 'ទាំងអស់' }}</p>
                <p class="line"><span class="label">ចំនួនសិស្សសរុបៈ</span>{{ $classSummary['totalStudents'] }}</p>
                <p class="line"><span class="label">ចំនួនសិស្សសកម្មៈ</span>{{ $classSummary['activeStudents'] }}</p>
            </div>
        </div>

        <div class="section">
            <p class="section-title">បញ្ជីសិស្ស៖</p>
            <table class="khmer-table">
                <thead>
                    <tr>
                        <th>រូបថត</th>
                        <th>អត្តលេខសិស្ស</th>
                        <th>គោត្តនាម-នាម</th>
                        <th>ឈ្មោះជាឡាតាំង</th>
                        <th>ភេទ</th>
                        <th>កម្រិតសិក្សា</th>
                        <th>ស្ថានភាព</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($classStudents as $item)
                        <tr>
                            <td>
                                @if (! empty($item['studentPhoto']))
                                    <div class="class-photo"><img src="{{ $item['studentPhoto'] }}" alt=""></div>
                                @else
                                    <div class="class-photo-empty">—</div>
                                @endif
                            </td>
                            <td>{{ $item['student']->student_code ?: ($item['student']->public_id ?: '—') }}</td>
                            <td>{{ trim($item['student']->first_name.' '.$item['student']->last_name) ?: '—' }}</td>
                            <td>{{ $item['student']->latin_name ?: '—' }}</td>
                            <td>{{ $khGender($item['student']->gender) }}</td>
                            <td>{{ $class->name ?: '—' }}</td>
                            <td>{{ $khStatus($item['student']->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="created-date">កាលបរិច្ឆេទបង្កើត៖ {{ $generatedAt->format('Y-m-d') }}</p>
    </div>
@endif
</body>
</html>
