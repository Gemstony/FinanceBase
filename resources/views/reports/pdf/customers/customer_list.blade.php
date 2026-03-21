<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Customer List Report</title>
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
        .break-word { word-wrap: break-word; overflow-wrap: break-word; word-break: break-all; }
        
        .table-danger { background-color: #f8d7da; }
        .table-warning { background-color: #fff3cd; }
    </style>
</head>
<body>
@php
    $customers = $report['customers'] ?? collect();
    $metrics = $report['metrics'] ?? [];

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
        <div class="report-title">Customer List Report</div>
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
        <span class="summary-label">Active Customers</span>
        <span class="summary-value text-success">{{ $metrics['active_customers'] ?? 0 }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">With Loans</span>
        <span class="summary-value text-info">{{ $metrics['customers_with_loans'] ?? 0 }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Defaulted</span>
        <span class="summary-value text-danger">{{ $metrics['defaulted_customers'] ?? 0 }}</span>
    </div>
</div>

<!-- Customer Table -->
@if($customers->count() > 0)
<table>
    <thead>
        <tr>
            <th>Customer</th>
            <th>Phone</th>
            <th>Status</th>
            <th class="nowrap">Total Loans</th>
            <th class="nowrap">Active</th>
            <th class="num nowrap">Disbursed</th>
            <th class="num nowrap">Repaid</th>
            <th class="num nowrap">Outstanding</th>
            <th class="num nowrap">Overdue</th>
            <th>Risk Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($customers as $customer)
        <tr class="{{ $customer->risk_status == 'Defaulted' ? 'table-danger' : ($customer->risk_status == 'At Risk' ? 'table-warning' : '') }}">
            <td>{{ $customer->name ?? '' }}</td>
            <td>{{ $customer->phone ?? '' }}</td>
            <td>
                @if($customer->is_active)
                    <span class="badge badge-success">Active</span>
                @else
                    <span class="badge badge-secondary">Inactive</span>
                @endif
            </td>
            <td class="nowrap">{{ $customer->total_loans ?? 0 }}</td>
            <td class="nowrap">{{ $customer->active_loans ?? 0 }}</td>
            <td class="num">{{ $fmt($customer->total_disbursed) }}</td>
            <td class="num">{{ $fmt($customer->total_repaid) }}</td>
            <td class="num {{ $customer->outstanding_balance > 0 ? 'text-danger font-weight-bold' : 'text-success' }}">
                {{ $fmt($customer->outstanding_balance) }}
            </td>
            <td class="num {{ $customer->overdue_amount > 0 ? 'text-danger font-weight-bold' : '' }}">
                {{ $fmt($customer->overdue_amount) }}
            </td>
            <td>
                @switch($customer->risk_status)
                    @case('Defaulted')
                        <span class="badge badge-danger">Defaulted</span>
                        @break
                    @case('At Risk')
                        <span class="badge badge-warning">At Risk</span>
                        @break
                    @case('Good')
                        <span class="badge badge-success">Good</span>
                        @break
                    @default
                        <span class="badge badge-secondary">{{ $customer->risk_status }}</span>
                @endswitch
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<p class="text-center text-muted">No customers found</p>
@endif

</body>
</html>