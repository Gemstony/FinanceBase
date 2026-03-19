<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>General Ledger - {{ ($report['account']['account_code'] ?? '') }} {{ $subshopName ?? 'All Branches' }}</title>
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
    $account = $report['account'] ?? [];
    $opening = $report['opening'] ?? [];
    $totals = $report['totals'] ?? [];
    $tx = $report['transactions_all'] ?? [];

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
        <div class="report-title">General Ledger</div>
        <div class="report-sub">
            <div><strong>Branch:</strong> {{ $branchLabel }}</div>
            <div><strong>Account:</strong> {{ ($account['account_code'] ?? '') }} - {{ ($account['account_name'] ?? '') }}</div>
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

<div class="muted">
    <div><strong>Opening Balance:</strong> {{ $fmt($opening['balance'] ?? 0) }}</div>
    <div><strong>Period Total Debit:</strong> {{ $fmt($totals['period_debit'] ?? 0) }} | <strong>Period Total Credit:</strong> {{ $fmt($totals['period_credit'] ?? 0) }}</div>
    <div><strong>Closing Balance:</strong> {{ $fmt($totals['closing_balance'] ?? 0) }}</div>
</div>

<table>
    <thead>
        <tr>
            <th class="nowrap">Date</th>
            <th class="nowrap">Reference</th>
            <th>Description</th>
            <th class="num nowrap">Debit</th>
            <th class="num nowrap">Credit</th>
            <th class="num nowrap">Running</th>
        </tr>
    </thead>
    <tbody>
        @foreach(($tx ?? []) as $t)
            @php
                $ref = '#' . (int) ($t['journal_entry_id'] ?? 0);
                $desc = trim((string) ($t['journal_description'] ?? ''));
                $lineDesc = trim((string) ($t['line_description'] ?? ''));
                $fullDesc = $desc;
                if($lineDesc !== '' && $lineDesc !== $desc) { $fullDesc = $desc !== '' ? ($desc . ' | ' . $lineDesc) : $lineDesc; }
            @endphp
            <tr>
                <td class="nowrap">{{ $t['transaction_date'] ?? '' }}</td>
                <td class="nowrap">{{ $ref }}<br><span class="muted">{{ $t['reference_type'] ?? '' }}{{ !empty($t['reference_id']) ? (' #' . (int) $t['reference_id']) : '' }}</span></td>
                <td>
                    {{ $fullDesc !== '' ? $fullDesc : '—' }}
                    @if(!empty($t['created_by_name']))
                        <br><span class="muted">By: {{ $t['created_by_name'] }}</span>
                    @endif
                </td>
                <td class="num">{{ $fmt($t['debit'] ?? 0) }}</td>
                <td class="num">{{ $fmt($t['credit'] ?? 0) }}</td>
                <td class="num">{{ $fmt($t['running_balance'] ?? 0) }}</td>
            </tr>
        @endforeach

        @if(empty($tx ?? []))
            <tr><td colspan="6" style="text-align:center; color:#777; padding:10px;">No transactions in selected period</td></tr>
        @endif
    </tbody>
    <tfoot>
        <tr class="row-head">
            <th colspan="3" style="text-align:right;">Totals</th>
            <th class="num">{{ $fmt($totals['period_debit'] ?? 0) }}</th>
            <th class="num">{{ $fmt($totals['period_credit'] ?? 0) }}</th>
            <th class="num">{{ $fmt($totals['closing_balance'] ?? 0) }}</th>
        </tr>
    </tfoot>
</table>

</body>
</html>
