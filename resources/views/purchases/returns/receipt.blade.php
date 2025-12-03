<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Return #{{ $order->order_no }}</title>
    <style>
        :root { --w: 360px; }
        * { box-sizing: border-box; }
        body { background:#f7f7f7; font-family: Arial, Helvetica, sans-serif; color:#111; }
        .receipt-wrap { width: var(--w); max-width: 100%; margin: 24px auto; background:#fff; border:1px solid #e2e2e2; border-radius:6px; padding:16px; }
        .center { text-align:center; }
        .muted { color:#555; font-size:12px; }
        .hr { border-top:1px dashed #cfcfcf; margin:10px 0; }
        h2.title { font-size:14px; letter-spacing:2px; margin:8px 0; }
        .grid { display:grid; grid-template-columns: 1fr auto; gap:8px; }
        .label { font-weight:bold; font-size:12px; }
        .val { font-size:12px; }
        .box { background:#f5f7fb; border:1px solid #e7ecf5; border-radius:6px; padding:10px; margin:10px 0; }
        .badge { display:inline-block; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:bold; }
        .badge-success{ background:#eaf7ef; color:#1e7e34; border:1px solid #cfead7; }
        .badge-warning{ background:#fff4e5; color:#b26a00; border:1px solid #ffe1b3; }
        .badge-danger{ background:#fde8e8; color:#b42318; border:1px solid #f9c6c6; }
        .items { margin-top:8px; }
        .item { padding:6px 0; border-bottom:1px dotted #e5e5e5; }
        .item:last-child { border-bottom:0; }
        .item .name { font-size:12px; font-weight:600; }
        .item .meta { font-size:11px; color:#666; }
        .amount-row { display:flex; justify-content:space-between; font-size:12px; margin:2px 0; }
        .amount-row.total { font-weight:bold; font-size:13px; background:#f8fafc; border-top:1px solid #e5ecf6; border-bottom:1px solid #e5ecf6; padding:6px; margin-top:8px; }
        .footer { text-align:center; font-size:11px; color:#666; margin-top:14px; }
        @media print { body { background:#fff; } .no-print { display:none !important; } .receipt-wrap { border:1px solid #000; box-shadow:none; margin:0 auto; } }
        .btn-bar { display:flex; justify-content:center; gap:8px; margin-bottom:10px; }
        .btn { border:1px solid #ddd; background:#fff; padding:6px 10px; border-radius:4px; font-size:12px; cursor:pointer; }
    </style>
</head>
<body>
<div class="receipt-wrap">
    <div class="btn-bar no-print">
        <a href="{{ url()->previous() }}" class="btn">Back</a>
        <button onclick="window.print()" class="btn">Print</button>
    </div>
    <div class="center">
        <div style="font-weight:700; font-size:16px;">{{ $order->subshop->name ?? 'Shop' }}</div>
        @if(($order->subshop->address ?? null) || ($order->subshop->phone ?? null))
            <div class="muted">
                {{ $order->subshop->address ?? '' }}<br>
                {{ $order->subshop->phone ?? '' }}
            </div>
        @endif
    </div>
    <div class="hr"></div>
    <h2 class="title center">PURCHASE RETURN</h2>

    <div class="grid">
        <div class="label">Purchase #:</div><div class="val">{{ $order->order_no }}</div>
        <div class="label">Return Date:</div><div class="val">{{ $return->created_at? $return->created_at->format('d/m/Y H:i') : '' }}</div>
        <div class="label">Processed By:</div><div class="val">{{ $order->creator->name ?? 'System' }}</div>
        <div class="label">Subshop:</div><div class="val">{{ $order->subshop->name ?? '-' }}</div>
    </div>

    <div class="box">
        <div class="label">Supplier:</div>
        <div class="val">{{ $order->supplier->name ?? '-' }}</div>
    </div>

    <div class="items">
        <div class="item">
            <div class="name">{{ $line->item_name }}</div>
            <div class="meta">Qty Returned: {{ $line->quantity }} @ {{ number_format($line->unit_price,2) }} {{ $line->unit ? ' • '.$line->unit : '' }}</div>
            <div class="amount-row"><span></span><span>{{ number_format($line->line_total,2) }}</span></div>
        </div>
    </div>

    <div class="hr"></div>
    <div class="amount-row"><span>Base:</span><span>{{ number_format($return->base_amount,2) }}</span></div>
    <div class="amount-row"><span>VAT:</span><span>{{ number_format($return->vat_amount,2) }}</span></div>
    <div class="amount-row total"><span>Total Returned:</span><span>{{ number_format($return->line_total,2) }}</span></div>

    <div class="box" style="background:#fff6f6;border-color:#f7d6d6;">
        <div class="grid">
            <div class="label">Refund:</div>
            <div class="val">
                @php $amt = isset($refund) && $refund ? (float)$refund->total_amount : 0; @endphp
                @if(isset($refund) && $refund)
                    {{ $amt < 0 ? number_format(-$amt,2) : number_format(0,2) }} {{ $refund->payment_method ? '('.strtoupper($refund->payment_method).')' : '' }}
                @else
                    {{ number_format(0,2) }}
                @endif
            </div>
            @if(isset($refund) && $refund)
                <div class="label">Refund Date:</div><div class="val">{{ $refund->transaction_date ? \Illuminate\Support\Carbon::parse($refund->transaction_date)->format('d/m/Y') : '' }}</div>
                <div class="label">Reference:</div><div class="val">{{ $refund->reference_number ?? '-' }}</div>
            @endif
        </div>
    </div>

    <div class="footer">
        Thank you. Keep this slip for your records.
    </div>
</div>
</body>
</html>
