<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $order->order_no }}</title>
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
        /* Print tweaks */
        @media print {
            body { background:#fff; }
            .no-print { display:none !important; }
            /* Keep a visible border when printing */
            .receipt-wrap { border:1px solid #000; box-shadow:none; margin:0 auto; }
        }
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
    <h2 class="title center">INVOICE</h2>
    <div class="center" style="margin:8px 0;">
        <div id="qr-wrap" style="display:inline-block; padding:6px; border:1px solid #e5e5e5; border-radius:4px;">
            <div id="qrcode" style="width:120px; height:120px;"></div>
        </div>
        <div class="muted" style="margin-top:4px;">Order #: {{ $order->order_no }}</div>
    </div>

    <div class="grid">
        <div class="label">Invoice #:</div><div class="val">{{ $order->order_no }}</div>
        <div class="label">Date:</div><div class="val">{{ $order->created_at? $order->created_at->format('d/m/Y H:i') : '' }}</div>
        <div class="label">Cashier:</div><div class="val">{{ $order->creator->name ?? 'System' }}</div>
        <div class="label">Subshop:</div><div class="val">{{ $order->subshop->name ?? '-' }}</div>
    </div>

    <div class="box">
        <div class="label">Customer:</div>
        <div class="val">{{ $order->customer->name ?? 'Walk-in' }}</div>
        @if($order->customer && ($order->customer->phone || $order->customer->address))
            <div class="muted">{{ $order->customer->phone ?? '' }} {{ $order->customer->address ? ' • '.$order->customer->address : '' }}</div>
        @endif
    </div>

    <div class="box" style="background:#fff6f6;border-color:#f7d6d6;">
        <div class="grid">
            <div class="label">Payment Status:</div>
            <div class="val">
                @php
                    $badge = $status==='paid' ? 'badge-success' : ($status==='pending' ? 'badge-danger' : 'badge-warning');
                    $label = strtoupper($status);
                @endphp
                <span class="badge {{ $badge }}">{{ $label }}</span>
            </div>
        </div>
    </div>

    <div class="items">
        @foreach($items as $it)
            <div class="item">
                <div class="name">{{ $it->item_name }}</div>
                <div class="meta">Qty: {{ $it->quantity }} @ {{ number_format($it->unit_price,2) }} {{ $it->unit ? ' • '.$it->unit : '' }}</div>
                <div class="amount-row"><span></span><span>{{ number_format($it->line_total,2) }}</span></div>
            </div>
        @endforeach
    </div>

    <div class="hr"></div>
    <div class="amount-row"><span>Subtotal:</span><span>{{ number_format($order->subtotal,2) }}</span></div>
    <div class="amount-row"><span>VAT:</span><span>{{ number_format($order->vat_total,2) }}</span></div>
    <div class="amount-row"><span>Discount:</span><span>{{ number_format($order->discount_total,2) }}</span></div>
    <div class="amount-row total"><span>TOTAL:</span><span>{{ number_format($order->grand_total,2) }}</span></div>

    <div class="box">
        <div class="amount-row"><span>Amount Paid:</span><span>{{ number_format($paid,2) }}</span></div>
        <div class="amount-row"><span>Remaining:</span><span>{{ number_format($remaining,2) }}</span></div>
    </div>

    <div class="footer">
        <div>Thank you for your business!</div>
        <div class="muted">Please keep this invoice for your records</div>
        <div class="muted">Printed at: {{ now()->format('Y-m-d H:i:s') }}</div>
    </div>qr
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    window.addEventListener('load', function(){
        try {
            var el = document.getElementById('qrcode');
            if (el && window.QRCode) {
                new QRCode(el, {
                    text: "Shop: {{ $order->subshop->name ?? 'Shop' }}\nOrder: {{ $order->order_no }}",
                    width: 120,
                    height: 120,
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        } catch (e) {}
        setTimeout(function(){ window.print(); }, 300);
    });

</script>
</body>
</html>
