<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Customer Performance Report</title>
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
        .badge-info { background: #17a2b8; color: #fff; }
        .badge-warning { background: #ffc107; color: #000; }
        .badge-orange { background: #fd7e14; color: #fff; }
        .badge-danger { background: #dc3545; color: #fff; }
        .badge-secondary { background: #6c757d; color: #fff; }
        
        .text-right { text-align: right !important; }
        .text-success { color: #28a745; }
        .text-info { color: #17a2b8; }
        .text-warning { color: #ffc107; }
        .text-danger { color: #dc3545; }
        .break-word { word-wrap: break-word; overflow-wrap: break-word; word-break: break-all; }
        
        .table-success { background-color: #d4edda; }
        .table-info { background-color: #d1ecf1; }
        .table-warning { background-color: #fff3cd; }
        .table-danger { background-color: #f8d7da; }
        
        .section-title {
            font-size: 11px;
            font-weight: 700;
            margin: 15px 0 8px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #000;
        }
    </style>
</head>
<body>
@php
    $report = $report ?? [];
    $customers = $report['customers'] ?? collect();
    $metrics = $report['metrics'] ?? [];
    $topPerformers = $report['top_performers'] ?? collect();
    $worstPerformers = $report['worst_performers'] ?? collect();

    $shopName = $shop->name ?? 'Institution';
    $shopEmail = $shop->email ?? null;
    $shopPhone = $shop->phone ?? null;
    $shopWebsite = $shop->website ?? null;
    $shopAddress = $shop->address ?? null;

    $fmt = function ($v) {
        return number_format((float) ($v ?? 0), 2);
    };
    
    $pct = function ($v) {
        return number_format((float) ($v ?? 0) * 100, 1) . '%';
    };
    
    $performanceColors = [
        'Excellent' => 'success',
        'Good' => 'info',
        'Average' => 'warning',
        'Poor' => 'orange',
        'Defaulted' => 'danger',
    ];
@endphp

<div class="header">
    <div class="header-left">
        <div class="header-logo">
            @if(!empty($shopLogoPath) && file_exists($shopLogoPath))
                <img class="logo" src="{{ $shopLogoPath }}" alt="Logo">
            @endif
        </div>
        <div class="header-info">
            <div class="inst-name">{{ $shopName }}</div>
            <div class="inst-meta">
                @if($shopAddress)
                    <div><strong>Address:</strong> {{ $shopAddress }}</div>
                @endif
                @if($shopPhone)
                    <div><strong>Phone:</strong> {{ $shopPhone }}</div>
                @endif
                @if($shopEmail)
                    <div><strong>Email:</strong> {{ $shopEmail }}</div>
                @endif
                @if($shopWebsite)
                    <div><strong>Website:</strong> {{ $shopWebsite }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="header-right">
        <div class="report-title">Customer Performance Report</div>
        <div class="report-sub">
            <div><strong>Branch:</strong> {{ $subshopName ?? 'All Branches' }}</div>
            <div><strong>Generated:</strong> {{ $generatedAt }}</div>
        </div>
    </div>
</div>

<!-- Summary Box -->
<div class="summary-box">
    <div class="summary-item">
        <span class="summary-label">Total Customers</span>
        <span class="summary-value">{{ $metrics['total_customers'] ?? 0 }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Excellent</span>
        <span class="summary-value text-success">{{ $metrics['excellent_count'] ?? 0 }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Good</span>
        <span class="summary-value text-info">{{ $metrics['good_count'] ?? 0 }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Average</span>
        <span class="summary-value text-warning">{{ $metrics['average_count'] ?? 0 }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Poor</span>
        <span class="summary-value" style="color: #fd7e14;">{{ $metrics['poor_count'] ?? 0 }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Defaulted</span>
        <span class="summary-value text-danger">{{ $metrics['defaulted_count'] ?? 0 }}</span>
    </div>
</div>

<!-- Financial Summary -->
<div class="summary-box">
    <div class="summary-item">
        <span class="summary-label">Average Score</span>
        <span class="summary-value">{{ $metrics['average_score'] ?? 0 }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Total Disbursed</span>
        <span class="summary-value text-success">{{ $fmt($metrics['total_disbursed'] ?? 0) }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Total Paid</span>
        <span class="summary-value text-info">{{ $fmt($metrics['total_paid'] ?? 0) }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Total Outstanding</span>
        <span class="summary-value text-danger">{{ $fmt($metrics['total_outstanding'] ?? 0) }}</span>
    </div>
</div>

<!-- Top Performers -->
@if($topPerformers->count() > 0)
<div class="section-title">Top 10 Performers</div>
<table>
    <thead>
        <tr>
            <th>Rank</th>
            <th>Customer</th>
            <th class="num">Score</th>
            <th>Performance</th>
        </tr>
    </thead>
    <tbody>
        @foreach($topPerformers as $index => $customer)
        <tr>
            <td class="nowrap">{{ $index + 1 }}</td>
            <td>{{ $customer->name ?? '' }}</td>
            <td class="num"><strong>{{ $customer->performance_score ?? 0 }}</strong></td>
            <td>
                @php $level = $customer->performance_level ?? 'Average'; @endphp
                <span class="badge badge-{{ $performanceColors[$level] ?? 'secondary' }}">{{ $level }}</span>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<!-- Worst Performers -->
@if($worstPerformers->count() > 0)
<div class="section-title">Bottom 10 Performers</div>
<table>
    <thead>
        <tr>
            <th>Rank</th>
            <th>Customer</th>
            <th class="num">Score</th>
            <th>Performance</th>
        </tr>
    </thead>
    <tbody>
        @foreach($worstPerformers as $index => $customer)
        <tr>
            <td class="nowrap">{{ $customers->count() - $worstPerformers->count() + $index + 1 }}</td>
            <td>{{ $customer->name ?? '' }}</td>
            <td class="num"><strong>{{ $customer->performance_score ?? 0 }}</strong></td>
            <td>
                @php $level = $customer->performance_level ?? 'Average'; @endphp
                <span class="badge badge-{{ $performanceColors[$level] ?? 'secondary' }}">{{ $level }}</span>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<!-- Customer Performance Table -->
@if($customers->count() > 0)
<div class="section-title">Customer Performance Details</div>
<table>
    <thead>
        <tr>
            <th>Customer</th>
            <th class="nowrap">Loans</th>
            <th class="num nowrap">Disbursed</th>
            <th class="num nowrap">Paid</th>
            <th class="num nowrap">Due</th>
            <th class="nowrap">R/ Rate</th>
            <th class="nowrap">On-Time</th>
            <th class="nowrap">Late</th>
            <th class="nowrap">Missed</th>
            <th class="num nowrap">Penalties</th>
            <th class="num">Score</th>
            <th>Performance</th>
        </tr>
    </thead>
    <tbody>
        @foreach($customers as $index => $customer)
        @php
            $performanceLevel = $customer->performance_level ?? 'Average';
            $rowClass = '';
            if ($performanceLevel === 'Excellent') {
                $rowClass = 'table-success';
            } elseif ($performanceLevel === 'Good') {
                $rowClass = 'table-info';
            } elseif ($performanceLevel === 'Poor') {
                $rowClass = 'table-warning';
            } elseif ($performanceLevel === 'Defaulted') {
                $rowClass = 'table-danger';
            }
        @endphp
        <tr class="{{ $rowClass }}">
            <td>
                <strong>{{ $customer->name ?? '' }}</strong><br>
                <small>{{ $customer->phone ?? '' }}</small>
            </td>
            <td class="nowrap">{{ $customer->total_loans ?? 0 }}</td>
            <td class="num">{{ $fmt($customer->total_disbursed ?? 0) }}</td>
            <td class="num">{{ $fmt($customer->total_paid ?? 0) }}</td>
            <td class="num">{{ $fmt($customer->total_due ?? 0) }}</td>
            <td class="nowrap">{{ $pct($customer->repayment_rate ?? 0) }}</td>
            <td class="nowrap text-success">{{ $customer->on_time_payments ?? 0 }}</td>
            <td class="nowrap text-warning">{{ $customer->late_payments ?? 0 }}</td>
            <td class="nowrap text-danger">{{ $customer->missed_payments ?? 0 }}</td>
            <td class="num">{{ $fmt($customer->total_penalties ?? 0) }}</td>
            <td class="num"><strong>{{ $customer->performance_score ?? 0 }}</strong></td>
            <td>
                <span class="badge badge-{{ $performanceColors[$performanceLevel] ?? 'secondary' }}">{{ $performanceLevel }}</span>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<p class="text-center text-muted">No performance data available</p>
@endif

</body>
</html>
