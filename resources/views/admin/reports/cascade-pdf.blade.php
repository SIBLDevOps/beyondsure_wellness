<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>7th level cascade report</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; color: #1f2430; font-size: 11px; margin: 0; background: #eef0f4; }
        .bar { position: sticky; top: 0; background: #1f2430; color: #fff; padding: 10px 16px; display: flex; gap: 12px; align-items: center; justify-content: space-between; }
        .bar button { background: #6d28d9; color: #fff; border: 0; border-radius: 6px; padding: 8px 14px; font-weight: 600; cursor: pointer; }
        .sheet { background: #fff; max-width: 1120px; margin: 14px auto; padding: 18px 20px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 18px 0 6px; }
        .sub { color: #5b6272; margin-bottom: 10px; }
        .kpis { display: flex; gap: 10px; margin: 10px 0; }
        .kpi { flex: 1; border: 1px solid #d7dbe3; border-radius: 6px; padding: 8px 10px; }
        .kpi b { display: block; font-size: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d7dbe3; padding: 4px 6px; vertical-align: top; text-align: left; }
        th { background: #f1f3f7; font-size: 10px; text-transform: uppercase; letter-spacing: .3px; }
        td.r, th.r { text-align: right; }
        .mono { font-family: Consolas, monospace; color: #6b7280; font-size: 9px; }
        .root { background: #fff4d6; }
        .tag { display: inline-block; background: #f5b800; color: #3b2d00; border-radius: 3px; padding: 0 4px; font-size: 8px; font-weight: 700; }
        .muted { color: #9aa1b0; }
        tr { page-break-inside: avoid; }
        thead { display: table-header-group; }
        .foot { margin-top: 10px; color: #5b6272; font-size: 9px; }
        @media print { body { background: #fff; } .bar { display: none; } .sheet { margin: 0; padding: 0; max-width: none; } }
    </style>
</head>
<body>
    @php
        $money = fn ($n) => '₹'.number_format($n, 2);
        $region ??= null;
    @endphp
    <div class="bar">
        <span>Choose <strong>Save as PDF</strong> as the printer, landscape is already set.</span>
        <button type="button" onclick="window.print()">Save as PDF</button>
    </div>

    <div class="sheet">
        <h1>7th level cascade report</h1>
        <div class="sub">
            {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }} · {{ $region ? $region->name.' region' : 'All regions' }} · generated {{ now()->format('d M Y H:i') }}
        </div>

        <div class="kpis">
            <div class="kpi">Matching events<b>{{ number_format($summary['events']) }}</b></div>
            <div class="kpi">Total cascade paid<b>{{ $money($summary['total']) }}</b></div>
            <div class="kpi">Paid to the root slot<b>{{ $money($summary['root_paid']) }}</b></div>
            <div class="kpi">Average per event<b>{{ $money($summary['events'] > 0 ? $summary['total'] / $summary['events'] : 0) }}</b></div>
        </div>

        <h2>Paid at each of the seven levels</h2>
        <table>
            <thead><tr><th>Level</th><th class="r">Rate</th><th class="r">Payments</th><th class="r">Paid</th><th class="r">Of which root slot</th><th class="r">Share</th></tr></thead>
            <tbody>
                @foreach ($summary['levels'] as $l)
                    <tr>
                        <td>Level {{ $l['level'] }}</td>
                        <td class="r">{{ rtrim(rtrim(number_format($l['rate'], 2), '0'), '.') }}%</td>
                        <td class="r">{{ number_format($l['payments']) }}</td>
                        <td class="r">{{ $money($l['paid']) }}</td>
                        <td class="r">{{ $l['root_payments'] ? $money($l['root_paid']).' ('.$l['root_payments'].')' : '—' }}</td>
                        <td class="r">{{ $l['share'] }}%</td>
                    </tr>
                @endforeach
                @if ($summary['beyond']['payments'] > 0)
                    <tr><td colspan="2">Other levels (older settings)</td><td class="r">{{ number_format($summary['beyond']['payments']) }}</td><td class="r">{{ $money($summary['beyond']['paid']) }}</td><td colspan="2"></td></tr>
                @endif
                <tr><th colspan="3">Total</th><th class="r">{{ $money($summary['total']) }}</th><th colspan="2"></th></tr>
            </tbody>
        </table>

        <h2>Every match and its seven slots</h2>
        <table>
            <thead>
                <tr>
                    <th>Matched at</th>
                    @for ($i = 1; $i <= \App\Services\ReportService::CASCADE_SLOTS; $i++)<th>Level {{ $i }}</th>@endfor
                    <th class="r">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($events as $e)
                    <tr>
                        <td><strong>{{ $e['source']->name }}</strong><div class="mono">{{ $e['source']->member_code }} · {{ $e['date']?->format('d M Y') }}</div></td>
                        @foreach ($e['slots'] as $slot)
                            <td class="{{ $slot && $slot['root'] ? 'root' : '' }}">
                                @if ($slot)
                                    {{ $slot['member']->name ?? '—' }}<div class="mono">{{ $slot['member']->member_code ?? '' }}</div>
                                    <strong>{{ $money($slot['amount']) }}</strong>
                                    @if ($slot['root'])<div><span class="tag">Root slot</span></div>@endif
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="r"><strong>{{ $money($e['total']) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="text-align:center;padding:18px;">No cascade payments in this date range.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="foot">Seven people are paid for each match, one per level. When a match is deeper than the levels reach, the root takes the last slot and is paid that level's rate.</div>
    </div>
</body>
</html>
