<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $subshop->name ?? 'Shop' }} Charts of Account Export</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; margin:0; padding:15px; color:#333; font-size:10px; line-height:1.4; }
        .header { background:#2c3e50; color:#fff; padding:20px; text-align:center; margin-bottom:20px; }
        .header h1{ font-size:26px; margin:0 0 8px; font-weight:700; }
        .header .subtitle{ font-size:14px; margin:0 0 10px; }
        .header .meta{ margin-top:10px; font-size:9px; border-top:1px solid rgba(255,255,255,0.3); padding-top:10px; }
        .stats-table{ width:100%; margin-bottom:20px; border-collapse:separate; border-spacing:8px; }
        .stats-table td{ width:50%; padding:12px 8px; text-align:center; border:2px solid #ddd; border-radius:4px; }
        .stat-label{ font-size:8px; color:#666; text-transform:uppercase; font-weight:700; letter-spacing:.5px; margin-bottom:5px; }
        .stat-value{ font-size:22px; font-weight:700; color:#2c3e50; margin:5px 0; }
        .stat-blue{ background:#e3f2fd; border-color:#2196f3; } .stat-blue .stat-value{ color:#1976d2; }
        .stat-green{ background:#e8f5e9; border-color:#4caf50; } .stat-green .stat-value{ color:#388e3c; }
        .stat-gray{ background:#eceff1; border-color:#90a4ae; } .stat-gray .stat-value{ color:#455a64; }
        .stat-purple{ background:#f3e5f5; border-color:#ab47bc; } .stat-purple .stat-value{ color:#8e24aa; }
        .data-table{ width:100%; border-collapse:collapse; font-size:8px; margin-top:15px; }
        .data-table thead th{ background:#34495e; color:#fff; padding:8px 4px; text-align:left; font-weight:700; font-size:8px; text-transform:uppercase; border:1px solid #2c3e50; }
        .data-table td{ padding:6px 4px; border:1px solid #ddd; vertical-align:top; word-wrap:break-word; }
        .data-table tbody tr:nth-child(odd){ background:#f9f9f9; }
        .data-table tbody tr:nth-child(even){ background:#fff; }
        .col-no{ width:3%; } .col-code{ width:8%; } .col-name{ width:18%; } .col-desc{ width:12%; } .col-class{ width:10%; } .col-group{ width:10%; } .col-cash{ width:7%; } .col-equity{ width:7%; } .col-cust{ width:6%; } .col-sys{ width:6%; } .col-act{ width:6%; } .col-date{ width:7%; }
        .badge{ display:inline-block; padding:2px 6px; font-size:7px; font-weight:700; border-radius:3px; text-transform:uppercase; }
        .badge-success{ background:#4caf50; color:#fff; }
        .badge-danger{ background:#f44336; color:#fff; }
        .badge-secondary{ background:#9e9e9e; color:#fff; }
        .data-table thead { display: table-header-group; }
        .footer { margin-top: 20px; padding: 10px; border-top: 1px solid #ddd; font-size: 9px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $subshop->name ?? 'Shop' }}</h1>
        <div class="subtitle">CHARTS OF ACCOUNT</div>
        <div style="font-size:10px; line-height:1.5; margin: 6px 0 4px;">
            This report lists your chart of accounts including system and user accounts.
        </div>
        <div class="meta">Generated: {{ now()->format('F j, Y \a\t g:i A') }} | System: DukaBase</div>
    </div>

    <table class="stats-table">
        <tr>
            <td class="stat-blue">
                <div class="stat-label">Total Accounts</div>
                <div class="stat-value">{{ number_format($summary['count'] ?? 0) }}</div>
            </td>
            <td class="stat-green">
                <div class="stat-label">Active</div>
                <div class="stat-value">{{ number_format($summary['active'] ?? 0) }}</div>
            </td>
        </tr>
        <tr>
            <td class="stat-gray">
                <div class="stat-label">System Accounts</div>
                <div class="stat-value">{{ number_format($summary['system'] ?? 0) }}</div>
            </td>
            <td class="stat-purple">
                <div class="stat-label">User Accounts</div>
                <div class="stat-value">{{ number_format($summary['user'] ?? 0) }}</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">#</th>
                <th class="col-code">CODE</th>
                <th class="col-name">ACCOUNT NAME</th>
                <th class="col-desc">DESCRIPTION</th>
                <th class="col-class">CLASS</th>
                <th class="col-group">GROUP</th>
                <th class="col-cash">CASH FLOW</th>
                <th class="col-equity">EQUITY</th>
                <th class="col-cust">CUSTOMER</th>
                <th class="col-sys">SYSTEM</th>
                <th class="col-act">ACTIVE</th>
                <th class="col-date">CREATED</th>
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
        </tbody>
    </table>

    <div class="footer">
        Generated by: {{ $generatedBy ?? 'System' }}
    </div>
</body>
</html>
