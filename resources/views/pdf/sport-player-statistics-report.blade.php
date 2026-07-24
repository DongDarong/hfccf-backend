<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sport Player Statistics Report</title>
    <style>
        @page { size: A4 landscape; margin: 14mm 12mm 16mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: Arial, "Noto Sans Khmer", sans-serif; font-size: 9px; }
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
        table.report th, table.report td { border: 1px solid #4b5563; padding: 4px 4px; vertical-align: top; word-wrap: break-word; }
        table.report th { background: #e5e7eb; text-align: center; font-weight: bold; font-size: 8px; }
        table.report td { text-align: center; font-size: 8px; }
        table.report th:first-child, table.report td:first-child { width: 4%; }
        table.report th:nth-child(2), table.report td:nth-child(2) { width: 12%; text-align: left; }
        table.report th:nth-child(3), table.report td:nth-child(3) { width: 12%; text-align: left; }
        .empty { text-align: center; padding: 12px; border: 1px solid #4b5563; }
        footer { position: fixed; bottom: -8mm; left: 0; right: 0; text-align: center; font-size: 8px; color: #4b5563; }
        .page-number::after { content: "Page " counter(page); }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo-cell">@if (!empty($organization['logo_data_uri']))<img class="logo" src="{{ $organization['logo_data_uri'] }}" alt="HFCCF logo">@endif</div>
        <div class="title-cell"><p class="org">{{ $organization['kh_name'] }}</p><h1>Player Statistics Report</h1><div>Sport Management System</div></div>
    </header>
    <table class="meta">
        <tr><td class="label">From Date</td><td>{{ $report['filters']['date_from'] ?: 'All' }}</td><td class="label">To Date</td><td>{{ $report['filters']['date_to'] ?: 'All' }}</td></tr>
        <tr><td class="label">Division</td><td>{{ $report['filter_labels']['division'] }}</td><td class="label">Team</td><td>{{ $report['filter_labels']['team'] }}</td></tr>
        <tr><td class="label">Tournament</td><td>{{ $report['filter_labels']['tournament'] }}</td><td class="label">Generated</td><td>{{ $generatedAt->format('Y-m-d H:i') }}</td></tr>
    </table>
    <h2>Summary</h2>
    <table class="report"><tr><th>Total Players</th><th>Players with Appearances</th><th>Total Goals</th><th>Total Assists</th><th>Total Yellow Cards</th><th>Total Red Cards</th></tr><tr><td>{{ $report['summary']['total_players'] }}</td><td>{{ $report['summary']['players_with_appearances'] }}</td><td>{{ $report['summary']['total_goals'] }}</td><td>{{ $report['summary']['total_assists'] }}</td><td>{{ $report['summary']['total_yellow_cards'] }}</td><td>{{ $report['summary']['total_red_cards'] }}</td></tr></table>
    <h2>Player Statistics</h2>
    @if (count($report['players']) > 0)
        <table class="report"><tr><th>Rank</th><th>Player</th><th>Team</th><th>Apps</th><th>G</th><th>A</th><th>YC</th><th>RC</th><th>PG</th><th>OG</th><th>PM</th><th>DP</th></tr>@foreach ($report['players'] as $player)<tr><td>{{ $player['rank_position'] }}</td><td>{{ $player['player_name'] }}</td><td>{{ $player['team_name'] }}</td><td>{{ $player['appearances'] }}</td><td>{{ $player['goals'] }}</td><td>{{ $player['assists'] }}</td><td>{{ $player['yellow_cards'] }}</td><td>{{ $player['red_cards'] }}</td><td>{{ $player['penalty_goals'] }}</td><td>{{ $player['own_goals'] }}</td><td>{{ $player['penalty_misses'] }}</td><td>{{ $player['discipline_points'] }}</td></tr>@endforeach</table>
    @else
        <div class="empty">No players found for the selected filters.</div>
    @endif
    <footer>Generated {{ $generatedAt->format('Y-m-d H:i') }} · Sport Player Statistics Report <span class="page-number"></span></footer>
</body>
</html>
