<!doctype html>
<html lang="{{ str_starts_with(strtolower((string) app()->getLocale()), 'kh') ? 'km' : 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $labels['title'] }} {{ $invoice->invoice_number }}</title>
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
            margin: 14.5mm 13mm 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #1f2937;
            font-family: 'Noto Sans Khmer PDF', sans-serif;
            font-size: 10.5px;
            line-height: 1.35;
            background: #ffffff;
        }

        .document {
            width: 100%;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        @media screen {
            body {
                background: #eef1f5;
                padding: 24px;
                overflow-x: hidden;
            }

            .document {
                width: 210mm;
                max-width: 100%;
                min-height: 297mm;
                margin: 0 auto;
                padding: 14.5mm 13mm 14mm;
                background: #ffffff;
                box-shadow: 0 4px 18px rgba(15, 23, 42, 0.12);
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }
        }

        @media print {
            body {
                padding: 0;
                background: #ffffff;
                overflow: visible;
            }

            .document {
                width: auto;
                max-width: none;
                min-height: calc(297mm - 28.5mm);
                margin: 0;
                padding: 0;
                box-shadow: none;
                overflow: visible;
                display: flex;
                flex-direction: column;
            }
        }

        .invoice-body {
            flex: 1 0 auto;
        }

        .header {
            display: table;
            table-layout: fixed;
            width: 100%;
            margin-bottom: 6px;
        }

        .header__left,
        .header__center,
        .header__right {
            display: table-cell;
            vertical-align: top;
        }

        .header__left {
            width: 38%;
            padding-right: 10px;
        }

        .header__center {
            width: 24%;
            text-align: center;
            vertical-align: middle;
            padding: 0 4px;
        }

        .header__right {
            width: 38%;
            text-align: right;
            padding-left: 10px;
            padding-right: 2px;
        }

        .brand {
            display: block;
            width: 100%;
            text-align: center;
        }

        .brand__logo,
        .brand__content {
            display: block;
        }

        .brand__logo {
            width: 100%;
            margin: 0 0 5px;
        }

        .brand__logo-box {
            width: 150px;
            height: 99px;
            margin: 0 auto;
            border: 1px solid #d2dceb;
            border-radius: 14px;
            background: linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
            color: #1d4f91;
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            line-height: 99px;
        }

        .brand__logo img {
            width: 150px;
            max-width: 150px;
            max-height: 99px;
            margin: 0 auto;
            display: block;
            object-fit: contain;
        }

        .org-name-kh {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.08;
            margin: 0 0 1px;
        }

        .org-name-en {
            font-size: 11.75px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.12;
            margin: 0 0 2px;
        }

        .program-name {
            font-size: 10.25px;
            font-weight: 700;
            color: #1d4f91;
            line-height: 1.12;
            margin: 0;
        }

        .org-line,
        .footer-line {
            margin: 0 0 2px;
            line-height: 1.2;
        }

        .org-line {
            color: #475569;
            font-size: 9.5px;
        }

        .invoice-title {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #1d4f91;
            margin: 0 0 5px;
            text-transform: uppercase;
        }

        .invoice-title__kh {
            display: block;
            font-size: 14.25px;
            line-height: 1.06;
            color: #1d4f91;
            margin-bottom: 1px;
            letter-spacing: 0;
        }

        .invoice-title__en {
            display: block;
            font-size: 15.5px;
            line-height: 1.06;
            color: #0f172a;
        }

        .divider {
            height: 2px;
            background: #1d4f91;
            margin: 9px 0 11px;
        }

        .invoice-meta {
            width: 100%;
            margin-top: 0;
        }

        .invoice-meta__item {
            padding: 0 0 6px;
        }

        .invoice-meta__label {
            display: block;
            margin: 0 0 1px;
            text-align: right;
        }

        .invoice-meta__value {
            display: block;
            text-align: right;
            color: #0f172a;
            font-weight: 700;
            line-height: 1.14;
            white-space: nowrap;
            overflow-wrap: normal;
            word-break: normal;
            hyphens: manual;
        }

        .invoice-meta__value--invoice-number {
            font-size: 9.5px;
            letter-spacing: 0.005em;
        }

        .invoice-meta__value--invoice-date,
        .invoice-meta__value--due-date {
            font-size: 9.75px;
        }

        .invoice-meta__value .status-chip {
            margin-top: 1px;
            display: inline-block;
        }

        .panel-grid {
            display: table;
            width: 100%;
            border-spacing: 0;
            margin-bottom: 10px;
        }

        .panel {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            border: 1px solid #d7dee7;
            background: #fcfdff;
            padding: 10px 12px 9px;
        }

        .panel + .panel {
            border-left: none;
        }

        .panel-title,
        .section-title {
            margin: 0 0 7px;
            font-size: 11px;
            font-weight: 700;
            color: #1d4f91;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .info-table,
        .items-table,
        .history-table,
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 3px 0;
            vertical-align: top;
        }

        .info-table td:first-child {
            width: 43%;
            color: #475569;
            padding-right: 8px;
            font-weight: 700;
        }

        .items-table {
            margin-bottom: 10px;
        }

        .items-table thead {
            display: table-header-group;
        }

        .items-table th {
            background: #eaf1f8;
            color: #0f172a;
            font-size: 9.5px;
            font-weight: 700;
            padding: 9px 7px 8px;
            border: 1px solid #cad5e2;
            text-align: center;
            vertical-align: middle;
        }

        .items-table td {
            border: 1px solid #d7dee7;
            padding: 7px 8px;
            vertical-align: top;
        }

        .items-table tbody tr,
        .totals-card,
        .section {
            page-break-inside: avoid;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .col-no {
            width: 6%;
        }

        .col-desc {
            width: 49%;
        }

        .col-qty {
            width: 11%;
        }

        .col-unit,
        .col-amount {
            width: 17%;
        }

        .totals-wrap {
            width: 100%;
            margin-bottom: 8px;
        }

        .totals-card {
            width: 40%;
            margin-left: auto;
            border: 1px solid #d7dee7;
            background: #fcfdff;
            padding: 9px 11px 10px;
        }

        .totals-table td {
            padding: 4px 0;
        }

        .totals-table td:first-child {
            color: #475569;
            padding-right: 12px;
            font-weight: 700;
        }

        .totals-divider td {
            padding-top: 5px;
            border-top: 1px solid #d7dee7;
        }

        .totals-table tr.total-row td,
        .totals-table tr.balance-row td {
            font-weight: 700;
            color: #0f172a;
        }

        .totals-table tr.total-row td {
            padding-top: 6px;
            font-size: 11px;
        }

        .totals-table tr.balance-row td {
            font-size: 11.5px;
            color: #1d4f91;
        }

        .footer {
            margin-top: auto;
            padding-top: 7px;
            padding-bottom: 1px;
            border-top: 1px solid #d7dee7;
            color: #475569;
            font-size: 9.5px;
            text-align: center;
        }

        .footer-contact {
            margin: 0 0 7px;
            line-height: 1.15;
        }

        .footer-contact-line {
            margin: 0;
            line-height: 1.15;
        }

        .footer-contact-line:last-child {
            margin-bottom: 0;
        }

        .footer-contact-row {
            display: inline-flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0 10px;
        }

        .footer-contact-sep {
            color: #94a3b8;
        }

        .footer-notice {
            margin: 0 0 6px;
        }

        .footer-meta {
            display: inline-flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 5px 18px;
            margin: 0;
        }

        .footer-meta-item {
            display: inline-flex;
            align-items: baseline;
            gap: 5px;
            white-space: nowrap;
        }

        .footer-meta-label {
            font-weight: 700;
            color: #334155;
        }

        .footer-meta-value {
            color: #0f172a;
            font-weight: 700;
        }

        .label-stack {
            display: block;
            line-height: 1.05;
        }

        .label-kh {
            display: block;
            color: #0f172a;
            font-size: 9px;
            font-weight: 700;
            margin-bottom: 1px;
        }

        .label-en {
            display: block;
            color: #64748b;
            font-size: 8px;
            font-weight: 400;
        }

        .status-chip {
            display: inline-block;
            padding: 3px 10px;
            border: 1px solid transparent;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4f91;
            font-size: 9.5px;
            font-weight: 700;
        }

        .status-chip--paid {
            background: #ecfdf3;
            color: #166534;
        }

        .status-chip--pending,
        .status-chip--partial,
        .status-chip--draft {
            background: #fff7ed;
            color: #9a3412;
        }

        .status-chip--overdue,
        .status-chip--cancelled {
            background: #fef2f2;
            color: #b91c1c;
        }
    </style>
</head>
<body>
    @php
        $stackLabel = static function (string $kh, string $en): string {
            return '<span class="label-stack"><span class="label-kh">'.e($kh).'</span><span class="label-en">'.e($en).'</span></span>';
        };

        $statusClass = static function (?string $status): string {
            $value = strtolower(trim((string) ($status ?? 'draft')));

            return 'status-chip--'.($value !== '' ? $value : 'draft');
        };
    @endphp
    <div class="document">
        <div class="invoice-body">
            <div class="header">
                <div class="header__left">
                    <div class="brand">
                        <div class="brand__logo">
                            @if (! empty($organization['logo_data_uri']))
                                <img src="{{ $organization['logo_data_uri'] }}" alt="HFCCF logo">
                            @else
                                <div class="brand__logo-box">HFCCF</div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="header__center">
                    <p class="invoice-title">
                        <span class="invoice-title__kh">វិក្កយបត្រ</span>
                        <span class="invoice-title__en">INVOICE</span>
                    </p>
                </div>
                <div class="header__right">
                    <div class="invoice-meta">
                        <div class="invoice-meta__item">
                            <div class="invoice-meta__label">{!! $stackLabel('លេខវិក្កយបត្រ', 'Invoice Number') !!}</div>
                            <div class="invoice-meta__value invoice-meta__value--invoice-number">{{ $invoice->invoice_number }}</div>
                        </div>
                        <div class="invoice-meta__item">
                            <div class="invoice-meta__label">{!! $stackLabel('កាលបរិច្ឆេទវិក្កយបត្រ', 'Invoice Date') !!}</div>
                            <div class="invoice-meta__value invoice-meta__value--invoice-date">{{ $issueDate }}</div>
                        </div>
                        <div class="invoice-meta__item">
                            <div class="invoice-meta__label">{!! $stackLabel('ថ្ងៃផុតកំណត់', 'Due Date') !!}</div>
                            <div class="invoice-meta__value invoice-meta__value--due-date">{{ $dueDate }}</div>
                        </div>
                        <div class="invoice-meta__item">
                            <div class="invoice-meta__label">{!! $stackLabel('ស្ថានភាព', 'Status') !!}</div>
                            <div class="invoice-meta__value">
                                <span class="status-chip {{ $statusClass($invoice->status) }}">{{ $statusLabel }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <div class="panel-grid">
                <div class="panel">
                    <p class="panel-title">{!! $stackLabel('ព័ត៌មានសិស្ស', 'Student Information') !!}</p>
                    <table class="info-table">
                        <tr>
                            <td>{!! $stackLabel('ឈ្មោះសិស្ស', 'Student Name') !!}</td>
                            <td>{{ $studentName }}</td>
                        </tr>
                        @if ($latinName)
                            <tr>
                                <td>{!! $stackLabel('ឈ្មោះឡាតាំង', 'Latin Name') !!}</td>
                                <td>{{ $latinName }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td>{!! $stackLabel('លេខសម្គាល់សិស្ស', 'Student ID') !!}</td>
                            <td>{{ $invoice->student?->public_id ?? $invoice->student?->id ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td>{!! $stackLabel('ថ្នាក់', 'Class') !!}</td>
                            <td>{{ $className }}</td>
                        </tr>
                    </table>
                </div>
                <div class="panel">
                    <p class="panel-title">{!! $stackLabel('ព័ត៌មានវិក្កយបត្រ', 'Invoice Information') !!}</p>
                    <table class="info-table">
                        <tr>
                            <td>{!! $stackLabel('ឆ្នាំសិក្សា', 'Academic Year') !!}</td>
                            <td>{{ $academicYearName }}</td>
                        </tr>
                        <tr>
                            <td>{!! $stackLabel('ឆមាស', 'Term') !!}</td>
                            <td>{{ $termName }}</td>
                        </tr>
                        <tr>
                            <td>{!! $stackLabel('ស្ថានភាពការទូទាត់', 'Payment Status') !!}</td>
                            <td>{{ $statusLabel }}</td>
                        </tr>
                        <tr>
                            <td>{!! $stackLabel('កាលបរិច្ឆេទបង្កើត', 'Created Date') !!}</td>
                            <td>{{ $createdAt }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="section">
                <p class="section-title">{!! $stackLabel('បញ្ជីសេវា', 'Invoice Items') !!}</p>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th class="col-no">{!! $stackLabel('ល.រ.', 'No.') !!}</th>
                            <th class="col-desc">{!! $stackLabel('បរិយាយ', 'Description') !!}</th>
                            <th class="col-qty amount">{!! $stackLabel('ចំនួន', 'Quantity') !!}</th>
                            <th class="col-unit amount">{!! $stackLabel('តម្លៃឯកតា', 'Unit Price') !!}</th>
                            <th class="col-amount amount">{!! $stackLabel('សរុប', 'Amount') !!}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ trim((string) $item->description) !== '' ? $item->description : '-' }}</td>
                                <td class="amount">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</td>
                                <td class="amount">{{ number_format((float) $item->unit_price, 2, '.', ',') }}</td>
                                <td class="amount">{{ number_format((float) $item->amount, 2, '.', ',') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="totals-wrap">
                <div class="totals-card">
                    <table class="totals-table">
                        <tr>
                            <td>{!! $stackLabel('សរុបរង', 'Subtotal') !!}</td>
                            <td class="amount">{{ $subtotal }}</td>
                        </tr>
                        <tr>
                            <td>{!! $stackLabel('បញ្ចុះតម្លៃ', 'Discount') !!}</td>
                            <td class="amount">{{ $discount }}</td>
                        </tr>
                        <tr class="total-row totals-divider">
                            <td>{!! $stackLabel('សរុបទឹកប្រាក់', 'Total Amount') !!}</td>
                            <td class="amount">{{ $total }}</td>
                        </tr>
                        <tr>
                            <td>{!! $stackLabel('ប្រាក់ទូទាត់', 'Paid Amount') !!}</td>
                            <td class="amount">{{ $paid }}</td>
                        </tr>
                        <tr class="balance-row">
                            <td>{!! $stackLabel('ប្រាក់នៅសល់', 'Balance Due') !!}</td>
                            <td class="amount">{{ $balance }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="footer">
            <div class="footer-contact">
                <div class="footer-contact-row">
                    @foreach ($organizationLines as $index => $line)
                        <span>{{ $line }}</span>
                        @if ($index < count($organizationLines) - 1)
                            <span class="footer-contact-sep">•</span>
                        @endif
                    @endforeach
                </div>
            </div>
            <div class="footer-notice">
                <p class="footer-line">វិក្កយបត្រនេះត្រូវបានបង្កើតដោយប្រព័ន្ធ HFCCF និងមិនត្រូវការហត្ថលេខា។</p>
                <p class="footer-line">This invoice was generated electronically by the HFCCF System and does not require a signature.</p>
            </div>
            <div class="footer-meta">
                <div class="footer-meta-item">
                    <span class="footer-meta-label">{!! $stackLabel('លេខវិក្កយបត្រ', 'Invoice Number') !!}</span>
                    <span class="footer-meta-value">{{ $invoice->invoice_number }}</span>
                </div>
                <div class="footer-meta-item">
                    <span class="footer-meta-label">{!! $stackLabel('បង្កើតនៅ', 'Generated On') !!}</span>
                    <span class="footer-meta-value">{{ $generatedAt }}</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
