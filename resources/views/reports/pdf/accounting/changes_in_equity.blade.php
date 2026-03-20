<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Changes in Equity - {{ $subshopName ?? 'All Branches' }}</title>
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

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #fff; font-weight: 700; text-align: left; }
        td.num, th.num { text-align: right; }

        .muted { color: #666; }
        .row-head { background: #f4f4f4; }
        .row-total { background: #f4f4f4; font-weight: 700; }
        .nowrap { white-space: nowrap; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        .summary-box { margin-bottom: 12px; }
        .summary-row { display: inline-block; margin-right: 15px; }
        .summary-label { font-size: 9px; color: #666; }
        .summary-value { font-size: 11px; font-weight: 700; }
        
        .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; background: #f4f4f4; padding: 6px; margin-bottom: 8px; }
    </style>
</head>
<body>
@php
    $filters = $report['filters'] ?? [];
    $hasData = $report['has_data'] ?? false;

    $shopName = $shop->name ?? 'Institution';
    $shopEmail = $shop->email ?? null;
    $shopPhone = $shop->phone ?? null;
    $shopWebsite = $shop->website ?? null;
    $shopAddress = $shop->address ?? null;

    $branchLabel = $subshopName ?: 'All Branches';

    $fmt = function ($v) {
        $n = (float) ($v ?? 0);
        $abs = number_format(abs($n), 2);
        return $n < 0 ? '(' . $abs . ')' : $abs;
    };
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
        <div class="report-title">Changes in Equity</div>
        <div class="report-sub">
            <div><strong>Branch:</strong> {{ $branchLabel }}</div>
            @if(!empty($filters['from_date']))
                <div><strong>From:</strong> {{ \Carbon\Carbon::parse($filters['from_date'])->format('d M Y') }}</div>
            @endif
            @if(!empty($filters['to_date']))
                <div><strong>To:</strong> {{ \Carbon\Carbon::parse($filters['to_date'])->format('d M Y') }}</div>
            @endif
            <div><strong>Generated:</strong> {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
        </div>
    </div>
</div>

@if(!$hasData)
    <div class="muted text-center" style="padding: 20px;">
        No equity changes found for selected period.
    </div>
@else
    {{-- Summary Section --}}
    <div class="summary-box">
        <div class="summary-row">
            <div class="summary-label">Opening Equity</div>
            <div class="summary-value">{{ $fmt($report['opening_equity'] ?? 0) }}</div>
        </div>
        <div class="summary-row">
            <div class="summary-label">Capital Contributions</div>
            <div class="summary-value">{{ $fmt($report['capital_contributions'] ?? 0) }}</div>
        </div>
        <div class="summary-row">
            <div class="summary-label">Net Profit</div>
            <div class="summary-value">{{ $fmt($report['net_profit'] ?? 0) }}</div>
        </div>
        <div class="summary-row">
            <div class="summary-label">Withdrawals</div>
            <div class="summary-value">{{ $fmt($report['withdrawals'] ?? 0) }}</div>
        </div>
    </div>

    {{-- Validation Warning --}}
    @if(!($report['validation']['balanced'] ?? true))
        <div class="muted" style="color: #d9534f; margin-bottom: 12px;">
            <strong>Warning:</strong> Equity does not match Balance Sheet. Difference: {{ $fmt($report['validation']['difference'] ?? 0) }}
        </div>
    @endif

    {{-- Main Statement Table --}}
    <div class="section-title">Statement of Changes in Equity</div>
    <table>
        <thead>
            <tr>
                <th class="nowrap">Description</th>
                <th class="num nowrap">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Opening Equity</strong></td>
                <td class="num"><strong>{{ $fmt($report['opening_equity'] ?? 0) }}</strong></td>
            </tr>
            <tr>
                <td>&nbsp;&nbsp;&nbsp;+ Capital Contributions</td>
                <td class="num">{{ $fmt($report['capital_contributions'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>&nbsp;&nbsp;&nbsp;+ Net Profit</td>
                <td class="num">{{ $fmt($report['net_profit'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>&nbsp;&nbsp;&nbsp;- Withdrawals</td>
                <td class="num">{{ $fmt($report['withdrawals'] ?? 0) }}</td>
            </tr>
            <tr class="row-total">
                <td><strong>= Closing Equity</strong></td>
                <td class="num"><strong>{{ $fmt($report['closing_equity'] ?? 0) }}</strong></td>
            </tr>
        </tbody>
    </table>

    {{-- Balance Sheet Reconciliation --}}
    <div class="section-title">Balance Sheet Reconciliation</div>
    <table>
        <thead>
            <tr>
                <th class="nowrap">Description</th>
                <th class="num nowrap">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Equity from Changes in Equity (Closing)</td>
                <td class="num">{{ $fmt($report['closing_equity'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Equity from Balance Sheet (as of {{ $filters['to_date'] ?? '' }})</td>
                <td class="num">{{ $fmt($report['balance_sheet_equity'] ?? 0) }}</td>
            </tr>
            <tr class="{{ ($report['validation']['balanced'] ?? true) ? 'row-head' : '' }}" style="{{ !($report['validation']['balanced'] ?? true) ? 'background: #f8d7da;' : '' }}">
                <td><strong>Difference</strong></td>
                <td class="num"><strong>{{ $fmt($report['validation']['difference'] ?? 0) }}</strong></td>
            </tr>
        </tbody>
    </table>

    {{-- Equity Breakdown --}}
    @if(!empty($report['equity_breakdown']))
        <div class="section-title">Equity Accounts Breakdown</div>
        <table>
            <thead>
                <tr>
                    <th class="nowrap">Account Code</th>
                    <th>Account Name</th>
                    <th class="num nowrap">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['equity_breakdown'] as $equity)
                    <tr>
                        <td class="nowrap">{{ $equity['account_code'] ?? '' }}</td>
                        <td>{{ $equity['account_name'] ?? '' }}</td>
                        <td class="num">{{ $fmt($equity['balance'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Retained Earnings --}}
    <div class="section-title">Retained Earnings</div>
    <table>
        <tbody>
            <tr>
                <td>Cumulative retained earnings as of {{ $filters['to_date'] ?? '' }}</td>
                <td class="num"><strong>{{ $fmt($report['retained_earnings'] ?? 0) }}</strong></td>
            </tr>
        </tbody>
    </table>
@endif

</body>
</html>
