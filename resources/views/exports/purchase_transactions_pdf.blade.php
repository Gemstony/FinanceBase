<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $subshop->name ?? 'Shop' }} Purchase Transactions Export</title>
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
        .col-no{ width:4%; } .col-date{ width:12%; } .col-ord{ width:10%; } .col-supp{ width:15%; }
        .col-type{ width:8%; } .col-amt{ width:10%; } .col-method{ width:12%; } .col-ref{ width:10%; } .col-user{ width:12%; }
        .badge{ display:inline-block; padding:2px 6px; font-size:7px; font-weight:700; border-radius:3px; text-transform:uppercase; }
        .badge-success{ background:#4caf50; color:#fff; } .badge-warning{ background:#ff9800; color:#fff; }
        .data-table thead { display: table-header-group; }
        .footer { margin-top: 20px; padding: 10px; border-top: 1px solid #ddd; font-size: 9px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $subshop->name ?? 'Shop' }}</h1>
        <div class="subtitle">PURCHASE TRANSACTIONS REPORT</div>
        <div style="font-size:10px; line-height:1.5; margin: 6px 0 4px;">
            This document provides a detailed view of all purchase payment transactions including payments and refunds for the selected period and filters.
        </div>
        <div class="meta">Generated: {{ now()->format('F j, Y \\a\\t g:i A') }} | System: DukaBase</div>
    </div>

    <table class="stats-table">
        <tr>
            <td class="stat-blue">
                <div class="stat-label">Total Transactions</div>
                <div class="stat-value">{{ number_format($summary['count'] ?? 0) }}</div>
            </td>
            <td class="stat-green">
                <div class="stat-label">Total Payments</div>
                <div class="stat-value">TZS{{ number_format($summary['total_payments'] ?? 0, 2) }}</div>
            </td>
        </tr>
        <tr>
            <td class="stat-orange">
                <div class="stat-label">Total Refunds</div>
                <div class="stat-value">TZS{{ number_format(abs($summary['total_refunds'] ?? 0), 2) }}</div>
            </td>
            <td class="stat-red">
                <div class="stat-label">Net Amount</div>
                <div class="stat-value">TZS{{ number_format($summary['net_amount'] ?? 0, 2) }}</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">#</th>
                <th class="col-date">DATE</th>
                <th class="col-ord">ORDER</th>
                <th class="col-supp">SUPPLIER</th>
                <th class="col-type">TYPE</th>
                <th class="col-amt">AMOUNT</th>
                <th class="col-method">PAYMENT METHOD</th>
                <th class="col-ref">REFERENCE</th>
                <th class="col-user">RECORDED BY</th>
            </tr>
        </thead>
        <tbody>
            <?php $i=1; ?>
            @foreach($rows as $txn)
            <tr>
                <td>{{ $i++ }}</td>
                <td>{{ optional($txn->transaction_date)->format('Y-m-d') }}</td>
                <td>{{ optional($txn->purchaseOrder)->order_no ?? '-' }}</td>
                <td>{{ optional($txn->purchaseOrder->supplier)->name ?? '-' }}</td>
                <td>
                    <span class="badge {{ $txn->total_amount < 0 ? 'badge-warning' : 'badge-success' }}">
                        {{ $txn->total_amount < 0 ? 'REFUND' : 'PAYMENT' }}
                    </span>
                </td>
                <td style="text-align:right;">{{ number_format($txn->total_amount, 2) }}</td>
                <td>{{ $txn->payment_method ?? '-' }}</td>
                <td>{{ $txn->reference_number ?? '-' }}</td>
                <td>{{ optional($txn->user)->name ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="margin-top:18px; font-size:9px; text-align:center; color:#666;">
        Report generated by: <strong>{{ $generatedBy ?? 'System' }}</strong> • {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
