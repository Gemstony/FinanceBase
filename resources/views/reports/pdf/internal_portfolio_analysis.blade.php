<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Internal Portfolio Analysis - {{ $subshopName ?? 'All Branches' }}</title>
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
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #fff; font-weight: 700; text-align: left; }
        td.num, th.num { text-align: right; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @php
        $shopName = $shop->name ?? 'Institution';
        $shopEmail = $shop->email ?? null;
        $shopPhone = $shop->phone ?? null;
        $shopWebsite = $shop->website ?? null;
        $shopAddress = $shop->address ?? null;
        $shopRegion = $shop->region ?? null;
        $shopCountry = $shop->country ?? null;
        $asFrom = \Carbon\Carbon::parse($dateFrom)->format('d M Y');
        $asTo = \Carbon\Carbon::parse($dateTo)->format('d M Y');
        $branchLabel = $subshopName ?: 'All Branches';
        $s = $report['summary'] ?? [];
        $early = $report['early_warning'] ?? [];
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
            <div class="report-title">Internal Portfolio Analysis</div>
            <div class="report-sub">
                <div><strong>Branch:</strong> {{ $branchLabel }}</div>
                <div><strong>Period:</strong> {{ $asFrom }} to {{ $asTo }}</div>
                <div><strong>Generated:</strong> {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
            </div>
        </div>
    </div>

    <div class="block-title">Summary</div>
    <table>
        <tbody>
            <tr><td>Portfolio Outstanding</td><td class="num">{{ number_format($s['portfolio_outstanding'] ?? 0, 2) }}</td></tr>
            <tr><td>PAR30</td><td class="num">{{ number_format($s['par30_pct'] ?? 0, 2) }}%</td></tr>
            <tr><td>Collection Efficiency</td><td class="num">{{ number_format($s['collection_efficiency_pct'] ?? 0, 2) }}%</td></tr>
            <tr><td>Default Rate</td><td class="num">{{ number_format($s['default_rate_pct'] ?? 0, 2) }}%</td></tr>
            <tr><td>Health Score</td><td class="num">{{ number_format($s['health_score_pct'] ?? 0, 2) }}% ({{ $s['health_category'] ?? '' }})</td></tr>
        </tbody>
    </table>

    <div class="block-title">Profitability by Product</div>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">Interest</th>
                <th class="num">Fees</th>
                <th class="num">Penalties</th>
                <th class="num">Cost</th>
                <th class="num">Revenue</th>
                <th class="num">Profit</th>
                <th class="num">PAR30</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['profitability_by_product'] ?? []) as $r)
                <tr>
                    <td>{{ $r['product'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['interest_earned'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['fees_collected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['penalties_collected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['estimated_cost'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['revenue'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['profit'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['profitability_by_product'] ?? []))
                <tr><td colspan="8" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Risk vs Return</div>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">Profit</th>
                <th class="num">PAR30</th>
                <th>Risk Level</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['risk_vs_return'] ?? []) as $r)
                <tr>
                    <td>{{ $r['product'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['profit'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                    <td>{{ $r['risk_level'] ?? '' }}</td>
                </tr>
            @endforeach
            @if(empty($report['risk_vs_return'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Officer Performance</div>
    <table>
        <thead>
            <tr>
                <th>Officer</th>
                <th class="num">Score</th>
                <th class="num">Total Portfolio</th>
                <th class="num">Loans Disbursed</th>
                <th class="num">PAR30</th>
                <th class="num">Efficiency</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['officer_performance'] ?? []) as $r)
                <tr>
                    <td>{{ $r['officer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['score_pct'] ?? 0, 2) }}%</td>
                    <td class="num">{{ number_format($r['total_portfolio'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['loans_disbursed'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                    <td class="num">{{ number_format($r['collection_efficiency_pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['officer_performance'] ?? []))
                <tr><td colspan="6" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Customer Segmentation</div>
    <table>
        <thead>
            <tr>
                <th>Segment</th>
                <th class="num">Customers</th>
                <th class="num">Portfolio</th>
                <th class="num">PAR30</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['customer_segmentation'] ?? []) as $r)
                <tr>
                    <td>{{ $r['segment'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['customers'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['portfolio'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['customer_segmentation'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Early Warning Indicators</div>
    <table>
        <tbody>
            <tr><td>Increasing PAR Trend</td><td class="num">{{ !empty(($early['flags']['increasing_par_trend'] ?? false)) ? 'YES' : 'NO' }}</td></tr>
            <tr><td>Rising Average DPD</td><td class="num">{{ !empty(($early['flags']['rising_avg_dpd'] ?? false)) ? 'YES' : 'NO' }}</td></tr>
            <tr><td>Declining Collection Efficiency</td><td class="num">{{ !empty(($early['flags']['declining_collection_efficiency'] ?? false)) ? 'YES' : 'NO' }}</td></tr>
        </tbody>
    </table>

    <div class="block-title">Strategic Insights</div>
    <table>
        <tbody>
            @foreach(($report['strategic_insights'] ?? []) as $ins)
                <tr><td>{{ $ins }}</td></tr>
            @endforeach
            @if(empty($report['strategic_insights'] ?? []))
                <tr><td style="text-align:center; color:#777; padding:10px;">No insights</td></tr>
            @endif
        </tbody>
    </table>

    <div class="page-break"></div>

    <div class="block-title">Loan Cycle Analysis</div>
    <table>
        <thead>
            <tr>
                <th>Cycle</th>
                <th class="num">Loans</th>
                <th class="num">Avg Loan Size</th>
                <th class="num">PAR30</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['loan_cycle_analysis'] ?? []) as $r)
                <tr>
                    <td>{{ $r['cycle'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loans'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['avg_loan_size'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['loan_cycle_analysis'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Income vs Portfolio (Yield)</div>
    <table>
        <tbody>
            <tr><td>Interest Income</td><td class="num">{{ number_format(($report['income_vs_portfolio']['interest_income'] ?? 0), 2) }}</td></tr>
            <tr><td>Average Portfolio</td><td class="num">{{ number_format(($report['income_vs_portfolio']['avg_portfolio'] ?? 0), 2) }}</td></tr>
            <tr><td>Yield</td><td class="num">{{ number_format(($report['income_vs_portfolio']['yield_pct'] ?? 0), 2) }}%</td></tr>
        </tbody>
    </table>

    <div class="block-title">Cohort Analysis (Disbursement Month)</div>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th class="num">Loans Disbursed</th>
                <th class="num">Portfolio Outstanding</th>
                <th class="num">PAR30</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['cohort_analysis'] ?? []) as $r)
                <tr>
                    <td>{{ $r['cohort_month'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loans_disbursed'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['portfolio_outstanding'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['cohort_analysis'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Behavioral Risk (Repeat Late Payers)</div>
    <table>
        <thead>
            <tr>
                <th>Customer</th>
                <th class="num">Late Payments</th>
                <th class="num">Avg Days Late</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['behavioral_risk']['repeat_late_payers'] ?? []) as $r)
                <tr>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['late_payments'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['avg_days_late'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['behavioral_risk']['repeat_late_payers'] ?? []))
                <tr><td colspan="3" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Concentration Risk (Top Exposure)</div>
    <table>
        <thead>
            <tr>
                <th>Top Customers</th>
                <th>Top Branches</th>
                <th>Top Products</th>
            </tr>
        </thead>
        <tbody>
            @php
                $tc = $report['concentration_risk']['top_customers'] ?? [];
                $tb = $report['concentration_risk']['top_branches'] ?? [];
                $tp = $report['concentration_risk']['top_products'] ?? [];
                $mx = max(count($tc), count($tb), count($tp));
            @endphp
            @for($i = 0; $i < $mx; $i++)
                <tr>
                    <td>
                        @if(!empty($tc[$i]))
                            {{ $tc[$i]['label'] ?? '' }} - {{ number_format($tc[$i]['pct'] ?? 0, 2) }}%
                        @endif
                    </td>
                    <td>
                        @if(!empty($tb[$i]))
                            {{ $tb[$i]['label'] ?? '' }} - {{ number_format($tb[$i]['pct'] ?? 0, 2) }}%
                        @endif
                    </td>
                    <td>
                        @if(!empty($tp[$i]))
                            {{ $tp[$i]['label'] ?? '' }} - {{ number_format($tp[$i]['pct'] ?? 0, 2) }}%
                        @endif
                    </td>
                </tr>
            @endfor
            @if($mx === 0)
                <tr><td colspan="3" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Cross Analysis (Top 50)</div>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Branch</th>
                <th>Officer</th>
                <th class="num">PAR30</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['cross_analysis'] ?? []) as $r)
                <tr>
                    <td>{{ $r['product'] ?? '' }}</td>
                    <td>{{ $r['branch'] ?? '' }}</td>
                    <td>{{ $r['officer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['cross_analysis'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>
</body>
</html>
