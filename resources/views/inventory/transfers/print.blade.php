<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dispatch Note #{{ $transfer->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #222; }
        .container { width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        h1 { margin: 0; font-size: 20px; }
        .meta { text-align: right; }
        .meta div { margin-bottom: 4px; }
        .section { margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #f0f0f0; }
        .small { font-size: 11px; color: #555; }
        .signatures { display: flex; justify-content: space-between; margin-top: 40px; }
        .sign { width: 47%; }
        .sign .line { border-top: 1px solid #000; margin-top: 50px; padding-top: 4px; text-align: center; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Dispatch Note</h1>
            <div class="small">Transfer #: {{ $transfer->id }}</div>
            <div class="small">Date: {{ $transfer->created_at? $transfer->created_at->format('Y-m-d H:i') : '' }}</div>
        </div>
        <div class="meta">
            <div><strong>From:</strong> {{ $transfer->sourceSubshop->name ?? '-' }}</div>
            <div><strong>To:</strong> {{ $transfer->destinationSubshop->name ?? '-' }}</div>
            <div><strong>Status:</strong> {{ ucwords(str_replace('_',' ', $transfer->status)) }}</div>
        </div>
    </div>

    <div class="section">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>SKU</th>
                    <th>Batch</th>
                    <th>Expiry</th>
                    <th>Planned</th>
                    <th>Dispatched</th>
                </tr>
            </thead>
            <tbody>
            @php $i=1; @endphp
            @foreach($transfer->items as $ti)
                @foreach($ti->batches as $tib)
                    <tr>
                        <td>{{ $i++ }}</td>
                        <td>{{ $ti->item_name_snapshot }}</td>
                        <td>{{ $ti->sku_snapshot }}</td>
                        <td>{{ $tib->batch_number }}</td>
                        <td>{{ $tib->expire_date ? \Carbon\Carbon::parse($tib->expire_date)->format('Y-m-d') : '-' }}</td>
                        <td>{{ number_format($tib->planned_qty,3) }}</td>
                        <td>{{ number_format($tib->dispatched_qty,3) }}</td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="section small">
        <strong>Notes:</strong>
        <div>{{ $transfer->notes }}</div>
    </div>

    <div class="signatures">
        <div class="sign">
            <div><strong>Dispatched By:</strong></div>
            <div class="line">Signature & Name</div>
            <div class="small">Date: _____________</div>
        </div>
        <div class="sign">
            <div><strong>Received By:</strong></div>
            <div class="line">Signature & Name</div>
            <div class="small">Date: _____________</div>
        </div>
    </div>

    <div class="no-print" style="margin-top:20px; text-align:center;">
        <button onclick="window.print()">Print</button>
    </div>
</div>
</body>
</html>
