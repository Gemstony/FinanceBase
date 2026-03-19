<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Delinquency Report - {{ $subshopName ?? 'All Branches' }}</title>
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
        .small { font-size: 9px; }

        .footer { border-top: 1px solid #000; margin-top: 14px; padding-top: 6px; font-size: 8px; }
        .footer-left { display: inline-block; vertical-align: top; width: 70%; }
        .footer-right { display: inline-block; vertical-align: top; width: 29%; text-align: right; }
    </style>
</head>
<body>
    @php
        $summary = $report['summary'] ?? [];
        $recovery = $report['recovery'] ?? [];

        $shopName = $shop->name ?? 'Institution';
        $shopEmail = $shop->email ?? null;
        $shopPhone = $shop->phone ?? null;
        $shopWebsite = $shop->website ?? null;
        $shopAddress = $shop->address ?? null;
        $shopRegion = $shop->region ?? null;
        $shopCountry = $shop->country ?? null;

        $periodLabel = \Carbon\Carbon::parse($dateFrom)->format('d M Y') . ' - ' . \Carbon\Carbon::parse($dateTo)->format('d M Y');
        $branchLabel = $subshopName ?: 'All Branches';

        $delinqPaginator = $report['delinquent_loans'] ?? null;
        $delinqItems = $delinqPaginator && method_exists($delinqPaginator, 'items') ? $delinqPaginator->items() : [];
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
            <div class="report-title">Delinquency Report</div>
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
                <div class="kpi-label">Overdue Loans</div>
                <div class="kpi-value">{{ number_format($summary['total_overdue_loans'] ?? 0) }}</div>
            </td>
            <td>
                <div class="kpi-label">Overdue Amount</div>
                <div class="kpi-value">{{ number_format($summary['total_overdue_amount'] ?? 0, 2) }}</div>
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
                <div class="kpi-label">Average DPD</div>
                <div class="kpi-value">{{ number_format($summary['avg_dpd'] ?? 0, 2) }}</div>
            </td>
        </tr>
    </table>

    <div class="block-title">Aging Analysis</div>
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
            @foreach(($report['aging'] ?? []) as $r)
                <tr>
                    <td>{{ $r['bucket'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loans'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['aging'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Recovery Tracking</div>
    <table>
        <tbody>
            <tr>
                <td><strong>Recovered Amount (Period)</strong></td>
                <td class="num">{{ number_format($recovery['recovered_amount'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Recovery Rate</strong></td>
                <td class="num">{{ number_format($recovery['recovery_rate_pct'] ?? 0, 2) }}%</td>
            </tr>
        </tbody>
    </table>

    <div class="block-title">DPD Distribution</div>
    <table>
        <thead>
            <tr>
                <th>Bucket</th>
                <th class="num">Loans</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['dpd_analysis']['distribution'] ?? []) as $r)
                <tr>
                    <td>{{ $r['bucket'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loans'] ?? 0) }}</td>
                </tr>
            @endforeach
            @if(empty($report['dpd_analysis']['distribution'] ?? []))
                <tr><td colspan="2" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Missed Installments (Top 10 Loans)</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th class="num">Missed</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['missed_installments'] ?? []) as $r)
                <tr>
                    <td>{{ $r['loan_code'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['missed_installments'] ?? 0) }}</td>
                </tr>
            @endforeach
            @if(empty($report['missed_installments'] ?? []))
                <tr><td colspan="2" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="page-break"></div>

    <div class="block-title">Delinquent Loan List</div>
    <div class="small" style="margin-bottom:6px;">
        Showing up to {{ number_format(count($delinqItems)) }} loans (PDF export is limited to the current page selection).
    </div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th>Product</th>
                <th>Branch</th>
                <th>Officer</th>
                <th class="num">Overdue</th>
                <th class="num">DPD</th>
                <th>Last Payment</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($delinqItems as $row)
                <tr>
                    <td>{{ $row->loan_code ?? '' }}</td>
                    <td>{{ $row->customer ?? '' }}</td>
                    <td>{{ $row->product ?? '' }}</td>
                    <td>{{ $row->branch ?? '' }}</td>
                    <td>{{ $row->officer ?? '' }}</td>
                    <td class="num">{{ number_format($row->overdue_amount ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($row->dpd ?? 0) }}</td>
                    <td>{{ $row->last_payment_date ?? '' }}</td>
                    <td>{{ $row->loan_status ?? '' }}</td>
                </tr>
            @endforeach
            @if(empty($delinqItems))
                <tr><td colspan="9" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Delinquency by Officer</div>
    <table>
        <thead>
            <tr>
                <th>Officer</th>
                <th class="num">Overdue Loans</th>
                <th class="num">Overdue Amount</th>
                <th class="num">PAR30</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['by_officer'] ?? []) as $r)
                <tr>
                    <td>{{ $r['officer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['overdue_loans'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['overdue_amount'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['by_officer'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Delinquency by Branch</div>
    <table>
        <thead>
            <tr>
                <th>Branch</th>
                <th class="num">Overdue Loans</th>
                <th class="num">Overdue Amount</th>
                <th class="num">PAR30</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['by_branch'] ?? []) as $r)
                <tr>
                    <td>{{ $r['branch'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['overdue_loans'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['overdue_amount'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['by_branch'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Delinquency by Product</div>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">Overdue Loans</th>
                <th class="num">Overdue Amount</th>
                <th class="num">PAR30</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['by_product'] ?? []) as $r)
                <tr>
                    <td>{{ $r['product'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['overdue_loans'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['overdue_amount'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['by_product'] ?? []))
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
            </tr>
        </thead>
        <tbody>
            @foreach(($report['high_risk'] ?? []) as $r)
                <tr>
                    <td>{{ $r['loan_code'] ?? '' }}</td>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['dpd'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['high_risk'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
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
                </tr>
            @endforeach
            @if(empty($report['writeoff_candidates'] ?? []))
                <tr><td colspan="5" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            <strong>{{ $shopName }}</strong> | Delinquency Report
        </div>
        <div class="footer-right">
            Page 1
        </div>
    </div>
</body>
</html>
