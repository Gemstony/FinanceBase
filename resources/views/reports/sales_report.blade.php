@extends('adminlte::page')

@section('title', 'Sales Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-chart-line"></i> Sales Overview</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-chart-line"></i> Sales</h1>
      <div class="small text-light-50">Period: {{ $dateFrom ?? '' }} to {{ $dateTo ?? '' }}</div>
    </div>
   
    <a href="{{ route('dashboard') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
  </div>
 </div>
@stop

@section('content')

<div class="container-fluid">
  <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
    <div class="card-body">
      <form method="get" action="{{ route('reports.sales') }}" class="mb-3">
        <div class="bg-light p-2 rounded border">
          <div class="form-row">
            <div class="form-group col-md-4">
              <label for="date_from">Date From</label>
              <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="{{ $dateFrom ?? '' }}">
            </div>
            <div class="form-group col-md-4">
              <label for="date_to">Date To</label>
              <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="{{ $dateTo ?? '' }}">
            </div>
            <div class="form-group col-md-4">
              <label for="subshop_id">Subshop</label>
              <select class="form-control form-control-sm" id="subshop_id" name="subshop_id">
                <option value="">All Subshops</option>
                @foreach($subshops ?? [] as $subshop)
                  <option value="{{ $subshop->id }}" {{ (request('subshop_id') == $subshop->id) ? 'selected' : '' }}>
                    {{ $subshop->name }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
        </div>
      </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Bottom Export Buttons -->
@can('export_sales_reports')
<div class="row m-3">
    <div class="col-12 text-center">
        <div class="btn-group" role="group">
            <a href="{{ $exportUrl }}" class="btn btn-success">
                <i class="fas fa-file-excel"></i> Export to Excel
            </a>
            <a href="{{ $csvUrl }}" class="btn btn-info text-white">
                <i class="fas fa-file-csv"></i> Export to CSV
            </a>
            <a href="{{ $pdfUrl }}" class="btn btn-danger">
                <i class="fas fa-file-pdf"></i> Export to PDF
            </a>
        </div>
    </div>
</div>
@endcan

  <div class="row mb-3">
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
      <div class="small-box kpi-box bg-success" data-toggle="tooltip" data-html="true" title="<strong>Net Sales</strong><br>Total sales after returns">
        <div class="inner">
          <h3 class="mb-0" title="Tsh {{ number_format($kpi['net_sales'] ?? 0, 2) }}">Tsh {{ number_format($kpi['net_sales'] ?? 0, 2) }}</h3>
          <p>Net Sales</p>
        </div>
        <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
      <div class="small-box kpi-box bg-primary" data-toggle="tooltip" data-html="true" title="<strong>Total Orders</strong><br>Number of completed orders">
        <div class="inner">
          <h3 class="mb-0" title="{{ number_format($kpi['orders'] ?? 0) }}">{{ number_format($kpi['orders'] ?? 0) }}</h3>
          <p>Orders</p>
        </div>
        <div class="icon"><i class="fas fa-receipt"></i></div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
      <div class="small-box kpi-box bg-info" data-toggle="tooltip" data-html="true" title="<strong>Average Order Value</strong><br>Net Sales ÷ Number of Orders">
        <div class="inner">
          <h3 class="mb-0" title="Tsh {{ number_format($kpi['aov'] ?? 0, 2) }}">Tsh {{ number_format($kpi['aov'] ?? 0, 2) }}</h3>
          <p>Avg. Order Value</p>
        </div>
        <div class="icon"><i class="fas fa-balance-scale"></i></div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
      <div class="small-box kpi-box bg-secondary" data-toggle="tooltip" data-html="true" title="<strong>Net Units Sold</strong><br>Total units sold minus returns">
        <div class="inner">
          <h3 class="mb-0" title="{{ number_format($kpi['units'] ?? 0) }}">{{ number_format($kpi['units'] ?? 0) }}</h3>
          <p>Net Units</p>
        </div>
        <div class="icon"><i class="fas fa-boxes"></i></div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
      <div class="small-box kpi-box bg-teal" data-toggle="tooltip" data-html="true" title="<strong>Gross Profit</strong><br>Net Sales minus Cost of Goods Sold">
        <div class="inner">
          <h3 class="mb-0" title="Tsh {{ number_format($kpi['gross_profit'] ?? 0, 2) }}">Tsh {{ number_format($kpi['gross_profit'] ?? 0, 2) }}</h3>
          <p>Gross Profit</p>
        </div>
        <div class="icon"><i class="fas fa-chart-pie"></i></div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
      <div class="small-box kpi-box bg-purple" data-toggle="tooltip" data-html="true" title="<strong>Profit Margin %</strong><br>(Gross Profit ÷ Net Sales) × 100">
        <div class="inner">
          <h3 class="mb-0" title="{{ number_format($kpi['margin_pct'] ?? 0, 2) }}%">{{ number_format($kpi['margin_pct'] ?? 0, 2) }}%</h3>
          <p>Margin %</p>
        </div>
        <div class="icon"><i class="fas fa-percentage"></i></div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
      <div class="small-box kpi-box bg-danger" data-toggle="tooltip" data-html="true" title="<strong>Total Returns</strong><br>Value of all returned items">
        <div class="inner">
          <h3 class="mb-0" title="Tsh {{ number_format($kpi['returns_amount'] ?? 0, 2) }}">Tsh {{ number_format($kpi['returns_amount'] ?? 0, 2) }}</h3>
          <p>Returns</p>
        </div>
        <div class="icon"><i class="fas fa-undo"></i></div>
      </div>
    </div>
  </div>

  <!-- Charts Section -->
<div class="row">
  <!-- Sales Trend Chart -->
  <div class="col-md-12">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Sales Trend <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Revenue trend over time to spot seasonality and growth. Filters apply."></i></h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="collapse">
            <i class="fas fa-minus"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <div class="chart-container">
          <div class="chart-wrapper">
            <canvas id="salesTrendChart" height="300"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Subshop Comparison Chart -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Subshop Comparison <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Compares sales across subshops to identify best/worst performers."></i></h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="collapse">
            <i class="fas fa-minus"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <div class="chart-container">
          <div class="chart-wrapper">
            <canvas id="subshopComparisonChart" height="300"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Top Categories Chart -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Top Categories <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Top categories by revenue/profit to guide assortment and promotions."></i></h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="collapse">
            <i class="fas fa-minus"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <div class="chart-container">
          <div class="chart-wrapper">
            <canvas id="topCategoriesChart" height="300"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>



<!-- Tables Section -->
<div class="row">
    <!-- Orders List -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Orders <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Most recent completed sales with amounts and payment status."></i></h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Subshop</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Cashier</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ordersList as $order)
                        <tr>
                            <td>{{ $order->invoice_number }}</td>
                            <td>{{ \Carbon\Carbon::parse($order->created_at)->format('M d, Y H:i') }}</td>
                            <td>{{ $order->customer_name ?: 'Walk-in Customer' }}</td>
                            <td>{{ $order->subshop_name ?: 'N/A' }}</td>
                            <td class="text-right">Tsh {{ number_format($order->grand_total, 2) }}</td>
                            <td>
                                <span class="badge badge-{{ $order->status === 'completed' ? 'success' : ($order->status === 'pending' ? 'warning' : 'danger') }}">
                                    {{ ucfirst($order->status) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-{{ $order->payment_status === 'paid' ? 'success' : ($order->payment_status === 'partial' ? 'info' : 'danger') }}">
                                    {{ ucfirst($order->payment_status) }}
                                </span>
                            </td>
                            <td>{{ $order->cashier_name ?: 'System' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center">No orders found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Product Performance -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Top Performing Products <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Products ranked by revenue and profit to focus merchandising and stock."></i></h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Qty Sold</th>
                            <th>Revenue</th>
                            <th>Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($productPerformance as $product)
                        <tr>
                            <td>
                                <div class="text-truncate" style="max-width: 150px;" title="{{ $product->product_name }}">
                                    {{ $product->product_name }}
                                </div>
                                <small class="text-muted">{{ $product->sku }}</small>
                            </td>
                            <td>{{ $product->category_name ?: 'Uncategorized' }}</td>
                            <td>{{ number_format($product->quantity_sold) }}</td>
                            <td class="text-right">Tsh {{ number_format($product->revenue, 2) }}</td>
                            <td class="text-right {{ $product->profit >= 0 ? 'text-success' : 'text-danger' }}">
                                Tsh {{ number_format($product->profit, 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center">No product data available</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Returns List -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Returns <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Latest return transactions with amounts and reasons to monitor quality issues."></i></h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                    <thead>
                        <tr>
                            <th>Return #</th>
                            <th>Order #</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($returnsList as $return)
                        <tr>
                            <td>#{{ $return->id }}</td>
                            <td>{{ $return->invoice_number }}</td>
                            <td>
                                <div class="text-truncate" style="max-width: 150px;" title="{{ $return->product_name }}">
                                    {{ $return->product_name }}
                                </div>
                                <small class="text-muted">{{ $return->reason ?: 'No reason provided' }}</small>
                            </td>
                            <td>{{ $return->quantity_returned }}</td>
                            <td class="text-right">Tsh {{ number_format($return->return_amount, 2) }}</td>
                            <td>
                                @php
                                    $status = $return->processed_by ? 'completed' : 'pending';
                                    $statusClass = $status === 'completed' ? 'success' : 'warning';
                                @endphp
                                <span class="badge badge-{{ $statusClass }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center">No returns found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<!-- Recommendations -->
<div class="card shadow-sm border-0 mt-4">
  <div class="card-header d-flex justify-content-between align-items-center" style="background:linear-gradient(135deg,#e6f7ff,#fff);">
    <h3 class="card-title mb-0"><i class="fas fa-lightbulb mr-1 text-info"></i> Recommendations</h3>
    <span class="text-muted small">Auto-generated from selected period and filters</span>
  </div>
  <div class="card-body">
    @php
      // Available inputs
      $k = $kpi ?? [];
      $chart = $chartData['salesTrend'] ?? null; // expect labels + datasets[0].data
      $subshopChart = $chartData['subshopComparison'] ?? null; // labels + datasets[0].data
      $topCats = $chartData['topCategories'] ?? null; // labels + datasets

      // Extract trend values safely
      $trendFirst = null; $trendLast = null; $trendPct = null; $trendDir = null;
      if (is_array($chart) && !empty($chart['datasets']) && isset($chart['datasets'][0]['data'])) {
        $arr = (array)$chart['datasets'][0]['data'];
        if (count($arr) >= 2) {
          $trendFirst = (float)reset($arr);
          $trendLast = (float)end($arr);
          if ($trendFirst > 0) { $trendPct = (($trendLast - $trendFirst) / $trendFirst) * 100; }
          if ($trendLast > $trendFirst) $trendDir = 'up'; elseif ($trendLast < $trendFirst) $trendDir = 'down'; else $trendDir = 'flat';
        }
      }

      // Subshop leaders/laggards
      $topShopName = null; $topShopVal = null; $lowShopName = null; $lowShopVal = null; $shopImbalance = null;
      if (is_array($subshopChart) && !empty($subshopChart['labels']) && !empty($subshopChart['datasets'][0]['data'])) {
        $labels = (array)$subshopChart['labels'];
        $vals = (array)$subshopChart['datasets'][0]['data'];
        if (count($labels) === count($vals) && count($vals) > 0) {
          $maxVal = max($vals); $minVal = min($vals);
          $maxIdx = array_search($maxVal, $vals);
          $minIdx = array_search($minVal, $vals);
          $topShopName = $labels[$maxIdx] ?? null; $topShopVal = $maxVal;
          $lowShopName = $labels[$minIdx] ?? null; $lowShopVal = $minVal;
          $shopImbalance = $maxVal - $minVal;
        }
      }

      // A few example products and return reasons
      $topProdNames = [];
      if (!empty($productPerformance)) {
        foreach ($productPerformance as $p) { if (count($topProdNames) >= 3) break; $topProdNames[] = $p->product_name; }
      }
      $returnExamples = [];
      if (!empty($returnsList)) {
        foreach ($returnsList as $r) { if (count($returnExamples) >= 2) break; $returnExamples[] = trim($r->reason ?: 'General return'); }
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
      {{-- Sales trend insight --}}
      @if($trendDir)
        <div class="rec-item">
          <div class="rec-icon primary"><i class="fas fa-chart-line"></i></div>
          <div>
            <div><strong>Revenue trend:</strong>
              @if($trendDir==='up')
                Increasing <span class="rec-badge">{{ is_null($trendPct)? 'up' : number_format($trendPct,1) . '% up' }}</span> over the selected period.
                @if(!is_null($trendFirst) && !is_null($trendLast))
                  <div class="rec-example mt-1">e.g., Tsh {{ number_format($trendFirst,2) }} → Tsh {{ number_format($trendLast,2) }}</div>
                @endif
                <div class="small text-muted mt-1">Action: double down on best-performing channels and top products; ensure inventory can support demand spikes.</div>
              @elseif($trendDir==='down')
                Decreasing <span class="rec-badge">{{ is_null($trendPct)? 'down' : number_format($trendPct,1) . '% down' }}</span> vs. the start of the period.
                @if(!is_null($trendFirst) && !is_null($trendLast))
                  <div class="rec-example mt-1">e.g., Tsh {{ number_format($trendFirst,2) }} → Tsh {{ number_format($trendLast,2) }}</div>
                @endif
                <div class="small text-muted mt-1">Action: review pricing/promotions and stockouts; target campaigns to lift AOV or conversion.</div>
              @else
                Stable sales across the period.
                <div class="small text-muted mt-1">Action: maintain campaigns; experiment with bundles to lift AOV.</div>
              @endif
            </div>
          </div>
        </div>
      @endif

      {{-- Profitability & AOV --}}
      <div class="rec-item">
        <div class="rec-icon success"><i class="fas fa-balance-scale"></i></div>
        <div>
          <div><strong>Profitability & ticket size:</strong> Margin <span class="rec-badge">{{ number_format($k['margin_pct'] ?? 0, 2) }}%</span>, AOV <span class="rec-badge">Tsh {{ number_format($k['aov'] ?? 0, 2) }}</span>.</div>
          <div class="small text-muted mt-1">Action: raise AOV via cross-sell (related items) and volume discounts; protect margin by reviewing cost and promo mix.</div>
        </div>
      </div>

      {{-- Top products focus --}}
      <div class="rec-item">
        <div class="rec-icon primary"><i class="fas fa-star"></i></div>
        <div>
          <div><strong>Lean into top sellers:</strong> prioritize stock and visibility for best performers.</div>
          @if(!empty($topProdNames))
            <div class="rec-example mt-1">e.g., {{ implode(', ', array_slice($topProdNames,0,3)) }}</div>
          @endif
          <div class="small text-muted mt-1">Action: feature these on POS and marketing; ensure sufficient inventory and fast replenishment.</div>
        </div>
      </div>

      {{-- Returns control --}}
      <div class="rec-item">
        <div class="rec-icon danger"><i class="fas fa-undo"></i></div>
        <div>
          <div><strong>Manage returns:</strong> Total returns <span class="rec-badge">Tsh {{ number_format($k['returns_amount'] ?? 0, 2) }}</span>.</div>
          @if(!empty($returnExamples))
            <div class="rec-example mt-1">e.g., reasons: {{ implode('; ', array_slice($returnExamples,0,2)) }}</div>
          @endif
          <div class="small text-muted mt-1">Action: address root causes (quality/fit/expectations); add exchange options and clearer product info.</div>
        </div>
      </div>

      {{-- Subshop performance balancing --}}
      @if(!is_null($shopImbalance) && $shopImbalance > 0 && $topShopName && $lowShopName)
        <div class="rec-item">
          <div class="rec-icon warning"><i class="fas fa-store"></i></div>
          <div>
            <div><strong>Subshop gap:</strong> {{ $topShopName }} leads vs {{ $lowShopName }} by <span class="rec-badge">Tsh {{ number_format($shopImbalance,2) }}</span>.</div>
            <div class="rec-example mt-1">e.g., {{ $topShopName }} ≈ Tsh {{ number_format($topShopVal,2) }}, {{ $lowShopName }} ≈ Tsh {{ number_format($lowShopVal,2) }}</div>
            <div class="small text-muted mt-1">Action: replicate top-shop tactics (pricing, staff, display); run targeted promos in underperforming shops.</div>
          </div>
        </div>
      @endif
    </div>
  </div>
</div>


</div>


@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/chart.js/Chart.min.css') }}">
<style>
  /* Chart containers */
  .chart-container {
    position: relative;
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.1);
  }
  
  .chart-title {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #f4f4f4;
  }
  
  .chart-wrapper {
    position: relative;
    height: 300px;
    width: 100%;
  }
  
  .chart-legend {
    margin-top: 15px;
    font-size: 13px;
    text-align: center;
  }
  /* Responsive font sizes for card numbers */
  .small-box .inner h3 {
    font-size: 1.8rem;
    font-weight: bold;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 2px;
    line-height: 1.1;
  }
  
  .small-box .inner p {
    font-size: 0.9rem;
    margin-bottom: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    opacity: 0.9;
  }
  
  .small-box .icon {
    font-size: 60px;
    position: absolute;
    right: 15px;
    top: 10px;
    transition: all 0.3s ease-in-out;
    opacity: 0.2;
  }
  
  .small-box:hover .icon {
    font-size: 65px;
    opacity: 0.3;
  }
  
  /* Responsive adjustments */
  @media (max-width: 768px) {
    .small-box .inner h3 {
      font-size: 1.5rem;
    }
    
    .small-box .inner p {
      font-size: 0.8rem;
    }
    
    .small-box .icon {
      font-size: 40px;
    }
    
    .small-box:hover .icon {
      font-size: 45px;
    }
  }
  
  /* Tooltip styling */
  .tooltip-inner {
    max-width: 300px;
    padding: 8px 12px;
    font-size: 0.9rem;
  }
  
  /* Card hover effects */
  .small-box {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    position: relative;
    overflow: hidden;
  }
  
  .small-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
  }
  
  /* Better spacing for mobile */
  @media (max-width: 576px) {
    .col-6 {
      padding: 5px !important;
    }
    
    .small-box {
      margin-bottom: 10px;
    }
  }

  /* KPI compact styles to match Purchases */
  .small-box.kpi-box { margin-bottom: .5rem; }
  .small-box.kpi-box .inner { padding: .5rem .5rem .25rem .5rem; }
  .small-box.kpi-box .inner h3 { font-size: 1.05rem; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .small-box.kpi-box .inner p { font-size: .75rem; margin: .25rem 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .small-box.kpi-box .icon { font-size: 1.8rem; top: .5rem; right: .5rem; opacity: .3; }
  @media (min-width: 576px) { .small-box.kpi-box .inner h3 { font-size: 1.15rem; } }
  @media (min-width: 768px) { .small-box.kpi-box .inner h3 { font-size: 1.2rem; } }
</style>
@endpush
@stop

@section('js')
<script src="{{ asset('plugins/chart.js/Chart.bundle.min.js') }}"></script>
<script>
  $(document).ready(function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip({
      container: 'body',
      boundary: 'window',
      trigger: 'hover'
    });

    // Add click to copy functionality to all h3 elements in small-box
    $('.small-box h3').on('click', function() {
      const text = $(this).text().trim();
      const $temp = $('<input>');
      $('body').append($temp);
      $temp.val(text).select();
      document.execCommand('copy');
      $temp.remove();

      // Show copied feedback
      const $badge = $('<span class="badge badge-light ml-2">Copied!</span>');
      $(this).append($badge);
      setTimeout(() => $badge.fadeOut(400, () => $badge.remove()), 1500);
    }).css('cursor', 'pointer');

    // Format number with commas
    function formatNumber(num) {
      return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // Initialize charts
    @if(isset($chartData))
      // 1. Sales Trend Chart
      const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
      const salesTrendChart = new Chart(salesTrendCtx, {
        type: 'line',
        data: @json($chartData['salesTrend']),
        options: {
          responsive: true,
          maintainAspectRatio: false,
          tooltips: {
            mode: 'index',
            intersect: false,
            callbacks: {
              label: function(tooltipItem, data) {
                let label = data.datasets[tooltipItem.datasetIndex].label || '';
                if (label) {
                  label += ': ';
                }
                label += 'Tsh ' + formatNumber(tooltipItem.yLabel.toFixed(2));
                return label;
              }
            }
          },
          scales: {
            yAxes: [{
              ticks: {
                beginAtZero: true,
                callback: function(value) {
                  return 'Tsh ' + formatNumber(value);
                }
              },
              gridLines: {
                display: true,
                color: 'rgba(0, 0, 0, 0.05)'
              }
            }],
            xAxes: [{
              gridLines: {
                display: false
              }
            }]
          },
          legend: {
            position: 'bottom',
            labels: {
              boxWidth: 12
            }
          }
        }
      });

      // 2. Subshop Comparison Chart
      const subshopCtx = document.getElementById('subshopComparisonChart').getContext('2d');
      const subshopChart = new Chart(subshopCtx, {
        type: 'bar',
        data: @json($chartData['subshopComparison']),
        options: {
          responsive: true,
          maintainAspectRatio: false,
          tooltips: {
            callbacks: {
              label: function(tooltipItem, data) {
                let label = data.datasets[tooltipItem.datasetIndex].label || '';
                if (label) {
                  label += ': ';
                }
                label += 'Tsh ' + formatNumber(tooltipItem.yLabel.toFixed(2));
                return label;
              }
            }
          },
          scales: {
            yAxes: [{
              ticks: {
                beginAtZero: true,
                callback: function(value) {
                  return 'Tsh ' + formatNumber(value);
                }
              },
              gridLines: {
                display: true,
                color: 'rgba(0, 0, 0, 0.05)'
              }
            }],
            xAxes: [{
              gridLines: {
                display: false
              }
            }]
          },
          legend: {
            display: false
          },
          title: {
            display: true,
            text: 'Total Sales by Subshop',
            fontSize: 14
          }
        }
      });

      // 3. Top Categories Chart
      const topCategoriesCtx = document.getElementById('topCategoriesChart').getContext('2d');
      const topCategoriesChart = new Chart(topCategoriesCtx, {
        type: 'bar',
        data: @json($chartData['topCategories']),
        options: {
          responsive: true,
          maintainAspectRatio: false,
          tooltips: {
            callbacks: {
              label: function(tooltipItem, data) {
                let label = data.datasets[tooltipItem.datasetIndex].label || '';
                if (label) {
                  label += ': ';
                }
                label += 'Tsh ' + formatNumber(tooltipItem.yLabel.toFixed(2));
                return label;
              }
            }
          },
          scales: {
            yAxes: [{
              ticks: {
                beginAtZero: true,
                callback: function(value) {
                  return 'Tsh ' + formatNumber(value);
                }
              },
              gridLines: {
                display: true,
                color: 'rgba(0, 0, 0, 0.05)'
              }
            }],
            xAxes: [{
              gridLines: {
                display: false
              }
            }]
          },
          legend: {
            position: 'bottom',
            labels: {
              boxWidth: 12
            }
          },
          title: {
            display: true,
            text: 'Top Categories by Revenue & Profit',
            fontSize: 14
          },
          tooltips: {
            mode: 'index',
            intersect: false
          }
        }
      });

      // Resize charts when tab is shown
      $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        salesTrendChart.resize();
        subshopChart.resize();
        topCategoriesChart.resize();
      });
    @endif
  });
</script>
@stop