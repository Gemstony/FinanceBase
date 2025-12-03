<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Sales Returns Export</title>
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; font-size: 12px; }
        th { background: #f1f5fb; text-align: left; }
    </style>
</head>
<body>
    <h3>Sales Returns - {{ $subshop->name ?? '-' }}</h3>
    <p>
        Total Returns: {{ number_format($summary['count'] ?? 0) }}
        | Returned Value: {{ number_format($summary['returned_total'] ?? 0, 2) }}
        | Refunded: {{ number_format($summary['refunded_total'] ?? 0, 2) }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Order No</th>
                <th>Item</th>
                <th>Unit Price</th>
                <th>Returned</th>
                <th>Base</th>
                <th>VAT</th>
                <th>Line Total</th>
                <th>Refunded</th>
                <th>Method</th>
                <th>Processed By</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $r)
            <tr>
                <td>{{ $r->created_at? $r->created_at->format('Y-m-d H:i:s') : '' }}</td>
                <td>{{ $r->order_no }}</td>
                <td>{{ $r->item_id }} — {{ $r->item_name ?? '' }}</td>
                <td>{{ number_format($r->unit_price,2,'.','') }}</td>
                <td>{{ (int)$r->quantity_returned }}</td>
                <td>{{ number_format($r->base_amount,2,'.','') }}</td>
                <td>{{ number_format($r->vat_amount,2,'.','') }}</td>
                <td>{{ number_format($r->line_total,2,'.','') }}</td>
                @php $refAmt = (float)($r->refund_amount ?? 0); @endphp
                <td>{{ $refAmt<0 ? number_format(-$refAmt,2,'.','') : '' }}</td>
                <td>{{ $r->refund_method ?? '' }}</td>
                <td>{{ $r->processed_by_name ?? '' }}</td>
                <td>{{ $r->reason ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
