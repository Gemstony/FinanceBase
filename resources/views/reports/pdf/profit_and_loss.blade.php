<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Profit & Loss</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
    h2 { margin: 0 0 8px; }
    .muted { color: #555; font-size: 11px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ddd; padding: 6px; }
    th { background: #f0f0f0; text-align: left; }
    .text-right { text-align: right; }
    .mb-2 { margin-bottom: 10px; }
  </style>
</head>
<body>
  <h2>Profit & Loss Report</h2>
  <div class="muted mb-2">Period: {{ $dateFrom }} to {{ $dateTo }} @if(!empty($subshopName)) | Subshop: {{ $subshopName }} @endif</div>

  <table class="mb-2">
    <tbody>
      <tr><th colspan="2">Revenue</th></tr>
      <tr><td>Gross Sales</td><td class="text-right">Tsh {{ number_format($kpi['gross_sales'] ?? 0, 2) }}</td></tr>
      <tr><td>Sales Returns</td><td class="text-right">(Tsh {{ number_format($kpi['sales_returns'] ?? 0, 2) }})</td></tr>
      <tr><td><strong>Net Sales</strong></td><td class="text-right"><strong>Tsh {{ number_format($kpi['net_sales'] ?? 0, 2) }}</strong></td></tr>
      <tr><th colspan="2">Cost of Sales</th></tr>
      <tr><td>COGS</td><td class="text-right">(Tsh {{ number_format($kpi['cogs'] ?? 0, 2) }})</td></tr>
      <tr><td><strong>Gross Profit</strong></td><td class="text-right"><strong>Tsh {{ number_format($kpi['gross_profit'] ?? 0, 2) }}</strong> ({{ number_format($kpi['gross_margin_pct'] ?? 0, 2) }}%)</td></tr>
      <tr><th colspan="2">Operating Expenses</th></tr>
      <tr><td>Expenses</td><td class="text-right">(Tsh {{ number_format($kpi['operating_expenses'] ?? 0, 2) }})</td></tr>
      <tr><th colspan="2">Net Result</th></tr>
      <tr><td><strong>Net Profit</strong></td><td class="text-right"><strong>Tsh {{ number_format($kpi['net_profit'] ?? 0, 2) }}</strong></td></tr>
    </tbody>
  </table>

  @if(($scope ?? 'summary') === 'daily')
    <h3>Daily Detail</h3>
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th class="text-right">Gross Sales</th>
          <th class="text-right">Sales Returns</th>
          <th class="text-right">Net Sales</th>
          <th class="text-right">COGS</th>
          <th class="text-right">Gross Profit</th>
          <th class="text-right">Operating Expenses</th>
          <th class="text-right">Net Profit</th>
        </tr>
      </thead>
      <tbody>
        @foreach(($daily ?? []) as $row)
          <tr>
            <td>{{ $row['Date'] }}</td>
            <td class="text-right">Tsh {{ number_format($row['Gross Sales'] ?? 0, 2) }}</td>
            <td class="text-right">(Tsh {{ number_format(abs($row['Sales Returns'] ?? 0), 2) }})</td>
            <td class="text-right">Tsh {{ number_format($row['Net Sales'] ?? 0, 2) }}</td>
            <td class="text-right">(Tsh {{ number_format(abs($row['COGS'] ?? 0), 2) }})</td>
            <td class="text-right">Tsh {{ number_format($row['Gross Profit'] ?? 0, 2) }}</td>
            <td class="text-right">(Tsh {{ number_format(abs($row['Operating Expenses'] ?? 0), 2) }})</td>
            <td class="text-right">Tsh {{ number_format($row['Net Profit'] ?? 0, 2) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</body>
</html>
