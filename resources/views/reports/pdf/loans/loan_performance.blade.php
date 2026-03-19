<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Loan Performance Report - {{ $subshopName ?? 'All Branches' }}</title>
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

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #fff; font-weight: 700; text-align: left; }
        td.num, th.num { text-align: right; }

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
            <div class="report-title">Loan Performance Report</div>
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
                <div class="kpi-label">Expected Repayments</div>
                <div class="kpi-value">{{ number_format($summary['total_expected'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Collected</div>
                <div class="kpi-value">{{ number_format($summary['total_collected'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Collection Rate</div>
                <div class="kpi-value">{{ number_format($summary['collection_rate_pct'] ?? 0, 2) }}%</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="kpi-label">On-Time Rate</div>
                <div class="kpi-value">{{ number_format($summary['on_time_rate_pct'] ?? 0, 2) }}%</div>
            </td>
            <td>
                <div class="kpi-label">Late Rate</div>
                <div class="kpi-value">{{ number_format($summary['late_payment_rate_pct'] ?? 0, 2) }}%</div>
            </td>
            <td>
                <div class="kpi-label">Default Rate</div>
                <div class="kpi-value">{{ number_format($summary['default_rate_pct'] ?? 0, 2) }}%</div>
            </td>
        </tr>
    </table>

    <div class="block-title">Repayment Trends (Monthly)</div>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th class="num">Expected</th>
                <th class="num">Collected</th>
                <th class="num">Efficiency</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['trends']['rows'] ?? []) as $r)
                <tr>
                    <td>{{ $r['month'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['expected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['efficiency_pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['trends']['rows'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">On-Time vs Late vs Missed</div>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th class="num">Count</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>On-Time</td>
                <td class="num">{{ number_format($report['on_time_late']['on_time']['count'] ?? 0) }}</td>
                <td class="num">{{ number_format($report['on_time_late']['on_time']['amount'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Late</td>
                <td class="num">{{ number_format($report['on_time_late']['late']['count'] ?? 0) }}</td>
                <td class="num">{{ number_format($report['on_time_late']['late']['amount'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Missed</td>
                <td class="num">{{ number_format($report['on_time_late']['missed']['count'] ?? 0) }}</td>
                <td class="num">{{ number_format($report['on_time_late']['missed']['amount'] ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="block-title">Performance by Loan Product</div>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">Loans</th>
                <th class="num">Collected</th>
                <th class="num">Efficiency</th>
                <th class="num">PAR30</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['by_product'] ?? []) as $r)
                <tr>
                    <td>{{ $r['product_name'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['total_loans'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['efficiency_pct'] ?? 0, 2) }}%</td>
                    <td class="num">{{ number_format($r['par30'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['by_product'] ?? []))
                <tr><td colspan="5" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Performance by Officer</div>
    <table>
        <thead>
            <tr>
                <th>Officer</th>
                <th class="num">Loans Managed</th>
                <th class="num">Collected</th>
                <th class="num">Efficiency</th>
                <th class="num">PAR30</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['by_officer'] ?? []) as $r)
                <tr>
                    <td>{{ $r['officer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loans_managed'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['efficiency_pct'] ?? 0, 2) }}%</td>
                    <td class="num">{{ number_format($r['par30'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['by_officer'] ?? []))
                <tr><td colspan="5" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Delinquency & Default Analysis</div>
    <table>
        <thead>
            <tr>
                <th>Metric</th>
                <th class="num">Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Overdue Loans</td>
                <td class="num">{{ number_format($report['delinquency']['overdue_loans'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Overdue Amount</td>
                <td class="num">{{ number_format($report['delinquency']['overdue_amount'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Loans > 90 Days</td>
                <td class="num">{{ number_format($report['delinquency']['loans_over_90_days'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Default Rate</td>
                <td class="num">{{ number_format($report['delinquency']['default_rate_pct'] ?? 0, 2) }}%</td>
            </tr>
        </tbody>
    </table>

    <div class="block-title">Write-Off & Recovery</div>
    <table>
        <thead>
            <tr>
                <th>Metric</th>
                <th class="num">Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Loans Written Off</td>
                <td class="num">{{ number_format($report['write_off']['written_off_loans'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Amount Written Off</td>
                <td class="num">{{ number_format($report['write_off']['amount_written_off'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Recoveries After Write-Off</td>
                <td class="num">{{ number_format($report['write_off']['recoveries_after_writeoff'] ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="block-title">Top Performing Loans</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th>Product</th>
                <th class="num">Efficiency</th>
                <th class="num">Late %</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['top_worst_loans']['top'] ?? []) as $r)
                <tr>
                    <td>{{ $r['loan_code'] ?? '' }}</td>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td>{{ $r['product'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['efficiency_pct'] ?? 0, 2) }}%</td>
                    <td class="num">{{ number_format($r['late_pct_of_collected'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['top_worst_loans']['top'] ?? []))
                <tr><td colspan="5" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Worst Performing Loans</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th>Product</th>
                <th class="num">Outstanding</th>
                <th class="num">Efficiency</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['top_worst_loans']['worst'] ?? []) as $r)
                <tr>
                    <td>{{ $r['loan_code'] ?? '' }}</td>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td>{{ $r['product'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['outstanding_in_period'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['efficiency_pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['top_worst_loans']['worst'] ?? []))
                <tr><td colspan="5" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            <strong>{{ $shopName }}</strong> | Loan Performance Report
        </div>
        <div class="footer-right">
            Page 1
        </div>
    </div>
</body>
</html>
