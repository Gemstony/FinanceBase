<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Fees Report - {{ $dateFrom }} to {{ $dateTo }}</title>
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
        
        .text-right { text-align: right !important; }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .break-word { word-wrap: break-word; overflow-wrap: break-word; word-break: break-all; }
    </style>
</head>
<body>
@php
    $metrics = $report['metrics'] ?? [];
    $summaryByFeeType = $report['summary_by_fee_type'] ?? collect();
    $details = $report['details'] ?? collect();
    $glValidation = $report['gl_validation'] ?? [];

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
        <div class="report-title">Fees Report</div>
        <div class="report-sub">
            <div><strong>Branch:</strong> {{ $subshopName ?? 'All Branches' }}</div>
            <div><strong>From:</strong> {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }}</div>
            <div><strong>To:</strong> {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}</div>
            <div><strong>Generated:</strong> {{ $generatedAt }}</div>
        </div>
    </div>
</div>

<!-- Summary Metrics -->
<div class="summary-box">
    <div class="summary-item">
        <span class="summary-label">Total Charged</span>
        <span class="summary-value">{{ $fmt($metrics['total_charged'] ?? 0) }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Total Paid</span>
        <span class="summary-value text-success">{{ $fmt($metrics['total_paid'] ?? 0) }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Outstanding</span>
        <span class="summary-value text-danger">{{ $fmt($metrics['total_outstanding'] ?? 0) }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Collection Rate</span>
        <span class="summary-value">{{ number_format($metrics['collection_rate'] ?? 0, 1) }}%</span>
    </div>
</div>

<!-- Summary by Fee Type -->
<div class="muted" style="margin-bottom: 10px; font-size: 9px;">
    <strong>Summary by Fee Type:</strong> {{ $summaryByFeeType->count() }} fee types | 
    <strong>Transactions:</strong> {{ $metrics['total_transactions'] ?? 0 }} | 
    <strong>Paid:</strong> {{ $metrics['paid_count'] ?? 0 }} | 
    <strong>Outstanding:</strong> {{ $metrics['outstanding_count'] ?? 0 }}
</div>

@if($summaryByFeeType->count() > 0)
<table>
    <thead>
        <tr>
            <th class="nowrap">Fee Type</th>
            <th class="num nowrap">Charged</th>
            <th class="num nowrap">Paid</th>
            <th class="num nowrap">Outstanding</th>
            <th class="num nowrap">Collection %</th>
        </tr>
    </thead>
    <tbody>
        @foreach($summaryByFeeType as $fee)
        <tr>
            <td>{{ $fee->fee_name }} <span class="muted">({{ $fee->fee_code }})</span></td>
            <td class="num">{{ $fmt($fee->total_charged) }}</td>
            <td class="num">{{ $fmt($fee->total_paid) }}</td>
            <td class="num {{ $fee->total_outstanding > 0 ? 'text-danger' : '' }}">{{ $fmt($fee->total_outstanding) }}</td>
            <td class="num">{{ $fee->collection_rate }}%</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="row-head">
            <th>Total</th>
            <th class="num">{{ $fmt($metrics['total_charged'] ?? 0) }}</th>
            <th class="num">{{ $fmt($metrics['total_paid'] ?? 0) }}</th>
            <th class="num {{ ($metrics['total_outstanding'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $fmt($metrics['total_outstanding'] ?? 0) }}</th>
            <th class="num">{{ number_format($metrics['collection_rate'] ?? 0, 1) }}%</th>
        </tr>
    </tfoot>
</table>
@else
<div class="muted" style="text-align:center; padding: 20px;">No fee data found for selected period</div>
@endif

<!-- GL Validation -->
@if(count($glValidation) > 0)
<div class="muted" style="margin-top: 15px; margin-bottom: 10px;">
    <strong>GL Validation:</strong> 
    Fee Income (GL): {{ $fmt($glValidation['fee_income_gl'] ?? 0) }} | 
    Accounts: {{ $glValidation['fee_income_accounts'] ?? 0 }} | 
    Variance: {{ $fmt(abs(($glValidation['fee_income_gl'] ?? 0) - ($metrics['total_paid'] ?? 0))) }}
</div>
@endif

<!-- Detailed Records -->
<div style="margin-top: 20px;">
    <div class="muted" style="margin-bottom: 10px; font-size: 9px;"><strong>Detailed Fee Records</strong></div>
    
    @if($details->count() > 0)
    <table>
        <thead>
            <tr>
                <th class="nowrap">Date</th>
                <th style="width: 120px;">Loan</th>
                <th>Customer</th>
                <th>Product</th>
                <th>Fee Type</th>
                <th class="num nowrap">Charged</th>
                <th class="num nowrap">Paid</th>
                <th class="num nowrap">Outstanding</th>
                <th class="nowrap">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($details as $detail)
            <tr>
                <td class="nowrap">{{ \Carbon\Carbon::parse($detail->applied_on)->format('d/m/Y') }}</td>
                <td class="break-word" style="max-width: 120px;">{{ $detail->loan_code ?? '' }}</td>
                <td>{{ $detail->customer_name ?? '' }}</td>
                <td>{{ $detail->loan_product_name ?? '' }}</td>
                <td>{{ $detail->fee_name ?? '' }}</td>
                <td class="num">{{ $fmt($detail->amount) }}</td>
                <td class="num">{{ $fmt($detail->paid_amount) }}</td>
                <td class="num {{ $detail->outstanding_amount > 0 ? 'text-danger font-weight-bold' : 'text-success' }}">
                    {{ $fmt($detail->outstanding_amount) }}
                </td>
                <td>
                    @if($detail->outstanding_amount > 0)
                        <span class="badge badge-warning">Outstanding</span>
                    @else
                        <span class="badge badge-success">Paid</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="muted" style="text-align:center; padding: 20px;">No detailed records found</div>
    @endif
</div>

<div class="muted" style="margin-top: 20px; padding-top: 10px; border-top: 1px solid #000; font-size: 8px; text-align: center;">
    Generated by FinanceBase System | {{ $generatedAt }}
</div>

</body>
</html>
