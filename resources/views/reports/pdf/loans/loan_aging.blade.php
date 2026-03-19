<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Loan Aging Report - {{ $subshopName ?? 'All Branches' }}</title>
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
        $shopName = $shop->name ?? 'Institution';
        $shopEmail = $shop->email ?? null;
        $shopPhone = $shop->phone ?? null;
        $shopWebsite = $shop->website ?? null;
        $shopAddress = $shop->address ?? null;
        $shopRegion = $shop->region ?? null;
        $shopCountry = $shop->country ?? null;
        $asAtLabel = \Carbon\Carbon::parse($asAtDate)->format('d M Y');
        $branchLabel = $subshopName ?: 'All Branches';
        $p = $report['loans'] ?? null;
        $loanItems = $p && method_exists($p, 'items') ? $p->items() : [];
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
            <div class="report-title">Loan Aging Report</div>
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
                <div class="kpi-label">Total Outstanding</div>
                <div class="kpi-value">{{ number_format($summary['total_outstanding'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Total Overdue Amount</div>
                <div class="kpi-value">{{ number_format($summary['total_overdue_amount'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Average DPD</div>
                <div class="kpi-value">{{ number_format($summary['avg_dpd'] ?? 0, 2) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="kpi-label">PAR30</div>
                <div class="kpi-value">{{ number_format($summary['par30_pct'] ?? 0, 2) }}%</div>
            </td>
            <td>
                <div class="kpi-label">PAR60</div>
                <div class="kpi-value">{{ number_format($summary['par60_pct'] ?? 0, 2) }}%</div>
            </td>
            <td>
                <div class="kpi-label">NPL Loans (&gt; 90)</div>
                <div class="kpi-value">{{ number_format($summary['non_performing_loans'] ?? 0) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="kpi-label">PAR90</div>
                <div class="kpi-value">{{ number_format($summary['par90_pct'] ?? 0, 2) }}%</div>
            </td>
            <td>
                <div class="kpi-label">Performing Loans (DPD = 0)</div>
                <div class="kpi-value">{{ number_format($summary['performing_loans'] ?? 0) }}</div>
            </td>
            <td>
                <div class="kpi-label">Maximum DPD</div>
                <div class="kpi-value">{{ number_format($summary['max_dpd'] ?? 0) }}</div>
            </td>
        </tr>
    </table>

    <div class="block-title">Aging Buckets</div>
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

    <div class="block-title">High-Risk Loans (Top 10)</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th class="num">DPD</th>
                <th class="num">Outstanding</th>
                <th>Risk</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['high_risk'] ?? []) as $r)
                <tr>
                    <td>{{ $r['loan_code'] ?? '' }}</td>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['dpd'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                    <td>{{ $r['risk_level'] ?? '' }}</td>
                </tr>
            @endforeach
            @if(empty($report['high_risk'] ?? []))
                <tr><td colspan="5" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Aging by Product</div>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">Current</th>
                <th class="num">1-30</th>
                <th class="num">31-60</th>
                <th class="num">61-90</th>
                <th class="num">90+</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['by_product'] ?? []) as $r)
                <tr>
                    <td>{{ $r['product'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['current'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['d1_30'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['d31_60'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['d61_90'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['d90p'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['by_product'] ?? []))
                <tr><td colspan="6" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Aging by Branch</div>
    <table>
        <thead>
            <tr>
                <th>Branch</th>
                <th class="num">Current</th>
                <th class="num">1-30</th>
                <th class="num">31-60</th>
                <th class="num">61-90</th>
                <th class="num">90+</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['by_branch'] ?? []) as $r)
                <tr>
                    <td>{{ $r['branch'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['current'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['d1_30'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['d31_60'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['d61_90'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['d90p'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['by_branch'] ?? []))
                <tr><td colspan="6" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Aging by Officer</div>
    <table>
        <thead>
            <tr>
                <th>Officer</th>
                <th class="num">Current</th>
                <th class="num">1-30</th>
                <th class="num">31-60</th>
                <th class="num">61-90</th>
                <th class="num">90+</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['by_officer'] ?? []) as $r)
                <tr>
                    <td>{{ $r['officer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['current'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['d1_30'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['d31_60'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['d61_90'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['d90p'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['by_officer'] ?? []))
                <tr><td colspan="6" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Write-Off Candidates</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th class="num">DPD</th>
                <th class="num">Outstanding</th>
                <th>Last Payment</th>
                <th>Recommendation</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['writeoff_candidates'] ?? []) as $r)
                <tr>
                    <td>{{ $r['loan_code'] ?? '' }}</td>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['dpd'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                    <td>{{ $r['last_payment_date'] ?? '' }}</td>
                    <td>{{ $r['recommendation'] ?? '' }}</td>
                </tr>
            @endforeach
            @if(empty($report['writeoff_candidates'] ?? []))
                <tr><td colspan="6" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="page-break"></div>

    <div class="block-title">Loan-Level Aging (Top Page)</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th>Product</th>
                <th>Branch</th>
                <th>Officer</th>
                <th class="num">Outstanding</th>
                <th class="num">DPD</th>
                <th>Bucket</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($loanItems as $r)
                @php
                    $dpd = (int) ($r->dpd ?? 0);
                    $bucket = 'Current';
                    if ($dpd <= 0) $bucket = 'Current';
                    elseif ($dpd <= 30) $bucket = '1-30';
                    elseif ($dpd <= 60) $bucket = '31-60';
                    elseif ($dpd <= 90) $bucket = '61-90';
                    else $bucket = '90+';
                @endphp
                <tr>
                    <td>{{ $r->loan_code ?? '' }}</td>
                    <td>{{ $r->customer ?? '' }}</td>
                    <td>{{ $r->product ?? '' }}</td>
                    <td>{{ $r->branch ?? '' }}</td>
                    <td>{{ $r->officer ?? '' }}</td>
                    <td class="num">{{ number_format((float) ($r->outstanding_balance ?? 0), 2) }}</td>
                    <td class="num">{{ number_format($dpd) }}</td>
                    <td>{{ $bucket }}</td>
                    <td>{{ ucfirst(str_replace('_',' ', (string) ($r->loan_status ?? ''))) }}</td>
                </tr>
            @endforeach
            @if(empty($loanItems))
                <tr><td colspan="9" style="text-align:center; color:#777; padding:10px;">No loans</td></tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            <strong>{{ $shopName }}</strong> | Loan Aging Report
        </div>
        <div class="footer-right">
            Page 1
        </div>
    </div>
</body>
</html>
