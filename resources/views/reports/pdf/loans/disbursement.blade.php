<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Loan Disbursement Report - {{ $subshopName ?? 'All Branches' }}</title>
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

        .section { margin-top: 14px; }
        .section-title { font-weight: 700; text-transform: uppercase; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 6px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #fff; font-weight: 700; text-align: left; }
        .text-right { text-align: right; }
        .muted { color: #555; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>

@php
  $filters = $report['filters'] ?? [];
  $s = $report['summary'] ?? [];
  $nvr = $report['new_vs_repeat'] ?? [];
  $dvr = $report['disbursement_vs_repayment'] ?? [];
  $eff = $report['efficiency'] ?? [];
  $sa = $report['status_analysis'] ?? [];

  $shopName = $shop->name ?? 'Institution';
  $shopEmail = $shop->email ?? null;
  $shopPhone = $shop->phone ?? null;
  $shopWebsite = $shop->website ?? null;
  $shopAddress = $shop->address ?? null;
  $shopRegion = $shop->region ?? null;
  $shopCountry = $shop->country ?? null;

  $periodLabel = \Carbon\Carbon::parse($dateFrom)->format('d M Y') . ' - ' . \Carbon\Carbon::parse($dateTo)->format('d M Y');
  $branchLabel = ($subshopName ?? null) ?: 'All Branches';
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
        <div class="report-title">Loan Disbursement Report</div>
        <div class="report-sub">
            <div><strong>Branch:</strong> {{ $branchLabel }}</div>
            <div><strong>Period:</strong> {{ $periodLabel }}</div>
            <div><strong>Generated:</strong> {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-title">Summary KPIs</div>
    <table>
        <tr>
            <th>Metric</th>
            <th class="text-right">Value</th>
        </tr>
        <tr>
            <td>Total Loans Disbursed</td>
            <td class="text-right">{{ number_format($s['total_loans_disbursed'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Total Disbursement Amount</td>
            <td class="text-right">{{ number_format($s['total_disbursement_amount'] ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td>Average Loan Size</td>
            <td class="text-right">{{ number_format($s['average_loan_size'] ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td>New Borrowers</td>
            <td class="text-right">{{ number_format($s['new_borrowers'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Repeat Borrowers</td>
            <td class="text-right">{{ number_format($s['repeat_borrowers'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Disbursement Growth (%)</td>
            <td class="text-right">{{ number_format($s['disbursement_growth_pct'] ?? 0, 2) }}%</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Disbursement Trends</div>
    <table>
        <tr>
            <th>Period</th>
            <th class="text-right">Loans</th>
            <th class="text-right">Amount</th>
        </tr>
        @foreach(($report['trends']['rows'] ?? []) as $r)
            <tr>
                <td>{{ $r['period'] ?? '' }}</td>
                <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($r['amount'] ?? 0, 2) }}</td>
            </tr>
        @endforeach
        @if(empty($report['trends']['rows'] ?? []))
            <tr><td colspan="3" class="text-center muted">No data</td></tr>
        @endif
    </table>
</div>

<div class="section">
    <div class="section-title">Disbursement by Product</div>
    <table>
        <tr>
            <th>Product</th>
            <th class="text-right">Loans</th>
            <th class="text-right">Amount</th>
            <th class="text-right">Avg Loan Size</th>
        </tr>
        @foreach(($report['by_product'] ?? []) as $r)
            <tr>
                <td>{{ $r['product'] ?? '' }}</td>
                <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($r['amount'] ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($r['avg_loan_size'] ?? 0, 2) }}</td>
            </tr>
        @endforeach
        @if(empty($report['by_product'] ?? []))
            <tr><td colspan="4" class="text-center muted">No data</td></tr>
        @endif
    </table>
</div>

<div class="section">
    <div class="section-title">Disbursement by Branch</div>
    <table>
        <tr>
            <th>Branch</th>
            <th class="text-right">Loans</th>
            <th class="text-right">Amount</th>
            <th class="text-right">Avg Loan Size</th>
        </tr>
        @foreach(($report['by_branch'] ?? []) as $r)
            <tr>
                <td>{{ $r['branch'] ?? '' }}</td>
                <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($r['amount'] ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($r['avg_loan_size'] ?? 0, 2) }}</td>
            </tr>
        @endforeach
        @if(empty($report['by_branch'] ?? []))
            <tr><td colspan="4" class="text-center muted">No data</td></tr>
        @endif
    </table>
</div>

<div class="section">
    <div class="section-title">Disbursement by Officer</div>
    <table>
        <tr>
            <th>Officer</th>
            <th class="text-right">Loans</th>
            <th class="text-right">Amount</th>
            <th class="text-right">Avg Loan Size</th>
        </tr>
        @foreach(($report['by_officer'] ?? []) as $r)
            <tr>
                <td>{{ $r['officer'] ?? '' }}</td>
                <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($r['amount'] ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($r['avg_loan_size'] ?? 0, 2) }}</td>
            </tr>
        @endforeach
        @if(empty($report['by_officer'] ?? []))
            <tr><td colspan="4" class="text-center muted">No data</td></tr>
        @endif
    </table>
</div>

<div class="section">
    <div class="section-title">New vs Repeat Borrowers</div>
    <table>
        <tr>
            <th>Category</th>
            <th class="text-right">Customers</th>
            <th class="text-right">Amount</th>
        </tr>
        <tr>
            <td>New Borrowers</td>
            <td class="text-right">{{ number_format($nvr['new']['count'] ?? 0) }}</td>
            <td class="text-right">{{ number_format($nvr['new']['amount'] ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td>Repeat Borrowers</td>
            <td class="text-right">{{ number_format($nvr['repeat']['count'] ?? 0) }}</td>
            <td class="text-right">{{ number_format($nvr['repeat']['amount'] ?? 0, 2) }}</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Loan Size Distribution</div>
    <table>
        <tr>
            <th>Bucket</th>
            <th class="text-right">Loans</th>
            <th class="text-right">Amount</th>
        </tr>
        @foreach(($report['loan_size_distribution'] ?? []) as $r)
            <tr>
                <td>{{ $r['bucket'] ?? '' }}</td>
                <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($r['amount'] ?? 0, 2) }}</td>
            </tr>
        @endforeach
        @if(empty($report['loan_size_distribution'] ?? []))
            <tr><td colspan="3" class="text-center muted">No data</td></tr>
        @endif
    </table>
</div>

<div class="section">
    <div class="section-title">Status Analysis</div>
    <table>
        <tr>
            <th>Status</th>
            <th class="text-right">Count</th>
            <th class="text-right">Amount</th>
        </tr>
        <tr>
            <td>Approved Not Disbursed</td>
            <td class="text-right">{{ number_format($sa['approved_not_disbursed']['count'] ?? 0) }}</td>
            <td class="text-right">{{ number_format($sa['approved_not_disbursed']['amount'] ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td>Disbursed</td>
            <td class="text-right">{{ number_format($sa['disbursed']['count'] ?? 0) }}</td>
            <td class="text-right">{{ number_format($sa['disbursed']['amount'] ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td>Cancelled</td>
            <td class="text-right">{{ number_format($sa['cancelled']['count'] ?? 0) }}</td>
            <td class="text-right">{{ number_format($sa['cancelled']['amount'] ?? 0, 2) }}</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Method Analysis</div>
    <table>
        <tr>
            <th>Method</th>
            <th class="text-right">Loans</th>
            <th class="text-right">Amount</th>
        </tr>
        @foreach(($report['method_analysis'] ?? []) as $r)
            <tr>
                <td>{{ $r['method'] ?? '' }}</td>
                <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($r['amount'] ?? 0, 2) }}</td>
            </tr>
        @endforeach
        @if(empty($report['method_analysis'] ?? []))
            <tr><td colspan="3" class="text-center muted">No data</td></tr>
        @endif
    </table>
</div>

<div class="section">
    <div class="section-title">Disbursement vs Repayment</div>
    <table>
        <tr><th>Metric</th><th class="text-right">Value</th></tr>
        <tr><td>Total Disbursed</td><td class="text-right">{{ number_format($dvr['total_disbursed'] ?? 0, 2) }}</td></tr>
        <tr><td>Total Repaid</td><td class="text-right">{{ number_format($dvr['total_repaid'] ?? 0, 2) }}</td></tr>
        <tr><td><strong>Net Portfolio Growth</strong></td><td class="text-right"><strong>{{ number_format($dvr['net_portfolio_growth'] ?? 0, 2) }}</strong></td></tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Top Borrowers (Top 10)</div>
    <table>
        <tr>
            <th>Customer</th>
            <th class="text-right">Loans</th>
            <th class="text-right">Total Disbursed</th>
        </tr>
        @foreach(($report['top_borrowers'] ?? []) as $r)
            <tr>
                <td>{{ $r['customer'] ?? '' }}</td>
                <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($r['amount'] ?? 0, 2) }}</td>
            </tr>
        @endforeach
        @if(empty($report['top_borrowers'] ?? []))
            <tr><td colspan="3" class="text-center muted">No data</td></tr>
        @endif
    </table>
</div>

<div class="section">
    <div class="section-title">Efficiency Metrics</div>
    <table>
        <tr><th>Metric</th><th class="text-right">Value</th></tr>
        <tr><td>Average Time to Disburse (days)</td><td class="text-right">{{ number_format($eff['avg_time_to_disburse_days'] ?? 0, 2) }}</td></tr>
        <tr><td>Approval Conversion Rate (%)</td><td class="text-right">{{ number_format($eff['approval_conversion_rate_pct'] ?? 0, 2) }}%</td></tr>
    </table>
</div>

<div class="page-break"></div>

<div class="section">
    <div class="section-title">Detailed Disbursement List</div>
    <table>
        <tr>
            <th>Loan</th>
            <th>Customer</th>
            <th>Product</th>
            <th>Branch</th>
            <th>Officer</th>
            <th>Disbursement Date</th>
            <th class="text-right">Amount</th>
            <th>Method</th>
            <th>Status</th>
        </tr>
        @foreach(($report['detailed_list'] ?? []) as $row)
            <tr>
                <td>{{ $row->loan_code ?? '' }}</td>
                <td>{{ $row->customer ?? '' }}</td>
                <td>{{ $row->product ?? '' }}</td>
                <td>{{ $row->branch ?? '' }}</td>
                <td>{{ $row->officer ?? '' }}</td>
                <td>{{ $row->disbursement_date ?? '' }}</td>
                <td class="text-right">{{ number_format($row->amount ?? 0, 2) }}</td>
                <td>{{ $row->disbursement_method ?? '' }}</td>
                <td>{{ $row->loan_status ?? '' }}</td>
            </tr>
        @endforeach
        @if(empty(($report['detailed_list'] ?? null)) || (method_exists(($report['detailed_list'] ?? null), 'total') && ($report['detailed_list']->total() === 0)))
            <tr><td colspan="9" class="text-center muted">No data</td></tr>
        @endif
    </table>
</div>

</body>
</html>
