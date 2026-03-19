<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Loan Repayments Report - {{ $subshopName ?? 'All Branches' }}</title>
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

        .block-title { font-size: 10px; font-weight: 700; margin: 14px 0 6px 0; text-transform: uppercase; }

        .kpi { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .kpi td { border: 1px solid #000; padding: 7px 6px; }
        .kpi .kpi-label { font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .kpi .kpi-value { font-size: 11px; font-weight: 700; margin-top: 2px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 6px; white-space: normal; word-break: break-word; overflow-wrap: anywhere; }
        th { background: #fff; font-weight: 700; text-align: left; }
        td.num, th.num { text-align: right; }

        .col-loan-code { width: 70px; }

        .footer { border-top: 1px solid #000; margin-top: 14px; padding-top: 6px; font-size: 8px; }
        .footer-left { display: inline-block; vertical-align: top; width: 70%; }
        .footer-right { display: inline-block; vertical-align: top; width: 29%; text-align: right; }
    </style>
</head>
<body>

@php
    $summary = $report['summary'] ?? [];

    $shopName = $shop->name ?? 'Institution';
    $shopEmail = $shop->email ?? null;
    $shopPhone = $shop->phone ?? null;
    $shopWebsite = $shop->website ?? null;
    $shopAddress = $shop->address ?? null;
    $shopRegion = $shop->region ?? null;
    $shopCountry = $shop->country ?? null;

    $periodLabel = \Carbon\Carbon::parse($dateFrom)->format('d M Y') . ' - ' . \Carbon\Carbon::parse($dateTo)->format('d M Y');
    $branchLabel = $subshopName ?: 'All Branches';
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
                @if($shopRegion || $shopCountry)
                    <div>
                        @if($shopRegion)<strong>Region:</strong> {{ $shopRegion }}@endif
                        @if($shopRegion && $shopCountry) | @endif
                        @if($shopCountry)<strong>Country:</strong> {{ $shopCountry }}@endif
                    </div>
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
        <div class="report-title">Loan Repayments Report</div>
        <div class="report-sub">
            <div><strong>Branch:</strong> {{ $branchLabel }}</div>
            <div><strong>Period:</strong> {{ $periodLabel }}</div>
            <div><strong>Generated:</strong> {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
        </div>
    </div>
</div>

<table class="kpi">
    <tr>
        <td>
            <div class="kpi-label">Total Collected</div>
            <div class="kpi-value">{{ number_format($summary['total_repayments_collected'] ?? 0, 2) }}</div>
        </td>
        <td>
            <div class="kpi-label">Transactions</div>
            <div class="kpi-value">{{ number_format($summary['repayment_transactions'] ?? 0) }}</div>
        </td>
        <td>
            <div class="kpi-label">Avg Payment</div>
            <div class="kpi-value">{{ number_format($summary['average_payment_amount'] ?? 0, 2) }}</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="kpi-label">On-Time Rate</div>
            <div class="kpi-value">{{ number_format($summary['on_time_repayment_rate_pct'] ?? 0, 2) }}%</div>
        </td>
        <td>
            <div class="kpi-label">Late Rate</div>
            <div class="kpi-value">{{ number_format($summary['late_payment_rate_pct'] ?? 0, 2) }}%</div>
        </td>
        <td>
            <div class="kpi-label">Efficiency</div>
            <div class="kpi-value">{{ number_format($summary['collection_efficiency_pct'] ?? 0, 2) }}%</div>
        </td>
    </tr>
    <tr>
        <td colspan="3">
            <div class="kpi-label">Recovery Rate</div>
            <div class="kpi-value">{{ number_format($summary['recovery_rate_pct'] ?? 0, 2) }}%</div>
        </td>
    </tr>
</table>

<div class="block-title">Recovery Tracking (Overdue-Based)</div>
<table>
    <thead>
        <tr>
            <th>Metric</th>
            <th class="num">Value</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Total Overdue Amount</td><td class="num">{{ number_format($report['recovery']['total_overdue_amount'] ?? 0, 2) }}</td></tr>
        <tr><td>Recovered Amount</td><td class="num">{{ number_format($report['recovery']['recovery_collected'] ?? 0, 2) }}</td></tr>
        <tr><td>Recovery Transactions</td><td class="num">{{ number_format($report['recovery']['recovery_transactions'] ?? 0) }}</td></tr>
        <tr><td>Recovery Rate</td><td class="num">{{ number_format($report['recovery']['recovery_rate_pct'] ?? 0, 2) }}%</td></tr>
    </tbody>
</table>

<div class="block-title">On-Time vs Late</div>
<table>
    <thead>
        <tr>
            <th>Metric</th>
            <th class="num">Value</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>On-Time Payments</td><td class="num">{{ number_format($report['on_time_vs_late']['on_time_payments'] ?? 0) }}</td></tr>
        <tr><td>Late Payments</td><td class="num">{{ number_format($report['on_time_vs_late']['late_payments'] ?? 0) }}</td></tr>
        <tr><td>On-Time Amount</td><td class="num">{{ number_format($report['on_time_vs_late']['on_time_amount'] ?? 0, 2) }}</td></tr>
        <tr><td>Late Amount</td><td class="num">{{ number_format($report['on_time_vs_late']['late_amount'] ?? 0, 2) }}</td></tr>
        <tr><td>On-Time Rate</td><td class="num">{{ number_format($report['on_time_vs_late']['on_time_rate'] ?? 0, 2) }}%</td></tr>
    </tbody>
</table>

<div class="block-title">Scheduled vs Actual</div>
<table>
    <thead>
        <tr>
            <th>Metric</th>
            <th class="num">Value</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Scheduled Amount</td><td class="num">{{ number_format($report['scheduled_vs_actual']['scheduled_amount'] ?? 0, 2) }}</td></tr>
        <tr><td>Actual Collected</td><td class="num">{{ number_format($report['scheduled_vs_actual']['actual_collected'] ?? 0, 2) }}</td></tr>
        <tr><td>Variance (Scheduled - Actual)</td><td class="num">{{ number_format($report['scheduled_vs_actual']['variance'] ?? 0, 2) }}</td></tr>
        <tr><td>Collection Efficiency</td><td class="num">{{ number_format($report['scheduled_vs_actual']['collection_efficiency'] ?? 0, 2) }}%</td></tr>
    </tbody>
</table>

<div class="block-title">Repayment Aging (Late Payments)</div>
<table>
    <thead>
        <tr>
            <th>Bucket (Days Late)</th>
            <th class="num">Payments</th>
            <th class="num">Late Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach(($report['aging']['buckets'] ?? []) as $b)
            <tr>
                <td>{{ $b['bucket'] ?? '' }}</td>
                <td class="num">{{ number_format($b['payments'] ?? 0) }}</td>
                <td class="num">{{ number_format($b['amount'] ?? 0, 2) }}</td>
            </tr>
        @endforeach
        @if(empty($report['aging']['buckets'] ?? []))
            <tr><td colspan="3" class="num">No data</td></tr>
        @endif
    </tbody>
</table>

<div class="block-title">Repayments by Product</div>
<table>
    <thead>
        <tr>
            <th>Product</th>
            <th class="num">Payments</th>
            <th class="num">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach(($report['by_product'] ?? []) as $row)
            <tr>
                <td>{{ $row['product'] ?? '' }}</td>
                <td class="num">{{ number_format($row['payments'] ?? 0) }}</td>
                <td class="num">{{ number_format($row['amount'] ?? 0, 2) }}</td>
            </tr>
        @endforeach
        @if(empty($report['by_product'] ?? []))
            <tr><td colspan="3" class="num">No data</td></tr>
        @endif
    </tbody>
</table>

<div class="block-title">Repayments by Branch</div>
<table>
    <thead>
        <tr>
            <th>Branch</th>
            <th class="num">Payments</th>
            <th class="num">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach(($report['by_branch'] ?? []) as $row)
            <tr>
                <td>{{ $row['branch'] ?? '' }}</td>
                <td class="num">{{ number_format($row['payments'] ?? 0) }}</td>
                <td class="num">{{ number_format($row['amount'] ?? 0, 2) }}</td>
            </tr>
        @endforeach
        @if(empty($report['by_branch'] ?? []))
            <tr><td colspan="3" class="num">No data</td></tr>
        @endif
    </tbody>
</table>

<div class="block-title">Payment Methods</div>
<table>
    <thead>
        <tr>
            <th>Method</th>
            <th class="num">Payments</th>
            <th class="num">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach(($report['payment_methods'] ?? []) as $row)
            <tr>
                <td>{{ ucfirst(str_replace('_',' ', $row['payment_method'] ?? '')) }}</td>
                <td class="num">{{ number_format($row['payments'] ?? 0) }}</td>
                <td class="num">{{ number_format($row['amount'] ?? 0, 2) }}</td>
            </tr>
        @endforeach
        @if(empty($report['payment_methods'] ?? []))
            <tr><td colspan="3" class="num">No data</td></tr>
        @endif
    </tbody>
</table>

<div class="block-title">Repayments by Officer</div>
<table>
    <thead>
        <tr>
            <th>Officer</th>
            <th class="num">Payments</th>
            <th class="num">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach(($report['by_officer'] ?? []) as $row)
            <tr>
                <td>{{ $row['officer'] ?? '' }}</td>
                <td class="num">{{ number_format($row['payments'] ?? 0) }}</td>
                <td class="num">{{ number_format($row['amount'] ?? 0, 2) }}</td>
            </tr>
        @endforeach
        @if(empty($report['by_officer'] ?? []))
            <tr><td colspan="3" class="num">No data</td></tr>
        @endif
    </tbody>
</table>

<div class="block-title">Partial vs Full</div>
<table>
    <thead>
        <tr>
            <th>Metric</th>
            <th class="num">Value</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Full Payments (Count)</td><td class="num">{{ number_format($report['partial_vs_full']['full_payments_count'] ?? 0) }}</td></tr>
        <tr><td>Partial Payments (Count)</td><td class="num">{{ number_format($report['partial_vs_full']['partial_payments_count'] ?? 0) }}</td></tr>
        <tr><td>Full Payments (Amount)</td><td class="num">{{ number_format($report['partial_vs_full']['full_payments_amount'] ?? 0, 2) }}</td></tr>
        <tr><td>Partial Payments (Amount)</td><td class="num">{{ number_format($report['partial_vs_full']['partial_payments_amount'] ?? 0, 2) }}</td></tr>
    </tbody>
</table>

<div class="block-title">Top Paying Customers</div>
<table>
    <thead>
        <tr>
            <th>Customer</th>
            <th class="num">Payments</th>
            <th class="num">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach(($report['top_customers'] ?? []) as $row)
            <tr>
                <td>{{ $row['customer'] ?? '' }}</td>
                <td class="num">{{ number_format($row['payments'] ?? 0) }}</td>
                <td class="num">{{ number_format($row['amount'] ?? 0, 2) }}</td>
            </tr>
        @endforeach
        @if(empty($report['top_customers'] ?? []))
            <tr><td colspan="3" class="num">No data</td></tr>
        @endif
    </tbody>
</table>

<div class="block-title">Officer Performance</div>
<table>
    <thead>
        <tr>
            <th>Officer</th>
            <th class="num">Payments</th>
            <th class="num">Amount</th>
            <th class="num">On-Time Rate</th>
            <th class="num">Recovery Rate</th>
        </tr>
    </thead>
    <tbody>
        @foreach(($report['officer_performance'] ?? []) as $row)
            <tr>
                <td>{{ $row['officer'] ?? '' }}</td>
                <td class="num">{{ number_format($row['payments'] ?? 0) }}</td>
                <td class="num">{{ number_format($row['amount'] ?? 0, 2) }}</td>
                <td class="num">{{ number_format($row['on_time_rate'] ?? 0, 2) }}%</td>
                <td class="num">{{ number_format($row['recovery_rate_pct'] ?? 0, 2) }}%</td>
            </tr>
        @endforeach
        @if(empty($report['officer_performance'] ?? []))
            <tr><td colspan="5" class="num">No data</td></tr>
        @endif
    </tbody>
</table>

@php
    $loanItems = ($report['loan_level'] ?? null) instanceof \Illuminate\Pagination\LengthAwarePaginator
        ? ($report['loan_level']->items() ?? [])
        : ($report['loan_level'] ?? []);

    $installmentItems = ($report['installment_level'] ?? null) instanceof \Illuminate\Pagination\LengthAwarePaginator
        ? ($report['installment_level']->items() ?? [])
        : ($report['installment_level'] ?? []);
@endphp

<div class="block-title">Loan-Level Repayment Table</div>
<table>
    <thead>
        <tr>
            <th>Loan Code</th>
            <th>Customer</th>
            <th>Product</th>
            <th>Branch</th>
            <th>Status</th>
            <th class="num">Total Due</th>
            <th class="num">Paid (Period)</th>
            <th class="num">Total Paid</th>
            <th class="num">Outstanding</th>
            <th>Last Payment</th>
        </tr>
    </thead>
    <tbody>
        @foreach($loanItems as $row)
            @php $r = is_array($row) ? (object) $row : $row; @endphp
            <tr>
                <td>{{ $r->loan_code ?? '' }}</td>
                <td>{{ $r->customer ?? '' }}</td>
                <td>{{ $r->product ?? '' }}</td>
                <td>{{ $r->branch ?? '' }}</td>
                <td>{{ ucfirst(str_replace('_',' ', $r->status ?? '')) }}</td>
                <td class="num">{{ number_format($r->total_due ?? 0, 2) }}</td>
                <td class="num">{{ number_format($r->period_paid ?? 0, 2) }}</td>
                <td class="num">{{ number_format($r->total_paid ?? 0, 2) }}</td>
                <td class="num">{{ number_format($r->outstanding ?? 0, 2) }}</td>
                <td>{{ $r->last_payment_date ?? '' }}</td>
            </tr>
        @endforeach
        @if(empty($loanItems))
            <tr><td colspan="10" class="num">No data</td></tr>
        @endif
    </tbody>
</table>

<div class="block-title">Installment-Level Tracking</div>
<table>
    <thead>
        <tr>
            <th>Customer</th>
            <th class="num">Installment #</th>
            <th>Due Date</th>
            <th>Paid Date</th>
            <th>Status</th>
            <th class="num">Total Due</th>
            <th class="num">Amount Paid</th>
            <th class="num">Outstanding</th>
            <th class="num">Paid (Period)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($installmentItems as $row)
            @php $r = is_array($row) ? (object) $row : $row; @endphp
            <tr>
                <td>{{ $r->customer ?? '' }}</td>
                <td class="num">{{ number_format($r->installment_number ?? 0) }}</td>
                <td>{{ $r->due_date ?? '' }}</td>
                <td>{{ $r->paid_date ?? '' }}</td>
                <td>{{ ucfirst(str_replace('_',' ', $r->status ?? '')) }}</td>
                <td class="num">{{ number_format($r->total_due ?? 0, 2) }}</td>
                <td class="num">{{ number_format($r->amount_paid ?? 0, 2) }}</td>
                <td class="num">{{ number_format($r->outstanding_amount ?? 0, 2) }}</td>
                <td class="num">{{ number_format($r->paid_in_period ?? 0, 2) }}</td>
            </tr>
        @endforeach
        @if(empty($installmentItems))
            <tr><td colspan="9" class="num">No data</td></tr>
        @endif
    </tbody>
</table>

<div class="footer">
    <div class="footer-left">
        <strong>Filters:</strong>
        Period {{ $dateFrom }} to {{ $dateTo }}
        @if(!empty($subshopName)) | Branch {{ $subshopName }} @endif
    </div>
    <div class="footer-right">
        Page <script type="text/php">if (isset($pdf)) { echo $pdf->getPageNumber(); }</script>
        / <script type="text/php">if (isset($pdf)) { echo $pdf->getPageCount(); }</script>
    </div>
</div>

</body>
</html>
