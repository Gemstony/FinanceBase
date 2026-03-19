<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Installment Aging Report - {{ $subshopName ?? 'All Branches' }}</title>
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

        .inst-table td.loan,
        .inst-table th.loan {
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
            width: 70px;
        }

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

        $p = $report['installments'] ?? null;
        $items = $p && method_exists($p, 'items') ? $p->items() : [];
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
            <div class="report-title">Installment Aging Report</div>
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
                <div class="kpi-label">Outstanding Installments</div>
                <div class="kpi-value">{{ number_format($summary['total_outstanding_installments'] ?? 0) }}</div>
            </td>
            <td>
                <div class="kpi-label">Outstanding Amount</div>
                <div class="kpi-value">{{ number_format($summary['total_outstanding_amount'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Overdue Installments</div>
                <div class="kpi-value">{{ number_format($summary['total_overdue_installments'] ?? 0) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="kpi-label">Overdue Amount</div>
                <div class="kpi-value">{{ number_format($summary['total_overdue_amount'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Average DPD</div>
                <div class="kpi-value">{{ number_format($summary['avg_dpd'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Maximum DPD</div>
                <div class="kpi-value">{{ number_format($summary['max_dpd'] ?? 0) }}</div>
            </td>
        </tr>
    </table>

    <div class="block-title">Installment Aging Buckets</div>
    <table>
        <thead>
            <tr>
                <th>Bucket</th>
                <th class="num">Installments</th>
                <th class="num">Outstanding</th>
                <th class="num">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['aging_buckets'] ?? []) as $r)
                <tr>
                    <td>{{ $r['bucket'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['installments'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['aging_buckets'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">High-Risk Installments (Top 10)</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th class="num">Inst #</th>
                <th class="num">DPD</th>
                <th class="num">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['high_risk_installments'] ?? []) as $r)
                <tr>
                    <td>{{ $r['loan_code'] ?? '' }}</td>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['installment_number'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['dpd'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['outstanding_balance'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['high_risk_installments'] ?? []))
                <tr><td colspan="5" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Partial Payment Analysis</div>
    @php $pp = $report['partial_payment'] ?? []; @endphp
    <table>
        <thead>
            <tr>
                <th>Metric</th>
                <th class="num">Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Partial Installments</td>
                <td class="num">{{ number_format($pp['partial_installments'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Total Partial Paid Amount</td>
                <td class="num">{{ number_format($pp['total_partial_paid_amount'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Total Partial Outstanding Amount</td>
                <td class="num">{{ number_format($pp['total_partial_outstanding_amount'] ?? 0, 2) }}</td>
            </tr>
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

    <div class="block-title">Recovery Priority Segmentation</div>
    <table>
        <thead>
            <tr>
                <th>Segment</th>
                <th class="num">Installments</th>
                <th class="num">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['recovery_segmentation'] ?? []) as $r)
                <tr>
                    <td>{{ $r['risk'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['installments'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['recovery_segmentation'] ?? []))
                <tr><td colspan="3" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">Missed Installments Analysis (By Loan)</div>
    <table>
        <thead>
            <tr>
                <th>Loan</th>
                <th>Customer</th>
                <th class="num">Missed</th>
                <th class="num">Overdue Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['missed_installments'] ?? []) as $r)
                <tr>
                    <td>{{ $r['loan_code'] ?? '' }}</td>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['missed_installments'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['overdue_amount'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['missed_installments'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="block-title">DPD Distribution</div>
    @php $dd = $report['dpd_distribution'] ?? []; @endphp
    <table>
        <thead>
            <tr>
                <th>Metric</th>
                <th class="num">Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Average DPD</td>
                <td class="num">{{ number_format($dd['avg_dpd'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Maximum DPD</td>
                <td class="num">{{ number_format($dd['max_dpd'] ?? 0) }}</td>
            </tr>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th>Bucket</th>
                <th class="num">Installments</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($dd['distribution'] ?? []) as $r)
                <tr>
                    <td>{{ $r['bucket'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['installments'] ?? 0) }}</td>
                </tr>
            @endforeach
            @if(empty($dd['distribution'] ?? []))
                <tr><td colspan="2" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="page-break"></div>

    <div class="block-title">Installment-Level Aging (Top Page)</div>
    <table class="inst-table">
        <thead>
            <tr>
                <th class="loan">Loan</th>
                <th>Customer</th>
                <th>Product</th>
                <th>Branch</th>
                <th class="num">Inst #</th>
                <th>Due Date</th>
                <th class="num">Amount</th>
                <th class="num">Paid</th>
                <th class="num">Outstanding</th>
                <th>Bucket</th>
                <th>Status</th>
                <th>Allocation</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $r)
                <tr>
                    <td class="loan">{{ $r->loan_code ?? '' }}</td>
                    <td>{{ $r->customer ?? '' }}</td>
                    <td>{{ $r->product ?? '' }}</td>
                    <td>{{ $r->branch ?? '' }}</td>
                    <td class="num">{{ number_format((int) ($r->installment_number ?? 0)) }}</td>
                    <td>{{ $r->due_date ?? '' }}</td>
                    <td class="num">{{ number_format((float) ($r->installment_amount ?? 0), 2) }}</td>
                    <td class="num">{{ number_format((float) ($r->paid_amount ?? 0), 2) }}</td>
                    <td class="num">{{ number_format((float) ($r->outstanding_balance ?? 0), 2) }}</td>
                    <td>{{ $r->aging_bucket ?? '' }}</td>
                    <td>{{ ucfirst((string) ($r->installment_status ?? '')) }}</td>
                    <td>
                        @if((int) ($r->allocation_issue ?? 0) === 1)
                            Allocation Issue
                        @else
                            OK
                        @endif
                    </td>
                </tr>
            @endforeach
            @if(empty($items))
                <tr><td colspan="14" style="text-align:center; color:#777; padding:10px;">No installments</td></tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            <strong>{{ $shopName }}</strong> | Installment Aging Report
        </div>
        <div class="footer-right">
            Page 1
        </div>
    </div>
</body>
</html>
