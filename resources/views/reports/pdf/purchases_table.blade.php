<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Purchases Table - {{ $subshopName ?? 'All Locations' }}</title>
    <style>
        @page { margin: 20px 15px; font-family: 'DejaVu Sans', Arial, sans-serif; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #333; }
        .header { background: #2c3e50; color: #fff; padding: 16px; border-radius: 4px; text-align: center; margin-bottom: 12px; }
        .header h1 { margin: 0 0 6px 0; font-size: 18px; letter-spacing: .4px; }
        .subtitle { font-size: 10px; opacity: .9; }
        .period { font-size: 9px; opacity: .9; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #e0e0e0; padding: 4px 3px; font-size: 9px; word-wrap: break-word; }
        thead th { background: #f5f7fa; color: #2c3e50; font-size: 9px; text-transform: uppercase; letter-spacing: .2px; }
        tfoot th { background: #f9fafb; font-weight: 700; }
        .text-right { text-align: right; }
        .footer { text-align: center; margin-top: 10px; font-size: 8px; color: #777; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PURCHASES TABLE</h1>
        <div class="subtitle">{{ $subshopName ?? 'All Locations' }}</div>
        <div class="period">{{ \Carbon\Carbon::parse($dateFrom)->format('F j, Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('F j, Y') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Order No</th>
                <th>Date</th>
                <th>Supplier</th>
                <th>Subshop</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">VAT</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Grand</th>
                <th class="text-right">Returns</th>
                <th class="text-right">Refunds</th>
                <th class="text-right">Net Spend</th>
                <th class="text-right">Net Paid</th>
                <th class="text-right">Net Remaining</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td>{{ $r['Order No'] }}</td>
                    <td>{{ $r['Date'] }}</td>
                    <td>{{ $r['Supplier'] }}</td>
                    <td>{{ $r['Subshop'] }}</td>
                    <td class="text-right">{{ number_format($r['Subtotal'], 2) }}</td>
                    <td class="text-right">{{ number_format($r['VAT'], 2) }}</td>
                    <td class="text-right">{{ number_format($r['Discount'], 2) }}</td>
                    <td class="text-right">{{ number_format($r['Grand'], 2) }}</td>
                    <td class="text-right">{{ number_format($r['Returns'], 2) }}</td>
                    <td class="text-right">{{ number_format($r['Refunds'], 2) }}</td>
                    <td class="text-right">{{ number_format($r['Net Spend'], 2) }}</td>
                    <td class="text-right">{{ number_format($r['Net Paid'], 2) }}</td>
                    <td class="text-right">{{ number_format($r['Net Remaining'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" style="text-align:center;">No data</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="8" class="text-right">Totals:</th>
                <th class="text-right">{{ number_format($totals['Returns'] ?? 0, 2) }}</th>
                <th class="text-right">{{ number_format($totals['Refunds'] ?? 0, 2) }}</th>
                <th class="text-right">{{ number_format($totals['Net Spend'] ?? 0, 2) }}</th>
                <th class="text-right">{{ number_format($totals['Net Paid'] ?? 0, 2) }}</th>
                <th class="text-right">{{ number_format($totals['Net Remaining'] ?? 0, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    <div class="footer">Generated at {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
</body>
</html>
