<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Loan Arrears Report - {{ $subshopName ?? 'All Branches' }}</title>
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

        .kpi { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .kpi td { border: 1px solid #000; padding: 7px 6px; }
        .kpi .kpi-label { font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .kpi .kpi-value { font-size: 11px; font-weight: 700; margin-top: 2px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #fff; font-weight: 700; text-align: left; }
        thead th { font-weight: 700; }
        td.num, th.num { text-align: right; }

        .block-title { font-size: 11px; font-weight: 700; margin: 12px 0 6px 0; }

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
        $branchLabel = $subshopName ?: 'All Branches';

        $loans = $report['loan_level'] ?? [];
        if ($loans instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $loans = $loans->items();
        }

        $inst = $report['installment_level'] ?? [];
        if ($inst instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $inst = $inst->items();
        }
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
            <div class="report-title">Loan Arrears Report</div>
            <div class="report-sub">
                <div><strong>Branch:</strong> {{ $branchLabel }}</div>
                <div><strong>As At:</strong> {{ $asAtDate ?? '' }}</div>
                <div><strong>Generated:</strong> {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
            </div>
        </div>
    </div>

    <table class="kpi">
        <tr>
            <td>
                <div class="kpi-label">Total Arrears</div>
                <div class="kpi-value">{{ number_format($summary['total_arrears'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Loans In Arrears</div>
                <div class="kpi-value">{{ number_format($summary['loans_in_arrears'] ?? 0) }}</div>
            </td>
            <td>
                <div class="kpi-label">Overdue Installments</div>
                <div class="kpi-value">{{ number_format($summary['overdue_installments'] ?? 0) }}</div>
            </td>
            <td>
                <div class="kpi-label">Arrears Ratio (%)</div>
                <div class="kpi-value">{{ number_format($report['arrears_ratio_pct'] ?? 0, 2) }}%</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="kpi-label">Avg Arrears / Loan</div>
                <div class="kpi-value">{{ number_format($summary['avg_arrears_per_loan'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Max Arrears</div>
                <div class="kpi-value">{{ number_format($summary['max_arrears'] ?? 0, 2) }}</div>
            </td>
            <td colspan="2">
                <div class="kpi-label">Outstanding Portfolio</div>
                <div class="kpi-value">{{ number_format($report['portfolio_outstanding'] ?? 0, 2) }}</div>
            </td>
        </tr>
    </table>

    <div class="block-title">Loan-Level Arrears</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th class="num">Arrears</th>
                <th class="num">Overdue Inst.</th>
                <th>Oldest Due</th>
                <th class="num">DPD</th>
                <th>Last Payment</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($loans as $r)
                <tr>
                    <td>{{ $r->loan_code ?? '' }}</td>
                    <td>{{ $r->customer ?? '' }}</td>
                    <td class="num">{{ number_format($r->arrears_amount ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r->overdue_installments ?? 0) }}</td>
                    <td>{{ $r->oldest_due_date ?? '' }}</td>
                    <td class="num">{{ number_format($r->dpd ?? 0) }}</td>
                    <td>{{ $r->last_payment_date ?? '' }}</td>
                    <td>{{ $r->loan_status ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">Missed Installments Analysis</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th class="num">Missed Installments</th>
                <th class="num">Total Arrears</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($report['missed_installments'] ?? []) as $r)
                <tr>
                    <td>{{ $r['loan_code'] ?? '' }}</td>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['missed_installments'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['arrears'] ?? 0, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">Arrears Trend</div>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th class="num">Total Arrears</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($report['trend'] ?? []) as $r)
                <tr>
                    <td>{{ $r['date'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['total_arrears'] ?? 0, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">Installment-Level Arrears Breakdown</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th class="num">Inst. #</th>
                <th>Due Date</th>
                <th class="num">Inst. Amount</th>
                <th class="num">Paid</th>
                <th class="num">Arrears</th>
                <th class="num">DPD</th>
            </tr>
        </thead>
        <tbody>
            @forelse($inst as $r)
                <tr>
                    <td>{{ $r->loan_code ?? '' }}</td>
                    <td class="num">{{ $r->installment_number ?? '' }}</td>
                    <td>{{ $r->due_date ?? '' }}</td>
                    <td class="num">{{ number_format($r->installment_amount ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r->paid_amount ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r->arrears_amount ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r->dpd ?? 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">Arrears Aging Buckets</div>
    <table>
        <thead>
            <tr>
                <th>Bucket</th>
                <th class="num">Loans</th>
                <th class="num">Total Arrears</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($report['aging_buckets'] ?? []) as $r)
                <tr>
                    <td>{{ $r['bucket'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loans'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['arrears'] ?? 0, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">Arrears by Loan Product</div>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">Loans</th>
                <th class="num">Total Arrears</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($report['by_product'] ?? []) as $r)
                <tr>
                    <td>{{ $r['product'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loans'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['arrears'] ?? 0, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">Arrears by Branch</div>
    <table>
        <thead>
            <tr>
                <th>Branch</th>
                <th class="num">Loans</th>
                <th class="num">Total Arrears</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($report['by_branch'] ?? []) as $r)
                <tr>
                    <td>{{ $r['branch'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loans'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['arrears'] ?? 0, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">Arrears by Loan Officer</div>
    <table>
        <thead>
            <tr>
                <th>Officer</th>
                <th class="num">Loans</th>
                <th class="num">Total Arrears</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($report['by_officer'] ?? []) as $r)
                <tr>
                    <td>{{ $r['officer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loans'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['arrears'] ?? 0, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">Top Defaulters</div>
    <table>
        <thead>
            <tr>
                <th>Customer</th>
                <th class="num">Loans</th>
                <th class="num">Total Arrears</th>
                <th class="num">DPD</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($report['top_defaulters'] ?? []) as $r)
                <tr>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loans'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['arrears'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['dpd'] ?? 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            <strong>Note:</strong> This report is read-only and generated from overdue installments as-at the selected date.
        </div>
        <div class="footer-right">
            Page <script type="text/php">if (isset($pdf)) { echo $PAGE_NUM . ' / ' . $PAGE_COUNT; }</script>
        </div>
    </div>
</body>
</html>
