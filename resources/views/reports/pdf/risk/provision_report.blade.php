<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Loan Loss Provision Report - {{ $subshopName ?? 'All Branches' }}</title>
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
        td.center, th.center { text-align: center; }

        .muted { color: #666; }
        .badge { padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .badge-success { background: #28a745; color: #fff; }
        .badge-warning { background: #ffc107; color: #000; }
        .badge-orange { background: #fd7e14; color: #fff; }
        .badge-danger { background: #dc3545; color: #fff; }
        .badge-dark { background: #343a40; color: #fff; }
    </style>
</head>
<body>
@php
    $shopName = $shop->name ?? 'Institution';
    $shopEmail = $shop->email ?? null;
    $shopPhone = $shop->phone ?? null;
    $shopWebsite = $shop->website ?? null;
    $shopAddress = $shop->address ?? null;

    $branchLabel = $subshopName ?: 'All Branches';
    $thresholds = $report['thresholds_used'] ?? [];
    $summary = $report['summary'] ?? [];
    $breakdown = $report['breakdown'] ?? [];

    $fmt = function ($v) {
        return number_format((float) ($v ?? 0), 2);
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
        <div class="report-title">Loan Loss Provision Report</div>
        <div class="report-sub">
            <div><strong>Branch:</strong> {{ $branchLabel }}</div>
            <div><strong>Generated:</strong> {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
        </div>
    </div>
</div>

<!-- Provision Rates -->
<div class="block-title">Provision Rates</div>
<table>
    <thead>
        <tr>
            <th class="center">PAR30</th>
            <th class="center">PAR60</th>
            <th class="center">PAR90</th>
            <th class="center">Default</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="center">{{ ($thresholds['par30_rate'] ?? 0) }}%</td>
            <td class="center">{{ ($thresholds['par60_rate'] ?? 0) }}%</td>
            <td class="center">{{ ($thresholds['par90_rate'] ?? 0) }}%</td>
            <td class="center">{{ ($thresholds['default_rate'] ?? 0) }}%</td>
        </tr>
    </tbody>
</table>

<!-- Portfolio Summary -->
<div class="block-title">Portfolio Summary</div>
<table>
    <thead>
        <tr>
            <th>Metric</th>
            <th class="num">Amount</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Total Portfolio Outstanding</td>
            <td class="num">{{ $fmt($summary['total_outstanding'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Total Provision Required</td>
            <td class="num" style="color: #856404; font-weight: bold;">{{ $fmt($summary['total_provision_required'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Provision Coverage Ratio</td>
            <td class="num">{{ $summary['provision_percentage'] ?? 0 }}%</td>
        </tr>
        <tr>
            <td>Net Portfolio Value</td>
            <td class="num" style="color: #155724; font-weight: bold;">{{ $fmt(($summary['total_outstanding'] ?? 0) - ($summary['total_provision_required'] ?? 0)) }}</td>
        </tr>
    </tbody>
</table>

<!-- Provision Breakdown -->
<div class="block-title">Provision Breakdown by Risk Category</div>
<table>
    <thead>
        <tr>
            <th>Risk Category</th>
            <th class="num">Loan Count</th>
            <th class="num">Outstanding</th>
            <th class="center">% of Portfolio</th>
            <th class="center">Rate</th>
            <th class="num">Provision Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($breakdown as $status => $data)
            @if($data['count'] > 0)
                <tr>
                    <td>
                        @php
                            $badgeClass = match($status) {
                                'current' => 'badge-success',
                                'par30' => 'badge-warning',
                                'par60' => 'badge-orange',
                                'par90' => 'badge-danger',
                                default => 'badge-dark',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ strtoupper($status) }}</span>
                    </td>
                    <td class="num">{{ number_format($data['count']) }}</td>
                    <td class="num">{{ $fmt($data['outstanding']) }}</td>
                    <td class="center">{{ $data['percentage_of_portfolio'] }}%</td>
                    <td class="center">{{ $data['rate'] }}%</td>
                    <td class="num" style="font-weight: bold;">{{ $fmt($data['provision']) }}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
    <tfoot>
        <tr style="background: #f8f9fa;">
            <th><strong>Total</strong></th>
            <th class="num">{{ number_format(array_sum(array_column($breakdown, 'count'))) }}</th>
            <th class="num">{{ $fmt($summary['total_outstanding'] ?? 0) }}</th>
            <th class="center">100%</th>
            <th class="center">-</th>
            <th class="num" style="color: #856404;">{{ $fmt($summary['total_provision_required'] ?? 0) }}</th>
        </tr>
    </tfoot>
</table>

<!-- Journal Entry -->
<div class="block-title">Suggested Journal Entry</div>
<table>
    <thead>
        <tr>
            <th>Account</th>
            <th class="num">Debit</th>
            <th class="num">Credit</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Loan Loss Expense</td>
            <td class="num">{{ $fmt($summary['total_provision_required'] ?? 0) }}</td>
            <td class="num">-</td>
            <td>Provision for loan losses</td>
        </tr>
        <tr>
            <td>Allowance for Loan Losses</td>
            <td class="num">-</td>
            <td class="num">{{ $fmt($summary['total_provision_required'] ?? 0) }}</td>
            <td>Provision for loan losses</td>
        </tr>
    </tbody>
</table>

</body>
</html>
