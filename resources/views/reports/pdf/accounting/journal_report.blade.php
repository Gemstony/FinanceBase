<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Journal Report - {{ $subshopName ?? 'All Branches' }}</title>
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
        .badge-danger { display:inline-block; padding:2px 6px; background:#dc3545; color:#fff; border-radius:3px; font-size:10px; }
    </style>
</head>
<body>

@php
    $filters = $report['filters'] ?? [];
    $totals = $report['totals'] ?? [];
    $entries = $report['entries_all'] ?? [];

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
        <div class="report-title">Journal Report</div>
        <div class="report-sub">
            <div><strong>Branch:</strong> {{ $branchLabel }}</div>
            <div><strong>Period:</strong> {{ $fromLabel }}{{ $fromLabel && $toLabel ? ' - ' : '' }}{{ $toLabel }}</div>
            @if(!empty($filters['reference']))
                <div><strong>Reference:</strong> {{ $filters['reference'] }}</div>
            @endif
            @if(!empty($filters['reference_type']))
                <div><strong>Type:</strong> {{ $filters['reference_type'] }}</div>
            @endif
            @if(!empty($filters['created_by']))
                <div><strong>Created By:</strong> {{ $filters['created_by'] }}</div>
            @endif
            <div><strong>Generated:</strong> {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
        </div>
    </div>
</div>

<div class="block-title">Summary</div>
<table>
    <thead>
        <tr>
            <th>Metric</th>
            <th class="num">Value</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Entries</td>
            <td class="num">{{ number_format((float) ($totals['entries_count'] ?? 0)) }}</td>
        </tr>
        <tr>
            <td>Total Debits</td>
            <td class="num">{{ $fmt($totals['total_debit'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Total Credits</td>
            <td class="num">{{ $fmt($totals['total_credit'] ?? 0) }}</td>
        </tr>
    </tbody>
</table>

@if(empty($entries))
    <div class="muted">No journal entries found for selected period</div>
@else
    <div class="block-title">Entries</div>
    @foreach($entries as $e)
        @php
            $isBalanced = !empty($e['is_balanced']);
        @endphp
        <table>
            <tbody>
                <tr>
                    <td colspan="3">
                        <strong>Journal Entry #{{ $e['id'] ?? '' }}</strong>
                        <span class="muted">({{ $e['transaction_date'] ?? '' }})</span>
                        @if(!$isBalanced)
                            <span class="badge-danger">Unbalanced Entry</span>
                        @endif
                        <div class="muted">Type: <strong>{{ $e['reference_type'] ?? '' }}</strong> | Reference: <strong>{{ $e['reference_id'] ?? '' }}</strong></div>
                        @if(!empty($e['description']))
                            <div>{{ $e['description'] }}</div>
                        @endif
                        <div class="muted">Created By: {{ $e['created_by_name'] ?? '' }}</div>
                    </td>
                </tr>
            </tbody>
        </table>

        <table>
            <thead>
                <tr>
                    <th>Account</th>
                    <th class="num" style="width: 140px;">Debit</th>
                    <th class="num" style="width: 140px;">Credit</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($e['lines'] ?? []) as $l)
                    <tr>
                        <td>{{ ($l['account_code'] ?? '') }} - {{ ($l['account_name'] ?? '') }}</td>
                        <td class="num">{{ $fmt($l['debit'] ?? 0) }}</td>
                        <td class="num">{{ $fmt($l['credit'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Totals</strong></td>
                    <td class="num"><strong>{{ $fmt($e['total_debit'] ?? 0) }}</strong></td>
                    <td class="num"><strong>{{ $fmt($e['total_credit'] ?? 0) }}</strong></td>
                </tr>
            </tfoot>
        </table>
    @endforeach
@endif

</body>
</html>
