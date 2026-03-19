<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>PAR Report - {{ $subshopName ?? 'All Branches' }}</title>
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
        .page-break { page-break-before: always; }
        .footer { border-top: 1px solid #000; margin-top: 14px; padding-top: 6px; font-size: 8px; }
        .footer-left { display: inline-block; vertical-align: top; width: 70%; }
        .footer-right { display: inline-block; vertical-align: top; width: 29%; text-align: right; }
    </style>
</head>
<body>
    @php
        $summary = $report['summary'] ?? [];
        $hr = $report['high_risk_portfolio'] ?? [];
        $c = $report['concentration'] ?? [];
        $w = $report['writeoff_exposure'] ?? [];
        $rec = $report['recovery_impact'] ?? [];
        $seg = $report['segmentation'] ?? [];
        $p = $report['loans'] ?? null;
        $shopName = $shop->name ?? 'Institution';
        $shopEmail = $shop->email ?? null;
        $shopPhone = $shop->phone ?? null;
        $shopWebsite = $shop->website ?? null;
        $shopAddress = $shop->address ?? null;
        $shopRegion = $shop->region ?? null;
        $shopCountry = $shop->country ?? null;
        $asAtLabel = \Carbon\Carbon::parse($asAtDate)->format('d M Y');
        $branchLabel = $subshopName ?: 'All Branches';
        $trend = $report['trends'] ?? [];
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
            <div class="report-title">PAR Report</div>
            <div class="report-sub">
                <div><strong>Branch:</strong> {{ $branchLabel }}</div>
                <div><strong>As At:</strong> {{ $asAtLabel }}</div>
                <div><strong>Generated:</strong> {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
            </div>
        </div>
    </div>

    <table class="kpi">
        <tr>
            <td>
                <div class="kpi-label">Total Portfolio Outstanding</div>
                <div class="kpi-value">{{ number_format($summary['total_portfolio_outstanding'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Total At-Risk Amount</div>
                <div class="kpi-value">{{ number_format($summary['total_at_risk_amount'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">PAR30</div>
                <div class="kpi-value">{{ number_format($summary['par30_pct'] ?? 0, 2) }}%</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="kpi-label">PAR60</div>
                <div class="kpi-value">{{ number_format($summary['par60_pct'] ?? 0, 2) }}%</div>
            </td>
            <td>
                <div class="kpi-label">PAR90</div>
                <div class="kpi-value">{{ number_format($summary['par90_pct'] ?? 0, 2) }}%</div>
            </td>
            <td>
                <div class="kpi-label">NPL Ratio</div>
                <div class="kpi-value">{{ number_format($summary['npl_ratio_pct'] ?? 0, 2) }}%</div>
            </td>
        </tr>
    </table>

    <div class="block-title">Summary Ratios</div>
    <table>
        <tbody>
            <tr><td>PAR30</td><td class="num">{{ number_format($summary['par30_pct'] ?? 0, 2) }}%</td></tr>
            <tr><td>PAR60</td><td class="num">{{ number_format($summary['par60_pct'] ?? 0, 2) }}%</td></tr>
            <tr><td>PAR90</td><td class="num">{{ number_format($summary['par90_pct'] ?? 0, 2) }}%</td></tr>
            <tr><td>NPL Loans</td><td class="num">{{ number_format($summary['npl_loans'] ?? 0) }}</td></tr>
            <tr><td>NPL Outstanding</td><td class="num">{{ number_format($summary['npl_outstanding'] ?? 0, 2) }}</td></tr>
            <tr><td>NPL Ratio</td><td class="num">{{ number_format($summary['npl_ratio_pct'] ?? 0, 2) }}%</td></tr>
        </tbody>
    </table>

    <div class="block-title">PAR Aging Analysis</div>
    <table>
        <thead>
            <tr>
                <th>Bucket</th>
                <th class="num">Loans</th>
                <th class="num">Outstanding</th>
                <th class="num">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['aging_buckets'] ?? []) as $r)
                <tr>
                    <td>{{ $r['bucket'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loans'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['aging_buckets'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">PAR by Product</div>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">Total</th>
                <th class="num">PAR30</th>
                <th class="num">PAR60</th>
                <th class="num">PAR90</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['by_product'] ?? []) as $r)
                <tr>
                    <td>{{ $r['product'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['total_portfolio'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                    <td class="num">{{ number_format($r['par60_pct'] ?? 0, 2) }}%</td>
                    <td class="num">{{ number_format($r['par90_pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['by_product'] ?? []))
                <tr><td colspan="5" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">PAR by Branch</div>
    <table>
        <thead>
            <tr>
                <th>Branch</th>
                <th class="num">Total</th>
                <th class="num">PAR30</th>
                <th class="num">PAR90</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['by_branch'] ?? []) as $r)
                <tr>
                    <td>{{ $r['branch'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['total_portfolio'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                    <td class="num">{{ number_format($r['par90_pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['by_branch'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">PAR by Officer</div>
    <table>
        <thead>
            <tr>
                <th>Officer</th>
                <th class="num">Total</th>
                <th class="num">PAR30</th>
                <th class="num">PAR90</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['by_officer'] ?? []) as $r)
                <tr>
                    <td>{{ $r['officer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['total_portfolio'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                    <td class="num">{{ number_format($r['par90_pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['by_officer'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">High-Risk Portfolio</div>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th class="num">Loans</th>
                <th class="num">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Loans DPD &gt; 60</td>
                <td class="num">{{ number_format($hr['over_60']['loans'] ?? 0) }}</td>
                <td class="num">{{ number_format($hr['over_60']['outstanding'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Loans DPD &gt; 90</td>
                <td class="num">{{ number_format($hr['over_90']['loans'] ?? 0) }}</td>
                <td class="num">{{ number_format($hr['over_90']['outstanding'] ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="page-break"></div>

    <div class="block-title">Top Risky Loans (Top 10)</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th class="num">DPD</th>
                <th class="num">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['top_risky_loans'] ?? []) as $r)
                <tr>
                    <td>{{ $r['loan_code'] ?? '' }}</td>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['dpd'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['top_risky_loans'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Risk Concentration (PAR30+)</div>
    <table>
        <tbody>
            <tr>
                <td><strong>Risk Outstanding</strong></td>
                <td class="num">{{ number_format($c['risk_outstanding'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td><strong>% of Portfolio</strong></td>
                <td class="num">{{ number_format($c['risk_pct_of_portfolio'] ?? 0, 2) }}%</td>
            </tr>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th>Top Customers</th>
                <th>Top Branches</th>
                <th>Top Products</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="vertical-align: top;">
                    @foreach(($c['top_customers'] ?? []) as $r)
                        <div>{{ $r['customer'] ?? '' }} - {{ number_format($r['pct_of_risk'] ?? 0, 2) }}%</div>
                    @endforeach
                    @if(empty($c['top_customers'] ?? []))
                        <div style="color:#777;">No data</div>
                    @endif
                </td>
                <td style="vertical-align: top;">
                    @foreach(($c['top_branches'] ?? []) as $r)
                        <div>{{ $r['branch'] ?? '' }} - {{ number_format($r['pct_of_risk'] ?? 0, 2) }}%</div>
                    @endforeach
                    @if(empty($c['top_branches'] ?? []))
                        <div style="color:#777;">No data</div>
                    @endif
                </td>
                <td style="vertical-align: top;">
                    @foreach(($c['top_products'] ?? []) as $r)
                        <div>{{ $r['product'] ?? '' }} - {{ number_format($r['pct_of_risk'] ?? 0, 2) }}%</div>
                    @endforeach
                    @if(empty($c['top_products'] ?? []))
                        <div style="color:#777;">No data</div>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <div class="block-title">Write-Off Exposure</div>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th class="num">Loans</th>
                <th class="num">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>DPD &gt; 90</td>
                <td class="num">{{ number_format($w['dpd_over_90']['loans'] ?? 0) }}</td>
                <td class="num">{{ number_format($w['dpd_over_90']['outstanding'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>DPD &gt; 120</td>
                <td class="num">{{ number_format($w['dpd_over_120']['loans'] ?? 0) }}</td>
                <td class="num">{{ number_format($w['dpd_over_120']['outstanding'] ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="block-title">Recovery Impact</div>
    <table>
        <tbody>
            <tr><td>Previous As At</td><td class="num">{{ $rec['previous_as_at'] ?? '' }}</td></tr>
            <tr><td>Current As At</td><td class="num">{{ $rec['current_as_at'] ?? '' }}</td></tr>
            <tr><td>Previous PAR30</td><td class="num">{{ number_format($rec['previous_par30_pct'] ?? 0, 2) }}%</td></tr>
            <tr><td>Current PAR30</td><td class="num">{{ number_format($rec['current_par30_pct'] ?? 0, 2) }}%</td></tr>
            <tr><td>Change (pp)</td><td class="num">{{ number_format($rec['par30_change_pct_points'] ?? 0, 2) }}</td></tr>
            <tr><td>Recovered Amount</td><td class="num">{{ number_format($rec['recovered_amount'] ?? 0, 2) }}</td></tr>
        </tbody>
    </table>

    <div class="block-title">Portfolio Segmentation</div>
    <table>
        <thead>
            <tr>
                <th>Segment</th>
                <th class="num">Amount</th>
                <th class="num">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($seg ?? []) as $r)
                <tr>
                    <td>{{ $r['segment'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['amount'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($seg ?? []))
                <tr><td colspan="3" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="page-break"></div>

    <div class="block-title">Loan Drill-Down (Filtered List)</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th>Product</th>
                <th>Branch</th>
                <th>Officer</th>
                <th class="num">Outstanding</th>
                <th class="num">Overdue</th>
                <th class="num">DPD</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($p && method_exists($p,'items') ? $p->items() : []) as $r)
                <tr>
                    <td>{{ $r->loan_code ?? '' }}</td>
                    <td>{{ $r->customer ?? '' }}</td>
                    <td>{{ $r->product ?? '' }}</td>
                    <td>{{ $r->branch ?? '' }}</td>
                    <td>{{ $r->officer ?? '' }}</td>
                    <td class="num">{{ number_format((float) ($r->outstanding_balance ?? 0), 2) }}</td>
                    <td class="num">{{ number_format((float) ($r->overdue_amount ?? 0), 2) }}</td>
                    <td class="num">{{ number_format((int) ($r->dpd ?? 0)) }}</td>
                    <td>{{ ucfirst(str_replace('_',' ', (string) ($r->loan_status ?? ''))) }}</td>
                </tr>
            @endforeach
            @if(!$p || count($p->items() ?? []) === 0)
                <tr><td colspan="9" style="text-align:center; color:#777; padding:10px;">No loans found</td></tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            <strong>{{ $shopName }}</strong> | PAR Report
        </div>
        <div class="footer-right">
            Page 1
        </div>
    </div>
</body>
</html>
