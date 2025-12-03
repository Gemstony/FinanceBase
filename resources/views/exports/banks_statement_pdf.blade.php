<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $subshop->name ?? 'Shop' }} Bank Statement</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; margin:0; padding:15px; color:#333; font-size:10px; line-height:1.4; }
        .header { background:#2c3e50; color:#fff; padding:20px; text-align:center; margin-bottom:20px; }
        .header h1{ font-size:26px; margin:0 0 8px; font-weight:700; }
        .header .subtitle{ font-size:14px; margin:0 0 10px; }
        .header .meta{ margin-top:10px; font-size:9px; border-top:1px solid rgba(255,255,255,0.3); padding-top:10px; }
        .stats-table{ width:100%; margin-bottom:20px; border-collapse:separate; border-spacing:8px; }
        .stats-table td{ width:50%; padding:12px 8px; text-align:center; border:2px solid #ddd; border-radius:4px; }
        .stat-label{ font-size:8px; color:#666; text-transform:uppercase; font-weight:700; letter-spacing:.5px; margin-bottom:5px; }
        .stat-value{ font-size:18px; font-weight:700; color:#2c3e50; margin:5px 0; }
        .stat-blue{ background:#e3f2fd; border-color:#2196f3; } .stat-blue .stat-value{ color:#1976d2; }
        .stat-green{ background:#e8f5e9; border-color:#4caf50; } .stat-green .stat-value{ color:#388e3c; }
        .stat-orange{ background:#fff3e0; border-color:#ff9800; } .stat-orange .stat-value{ color:#f57c00; }
        .stat-red{ background:#ffebee; border-color:#f44336; } .stat-red .stat-value{ color:#d32f2f; }
        .stat-grey{ background:#eceff1; border-color:#90a4ae; } .stat-grey .stat-value{ color:#455a64; }
        .data-table{ width:100%; border-collapse:collapse; font-size:8px; margin-top:15px; }
        .data-table thead th{ background:#34495e; color:#fff; padding:8px 4px; text-align:left; font-weight:700; font-size:8px; text-transform:uppercase; border:1px solid #2c3e50; }
        .data-table td{ padding:6px 4px; border:1px solid #ddd; vertical-align:top; word-wrap:break-word; }
        .data-table tbody tr:nth-child(odd){ background:#f9f9f9; }
        .data-table tbody tr:nth-child(even){ background:#fff; }
        .badge{ display:inline-block; padding:2px 6px; font-size:7px; font-weight:700; border-radius:3px; text-transform:uppercase; }
        .data-table thead { display: table-header-group; }
        .right{ text-align:right; }
        .footer { margin-top: 20px; padding: 10px; border-top: 1px solid #ddd; font-size: 9px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $subshop->name ?? 'Shop' }}</h1>
        <div class="subtitle">BANK STATEMENT</div>
        <div style="font-size:10px; line-height:1.5; margin: 6px 0 4px;">
            Detailed ledger with running balance.
            @if($banks && $banks->count() === 1)
             Bank: {{ $banks->first()->name }} ({{ $banks->first()->account_number }})
            @else
             Banks: {{ $banks->pluck('name')->join(', ') }}
            @endif
            @if(!empty($dateFrom) || !empty($dateTo))
             | Period: {{ $dateFrom ?? '...' }} to {{ $dateTo ?? '...' }}
            @endif
        </div>
        <div class="meta">Generated: {{ now()->format('F j, Y \a\t g:i A') }} | By: {{ $generatedBy ?? 'System' }}</div>
    </div>

    @if(!empty($summary))
    <table class="stats-table">
        @foreach(collect($summary)->chunk(2) as $chunk)
            <tr>
                @foreach($chunk as $s)
                    <td>
                        <div class="stat-label">Bank</div>
                        <div class="stat-value">{{ $s['bank_name'] }}</div>
                        <div class="stat-label">Opening</div>
                        <div class="stat-value">TZS{{ number_format((float)$s['opening'], 2) }}</div>
                        <div class="stat-label">Inflows</div>
                        <div class="stat-value">TZS{{ number_format((float)$s['inflow'], 2) }}</div>
                        <div class="stat-label">Outflows</div>
                        <div class="stat-value">TZS{{ number_format((float)$s['outflow'], 2) }}</div>
                        <div class="stat-label">Net</div>
                        <div class="stat-value">TZS{{ number_format((float)$s['net'], 2) }}</div>
                        <div class="stat-label">Closing ({{ (int)$s['count'] }} txns)</div>
                        <div class="stat-value">TZS{{ number_format((float)$s['closing'], 2) }}</div>
                    </td>
                @endforeach
                @if($chunk->count() === 1)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>
    @endif

    <table class="data-table">
        <thead>
            <tr>
                <th>Bank</th>
                <th>Date</th>
                <th>Source</th>
                <th>Reference</th>
                <th>Description</th>
                <th class="right">In</th>
                <th class="right">Out</th>
                <th class="right">Running Balance</th>
            </tr>
        </thead>
        <tbody>
        @forelse($rows as $r)
            <tr>
                <td>{{ $r['bank_name'] }}</td>
                <td>{{ $r['date'] }}</td>
                <td>{{ $r['source'] }}</td>
                <td>{{ $r['reference'] }}</td>
                <td>{{ $r['description'] }}</td>
                <td class="right">{{ number_format((float)$r['inflow'], 2) }}</td>
                <td class="right">{{ number_format((float)$r['outflow'], 2) }}</td>
                <td class="right">{{ number_format((float)$r['running_balance'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="right" style="color:#777;">No data for the selected period.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="footer">
        Report generated by: <strong>{{ $generatedBy ?? 'System' }}</strong> • {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
