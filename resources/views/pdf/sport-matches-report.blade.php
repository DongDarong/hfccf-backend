<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sport Matches Report</title>
    <style>
        @page { size: A4 portrait; margin: 14mm 12mm 16mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: Arial, "Noto Sans Khmer", sans-serif; font-size: 10px; }
        .header { display: table; width: 100%; border-bottom: 2px solid #1f3a5f; padding-bottom: 10px; }
        .logo-cell, .title-cell { display: table-cell; vertical-align: middle; }
        .logo-cell { width: 24%; }
        .logo { max-width: 92px; max-height: 58px; object-fit: contain; }
        .title-cell { text-align: center; }
        h1 { margin: 0 0 4px; font-size: 18px; color: #1f3a5f; }
        .org { margin: 0 0 3px; font-size: 11px; font-weight: bold; }
        .meta { margin-top: 10px; width: 100%; border-collapse: collapse; }
        .meta td { padding: 3px 5px; border: 1px solid #9ca3af; }
        .meta .label { width: 18%; font-weight: bold; background: #f3f4f6; }
        h2 { margin: 16px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #1f3a5f; font-size: 13px; color: #1f3a5f; }
        table.report { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.report th, table.report td { border: 1px solid #4b5563; padding: 5px 6px; vertical-align: top; word-wrap: break-word; }
        table.report th { background: #e5e7eb; text-align: left; font-weight: bold; }
        table.report th:first-child, table.report td:first-child { width: 6%; text-align: center; }
        .empty { text-align: center; padding: 12px; border: 1px solid #4b5563; }
        footer { position: fixed; bottom: -8mm; left: 0; right: 0; text-align: center; font-size: 8px; color: #4b5563; }
        .page-number::after { content: "Page " counter(page); }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo-cell">@if (!empty($organization['logo_data_uri']))<img class="logo" src="{{ $organization['logo_data_uri'] }}" alt="HFCCF logo">@endif</div>
        <div class="title-cell"><p class="org">{{ $organization['kh_name'] }}</p><h1>Matches Report</h1><div>Sport Management System</div></div>
    </header>
    <table class="meta">
        <tr><td class="label">From Date</td><td>{{ $report['filters']['date_from'] ?: 'All' }}</td><td class="label">To Date</td><td>{{ $report['filters']['date_to'] ?: 'All' }}</td></tr>
        <tr><td class="label">Division</td><td>{{ $report['filter_labels']['division'] }}</td><td class="label">Team</td><td>{{ $report['filter_labels']['team'] }}</td></tr>
        <tr><td class="label">Tournament</td><td>{{ $report['filter_labels']['tournament'] }}</td><td class="label">Generated</td><td>{{ $generatedAt->format('Y-m-d H:i') }}</td></tr>
    </table>
    <h2>Summary</h2>
    <table class="report"><tr><th>Total Matches</th><th>Completed Matches</th><th>Scheduled Matches</th><th>Total Teams</th></tr><tr><td>{{ $report['summary']['total_matches'] }}</td><td>{{ $report['summary']['completed_matches'] }}</td><td>{{ $report['summary']['scheduled_matches'] }}</td><td>{{ $report['summary']['total_teams'] }}</td></tr></table>
    <h2>Match Summary</h2>
    @if (count($report['matches']) > 0)
        <table class="report"><tr><th>No.</th><th>Tournament</th><th>Division</th><th>Home Team</th><th>Away Team</th><th>Score</th><th>Date</th><th>Venue</th><th>Status</th></tr>@foreach ($report['matches'] as $index => $match)<tr><td>{{ $index + 1 }}</td><td>{{ $match['tournamentName'] ?: '-' }}</td><td>{{ $match['divisionName'] ?: '-' }}</td><td>{{ $match['homeTeamName'] ?: '-' }}</td><td>{{ $match['awayTeamName'] ?: '-' }}</td><td>{{ $match['score'] }}</td><td>{{ $match['date'] ?: '-' }}</td><td>{{ $match['venue'] ?: '-' }}</td><td>{{ $match['status'] ?: '-' }}</td></tr>@endforeach</table>
    @else
        <div class="empty">No matches found for the selected filters.</div>
    @endif
    <footer>Generated {{ $generatedAt->format('Y-m-d H:i') }} · Sport Matches Report <span class="page-number"></span></footer>
</body>
</html>
