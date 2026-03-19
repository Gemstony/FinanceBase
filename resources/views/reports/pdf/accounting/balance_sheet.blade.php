<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Balance Sheet - {{ $subshopName ?? 'All Branches' }}</title>
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

        .muted { color: #666; }
    </style>
</head>
<body>
@php
    $tree = $report['tree'] ?? [];
    $totals = $report['totals'] ?? [];
    $validation = $report['validation'] ?? [];

    $shopName = $shop->name ?? 'Institution';
    $shopEmail = $shop->email ?? null;
    $shopPhone = $shop->phone ?? null;
    $shopWebsite = $shop->website ?? null;
    $shopAddress = $shop->address ?? null;

    $asOfLabel = \Carbon\Carbon::parse($asOf)->format('d M Y');
    $branchLabel = $subshopName ?: 'All Branches';

    $fmt = function ($v) {
        $n = (float) ($v ?? 0);
        $abs = number_format(abs($n), 2);
        return $n < 0 ? '(' . $abs . ')' : $abs;
    };

    $hasCompare = !empty($compareAsOf);
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
        <div class="report-title">Balance Sheet</div>
        <div class="report-sub">
            <div><strong>Branch:</strong> {{ $branchLabel }}</div>
            <div><strong>As-of:</strong> {{ $asOfLabel }}</div>
            @if($hasCompare)
                <div><strong>Compare:</strong> {{ \Carbon\Carbon::parse($compareAsOf)->format('d M Y') }}</div>
            @endif
            <div><strong>Generated:</strong> {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
        </div>
    </div>
</div>

@if(!empty($validation) && empty($validation['balanced']))
    <div class="block-title">Validation</div>
    <div class="muted">Not balanced. Difference: {{ $fmt($validation['difference'] ?? 0) }}</div>
@endif

<div class="block-title">Assets</div>
@include('reports.pdf.accounting.partials.balance_sheet_pdf_section', ['title' => 'Current Assets', 'tree' => $tree['assets']['current'] ?? [], 'fmt' => $fmt, 'hasCompare' => $hasCompare])
@include('reports.pdf.accounting.partials.balance_sheet_pdf_section', ['title' => 'Non-Current Assets', 'tree' => $tree['assets']['non_current'] ?? [], 'fmt' => $fmt, 'hasCompare' => $hasCompare])
<table>
    <tr>
        <td><strong>Total Assets</strong></td>
        <td class="num"><strong>{{ $fmt($totals['assets_total'] ?? 0) }}</strong></td>
        @if($hasCompare)
            <td class="num"></td>
        @endif
    </tr>
</table>

<div class="block-title">Liabilities</div>
@include('reports.pdf.accounting.partials.balance_sheet_pdf_section', ['title' => 'Current Liabilities', 'tree' => $tree['liabilities']['current'] ?? [], 'fmt' => $fmt, 'hasCompare' => $hasCompare])
@include('reports.pdf.accounting.partials.balance_sheet_pdf_section', ['title' => 'Non-Current Liabilities', 'tree' => $tree['liabilities']['non_current'] ?? [], 'fmt' => $fmt, 'hasCompare' => $hasCompare])
<table>
    <tr>
        <td><strong>Total Liabilities</strong></td>
        <td class="num"><strong>{{ $fmt($totals['liabilities_total'] ?? 0) }}</strong></td>
        @if($hasCompare)
            <td class="num"></td>
        @endif
    </tr>
</table>

<div class="block-title">Equity</div>
@include('reports.pdf.accounting.partials.balance_sheet_pdf_section', ['title' => 'Equity', 'tree' => $tree['equity']['items'] ?? [], 'fmt' => $fmt, 'hasCompare' => $hasCompare])
<table>
    <tbody>
        <tr>
            <td>Retained Earnings</td>
            <td class="num">{{ $fmt($tree['equity']['computed_totals']['retained_earnings'] ?? 0) }}</td>
            @if($hasCompare)
                <td class="num">{{ ($tree['equity']['computed_totals']['prev_retained_earnings'] ?? null) !== null ? $fmt($tree['equity']['computed_totals']['prev_retained_earnings']) : '' }}</td>
            @endif
        </tr>
        <tr>
            <td>Current Year Profit</td>
            <td class="num">{{ $fmt($tree['equity']['computed_totals']['current_year_profit'] ?? 0) }}</td>
            @if($hasCompare)
                <td class="num">{{ ($tree['equity']['computed_totals']['prev_current_year_profit'] ?? null) !== null ? $fmt($tree['equity']['computed_totals']['prev_current_year_profit']) : '' }}</td>
            @endif
        </tr>
        <tr>
            <td><strong>Total Equity</strong></td>
            <td class="num"><strong>{{ $fmt($totals['equity_total'] ?? 0) }}</strong></td>
            @if($hasCompare)
                <td class="num"></td>
            @endif
        </tr>
        <tr>
            <td><strong>Total Liabilities + Equity</strong></td>
            <td class="num"><strong>{{ $fmt(($totals['liabilities_total'] ?? 0) + ($totals['equity_total'] ?? 0)) }}</strong></td>
            @if($hasCompare)
                <td class="num"></td>
            @endif
        </tr>
    </tbody>
</table>

</body>
</html>
