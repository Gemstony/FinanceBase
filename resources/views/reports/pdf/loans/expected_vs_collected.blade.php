<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Expected vs Collected Report - {{ $subshopName ?? 'All Branches' }}</title>
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

        .block-title { font-size: 11px; font-weight: 700; margin: 12px 0 6px 0; }

        .kpi { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .kpi td { border: 1px solid #000; padding: 7px 6px; }
        .kpi .kpi-label { font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .kpi .kpi-value { font-size: 11px; font-weight: 700; margin-top: 2px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #fff; font-weight: 700; text-align: left; }
        thead th { font-weight: 700; }
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

        $periodLabel = \Carbon\Carbon::parse($startDate)->format('d M Y') . ' - ' . \Carbon\Carbon::parse($endDate)->format('d M Y');
        $branchLabel = $subshopName ?: 'All Branches';

        $loans = $report['loan_level'] ?? [];
        if ($loans instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $loans = $loans->items();
        }

        $inst = $report['installment_level'] ?? [];
        if ($inst instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $inst = $inst->items();
        }

        $byProduct = $report['by_product'] ?? [];
        $byBranch = $report['by_branch'] ?? [];
        $byOfficer = $report['by_officer'] ?? [];
        $topLoans = $report['top_underperforming']['top'] ?? [];
        $underLoans = $report['top_underperforming']['underperforming'] ?? [];
        $missed = $report['missed_collections'] ?? [];
        $partial = $report['partial_payments'] ?? [];
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
            <div class="report-title">Expected vs Collected Report</div>
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
                <div class="kpi-label">Total Expected</div>
                <div class="kpi-value">{{ number_format($summary['total_expected'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Total Collected</div>
                <div class="kpi-value">{{ number_format($summary['total_collected'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Variance (Expected - Collected)</div>
                <div class="kpi-value">{{ number_format($summary['total_variance'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Collection Rate (%)</div>
                <div class="kpi-value">{{ number_format($summary['collection_rate_pct'] ?? 0, 2) }}%</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="kpi-label">Due Installments</div>
                <div class="kpi-value">{{ number_format($summary['total_due_installments'] ?? 0) }}</div>
            </td>
            <td>
                <div class="kpi-label">Paid Installments</div>
                <div class="kpi-value">{{ number_format($summary['total_paid_installments'] ?? 0) }}</div>
            </td>
            <td colspan="2">
                <div class="kpi-label">Arrears Contribution (Shortfall)</div>
                <div class="kpi-value">{{ number_format($report['arrears_contribution']['shortfall'] ?? 0, 2) }} ({{ number_format($report['arrears_contribution']['shortfall_pct_of_expected'] ?? 0, 2) }}%)</div>
            </td>
        </tr>
    </table>

    <div class="block-title">Period Breakdown</div>
    <table>
        <thead>
            <tr>
                <th>Period</th>
                <th class="num">Expected</th>
                <th class="num">Collected</th>
                <th class="num">Variance</th>
                <th class="num">Rate (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($report['period_breakdown']['rows'] ?? []) as $r)
                <tr>
                    <td>{{ $r['period'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['expected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['variance'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['collection_rate_pct'] ?? 0, 2) }}%</td>
                </tr>
            @empty
                <tr><td colspan="5">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">By Loan Product</div>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">Expected</th>
                <th class="num">Collected</th>
                <th class="num">Variance</th>
                <th class="num">Rate (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($byProduct as $r)
                <tr>
                    <td>{{ $r['product'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['expected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['variance'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['collection_rate_pct'] ?? 0, 2) }}%</td>
                </tr>
            @empty
                <tr><td colspan="5">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">By Branch</div>
    <table>
        <thead>
            <tr>
                <th>Branch</th>
                <th class="num">Expected</th>
                <th class="num">Collected</th>
                <th class="num">Variance</th>
                <th class="num">Rate (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($byBranch as $r)
                <tr>
                    <td>{{ $r['branch'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['expected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['variance'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['collection_rate_pct'] ?? 0, 2) }}%</td>
                </tr>
            @empty
                <tr><td colspan="5">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">By Loan Officer</div>
    <table>
        <thead>
            <tr>
                <th>Officer</th>
                <th class="num">Expected</th>
                <th class="num">Collected</th>
                <th class="num">Variance</th>
                <th class="num">Rate (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($byOfficer as $r)
                <tr>
                    <td>{{ $r['officer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['expected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['variance'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['collection_rate_pct'] ?? 0, 2) }}%</td>
                </tr>
            @empty
                <tr><td colspan="5">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">Top Performing Loans (Top 10)</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th class="num">Expected</th>
                <th class="num">Collected</th>
                <th class="num">Rate (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($topLoans as $r)
                <tr>
                    <td>{{ $r['loan_code'] ?? '' }}</td>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['expected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['collection_rate_pct'] ?? 0, 2) }}%</td>
                </tr>
            @empty
                <tr><td colspan="5">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">Underperforming Loans (Bottom 10)</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th class="num">Expected</th>
                <th class="num">Collected</th>
                <th class="num">Rate (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($underLoans as $r)
                <tr>
                    <td>{{ $r['loan_code'] ?? '' }}</td>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['expected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['collection_rate_pct'] ?? 0, 2) }}%</td>
                </tr>
            @empty
                <tr><td colspan="5">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">Missed Collections (Due but Not Paid)</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th class="num">Expected</th>
                <th class="num">Collected</th>
                <th class="num">Missed</th>
            </tr>
        </thead>
        <tbody>
            @forelse($missed as $r)
                <tr>
                    <td>{{ $r['loan_code'] ?? '' }}</td>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['expected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['missed_amount'] ?? 0, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">Partial Payments</div>
    <table>
        <thead>
            <tr>
                <th>Metric</th>
                <th class="num">Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Partial installments</td>
                <td class="num">{{ number_format($partial['partial_installments'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Total remaining amount</td>
                <td class="num">{{ number_format($partial['remaining_amount'] ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="block-title">Loan-Level Performance</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th class="num">Expected</th>
                <th class="num">Collected</th>
                <th class="num">Variance</th>
                <th class="num">Rate</th>
            </tr>
        </thead>
        <tbody>
            @forelse($loans as $r)
                @php
                    $expected = (float) ($r->expected ?? 0);
                    $collected = (float) ($r->collected ?? 0);
                    $rate = $expected > 0 ? round(($collected / $expected) * 100, 2) : 0;
                @endphp
                <tr>
                    <td>{{ $r->loan_code ?? '' }}</td>
                    <td>{{ $r->customer ?? '' }}</td>
                    <td class="num">{{ number_format($expected, 2) }}</td>
                    <td class="num">{{ number_format($collected, 2) }}</td>
                    <td class="num">{{ number_format((float) ($r->variance ?? 0), 2) }}</td>
                    <td class="num">{{ number_format($rate, 2) }}%</td>
                </tr>
            @empty
                <tr><td colspan="6">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">Installment-Level Comparison</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th class="num">Inst. #</th>
                <th>Due Date</th>
                <th class="num">Expected</th>
                <th class="num">Collected</th>
                <th class="num">Variance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($inst as $r)
                <tr>
                    <td>{{ $r->loan_code ?? '' }}</td>
                    <td class="num">{{ $r->installment_number ?? '' }}</td>
                    <td>{{ $r->due_date ?? '' }}</td>
                    <td class="num">{{ number_format($r->expected ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r->collected ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r->variance ?? 0, 2) }}</td>
                    <td>{{ $r->status ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            <strong>Note:</strong> Expected is based on installments due within the selected period; Collected is based on payments made within the selected period.
        </div>
        <div class="footer-right">
            Page <script type="text/php">if (isset($pdf)) { echo $PAGE_NUM . ' / ' . $PAGE_COUNT; }</script>
        </div>
    </div>
</body>
</html>
