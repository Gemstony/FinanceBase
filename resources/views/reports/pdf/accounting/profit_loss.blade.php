<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Profit &amp; Loss {{ $subshopName ?? 'All Branches' }}</title>
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
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body>
@php
    $filters = $report['filters'] ?? [];
    $tree = $report['tree'] ?? [];
    $totals = $report['totals'] ?? [];
    $prev = $report['previous_period'] ?? null;

    $compare = (string) ($filters['compare'] ?? 'none');
    $hasCompare = $compare !== '' && strtolower($compare) !== 'none';
    $showPct = (bool) ($filters['show_pct'] ?? false);

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
        <div class="report-title">Profit &amp; Loss</div>
        <div class="report-sub">
            <div><strong>Branch:</strong> {{ $branchLabel }}</div>
            @if(!empty($filters['from_date']))
                <div><strong>From:</strong> {{ \Carbon\Carbon::parse($filters['from_date'])->format('d M Y') }}</div>
            @endif
            @if(!empty($filters['to_date']))
                <div><strong>To:</strong> {{ \Carbon\Carbon::parse($filters['to_date'])->format('d M Y') }}</div>
            @endif
            @if($prev)
                <div><strong>Prev:</strong> {{ $prev['from_date'] ?? '' }} to {{ $prev['to_date'] ?? '' }}</div>
            @endif
            <div><strong>Generated:</strong> {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Total Income</th>
            <th class="num">{{ $fmt($totals['total_income'] ?? 0) }}</th>
        </tr>
        <tr>
            <th>Total Expenses</th>
            <th class="num">{{ $fmt($totals['total_expenses'] ?? 0) }}</th>
        </tr>
        <tr>
            <th>{{ $totals['net_label'] ?? 'Net Profit' }}</th>
            <th class="num">{{ $fmt($totals['net_profit'] ?? 0) }}</th>
        </tr>
    </thead>
</table>

@foreach(['income' => 'Income', 'expense' => 'Expenses'] as $sectionKey => $sectionLabel)
    @php
        $section = $tree[$sectionKey] ?? [];
        $groups = $section['groups'] ?? [];
    @endphp

    <table>
        <thead>
            <tr>
                <th colspan="{{ 2 + ($hasCompare ? 2 : 0) + ($showPct ? 1 : 0) }}">{{ $sectionLabel }}</th>
            </tr>
            <tr>
                <th>Account</th>
                <th class="num">Current</th>
                @if($hasCompare)
                    <th class="num">Previous</th>
                    <th class="num">Difference</th>
                @endif
                @if($showPct)
                    <th class="num">%</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach(($groups ?? []) as $g)
                <tr class="row-head">
                    <td colspan="{{ 2 + ($hasCompare ? 2 : 0) + ($showPct ? 1 : 0) }}"><strong>{{ $g['group_code'] ?? '' }} - {{ $g['group_name'] ?? '' }}</strong></td>
                </tr>

                @foreach(($g['accounts'] ?? []) as $a)
                    <tr>
                        <td>{{ $a['account_code'] ?? '' }} - {{ $a['account_name'] ?? '' }}</td>
                        <td class="num">{{ $fmt($a['amount'] ?? 0) }}</td>
                        @if($hasCompare)
                            <td class="num">{{ $fmt($a['previous_amount'] ?? 0) }}</td>
                            <td class="num">{{ $fmt($a['difference'] ?? 0) }}</td>
                        @endif
                        @if($showPct)
                            <td class="num">{{ number_format((float) ($a['pct'] ?? 0), 2) }}</td>
                        @endif
                    </tr>
                @endforeach

                <tr>
                    <td style="text-align:right;"><strong>Subtotal</strong></td>
                    <td class="num"><strong>{{ $fmt($g['subtotal'] ?? 0) }}</strong></td>
                    @if($hasCompare)
                        <td class="num"><strong>{{ $fmt($g['previous_subtotal'] ?? 0) }}</strong></td>
                        <td class="num"><strong>{{ $fmt($g['difference_subtotal'] ?? 0) }}</strong></td>
                    @endif
                    @if($showPct)
                        <td class="num"><strong>{{ number_format((float) ($g['pct'] ?? 0), 2) }}</strong></td>
                    @endif
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="row-head">
                <th style="text-align:right;">Total {{ $sectionLabel }}</th>
                <th class="num">{{ $fmt($section['total'] ?? 0) }}</th>
                @if($hasCompare)
                    <th class="num">{{ $fmt($section['previous_total'] ?? 0) }}</th>
                    <th class="num">{{ $fmt($section['difference_total'] ?? 0) }}</th>
                @endif
                @if($showPct)
                    <th class="num">{{ number_format(100, 2) }}</th>
                @endif
            </tr>
        </tfoot>
    </table>
@endforeach

</body>
</html>
