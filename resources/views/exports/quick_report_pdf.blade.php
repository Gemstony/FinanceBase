<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ ($subshop->name ?? $shop->name ?? 'Shop') }} QUICK REPORT</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; margin:0; padding:18px; color:#333; font-size:11px; line-height:1.5; }
        .header { background:#2c3e50; color:#fff; padding:20px; margin:-18px -18px 18px; text-align:center; }
        .header h1 { margin:0 0 6px; font-size:24px; font-weight:700; letter-spacing:.5px; }
        .header .subtitle { font-size:12px; opacity:.9; }
        .meta { margin-top:10px; font-size:10px; border-top:1px solid rgba(255,255,255,0.25); padding-top:8px; }
        .section-title { margin:18px 0 8px; font-weight:700; font-size:12px; color:#2c3e50; text-transform:uppercase; }
        .grid { display: table; width: 100%; border-collapse: collapse; }
        .grid .col { display: table-cell; vertical-align: top; padding:8px; border:1px solid #ddd; }
        .grid .col h3 { margin:0 0 4px; font-size:12px; color:#555; }
        .grid .value { font-size:14px; font-weight:700; color:#000; }
        .kpi-grid { width:100%; border-collapse: collapse; }
        .kpi-grid th, .kpi-grid td { border:1px solid #ddd; padding:6px 8px; font-size:10px; text-align:left; }
        .kpi-grid th { background:#f0f3f6; text-transform:uppercase; font-weight:700; }
        .table { width:100%; border-collapse: collapse; }
        .table th, .table td { border:1px solid #ddd; padding:6px 8px; font-size:10px; }
        .table th { background:#f7f7f7; text-transform:uppercase; font-weight:700; }
        .muted { color:#777; }
        .footer { margin-top: 16px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 9px; color: #666; text-align:center; }
        .table thead { display: table-header-group; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $subshop->name ?? ($shop->name ?? 'Shop') }}</h1>
        <div class="subtitle">Quick Dashboard Report</div>
        <div class="meta">
            Period: {{ $dateFrom && $dateTo ? ($dateFrom.' to '.$dateTo) : 'Default period' }}
            • Generated: {{ now()->format('F j, Y g:i A') }}
            • By: {{ $generatedBy ?? 'System' }}
        </div>
    </div>

    <div class="section-title">Key Performance Indicators</div>
    <table class="kpi-grid">
        <thead>
            <tr>
                <th>Metric</th>
                <th>Value</th>
                <th>Change</th>
            </tr>
        </thead>
        <tbody>
            @foreach($kpis as $key => $kpi)
                <tr>
                    <td>{{ $kpi['label'] ?? ucwords(str_replace('_',' ', $key)) }}</td>
                    <td>{{ $kpi['formatted'] ?? (is_numeric($kpi['value'] ?? null) ? number_format((float)$kpi['value'], 0) : ($kpi['value'] ?? '')) }}</td>
                    <td>
                        @php $chg = $kpi['change'] ?? 0; @endphp
                        @if(isset($kpi['change']))
                            {{ ($chg > 0 ? '+' : ($chg < 0 ? '-' : '')) . number_format(abs((float)$chg), 1) }}%
                        @else
                            <span class="muted">n/a</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Smart Alerts Summary</div>
    <table class="table" style="margin-bottom:10px;">
        <thead>
            <tr>
                <th>Priority</th>
                <th>Count</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Critical</td><td>{{ isset($alerts['critical']) ? count($alerts['critical']) : 0 }}</td></tr>
            <tr><td>High</td><td>{{ isset($alerts['high']) ? count($alerts['high']) : 0 }}</td></tr>
            <tr><td>Medium</td><td>{{ isset($alerts['medium']) ? count($alerts['medium']) : 0 }}</td></tr>
        </tbody>
    </table>

    <table class="table">
        <thead>
            <tr>
                <th>Priority</th>
                <th>Type</th>
                <th>Title</th>
                <th>Message</th>
            </tr>
        </thead>
        <tbody>
            @php
                $rows = [];
                foreach (['critical','high','medium'] as $p) {
                    if (!empty($alerts[$p])) {
                        foreach ($alerts[$p] as $a) {
                            $rows[] = [
                                'priority' => ucfirst($p),
                                'type' => $a['type'] ?? '',
                                'title' => $a['title'] ?? '',
                                'message' => $a['message'] ?? ''
                            ];
                        }
                    }
                }
                $rows = array_slice($rows, 0, 30);
            @endphp
            @forelse($rows as $r)
                <tr>
                    <td>{{ $r['priority'] }}</td>
                    <td>{{ $r['type'] }}</td>
                    <td>{{ $r['title'] }}</td>
                    <td>{{ $r['message'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No alerts in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">DukaBase • Quick Report • {{ now()->format('Y-m-d H:i:s') }}</div>
</body>
</html>
