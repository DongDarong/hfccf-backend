<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sport Standings Report</title>
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
        table.report th:first-child, table.report td:first-child { width: 5%; text-align: center; }
        table.report th:nth-child(2), table.report td:nth-child(2) { width: 15%; }
        .empty { text-align: center; padding: 12px; border: 1px solid #4b5563; }
        footer { position: fixed; bottom: -8mm; left: 0; right: 0; text-align: center; font-size: 8px; color: #4b5563; }
        .page-number::after { content: "Page " counter(page); }
        .tournament-name { font-size: 11px; font-weight: bold; color: #1f3a5f; margin-top: 8px; margin-bottom: 4px; }
        .group-label { font-size: 9px; color: #4b5563; font-style: italic; margin-bottom: 3px; }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo-cell">@if (!empty($organization['logo_data_uri']))<img class="logo" src="{{ $organization['logo_data_uri'] }}" alt="HFCCF logo">@endif</div>
        <div class="title-cell"><p class="org">{{ $organization['kh_name'] }}</p><h1>Standings Report</h1><div>Sport Management System</div></div>
    </header>
    <table class="meta">
        <tr><td class="label">From Date</td><td>{{ $report['filters']['date_from'] ?: 'All' }}</td><td class="label">To Date</td><td>{{ $report['filters']['date_to'] ?: 'All' }}</td></tr>
        <tr><td class="label">Division</td><td>{{ $report['filter_labels']['division'] }}</td><td class="label">Team</td><td>{{ $report['filter_labels']['team'] }}</td></tr>
        <tr><td class="label">Tournament</td><td>{{ $report['filter_labels']['tournament'] }}</td><td class="label">Generated</td><td>{{ $generatedAt->format('Y-m-d H:i') }}</td></tr>
    </table>
    <h2>Summary</h2>
    <table class="report"><tr><th>Total Teams</th><th>Tournaments</th><th>Groups</th></tr><tr><td>{{ $report['summary']['total_teams'] }}</td><td>{{ $report['summary']['tournaments_with_standings'] }}</td><td>{{ $report['summary']['total_groups'] }}</td></tr></table>
    <h2>Standings</h2>
    @if (count($report['standings']) > 0)
        @php
            $standingsByTournament = collect($report['standings'])->groupBy('tournament_name');
        @endphp
        @foreach ($standingsByTournament as $tournamentName => $standings)
            <div class="tournament-name">{{ $tournamentName }}</div>
            @php
                $standingsByGroup = collect($standings)->groupBy('group_name');
            @endphp
            @if ($standingsByGroup->count() > 1)
                @foreach ($standingsByGroup as $groupName => $groupStandings)
                    @if (!empty($groupName))
                        <div class="group-label">{{ $groupName }}</div>
                    @endif
                    <table class="report"><tr><th>Pos</th><th>Team</th><th>P</th><th>W</th><th>D</th><th>L</th><th>GF</th><th>GA</th><th>GD</th><th>Pts</th></tr>@foreach ($groupStandings as $standing)<tr><td>{{ $standing['rank_position'] }}</td><td>{{ $standing['team_name'] }}</td><td>{{ $standing['played'] }}</td><td>{{ $standing['wins'] }}</td><td>{{ $standing['draws'] }}</td><td>{{ $standing['losses'] }}</td><td>{{ $standing['goals_for'] }}</td><td>{{ $standing['goals_against'] }}</td><td>{{ $standing['goal_difference'] }}</td><td>{{ $standing['points'] }}</td></tr>@endforeach</table>
                @endforeach
            @else
                <table class="report"><tr><th>Pos</th><th>Team</th><th>P</th><th>W</th><th>D</th><th>L</th><th>GF</th><th>GA</th><th>GD</th><th>Pts</th></tr>@foreach ($standings as $standing)<tr><td>{{ $standing['rank_position'] }}</td><td>{{ $standing['team_name'] }}</td><td>{{ $standing['played'] }}</td><td>{{ $standing['wins'] }}</td><td>{{ $standing['draws'] }}</td><td>{{ $standing['losses'] }}</td><td>{{ $standing['goals_for'] }}</td><td>{{ $standing['goals_against'] }}</td><td>{{ $standing['goal_difference'] }}</td><td>{{ $standing['points'] }}</td></tr>@endforeach</table>
            @endif
        @endforeach
    @else
        <div class="empty">No standings found for the selected filters.</div>
    @endif
    <footer>Generated {{ $generatedAt->format('Y-m-d H:i') }} · Sport Standings Report <span class="page-number"></span></footer>
</body>
</html>
