<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Charts of Account - {{ $subshop->name ?? 'All Branches' }}</title>
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

        .page-break { page-break-before: always; }
        .small { font-size: 9px; }

        .footer { border-top: 1px solid #000; margin-top: 14px; padding-top: 6px; font-size: 8px; }
        .footer-left { display: inline-block; vertical-align: top; width: 70%; }
        .footer-right { display: inline-block; vertical-align: top; width: 29%; text-align: right; }

        .badge { display: inline-block; padding: 2px 6px; font-size: 7px; font-weight: 700; border-radius: 3px; text-transform: uppercase; }
        .badge-success { background: #4caf50; color: #fff; }
        .badge-danger { background: #f44336; color: #fff; }
        .badge-secondary { background: #9e9e9e; color: #fff; }
    </style>
</head>
<body>
    @php
        $shopName = $shop->name ?? $subshop->name ?? 'Institution';
        $shopEmail = $shop->email ?? $subshop->email ?? null;
        $shopPhone = $shop->phone ?? $subshop->phone ?? null;
        $shopWebsite = $shop->website ?? $subshop->website ?? null;
        $shopAddress = $shop->address ?? $subshop->address ?? null;
        $shopRegion = $shop->region ?? $subshop->region ?? null;
        $shopCountry = $shop->country ?? $subshop->country ?? null;
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
            <div class="report-title">Charts of Account</div>
            <div class="report-sub">
                <div><strong>Branch:</strong> {{ $subshop->name ?? 'All Branches' }}</div>
                <div><strong>Generated:</strong> {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
            </div>
        </div>
    </div>

    <table class="kpi">
        <tr>
            <td>
                <div class="kpi-label">Total Accounts</div>
                <div class="kpi-value">{{ number_format($summary['count'] ?? 0) }}</div>
            </td>
            <td>
                <div class="kpi-label">Active</div>
                <div class="kpi-value">{{ number_format($summary['active'] ?? 0) }}</div>
            </td>
            <td>
                <div class="kpi-label">System Accounts</div>
                <div class="kpi-value">{{ number_format($summary['system'] ?? 0) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="kpi-label">User Accounts</div>
                <div class="kpi-value">{{ number_format($summary['user'] ?? 0) }}</div>
            </td>
            <td colspan="2"></td>
        </tr>
    </table>

    <div class="block-title">Account List</div>
    <div class="small" style="margin-bottom:6px;">
        Showing {{ number_format(count($rows)) }} accounts.
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Code</th>
                <th>Account Name</th>
                <th>Description</th>
                <th>Class</th>
                <th>Group</th>
                <th>Cash Flow</th>
                <th>Equity</th>
                <th>Customer</th>
                <th>System</th>
                <th>Active</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            <?php $i=1; ?>
            @foreach($rows as $row)
            <tr>
                <td>{{ $i++ }}</td>
                <td>{{ $row->account_code }}</td>
                <td>{{ $row->account_name }}</td>
                <td>{{ $row->description ?? '-' }}</td>
                <td>{{ $row->accountClass->name ?? '-' }}</td>
                <td>{{ $row->accountGroup->name ?? '-' }}</td>
                <td>
                    <span class="badge {{ $row->cash_flow_impact === 'IN' ? 'badge-success' : ($row->cash_flow_impact === 'OUT' ? 'badge-danger' : 'badge-secondary') }}">
                        {{ $row->cash_flow_impact ?? 'NONE' }}
                    </span>
                </td>
                <td>
                    <span class="badge {{ $row->equity_impact === 'INCREASE' ? 'badge-success' : ($row->equity_impact === 'DECREASE' ? 'badge-danger' : 'badge-secondary') }}">
                        {{ $row->equity_impact ?? 'NONE' }}
                    </span>
                </td>
                <td>
                    <span class="badge {{ $row->is_customer_account ? 'badge-success' : 'badge-secondary' }}">
                        {{ $row->is_customer_account ? 'YES' : 'NO' }}
                    </span>
                </td>
                <td>
                    <span class="badge {{ $row->is_system_account ? 'badge-secondary' : 'badge-success' }}">
                        {{ $row->is_system_account ? 'SYSTEM' : 'USER' }}
                    </span>
                </td>
                <td>
                    <span class="badge {{ ($row->is_active ?? 1) ? 'badge-success' : 'badge-secondary' }}">
                        {{ ($row->is_active ?? 1) ? 'YES' : 'NO' }}
                    </span>
                </td>
                <td>{{ optional($row->created_at)->format('Y-m-d') }}</td>
            </tr>
            @endforeach
            @if(empty($rows))
                <tr><td colspan="12" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            <strong>{{ $shopName }}</strong> | Charts of Account
        </div>
        <div class="footer-right">
            Page 1
        </div>
    </div>
</body>
</html>
