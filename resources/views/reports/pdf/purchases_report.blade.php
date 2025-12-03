<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Purchases Report - {{ $subshopName ?? 'All Locations' }}</title>
    <style>
        @page { margin: 20px 15px; font-family: 'DejaVu Sans', Arial, sans-serif; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #333; }
        .header { background: #2c3e50; color: #fff; padding: 20px; border-radius: 4px; text-align: center; margin-bottom: 18px; }
        .header h1 { margin: 0 0 8px 0; font-size: 20px; letter-spacing: .5px; }
        .subtitle { font-size: 12px; opacity: .9; }
        .period { font-size: 11px; opacity: .9; margin-top: 4px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; }
        .box { background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 12px; text-align: center; }
        .label { font-size: 9px; color: #666; text-transform: uppercase; font-weight: 600; margin-bottom: 5px; }
        .value { font-size: 18px; font-weight: 700; color: #2c3e50; }
        .footer { text-align: center; margin-top: 24px; font-size: 8px; color: #777; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PURCHASES REPORT</h1>
        <div class="subtitle">{{ $subshopName ?? 'All Locations' }}</div>
        <div class="period">{{ \Carbon\Carbon::parse($dateFrom)->format('F j, Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('F j, Y') }}</div>
    </div>

    <div class="stats">
        <div class="box">
            <div class="label">Total Purchases</div>
            <div class="value">{{ number_format($kpi['total_purchases'] ?? 0, 2) }}</div>
        </div>
        <div class="box">
            <div class="label">Orders</div>
            <div class="value">{{ number_format($kpi['orders'] ?? 0) }}</div>
        </div>
        <div class="box">
            <div class="label">Avg. Purchase Value</div>
            <div class="value">{{ number_format($kpi['apv'] ?? 0, 2) }}</div>
        </div>
        <div class="box">
            <div class="label">Taxes</div>
            <div class="value">{{ number_format($kpi['taxes'] ?? 0, 2) }}</div>
        </div>
        <div class="box">
            <div class="label">Discounts</div>
            <div class="value">{{ number_format($kpi['discounts'] ?? 0, 2) }}</div>
        </div>
        <div class="box">
            <div class="label">Outstanding A/P</div>
            <div class="value">{{ number_format($kpi['outstanding_ap'] ?? 0, 2) }}</div>
        </div>
    </div>

    <div class="footer">Generated at {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
</body>
</html>
