<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $subshop->name ?? 'Shop' }} Sales Returns Export</title>
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
        .data-table{ width:100%; border-collapse:collapse; font-size:8px; margin-top:15px; }
        .data-table thead th{ background:#34495e; color:#fff; padding:8px 4px; text-align:left; font-weight:700; font-size:8px; text-transform:uppercase; border:1px solid #2c3e50; }
        .data-table td{ padding:6px 4px; border:1px solid #ddd; vertical-align:top; word-wrap:break-word; }
        .data-table tbody tr:nth-child(odd){ background:#f9f9f9; }
        .data-table tbody tr:nth-child(even){ background:#fff; }
        .badge{ display:inline-block; padding:2px 6px; font-size:7px; font-weight:700; border-radius:3px; text-transform:uppercase; }
        .badge-ok{ background:#4caf50; color:#fff; }
        .badge-nr{ background:#b0bec5; color:#fff; }
        .data-table thead { display: table-header-group; }
        .footer { margin-top: 20px; padding: 10px; border-top: 1px solid #ddd; font-size: 9px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $subshop->name ?? 'Shop' }}</h1>
        <div class="subtitle">SALES RETURNS REPORT</div>
        <div style="font-size:10px; line-height:1.5; margin: 6px 0 4px;">
            This document consolidates sales returns for the selected filters, including totals and refund indicators.
        </div>
        <div class="meta">Generated: {{ now()->format('F j, Y \a\t g:i A') }} | System: DukaBase</div>
    </div>

    <table class="stats-table">
        <tr>
            <td class="stat-blue">
                <div class="stat-label">Total Returns</div>
                <div class="stat-value">{{ number_format($summary['count'] ?? 0) }}</div>
            </td>
            <td class="stat-green">
                <div class="stat-label">Returned Value</div>
                <div class="stat-value">TZS{{ number_format($summary['returned_total'] ?? 0, 2) }}</div>
            </td>
        </tr>
        <tr>
            <td class="stat-orange">
                <div class="stat-label">Total Refunded</div>
                <div class="stat-value">TZS{{ number_format($summary['refunded_total'] ?? 0, 2) }}</div>
            </td>
            <td>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>ORDER</th>
                <th>DATE</th>
                <th>ITEM</th>
                <th>UNIT PRICE</th>
                <th>RETURNED</th>
                <th>BASE</th>
                <th>VAT</th>
                <th>LINE TOTAL</th>
                <th>REFUNDED</th>
                <th>METHOD</th>
                <th>PROCESSED BY</th>
                <th>REASON</th>
            </tr>
        </thead>
        <tbody>
            <?php $i=1; ?>
            @foreach($rows as $r)
            @php $refAmt = (float)($r->refund_amount ?? 0); @endphp
            <tr>
                <td>{{ $i++ }}</td>
                <td>{{ $r->order_no }}</td>
                <td>{{ optional($r->created_at)->format('Y-m-d H:i') }}</td>
                <td>{{ $r->item_id }} — {{ $r->item_name ?? '' }}</td>
                <td>{{ number_format($r->unit_price,2) }}</td>
                <td>{{ (int)$r->quantity_returned }}</td>
                <td>{{ number_format($r->base_amount,2) }}</td>
                <td>{{ number_format($r->vat_amount,2) }}</td>
                <td>{{ number_format($r->line_total,2) }}</td>
                <td>
                    @if($refAmt < 0)
                        <span class="badge badge-ok">{{ number_format(-$refAmt,2) }}</span>
                    @else
                        <span class="badge badge-nr">No Refund</span>
                    @endif
                </td>
                <td>{{ $r->refund_method ?? '-' }}</td>
                <td>{{ $r->processed_by_name ?? '-' }}</td>
                <td>{{ $r->reason ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Report generated by: <strong>{{ $generatedBy ?? 'System' }}</strong> • {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
