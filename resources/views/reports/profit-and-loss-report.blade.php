@extends('adminlte::page')

@section('title', 'Profit & Loss Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-balance-scale"></i> Profit & Loss Overview</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-balance-scale"></i> P&L</h1>
      <div class="small text-light-50">Period: {{ $dateFrom ?? '' }} to {{ $dateTo ?? '' }}</div>
  </div>
  <a href="{{ route('dashboard') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
  </div>
</div>
@stop

@section('content')

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="{{ route('reports.pl.index') }}" class="form-row">
      <div class="form-group col-12 col-sm-6 col-md-3">
        <label for="date_from">From</label>
        <input type="date" id="date_from" name="date_from" value="{{ $dateFrom ?? '' }}" class="form-control" />
      </div>


      <div class="form-group col-12 col-sm-6 col-md-3">
        <label for="date_to">To</label>
        <input type="date" id="date_to" name="date_to" value="{{ $dateTo ?? '' }}" class="form-control" />
      </div>
      <div class="form-group col-12 col-sm-6 col-md-3">
        <label for="subshop_id">Subshop</label>
        <select id="subshop_id" name="subshop_id" class="form-control">
          <option value="">All Accessible</option>
          @foreach(($subshops ?? []) as $s)
            <option value="{{ $s->id }}" {{ ($selectedSubshopId ?? null) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-group col-12 col-sm-6 col-md-2">
        <label for="basis">Basis
          <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Accrual: recognize revenue on order and expenses when incurred. Cash: recognize revenue on payments and expenses when paid (approved & expense_date)."></i>
        </label>
        <select id="basis" name="basis" class="form-control">
          <option value="accrual" {{ ($basis ?? 'accrual')=='accrual' ? 'selected' : '' }}>Accrual</option>
          <option value="cash" {{ ($basis ?? 'accrual')=='cash' ? 'selected' : '' }}>Cash</option>
        </select>
      </div>
      <div class="form-group col-12 col-sm-6 col-md-2">
        <label for="compare">Compare
          <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Compare KPIs against previous period (same length immediately before) or same period last year."></i>
        </label>
        <select id="compare" name="compare" class="form-control">
          <option value="none" {{ ($compare ?? 'none')=='none' ? 'selected' : '' }}>None</option>
          <option value="prev_period" {{ ($compare ?? 'none')=='prev_period' ? 'selected' : '' }}>Previous Period</option>
          <option value="prev_year" {{ ($compare ?? 'none')=='prev_year' ? 'selected' : '' }}>Same Period Last Year</option>
        </select>
      </div>
      <div class="form-group col-12 col-md-1 d-flex align-items-end">
        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-filter"></i> Apply</button>
      </div>
      <div class="form-group col-12 col-md-2 d-flex align-items-end">
        <a href="{{ route('reports.pl.index') }}" class="btn btn-outline-secondary btn-block"><i class="fas fa-times"></i> Clear</a>
      </div>
    </form>
  </div>
  </div>


  @can('export_profit_and_loss_reports')
  <div class="mb-3">
    <div class="d-flex flex-column flex-sm-row">
      <div class="mr-sm-2 mb-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="Export Summary">
          <a class="btn btn-outline-primary" href="{{ route('reports.pl.export', ['format' => 'xlsx', 'date_from' => $dateFrom ?? null, 'date_to' => $dateTo ?? null, 'subshop_id' => $selectedSubshopId ?? null, 'basis' => $basis ?? 'accrual', 'scope' => 'summary']) }}">
            <i class="fas fa-file-excel"></i> Summary XLSX
          </a>
          <a class="btn btn-outline-secondary" href="{{ route('reports.pl.export', ['format' => 'csv', 'date_from' => $dateFrom ?? null, 'date_to' => $dateTo ?? null, 'subshop_id' => $selectedSubshopId ?? null, 'basis' => $basis ?? 'accrual', 'scope' => 'summary']) }}">
            <i class="fas fa-file-csv"></i> Summary CSV
          </a>
          <a class="btn btn-outline-danger" href="{{ route('reports.pl.export', ['format' => 'pdf', 'date_from' => $dateFrom ?? null, 'date_to' => $dateTo ?? null, 'subshop_id' => $selectedSubshopId ?? null, 'basis' => $basis ?? 'accrual', 'scope' => 'summary']) }}">
            <i class="fas fa-file-pdf"></i> Summary PDF
          </a>
        </div>
      </div>
      <div class="mb-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="Export Detailed">
          <a class="btn btn-outline-primary" href="{{ route('reports.pl.export', ['format' => 'xlsx', 'date_from' => $dateFrom ?? null, 'date_to' => $dateTo ?? null, 'subshop_id' => $selectedSubshopId ?? null, 'basis' => $basis ?? 'accrual', 'scope' => 'daily']) }}">
            <i class="fas fa-file-excel"></i> Detailed XLSX
          </a>
          <a class="btn btn-outline-secondary" href="{{ route('reports.pl.export', ['format' => 'csv', 'date_from' => $dateFrom ?? null, 'date_to' => $dateTo ?? null, 'subshop_id' => $selectedSubshopId ?? null, 'basis' => $basis ?? 'accrual', 'scope' => 'daily']) }}">
            <i class="fas fa-file-csv"></i> Detailed CSV
          </a>
          <a class="btn btn-outline-danger" href="{{ route('reports.pl.export', ['format' => 'pdf', 'date_from' => $dateFrom ?? null, 'date_to' => $dateTo ?? null, 'subshop_id' => $selectedSubshopId ?? null, 'basis' => $basis ?? 'accrual', 'scope' => 'daily']) }}">
            <i class="fas fa-file-pdf"></i> Detailed PDF
          </a>
        </div>
      </div>
    </div>
  </div>
  @endcan

  @if(!empty($kpiCompare))
  <div class="card mt-3">
    <div class="card-header"><strong>Comparison ({{ $kpiCompare['window']==='prev_period' ? 'Previous Period' : 'Same Period Last Year' }})</strong> <small class="text-muted">{{ $kpiCompare['range'][0] }} to {{ $kpiCompare['range'][1] }}</small></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th>Metric</th>
              <th class="text-right">Current</th>
              <th class="text-right">Previous</th>
              <th class="text-right">Change</th>
            </tr>
          </thead>
          <tbody>
            @php
              $rows = [
                ['label'=>'Net Sales','k'=>'net_sales','fmt'=>'currency'],
                ['label'=>'Gross Profit','k'=>'gross_profit','fmt'=>'currency'],
                ['label'=>'Net Profit','k'=>'net_profit','fmt'=>'currency'],
                ['label'=>'Gross Margin %','k'=>'gross_margin_pct','fmt'=>'percent'],
              ];
            @endphp
            @foreach($rows as $r)
              @php $cur = $kpiCompare['values'][$r['k']]['current'] ?? 0; $prev = $kpiCompare['values'][$r['k']]['prev'] ?? 0; $delta = $cur - $prev; $pct = $prev>0 ? (($cur-$prev)/$prev*100) : null; @endphp
              <tr>
                <td>{{ $r['label'] }}</td>
                <td class="text-right">@if($r['fmt']==='currency') Tsh {{ number_format($cur,2) }} @else {{ number_format($cur,2) }}%@endif</td>
                <td class="text-right">@if($r['fmt']==='currency') Tsh {{ number_format($prev,2) }} @else {{ number_format($prev,2) }}%@endif</td>
                <td class="text-right">
                  @if($r['fmt']==='currency')
                    {{ $delta>=0?'+':'' }}Tsh {{ number_format($delta,2) }} @if(!is_null($pct)) ({{ $pct>=0?'+':'' }}{{ number_format($pct,2) }}%) @endif
                  @else
                    {{ $delta>=0?'+':'' }}{{ number_format($delta,2) }}% @if(!is_null($pct)) ({{ $pct>=0?'+':'' }}{{ number_format($pct,2) }}%) @endif
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
  @endif

  @if(!empty($subshopBreakdown) && count($subshopBreakdown)>0)
  <div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><strong>Subshop Breakdown</strong>
        <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Shows sales, returns, net sales, COGS and gross profit by subshop for the selected period and basis."></i>
      </span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th>Subshop</th>
              <th class="text-right">Gross Sales</th>
              <th class="text-right">Sales Returns</th>
              <th class="text-right">Net Sales</th>
              <th class="text-right">COGS</th>
              <th class="text-right">Gross Profit</th>
            </tr>
          </thead>
          <tbody>
            @foreach($subshopBreakdown as $row)
              <tr>
                <td>{{ $row['subshop'] }}</td>
                <td class="text-right">Tsh {{ number_format($row['gross_sales'] ?? 0, 2) }}</td>
                <td class="text-right">(Tsh {{ number_format($row['sales_returns'] ?? 0, 2) }})</td>
                <td class="text-right">Tsh {{ number_format($row['net_sales'] ?? 0, 2) }}</td>
                <td class="text-right">(Tsh {{ number_format($row['cogs'] ?? 0, 2) }})</td>
                <td class="text-right">Tsh {{ number_format($row['gross_profit'] ?? 0, 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
  @endif

  <div class="row mb-3">
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
      <div class="small-box kpi-box bg-primary" data-toggle="tooltip" title="Gross sales within period">
        <div class="inner">
          <h3 class="mb-0">Tsh {{ number_format($kpi['gross_sales'] ?? 0, 2) }}</h3>
          <p>Gross Sales</p>
        </div>
        <div class="icon"><i class="fas fa-cash-register"></i></div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
      <div class="small-box kpi-box bg-warning" data-toggle="tooltip" title="Sales returns reduce revenue">
        <div class="inner">
          <h3 class="mb-0">Tsh {{ number_format($kpi['sales_returns'] ?? 0, 2) }}</h3>
          <p>Sales Returns</p>
        </div>
        <div class="icon"><i class="fas fa-undo"></i></div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
      <div class="small-box kpi-box bg-success" data-toggle="tooltip" title="Net Sales = Gross − Returns">
        <div class="inner">
          <h3 class="mb-0">Tsh {{ number_format($kpi['net_sales'] ?? 0, 2) }}</h3>
          <p>Net Sales</p>
        </div>
        <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
      <div class="small-box kpi-box bg-secondary" data-toggle="tooltip" title="Cost of goods sold for the period">
        <div class="inner">
          <h3 class="mb-0">Tsh {{ number_format($kpi['cogs'] ?? 0, 2) }}</h3>
          <p>COGS</p>
        </div>
        <div class="icon"><i class="fas fa-box-open"></i></div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
      <div class="small-box kpi-box bg-info" data-toggle="tooltip" title="Gross Profit = Net Sales − COGS">
        <div class="inner">
          <h3 class="mb-0">Tsh {{ number_format($kpi['gross_profit'] ?? 0, 2) }}</h3>
          <p>Gross Profit</p>
        </div>
        <div class="icon"><i class="fas fa-chart-line"></i></div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
      <div class="small-box kpi-box bg-purple" data-toggle="tooltip" title="Gross Margin % = Gross Profit / Net Sales">
        <div class="inner">
          <h3 class="mb-0">{{ number_format($kpi['gross_margin_pct'] ?? 0, 2) }}%</h3>
          <p>Gross Margin %</p>
        </div>
        <div class="icon"><i class="fas fa-percentage"></i></div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
      <div class="small-box kpi-box bg-teal" data-toggle="tooltip" title="Operating expenses in the period">
        <div class="inner">
          <h3 class="mb-0">Tsh {{ number_format($kpi['operating_expenses'] ?? 0, 2) }}</h3>
          <p>Operating Expenses</p>
        </div>
        <div class="icon"><i class="fas fa-receipt"></i></div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
      <div class="small-box kpi-box bg-danger" data-toggle="tooltip" title="Net Profit = Gross Profit − Operating Expenses">
        <div class="inner">
          <h3 class="mb-0">Tsh {{ number_format($kpi['net_profit'] ?? 0, 2) }}</h3>
          <p>Net Profit</p>
        </div>
        <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
      </div>
    </div>
  </div>
  <div class="row mt-3">

    <div class="col-12 col-lg-6 mb-3">
      <div class="card h-100">
        <div class="card-header"><strong>Sales vs COGS (Over Time)</strong> <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Revenue vs COGS trend for the selected basis and period. Cash basis uses payments as revenue."></i></div>
        <div class="card-body chart-container"><canvas id="plSalesCogsChart"></canvas></div>
      </div>
    </div>
    <div class="col-12 col-lg-6 mb-3">
      <div class="card h-100">
        <div class="card-header"><strong>Gross Margin % (Over Time)</strong> <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Gross margin = (Net Sales − COGS) ÷ Net Sales × 100. Displays trend over the period."></i></div>
        <div class="card-body chart-container"><canvas id="plMarginChart"></canvas></div>
      </div>
    </div>
    <div class="col-12 mb-3">
      <div class="card h-100">
        <div class="card-header"><strong>Waterfall: Gross → Net</strong> <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Breakdown from Gross Sales to Net Profit: subtract returns, COGS, expenses. Honors selected basis for revenue and expenses."></i></div>
        <div class="card-body chart-container"><canvas id="plWaterfallChart"></canvas></div>
      </div>
    </div>
  </div>


  <div class="card">
    <div class="card-header"><strong>Summary</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <tbody>
            <tr class="bg-light"><th colspan="2">Revenue</th></tr>
            <tr><td>Gross Sales</td><td class="text-right">Tsh {{ number_format($kpi['gross_sales'] ?? 0, 2) }}</td></tr>
            <tr><td>Sales Returns</td><td class="text-right">(Tsh {{ number_format($kpi['sales_returns'] ?? 0, 2) }})</td></tr>
            <tr><td><strong>Net Sales</strong></td><td class="text-right"><strong>Tsh {{ number_format($kpi['net_sales'] ?? 0, 2) }}</strong></td></tr>
            <tr class="bg-light"><th colspan="2">Cost of Sales</th></tr>
            <tr><td>COGS</td><td class="text-right">(Tsh {{ number_format($kpi['cogs'] ?? 0, 2) }})</td></tr>
            <tr><td><strong>Gross Profit</strong></td><td class="text-right"><strong>Tsh {{ number_format($kpi['gross_profit'] ?? 0, 2) }}</strong> ({{ number_format($kpi['gross_margin_pct'] ?? 0, 2) }}%)</td></tr>
            <tr class="bg-light"><th colspan="2">Operating Expenses</th></tr>
            <tr><td>Expenses</td><td class="text-right">(Tsh {{ number_format($kpi['operating_expenses'] ?? 0, 2) }})</td></tr>
            <tr class="bg-light"><th colspan="2">Net Result</th></tr>
            <tr><td><strong>Net Profit</strong></td><td class="text-right"><strong>Tsh {{ number_format($kpi['net_profit'] ?? 0, 2) }}</strong></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

    <!-- Recommendations -->
<div class="card shadow-sm border-0 mt-4">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:linear-gradient(135deg,#f5f3ff,#fff);">
      <h3 class="card-title mb-0"><i class="fas fa-lightbulb mr-1 text-purple"></i> Recommendations</h3>
      <span class="text-muted small">Auto-generated from selected period, basis and filters</span>
    </div>
    <div class="card-body">
      @php
        // Short-hand KPIs
        $ks = $kpi ?? [];
        $netSales = (float)($ks['net_sales'] ?? 0);
        $cogs = (float)($ks['cogs'] ?? 0);
        $gp = (float)($ks['gross_profit'] ?? 0);
        $gmPct = (float)($ks['gross_margin_pct'] ?? 0);
        $opex = (float)($ks['operating_expenses'] ?? 0);
        $np = (float)($ks['net_profit'] ?? 0);
        $salesReturns = (float)($ks['sales_returns'] ?? 0);

        // Trend vs comparison (if present)
        $trendText = null; $trendBadge = null; $npCur = null; $npPrev = null; $npPct = null; $compareLbl = null;
        if (!empty($kpiCompare) && !empty($kpiCompare['values']['net_profit'])) {
          $npCur = (float)($kpiCompare['values']['net_profit']['current'] ?? 0);
          $npPrev = (float)($kpiCompare['values']['net_profit']['prev'] ?? 0);
          if ($npPrev > 0) { $npPct = (($npCur - $npPrev)/$npPrev)*100; }
          if ($npCur > $npPrev) { $trendText = 'up'; $trendBadge = is_null($npPct) ? 'up' : (number_format($npPct,1).'% up'); }
          elseif ($npCur < $npPrev) { $trendText = 'down'; $trendBadge = is_null($npPct) ? 'down' : (number_format($npPct,1).'% down'); }
          else { $trendText = 'flat'; }
          $compareLbl = $kpiCompare['window']==='prev_period' ? 'prev. period' : 'same period last year';
        }

        // Subshop disparity by gross profit (fallback net sales)
        $hiShop = null; $loShop = null; $hiVal = null; $loVal = null; $gap = null;
        if (!empty($subshopBreakdown) && is_array($subshopBreakdown)) {
          $vals = array_map(function($r){ return (float)($r['gross_profit'] ?? $r['net_sales'] ?? 0); }, $subshopBreakdown);
          if (count($vals) > 0) {
            $maxVal = max($vals); $minVal = min($vals);
            $maxIdx = array_search($maxVal, $vals); $minIdx = array_search($minVal, $vals);
            $rowMax = $subshopBreakdown[$maxIdx] ?? null; $rowMin = $subshopBreakdown[$minIdx] ?? null;
            $hiShop = $rowMax['subshop'] ?? null; $loShop = $rowMin['subshop'] ?? null;
            $hiVal = $maxVal; $loVal = $minVal; $gap = $maxVal - $minVal;
          }
        }
      @endphp

      <style>
        .rec-item{display:flex;align-items:flex-start;margin-bottom:.75rem;}
        .rec-icon{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;margin-right:.5rem;font-size:.9rem;}
        .rec-icon.primary{background:#e9f2ff;color:#0d6efd}
        .rec-icon.success{background:#e9fbea;color:#198754}
        .rec-icon.warning{background:#fff7e6;color:#fd7e14}
        .rec-icon.danger{background:#feeceb;color:#dc3545}
        .rec-badge{display:inline-block;background:#f1f5f9;color:#0f172a;border-radius:999px;padding:.05rem .5rem;font-weight:600;font-size:.8rem}
        .rec-example{font-size:.8rem;color:#64748b}
      </style>

      <div class="mb-0">
        {{-- Profit trend --}}
        @if(!is_null($trendText))
          <div class="rec-item">
            <div class="rec-icon primary"><i class="fas fa-trend-up"></i></div>
            <div>
              <div><strong>Net profit trend:</strong>
                @if($trendText==='up')
                  Improving <span class="rec-badge">{{ $trendBadge }}</span> vs {{ $compareLbl }}.
                  <div class="rec-example mt-1">e.g., Tsh {{ number_format($npPrev,2) }} → Tsh {{ number_format($npCur,2) }}</div>
                  <div class="small text-muted mt-1">Action: sustain drivers (top products/channels); reinvest in high-ROI campaigns.</div>
                @elseif($trendText==='down')
                  Softening <span class="rec-badge">{{ $trendBadge }}</span> vs {{ $compareLbl }}.
                  <div class="rec-example mt-1">e.g., Tsh {{ number_format($npPrev,2) }} → Tsh {{ number_format($npCur,2) }}</div>
                  <div class="small text-muted mt-1">Action: check price/discount pressure, COGS drift, and expense spikes; fix leak points.</div>
                @else
                  Stable vs {{ $compareLbl }}.
                  <div class="small text-muted mt-1">Action: keep mix steady; run small tests to find incremental uplift.</div>
                @endif
              </div>
            </div>
          </div>
        @endif

        {{-- Margin health --}}
        <div class="rec-item">
          <div class="rec-icon success"><i class="fas fa-percentage"></i></div>
          <div>
            <div><strong>Margin health:</strong> Gross Margin <span class="rec-badge">{{ number_format($gmPct,2) }}%</span>.</div>
            <div class="small text-muted mt-1">Action: review pricing and bundle mix; reduce unit cost via supplier terms or pack sizes; curb discounts where elasticity is low.</div>
          </div>
        </div>

        {{-- Revenue vs COGS --}}
        <div class="rec-item">
          <div class="rec-icon primary"><i class="fas fa-exchange-alt"></i></div>
          <div>
            <div><strong>Revenue vs COGS:</strong> Net Sales <span class="rec-badge">Tsh {{ number_format($netSales,2) }}</span> • COGS <span class="rec-badge">Tsh {{ number_format($cogs,2) }}</span>.</div>
            <div class="small text-muted mt-1">Action: prioritize high-margin SKUs; address shrink/returns; ensure cost updates reflect latest purchase prices.</div>
          </div>
        </div>

        {{-- Expense control --}}
        <div class="rec-item">
          <div class="rec-icon warning"><i class="fas fa-receipt"></i></div>
          <div>
            @php $opexRate = $netSales>0 ? ($opex/$netSales*100) : null; @endphp
            <div><strong>Expense control:</strong> Opex <span class="rec-badge">Tsh {{ number_format($opex,2) }}</span>@if(!is_null($opexRate)) <span class="rec-badge">{{ number_format($opexRate,1) }}% of Net Sales</span>@endif.</div>
            <div class="small text-muted mt-1">Action: trim non-essential spend; negotiate recurring costs; automate workflows to save labor hours.</div>
          </div>
        </div>

        {{-- Returns impact --}}
        <div class="rec-item">
          <div class="rec-icon danger"><i class="fas fa-undo"></i></div>
          <div>
            <div><strong>Returns impact:</strong> Sales Returns <span class="rec-badge">Tsh {{ number_format($salesReturns,2) }}</span>.</div>
            <div class="small text-muted mt-1">Action: investigate top return reasons; improve product info and QC; optimize FEFO for perishables.</div>
          </div>
        </div>

        {{-- Subshop disparity --}}
        @if(!is_null($gap) && $gap>0 && $hiShop && $loShop)
          <div class="rec-item">
            <div class="rec-icon primary"><i class="fas fa-store"></i></div>
            <div>
              <div><strong>Subshop disparity:</strong> Gross profit gap <span class="rec-badge">Tsh {{ number_format($gap,2) }}</span> ({{ $hiShop }} high vs {{ $loShop }} low).</div>
              <div class="rec-example mt-1">e.g., {{ $hiShop }} ≈ Tsh {{ number_format($hiVal,2) }}, {{ $loShop }} ≈ Tsh {{ number_format($loVal,2) }}</div>
              <div class="small text-muted mt-1">Action: replicate best practices from {{ $hiShop }} (pricing, assortment, staff); target lift initiatives in {{ $loShop }}.</div>
            </div>
          </div>
        @endif
      </div>
    </div>
</div>

@push('css')
  <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
  <style>
    .small-box.kpi-box { margin-bottom: .5rem; }
    .small-box.kpi-box .inner { padding: .5rem .5rem .25rem .5rem; }
    .small-box.kpi-box .inner h3 { font-size: 1.05rem; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .small-box.kpi-box .inner p { font-size: .75rem; margin: .25rem 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .small-box.kpi-box .icon { font-size: 1.8rem; top: .5rem; right: .5rem; opacity: .3; }
    @media (min-width: 576px) { .small-box.kpi-box .inner h3 { font-size: 1.15rem; } }
    @media (min-width: 768px) { .small-box.kpi-box .inner h3 { font-size: 1.2rem; } }
    /* Chart containers fixed height so charts don't grow infinitely */
    .chart-container { position: relative; height: 260px; max-height: 320px; }
    @media (max-width: 576px) { .chart-container { height: 220px; } }
  </style>
@endpush
@stop

@section('js')
<script src="{{ asset('plugins/chart.js/Chart.bundle.min.js') }}"></script>
<script>
  $(function(){
    $('[data-toggle="tooltip"]').tooltip();

    const params = new URLSearchParams({
      date_from: '{{ $dateFrom ?? '' }}',
      date_to: '{{ $dateTo ?? '' }}',
      subshop_id: '{{ $selectedSubshopId ?? '' }}',
      basis: '{{ $basis ?? 'accrual' }}',
      compare: '{{ $compare ?? 'none' }}'
    });

    // Sales vs COGS
    fetch(`{{ route('reports.pl.analytics.sales_cogs') }}?` + params.toString())
      .then(r=>r.json()).then(d=>{
        const ctx = document.getElementById('plSalesCogsChart').getContext('2d');
        new Chart(ctx, {
          type: 'line',
          data: {
            labels: d.labels,
            datasets: [
              {label:'Sales', data:d.revenue, borderColor:'rgba(54,162,235,1)', backgroundColor:'rgba(54,162,235,0.15)', fill:true, tension:.3},
              {label:'COGS', data:d.cogs, borderColor:'rgba(255,99,132,1)', backgroundColor:'rgba(255,99,132,0.15)', fill:true, tension:.3}
            ]
          },
          options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } } }
        });
      });

    // Margin %
    fetch(`{{ route('reports.pl.analytics.margin') }}?` + params.toString())
      .then(r=>r.json()).then(d=>{
        const ctx = document.getElementById('plMarginChart').getContext('2d');
        new Chart(ctx, {
          type: 'line',
          data: { labels: d.labels, datasets: [{ label:'Gross Margin %', data:d.margin_pct, borderColor:'rgba(75,192,192,1)', backgroundColor:'rgba(75,192,192,0.15)', fill:true, tension:.3 }] },
          options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true, ticks:{ callback:(v)=>v+'%' } } } }
        });
      });

    // Waterfall (stacked bars approximation)
    fetch(`{{ route('reports.pl.analytics.waterfall') }}?` + params.toString())
      .then(r=>r.json()).then(d=>{
        const labels = d.labels;
        const vals = d.values;
        // Build stacked components: positives in one dataset, negatives in another
        const positives = vals.map(v=> v>0?v:0);
        const negatives = vals.map(v=> v<0?Math.abs(v):0);
        const ctx = document.getElementById('plWaterfallChart').getContext('2d');
        new Chart(ctx, {
          type: 'bar',
          data: {
            labels,
            datasets: [
              { label:'Positive', data: positives, backgroundColor:'rgba(54,162,235,0.6)', stack:'wf' },
              { label:'Negative', data: negatives, backgroundColor:'rgba(255,99,132,0.6)', stack:'wf' }
            ]
          },
          options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } }, plugins:{ legend:{ display:false } } }
        });
      });
  });
  </script>
@stop