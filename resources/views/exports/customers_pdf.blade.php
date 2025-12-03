<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $subshop->name ?? 'Shop' }} Customers Export</title>
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
        .stat-orange{ background:#fff3e0; border-color:#ff9800; } .stat-orange .stat-value{ color:#f57c00; }
        .stat-red{ background:#ffebee; border-color:#f44336; } .stat-red .stat-value{ color:#d32f2f; }
        .data-table{ width:100%; border-collapse:collapse; font-size:8px; margin-top:15px; }
        .data-table thead th{ background:#34495e; color:#fff; padding:8px 4px; text-align:left; font-weight:700; font-size:8px; text-transform:uppercase; border:1px solid #2c3e50; }
        .data-table td{ padding:6px 4px; border:1px solid #ddd; vertical-align:top; word-wrap:break-word; }
        .data-table tbody tr:nth-child(odd){ background:#f9f9f9; }
        .data-table tbody tr:nth-child(even){ background:#fff; }
        .col-no{ width:4%; } .col-name{ width:18%; } .col-contact{ width:16%; } .col-email{ width:18%; } .col-phone{ width:12%; }
        .col-status{ width:8%; } .col-orders{ width:8%; } .col-spent{ width:10%; } .col-date{ width:10%; }
        .badge{ display:inline-block; padding:2px 6px; font-size:7px; font-weight:700; border-radius:3px; text-transform:uppercase; }
        .badge-success{ background:#4caf50; color:#fff; } .badge-warning{ background:#ff9800; color:#fff; } .badge-danger{ background:#f44336; color:#fff; } .badge-secondary{ background:#607d8b; color:#fff; }
        .data-table thead { display: table-header-group; }
        .right{ text-align:right; }
        .footer { margin-top: 20px; padding: 10px; border-top: 1px solid #ddd; font-size: 9px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $subshop->name ?? 'Shop' }}</h1>
        <div class="subtitle">CUSTOMERS REPORT</div>
        <div style="font-size:10px; line-height:1.5; margin: 6px 0 4px;">
            This report lists customers with activity metrics (orders and total spent) based on your selected filters.
        </div>
        <div class="meta">Generated: {{ now()->format('F j, Y \a\t g:i A') }} | System: DukaBase</div>
    </div>

    <table class="stats-table">
        <tr>
            <td class="stat-blue">
                <div class="stat-label">Total Customers</div>
                <div class="stat-value">{{ number_format($summary['count'] ?? 0) }}</div>
            </td>
            <td class="stat-green">
                <div class="stat-label">Total Spent</div>
                <div class="stat-value">TZS{{ number_format($summary['total_spent'] ?? 0, 2) }}</div>
            </td>
        </tr>
        <tr>
            <td class="stat-orange">
                <div class="stat-label">Total Orders</div>
                <div class="stat-value">{{ number_format($summary['total_orders'] ?? 0) }}</div>
            </td>
            <td class="stat-red">
                <div class="stat-label">Active ({{ number_format($summary['active_count'] ?? 0) }}) | Inactive ({{ number_format($summary['inactive_count'] ?? 0) }})</div>
                <div class="stat-value">&nbsp;</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">#</th>
                <th class="col-name">NAME</th>
                <th class="col-contact">CONTACT PERSON</th>
                <th class="col-email">EMAIL</th>
                <th class="col-phone">PHONE</th>
                <th class="col-status">STATUS</th>
                <th class="col-orders">ORDERS</th>
                <th class="col-spent right">TOTAL SPENT</th>
                <th class="col-date">JOINED</th>
            </tr>
        </thead>
        <tbody>
            <?php $i=1; ?>
            @foreach ($rows as $e)
                <tr>
                    <td>{{ $i++ }}</td>
                    <td>{{ $e->name }}</td>
                    <td>{{ $e->contact_person ?? '-' }}</td>
                    <td>{{ $e->email ?? '-' }}</td>
                    <td>{{ $e->phone ?? '-' }}</td>
                    <td>
                        @php $st = $e->is_active ? 'ACTIVE' : 'INACTIVE'; @endphp
                        <span class="badge {{ $e->is_active ? 'badge-success' : 'badge-secondary' }}">{{ $st }}</span>
                    </td>
                    <td class="right">{{ (int)($e->orders_count ?? 0) }}</td>
                    <td class="right">{{ number_format((float)($e->total_spent ?? 0), 2) }}</td>
                    <td>{{ optional($e->created_at)->format('Y-m-d') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Report generated by: <strong>{{ $generatedBy ?? 'System' }}</strong> • {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
