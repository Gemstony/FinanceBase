<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Loan Portfolio Report - {{ $subshopName ?? 'All Branches' }}</title>
    <style>
        @page { margin: 20px 15px; font-family: 'DejaVu Sans', Arial, sans-serif; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #333; }
        .header { background: #2c3e50; color: #fff; padding: 20px; border-radius: 4px; text-align: center; margin-bottom: 18px; }
        .header h1 { margin: 0 0 8px 0; font-size: 18px; letter-spacing: .5px; }
        .subtitle { font-size: 12px; opacity: .9; }
        .period { font-size: 11px; opacity: .9; margin-top: 4px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; margin-bottom: 16px; }
        .box { background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 10px; text-align: center; }
        .label { font-size: 9px; color: #666; text-transform: uppercase; font-weight: 600; margin-bottom: 5px; }
        .value { font-size: 14px; font-weight: 700; color: #2c3e50; }
        .section-title { font-size: 11px; font-weight: 700; margin: 16px 0 8px 0; text-transform: uppercase; color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #e5e5e5; padding: 6px; }
        th { background: #f6f7f8; font-weight: 700; text-align: left; }
        td.num, th.num { text-align: right; }
        .footer { text-align: center; margin-top: 18px; font-size: 8px; color: #777; }
    </style>
</head>
<body>
    <div class="header">
        <h1>LOAN PORTFOLIO REPORT</h1>
        <div class="subtitle">{{ $subshopName ?? 'All Branches' }}</div>
        <div class="period">{{ \Carbon\Carbon::parse($dateFrom)->format('F j, Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('F j, Y') }}</div>
    </div>

    @php($summary = $report['summary'] ?? [])

    <div class="stats">
        <div class="box">
            <div class="label">Total Outstanding</div>
            <div class="value">{{ number_format($summary['total_outstanding'] ?? 0, 2) }}</div>
        </div>
        <div class="box">
            <div class="label">Active Loans</div>
            <div class="value">{{ number_format($summary['active_loans'] ?? 0) }}</div>
        </div>
        <div class="box">
            <div class="label">Active Borrowers</div>
            <div class="value">{{ number_format($summary['active_borrowers'] ?? 0) }}</div>
        </div>
        <div class="box">
            <div class="label">Disbursed (Period)</div>
            <div class="value">{{ number_format($summary['total_disbursed_period'] ?? 0, 2) }}</div>
        </div>
        <div class="box">
            <div class="label">Repayments (Period)</div>
            <div class="value">{{ number_format($summary['total_repayments_period'] ?? 0, 2) }}</div>
        </div>
        <div class="box">
            <div class="label">Avg Loan Size</div>
            <div class="value">{{ number_format($summary['avg_loan_size'] ?? 0, 2) }}</div>
        </div>
    </div>

    <div class="section-title">Portfolio by Product</div>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">Loans</th>
                <th class="num">Outstanding</th>
                <th class="num">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['composition']['by_product'] ?? []) as $r)
                <tr>
                    <td>{{ $r['product_name'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loans_count'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['composition']['by_product'] ?? []))
                <tr><td colspan="4" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="section-title">Portfolio at Risk (PAR)</div>
    <table>
        <thead>
            <tr>
                <th>Bucket</th>
                <th class="num">Outstanding</th>
                <th class="num">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['par'] ?? []) as $r)
                <tr>
                    <td>{{ $r['bucket'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($r['pct'] ?? 0, 2) }}%</td>
                </tr>
            @endforeach
            @if(empty($report['par'] ?? []))
                <tr><td colspan="3" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="section-title">Portfolio Aging</div>
    <table>
        <thead>
            <tr>
                <th>Bucket</th>
                <th class="num">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['aging'] ?? []) as $r)
                <tr>
                    <td>{{ $r['bucket'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['aging'] ?? []))
                <tr><td colspan="2" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="section-title">Top Borrowers</div>
    <table>
        <thead>
            <tr>
                <th>Customer</th>
                <th class="num">Loan Count</th>
                <th class="num">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['top_borrowers'] ?? []) as $r)
                <tr>
                    <td>{{ $r['customer'] ?? '' }}</td>
                    <td class="num">{{ number_format($r['loan_count'] ?? 0) }}</td>
                    <td class="num">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(empty($report['top_borrowers'] ?? []))
                <tr><td colspan="3" style="text-align:center; color:#777; padding:10px;">No data</td></tr>
            @endif
        </tbody>
    </table>

    <div class="footer">Generated at {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
</body>
</html>
