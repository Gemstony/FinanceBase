<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Customer Risk Report</title>
    <style>
        @page { margin: 24px 22px; font-family: 'DejaVu Sans', Arial, sans-serif; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #000; }

        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 12px; }
        .header-left { display: inline-block; vertical-align: top; width: 64%; }
        .header-right { display: inline-block; vertical-align: top; width: 35%; text-align: right; }
        .header-logo { display: inline-block; vertical-align: top; width: 90px; }
        .header-info { display: inline-block; vertical-align: top; width: calc(100% - 95px); }
        .logo { max-width: 85px; max-height: 85px; object-fit: contain; }

        .inst-name { font-size: 14px; font-weight: 700; margin: 0 0 3px 0; }
        .inst-meta { font-size: 9px; line-height: 1.35; }
        .report-title { font-size: 12px; font-weight: 700; text-transform: uppercase; text-align: right; margin: 0 0 3px 0; }
        .report-sub { font-size: 9px; text-align: right; line-height: 1.35; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #fff; font-weight: 700; text-align: left; }
        td.num, th.num { text-align: right; }

        .muted { font-weight: bold; }
        .row-head { background: #f4f4f4; }
        .nowrap { white-space: nowrap; }
        
        .summary-box { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 15px; 
            padding: 10px; 
            border: 1px solid #000;
        }
        .summary-item { 
            text-align: center; 
            flex: 1; 
            border-right: 1px solid #000; 
        }
        .summary-item:last-child { border-right: none; }
        .summary-label { font-size: 9px; color: #666; display: block; margin-bottom: 3px; }
        .summary-value { font-size: 12px; font-weight: 700; }
        
        .badge { 
            padding: 2px 6px; 
            border-radius: 3px; 
            font-size: 8px; 
            font-weight: 700;
        }
        .badge-success { background: #28a745; color: #fff; }
        .badge-warning { background: #ffc107; color: #000; }
        .badge-danger { background: #dc3545; color: #fff; }
        .badge-secondary { background: #6c757d; color: #fff; }
        
        .text-right { text-align: right !important; }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .text-warning { color: #ffc107; }
        .break-word { word-wrap: break-word; overflow-wrap: break-word; word-break: break-all; }
        
        .table-danger { background-color: #f8d7da; }
        .table-warning { background-color: #fff3cd; }
        .table-info { background-color: #d1ecf1; }
        
        .section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin: 15px 0 8px 0;
            padding-bottom: 4px;
            border-bottom: 1px solid #000;
        }
    </style>
</head>
<body>
@php
    $customers = $report['customers'] ?? collect();
    $metrics = $report['metrics'] ?? [];
    $chartData = $report['chart_data'] ?? [];
    $topRiskCustomers = $report['top_risk_customers'] ?? collect();

    $shopName = $shop->name ?? 'Institution';
    $shopEmail = $shop->email ?? null;
    $shopPhone = $shop->phone ?? null;
    $shopWebsite = $shop->website ?? null;
    $shopAddress = $shop->address ?? null;

    $fmt = function ($v) {
        return number_format((float) ($v ?? 0), 2);
    };
@endphp

<div class="header">
    <div class="header-left">
        <div class="header-logo">
            @if($shopLogoPath && file_exists($shopLogoPath))
                <img src="{{ $shopLogoPath }}" class="logo" alt="Logo">
            @endif
        </div>
        <div class="header-info">
            <h1 class="inst-name">{{ $shopName }}</h1>
            <div class="inst-meta">
                @if($shopAddress)<br>{{ $shopAddress }}@endif
                @if($shopPhone)<br>Phone: {{ $shopPhone }}@endif
                @if($shopEmail)<br>Email: {{ $shopEmail }}@endif
                @if($shopWebsite)<br>Website: {{ $shopWebsite }}@endif
            </div>
        </div>
    </div>
    <div class="header-right">
        <h2 class="report-title">Customer Risk Report</h2>
        <div class="report-sub">
            Branch: {{ $subshopName }}<br>
            Generated: {{ $generatedAt }}
        </div>
    </div>
</div>

<!-- Summary Metrics -->
<div class="summary-box">
    <div class="summary-item">
        <span class="summary-label">Total Customers</span>
        <span class="summary-value">{{ $metrics['total_customers'] ?? 0 }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Low Risk</span>
        <span class="summary-value text-success">{{ $metrics['low_risk_count'] ?? 0 }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Medium Risk</span>
        <span class="summary-value text-info">{{ $metrics['medium_risk_count'] ?? 0 }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">High Risk</span>
        <span class="summary-value text-warning">{{ $metrics['high_risk_count'] ?? 0 }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Defaulted</span>
        <span class="summary-value text-danger">{{ $metrics['defaulted_count'] ?? 0 }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Avg Risk Score</span>
        <span class="summary-value">{{ $metrics['average_risk_score'] ?? 0 }}</span>
    </div>
</div>

<!-- Financial Summary -->
<div class="summary-box">
    <div class="summary-item">
        <span class="summary-label">Total Outstanding</span>
        <span class="summary-value">{{ $fmt($metrics['total_outstanding'] ?? 0) }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Total Overdue</span>
        <span class="summary-value text-danger">{{ $fmt($metrics['total_overdue'] ?? 0) }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Outstanding Penalties</span>
        <span class="summary-value text-warning">{{ $fmt($metrics['total_penalties'] ?? 0) }}</span>
    </div>
</div>

<!-- Top Risk Customers -->
@if($topRiskCustomers->count() > 0)
<div class="section-title">Top Risk Customers</div>
<table>
    <thead>
        <tr>
            <th>Customer</th>
            <th>Loans</th>
            <th class="num">Outstanding</th>
            <th class="num">Overdue</th>
            <th>DPD</th>
            <th class="num">Risk Score</th>
            <th>Risk Level</th>
        </tr>
    </thead>
    <tbody>
        @foreach($topRiskCustomers as $customer)
        <tr>
            <td>{{ $customer->name ?? 'N/A' }}</td>
            <td class="num">{{ $customer->total_loans ?? 0 }}</td>
            <td class="num">{{ $fmt($customer->outstanding_balance ?? 0) }}</td>
            <td class="num text-danger">{{ $fmt($customer->overdue_amount ?? 0) }}</td>
            <td class="num">{{ $customer->days_past_due ?? 0 }}</td>
            <td class="num">{{ $customer->risk_score ?? 0 }}</td>
            <td>
                @if(($customer->risk_level ?? 'Low Risk') === 'Defaulted')
                    <span class="badge badge-danger">Defaulted</span>
                @elseif(($customer->risk_level ?? 'Low Risk') === 'High Risk')
                    <span class="badge badge-warning">High Risk</span>
                @elseif(($customer->risk_level ?? 'Low Risk') === 'Medium Risk')
                    <span class="badge badge-secondary">Medium Risk</span>
                @else
                    <span class="badge badge-success">Low Risk</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<!-- Customer Risk Details -->
<div class="section-title">Customer Risk Details</div>
<table>
    <thead>
        <tr>
            <th>Customer</th>
            <th class="num">Loans</th>
            <th class="num">Outstanding</th>
            <th class="num">Overdue</th>
            <th class="num">DPD</th>
            <th class="num">Penalties</th>
            <th class="num">Risk Score</th>
            <th>Risk Level</th>
        </tr>
    </thead>
    <tbody>
        @forelse($customers as $customer)
            @php
                $riskLevel = $customer->risk_level ?? 'Low Risk';
                $rowClass = '';
                if ($riskLevel === 'Defaulted') {
                    $rowClass = 'table-danger';
                } elseif ($riskLevel === 'High Risk') {
                    $rowClass = 'table-warning';
                }
            @endphp
            <tr class="{{ $rowClass }}">
                <td>{{ $customer->name ?? 'N/A' }}</td>
                <td class="num">{{ $customer->total_loans ?? 0 }}</td>
                <td class="num">{{ $fmt($customer->outstanding_balance ?? 0) }}</td>
                <td class="num text-danger">{{ $fmt($customer->overdue_amount ?? 0) }}</td>
                <td class="num">{{ $customer->days_past_due ?? 0 }}</td>
                <td class="num text-warning">{{ $fmt($customer->outstanding_penalties ?? 0) }}</td>
                <td class="num">{{ $customer->risk_score ?? 0 }}</td>
                <td>
                    @if($riskLevel === 'Defaulted')
                        <span class="badge badge-danger">Defaulted</span>
                    @elseif($riskLevel === 'High Risk')
                        <span class="badge badge-warning">High Risk</span>
                    @elseif($riskLevel === 'Medium Risk')
                        <span class="badge badge-secondary">Medium Risk</span>
                    @else
                        <span class="badge badge-success">Low Risk</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center">No risk data available</td>
            </tr>
        @endforelse
    </tbody>
</table>

</body>
</html>
