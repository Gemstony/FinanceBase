<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cash Book - {{ $subshopName ?? 'All Branches' }}</title>
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
    $filters = $report['filters'] ?? [];
    $account = $report['account'] ?? [];
    $opening = $report['opening'] ?? [];
    $totals = $report['totals'] ?? [];
    $rows = $report['transactions_all'] ?? [];

    $shopName = $shop->name ?? 'Institution';
    $shopEmail = $shop->email ?? null;
    $shopPhone = $shop->phone ?? null;
    $shopWebsite = $shop->website ?? null;
    $shopAddress = $shop->address ?? null;

    $branchLabel = $subshopName ?: 'All Branches';
    $fromLabel = !empty($filters['from_date']) ? \Carbon\Carbon::parse((string) $filters['from_date'])->format('d M Y') : '';
    $toLabel = !empty($filters['to_date']) ? \Carbon\Carbon::parse((string) $filters['to_date'])->format('d M Y') : '';

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
        <div class="report-title">Cash Book</div>
        <div class="report-sub">
            <div><strong>Branch:</strong> {{ $branchLabel }}</div>
            <div><strong>Account:</strong> {{ ($account['account_code'] ?? '') }} - {{ ($account['account_name'] ?? '') }}</div>
            <div><strong>Period:</strong> {{ $fromLabel }}{{ $fromLabel && $toLabel ? ' - ' : '' }}{{ $toLabel }}</div>
            @if(!empty($filters['reference_type']))
                <div><strong>Type:</strong> {{ $filters['reference_type'] }}</div>
            @endif
            @if(!empty($filters['reference_search']))
                <div><strong>Reference:</strong> {{ $filters['reference_search'] }}</div>
            @endif
            <div><strong>Generated:</strong> {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
        </div>
    </div>
</div>

<div class="block-title">Balances</div>
<table>
    <thead>
        <tr>
            <th>Metric</th>
            <th class="num">Value</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Opening Balance</td>
            <td class="num">{{ $fmt($opening['balance'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Total Receipts</td>
            <td class="num">{{ $fmt($totals['period_debit'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Total Payments</td>
            <td class="num">{{ $fmt($totals['period_credit'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Closing Balance</td>
            <td class="num">{{ $fmt($totals['closing_balance'] ?? 0) }}</td>
        </tr>
    </tbody>
</table>

<div class="block-title">Transactions</div>
<table>
    <thead>
        <tr>
            <th style="width: 12%">Date</th>
            <th style="width: 16%">Journal</th>
            <th>Description</th>
            <th class="num" style="width: 12%">Receipt</th>
            <th class="num" style="width: 12%">Payment</th>
            <th class="num" style="width: 14%">Running</th>
        </tr>
    </thead>
    <tbody>
        @if(empty($rows))
            <tr>
                <td colspan="6" class="muted">No cash transactions found for selected period.</td>
            </tr>
        @else
            @foreach($rows as $t)
                @php
                    $ref = '#' . (int) ($t['journal_entry_id'] ?? 0);
                    $desc = trim((string) ($t['journal_description'] ?? ''));
                    $lineDesc = trim((string) ($t['line_description'] ?? ''));
                    $fullDesc = $desc;
                    if($lineDesc !== '' && $lineDesc !== $desc) { $fullDesc = $desc !== '' ? ($desc . ' | ' . $lineDesc) : $lineDesc; }
                @endphp
                <tr>
                    <td>{{ $t['transaction_date'] ?? '' }}</td>
                    <td>{{ $ref }}</td>
                    <td>{{ $fullDesc !== '' ? $fullDesc : '—' }}</td>
                    <td class="num">{{ $fmt($t['debit'] ?? 0) }}</td>
                    <td class="num">{{ $fmt($t['credit'] ?? 0) }}</td>
                    <td class="num"><strong>{{ $fmt($t['running_balance'] ?? 0) }}</strong></td>
                </tr>
            @endforeach
        @endif
    </tbody>
</table>

</body>
</html>
