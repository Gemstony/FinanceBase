<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Loan Portfolio Report - {{ $subshopName ?? 'All Branches' }}</title>
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

        .muted { color: #000; }
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
            <div class="report-title">Loan Portfolio Report</div>
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
                <div class="kpi-label">Total Outstanding</div>
                <div class="kpi-value">{{ number_format($summary['total_outstanding'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Active Loans</div>
                <div class="kpi-value">{{ number_format($summary['active_loans'] ?? 0) }}</div>
            </td>
            <td>
                <div class="kpi-label">Active Borrowers</div>
                <div class="kpi-value">{{ number_format($summary['active_borrowers'] ?? 0) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="kpi-label">Disbursed (Period)</div>
                <div class="kpi-value">{{ number_format($summary['total_disbursed_period'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Repayments (Period)</div>
                <div class="kpi-value">{{ number_format($summary['total_repayments_period'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Average Loan Size</div>
                <div class="kpi-value">{{ number_format($summary['avg_loan_size'] ?? 0, 2) }}</div>
            </td>
        </tr>
    </table>

    <div class="block-title">Portfolio by Product</div>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">Loans</th>
                <th class="num">Outstanding</th>
                <th class="num">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['composition']['by_product'] ?? []) as $r)
                <tr>
                    <td>{{ $r['product_name'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loans_count'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['composition']['by_product'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Portfolio by Branch</div>
    <table>
        <thead>
            <tr>
                <th>Branch</th>
                <th class="num">Active Loans</th>
                <th class="num">Outstanding</th>
                <th class="num">PAR 30</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['composition']['by_branch'] ?? []) as $r)
                <tr>
                    <td>{{ $r['branch'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['active_loans'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['par30'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['composition']['by_branch'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Portfolio by Loan Officer (Disbursement Processor)</div>
    <table>
        <thead>
            <tr>
                <th>Officer</th>
                <th class="num">Loans Managed</th>
                <th class="num">Outstanding</th>
                <th class="num">Repayments Collected</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['composition']['by_officer'] ?? []) as $r)
                <tr>
                    <td>{{ $r['officer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loans_managed'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['repayments_collected'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['composition']['by_officer'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Disbursement Analysis</div>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th class="num">Loans Disbursed</th>
                <th class="num">Amount</th>
                <th class="num">Average Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['disbursement_analysis'] ?? []) as $r)
                <tr>
                    <td>{{ $r['month'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loans_disbursed'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['amount'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['avg_amount'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['disbursement_analysis'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Repayment Performance</div>
    <table>
        <thead>
            <tr>
                <th>Metric</th>
                <th class="num">Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Expected Repayments</td>
                <td class="num">{{ number_format($report['repayment_performance']['expected'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Collected Repayments</td>
                <td class="num">{{ number_format($report['repayment_performance']['collected'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Collection Efficiency</td>
                <td class="num">{{ number_format($report['repayment_performance']['efficiency_pct'] ?? 0, 2) }}%</td>
            </tr>
            <tr>
                <td>Collection Gap</td>
                <td class="num">{{ number_format(($report['repayment_performance']['expected'] ?? 0) - ($report['repayment_performance']['collected'] ?? 0), 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="block-title">Portfolio at Risk (PAR)</div>
    <table>
        <thead>
            <tr>
                <th>Bucket</th>
                <th class="num">Outstanding</th>
                <th class="num">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['par'] ?? []) as $r)
                <tr>
                    <td>{{ $r['bucket'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['par'] ?? []))
                <tr><td colspan="3" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Portfolio Aging</div>
    <table>
        <thead>
            <tr>
                <th>Bucket</th>
                <th class="num">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['aging'] ?? []) as $r)
                <tr>
                    <td>{{ $r['bucket'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['aging'] ?? []))
                <tr><td colspan="2" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Top Borrowers</div>
    <table>
        <thead>
            <tr>
                <th>Customer</th>
                <th class="num">Loan Count</th>
                <th class="num">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['top_borrowers'] ?? []) as $r)
                <tr>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loan_count'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['top_borrowers'] ?? []))
                <tr><td colspan="3" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            <strong>{{ $shopName }}</strong> | Loan Portfolio Report
        </div>
        <div class="footer-right">
            Page 1
        </div>
    </div>
</body>
</html>
