@extends('adminlte::page')

@section('title', 'Purchases Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-shopping-cart"></i> Purchases Overview</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-shopping-cart"></i> Purchases</h1>
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
      <form method="get" action="{{ route('reports.purchases.index') }}" class="mb-3">
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
          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="q">Quick Search</label>
              <input type="text" class="form-control form-control-sm" id="q" name="q" value="{{ $q ?? '' }}" placeholder="Search by Order No or Supplier">
            </div>
            <div class="form-group col-md-6">
              <label for="sort">Sort By</label>
              <select class="form-control form-control-sm" id="sort" name="sort">
                <option value="date_desc" {{ ($sort ?? '')==='date_desc' ? 'selected' : '' }}>Date (Newest)</option>
                <option value="date_asc" {{ ($sort ?? '')==='date_asc' ? 'selected' : '' }}>Date (Oldest)</option>
                <option value="grand_desc" {{ ($sort ?? '')==='grand_desc' ? 'selected' : '' }}>Grand Total (High-Low)</option>
                <option value="grand_asc" {{ ($sort ?? '')==='grand_asc' ? 'selected' : '' }}>Grand Total (Low-High)</option>
                <option value="paid_desc" {{ ($sort ?? '')==='paid_desc' ? 'selected' : '' }}>Paid (High-Low)</option>
                <option value="paid_asc" {{ ($sort ?? '')==='paid_asc' ? 'selected' : '' }}>Paid (Low-High)</option>
                <option value="net_desc" {{ ($sort ?? '')==='net_desc' ? 'selected' : '' }}>Net Spend (High-Low)</option>
                <option value="net_asc" {{ ($sort ?? '')==='net_asc' ? 'selected' : '' }}>Net Spend (Low-High)</option>
                <option value="remain_desc" {{ ($sort ?? '')==='remain_desc' ? 'selected' : '' }}>Net Remaining (High-Low)</option>
                <option value="remain_asc" {{ ($sort ?? '')==='remain_asc' ? 'selected' : '' }}>Net Remaining (Low-High)</option>
              </select>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
        </div>
      </form>

      <!-- Export Buttons -->
       @can('export_purchases_reports')
      <div class="row m-3">
        <div class="col-12">
          <div class="d-flex flex-column flex-sm-row flex-wrap justify-content-center align-items-stretch">
            <a href="{{ $exportUrl }}" class="btn btn-success export-btn mb-2 mb-sm-0 mr-sm-2">
              <i class="fas fa-file-excel"></i> Export to Excel
            </a>
            <a href="{{ $csvUrl }}" class="btn btn-info text-white export-btn mb-2 mb-sm-0 mr-sm-2">
              <i class="fas fa-file-csv"></i> Export to CSV
            </a>
            <a href="{{ $pdfUrl }}" class="btn btn-danger export-btn mb-2 mb-sm-0 mr-sm-2">
              <i class="fas fa-file-pdf"></i> Export to PDF
            </a>
            <a href="{{ $tableExportXlsxUrl ?? '#' }}" class="btn btn-outline-success export-btn mb-2 mb-sm-0 mr-sm-2">
              <i class="fas fa-table"></i> Download Table (Excel)
            </a>
            <a href="{{ $tableExportCsvUrl ?? '#' }}" class="btn btn-outline-info text-info export-btn mb-2 mb-sm-0">
              <i class="fas fa-table"></i> Download Table (CSV)
            </a>
            <a href="{{ $tableExportPdfUrl ?? '#' }}" class="btn btn-outline-danger export-btn mb-2 mb-sm-0 ml-sm-2">
              <i class="fas fa-table"></i> Download Table (PDF)
            </a>
          </div>
        </div>
      </div>
      @endcan

      <!-- KPIs Summary cards -->
    <div class="row mb-3">
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
          <div class="small-box kpi-box bg-success" data-toggle="tooltip" data-html="true" title="<strong>Total Purchases</strong><br>Sum of purchase grand totals">
            <div class="inner">
              <h3 class="mb-0" title="Tsh {{ number_format($kpi['total_purchases'] ?? 0, 2) }}">Tsh {{ number_format($kpi['total_purchases'] ?? 0, 2) }}</h3>
              <p>Total Purchases</p>
            </div>
            <div class="icon"><i class="fas fa-money-check-alt"></i></div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
          <div class="small-box kpi-box bg-primary" data-toggle="tooltip" data-html="true" title="<strong>Total Orders</strong><br>Number of purchase orders">
            <div class="inner">
              <h3 class="mb-0" title="{{ number_format($kpi['orders'] ?? 0) }}">{{ number_format($kpi['orders'] ?? 0) }}</h3>
              <p>Orders</p>
            </div>
            <div class="icon"><i class="fas fa-receipt"></i></div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
          <div class="small-box kpi-box bg-info" data-toggle="tooltip" data-html="true" title="<strong>Average Purchase Value</strong><br>Total Purchases ÷ Orders">
            <div class="inner">
              <h3 class="mb-0" title="Tsh {{ number_format($kpi['apv'] ?? 0, 2) }}">Tsh {{ number_format($kpi['apv'] ?? 0, 2) }}</h3>
              <p>Avg. Purchase Value</p>
            </div>
            <div class="icon"><i class="fas fa-balance-scale"></i></div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
          <div class="small-box kpi-box bg-warning" data-toggle="tooltip" data-html="true" title="<strong>Taxes</strong><br>Total VAT on purchases">
            <div class="inner">
              <h3 class="mb-0" title="Tsh {{ number_format($kpi['taxes'] ?? 0, 2) }}">Tsh {{ number_format($kpi['taxes'] ?? 0, 2) }}</h3>
              <p>Taxes</p>
            </div>
            <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
          <div class="small-box kpi-box bg-secondary" data-toggle="tooltip" data-html="true" title="<strong>Discounts</strong><br>Total purchase discounts">
            <div class="inner">
              <h3 class="mb-0" title="Tsh {{ number_format($kpi['discounts'] ?? 0, 2) }}">Tsh {{ number_format($kpi['discounts'] ?? 0, 2) }}</h3>
              <p>Discounts</p>
            </div>
            <div class="icon"><i class="fas fa-percentage"></i></div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-3">
          <div class="small-box kpi-box bg-danger" data-toggle="tooltip" data-html="true" title="<strong>Outstanding A/P</strong><br>Unpaid amount to suppliers">
            <div class="inner">
              <h3 class="mb-0" title="Tsh {{ number_format($kpi['outstanding_ap'] ?? 0, 2) }}">Tsh {{ number_format($kpi['outstanding_ap'] ?? 0, 2) }}</h3>
              <p>Outstanding A/P</p>
            </div>
            <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
          </div>
        </div>
      </div>

      <div class="card mt-3 border-0">
        <div class="card-body p-0">
          <div class="container-fluid py-3">
            <div class="row">
              <div class="col-lg-6 mb-3">
                <div class="card h-100"><div class="card-body"><h5 class="card-title">Purchases Over Time <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Tracks net spend, paid, and remaining by date to monitor purchasing pace and liabilities."></i></h5><canvas id="chartSpend"></canvas></div></div>
              </div>
              <div class="col-lg-6 mb-3">
                <div class="card h-100"><div class="card-body"><h5 class="card-title">Orders vs APV <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Compares number of purchase orders to Average Purchase Value (APV = Total Purchases ÷ Orders). Helps see if you’re placing fewer, larger POs or many small ones."></i></h5><canvas id="chartOrdersApv"></canvas></div></div>
              </div>
              <div class="col-lg-6 mb-3">
                <div class="card h-100"><div class="card-body"><h5 class="card-title">A/P Aging <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Outstanding payables bucketed by age (e.g., 0–30, 31–60 days). Highlights overdue obligations."></i></h5><canvas id="chartAging"></canvas></div></div>
              </div>
              <div class="col-lg-6 mb-3">
                <div class="card h-100"><div class="card-body"><h5 class="card-title">Supplier Pareto <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Pareto view of net spend by supplier plus cumulative %. Identifies key suppliers driving most of your spend."></i></h5><canvas id="chartPareto"></canvas></div></div>
              </div>
              <div class="col-lg-12 mb-3">
                <div class="card h-100"><div class="card-body"><h5 class="card-title">Returns Value & Rate <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Tracks purchase returns (value) and returns rate % to monitor supplier or quality issues."></i></h5><canvas id="chartReturns"></canvas></div></div>
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead class="thead-dark">
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
                  <th class="text-right">Paid</th>
                  <th class="text-right">Net Remaining</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($purchasesList ?? [] as $row)
                  @php
                    $paid = (float)($row->paid_total ?? 0);
                    $refunds = (float)($row->refunds_total ?? 0);
                    $returns = (float)($row->returns_total ?? 0);
                    $netSpend = (float)($row->net_spend ?? 0);
                    $netPaid = max(0, (float)($row->net_paid ?? 0));
                    $remain = max(0, (float)($row->net_remaining ?? 0));
                    $status = $remain <= 0 ? 'PAID' : ($paid <= 0 ? 'PENDING' : 'PARTIAL');
                    $badge = $status === 'PAID' ? 'badge-success' : ($status === 'PENDING' ? 'badge-warning' : 'badge-info');
                  @endphp
                  <tr>
                    <td>{{ $row->order_no }}</td>
                    <td>{{ \Carbon\Carbon::parse($row->created_at)->format('Y-m-d H:i') }}</td>
                    <td>
                      @if(!empty($row->supplier_name))
                        <a href="{{ route('suppliers.index', ['q' => $row->supplier_name]) }}" >{{ $row->supplier_name }}</a>
                      @else
                        {{ $row->supplier_name ?? '-' }}
                      @endif
                    </td>
                    <td>{{ $row->subshop_name }}</td>
                    <td class="text-right">{{ number_format((float)$row->subtotal, 2) }}</td>
                    <td class="text-right">{{ number_format((float)$row->vat_total, 2) }}</td>
                    <td class="text-right">{{ number_format((float)$row->discount_total, 2) }}</td>
                    <td class="text-right">{{ number_format((float)$row->grand_total, 2) }}</td>
                    <td class="text-right">{{ number_format($returns, 2) }}</td>
                    <td class="text-right">{{ number_format($refunds, 2) }}</td>
                    <td class="text-right">{{ number_format($netSpend, 2) }}</td>
                    <td class="text-right">{{ number_format($netPaid, 2) }}</td>
                    <td class="text-right">{{ number_format($remain, 2) }}</td>
                    <td><span class="badge {{ $badge }}">{{ $status }}</span></td>
                    <td>
                      <div class="btn-group btn-group-sm" role="group">
                        @can('print_purchases_receipt_invoice')
                        <a class="btn btn-outline-secondary" href="{{ route('purchase_orders.print', ['order' => $row->id]) }}" target="_blank"><i class="fas fa-print"></i></a>
                        @endcan
                        @can('view_purchase_history')
                        <a class="btn btn-outline-primary" href="{{ route('purchase_orders.index', ['q' => $row->order_no]) }}"  title="View & Add Payment / Returns"><i class="fas fa-external-link-alt"></i></a>
                        @endcan
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="15" class="text-center">No purchases for selected period</td>
                  </tr>
                @endforelse
              </tbody>
              @if(($purchasesList ?? null) && $purchasesList->count())
              <tfoot>
                <tr>
                  <th colspan="8" class="text-right">Page Totals:</th>
                  <th class="text-right">{{ number_format($pageTotals['returns'] ?? 0, 2) }}</th>
                  <th class="text-right">{{ number_format($pageTotals['refunds'] ?? 0, 2) }}</th>
                  <th class="text-right">{{ number_format($pageTotals['net_spend'] ?? 0, 2) }}</th>
                  <th class="text-right">{{ number_format($pageTotals['net_paid'] ?? 0, 2) }}</th>
                  <th class="text-right">{{ number_format($pageTotals['remaining'] ?? 0, 2) }}</th>
                  <th></th>
                  <th></th>
                </tr>
                <tr>
                  <th colspan="8" class="text-right">Overall Totals:</th>
                  <th class="text-right">{{ number_format($overallTotals['returns'] ?? 0, 2) }}</th>
                  <th class="text-right">{{ number_format($overallTotals['refunds'] ?? 0, 2) }}</th>
                  <th class="text-right">{{ number_format($overallTotals['net_spend'] ?? 0, 2) }}</th>
                  <th class="text-right">{{ number_format($overallTotals['net_paid'] ?? 0, 2) }}</th>
                  <th class="text-right">{{ number_format($overallTotals['remaining'] ?? 0, 2) }}</th>
                  <th></th>
                  <th></th>
                </tr>
              </tfoot>
              @endif
            </table>
          </div>

          <div class="p-2">
            {{ ($purchasesList ?? null) ? $purchasesList->links() : '' }}
          </div>
        </div>
      </div>



    </div>
  </div>
</div>

<!-- Recommendations -->
<div class="card shadow-sm border-0 mt-4">
  <div class="card-header d-flex justify-content-between align-items-center" style="background:linear-gradient(135deg,#f0fff4,#fff);">
    <h3 class="card-title mb-0"><i class="fas fa-lightbulb mr-1 text-success"></i> Recommendations</h3>
    <span class="text-muted small">Auto-generated from selected period and filters</span>
  </div>
  <div class="card-body">
    @php
      $k = $kpi ?? [];
      $orders = (int)($k['orders'] ?? 0);
      $totalPurch = (float)($k['total_purchases'] ?? 0);
      $apv = (float)($k['apv'] ?? 0);
      $taxes = (float)($k['taxes'] ?? 0);
      $discounts = (float)($k['discounts'] ?? 0);
      $outAP = (float)($k['outstanding_ap'] ?? 0);

      // Sample trend using first/last few rows on the current page (fallback: grand_total -> net_spend -> paid_total)
      $trendDir = null; $trendPct = null; $sampleStart = null; $sampleEnd = null;
      if (!empty($purchasesList) && $purchasesList->count() >= 2) {
        $arr = $purchasesList->values();
        $take = min(3, $arr->count());
        $sumStart = 0; $sumEnd = 0;
        for($i=0;$i<$take;$i++){
          $rowS = $arr[$arr->count()-1-$i]; // oldest from end
          $rowE = $arr[$i];                 // newest at start (default sort desc)
          $valS = (float)($rowS->net_spend ?? $rowS->grand_total ?? 0);
          $valE = (float)($rowE->net_spend ?? $rowE->grand_total ?? 0);
          $sumStart += $valS; $sumEnd += $valE;
        }
        $sampleStart = $sumStart / $take; $sampleEnd = $sumEnd / $take;
        if ($sampleStart > 0) { $trendPct = (($sampleEnd - $sampleStart)/$sampleStart)*100; }
        if ($sampleEnd > $sampleStart) $trendDir = 'up'; elseif ($sampleEnd < $sampleStart) $trendDir = 'down'; else $trendDir = 'flat';
      }

      // Example suppliers from current page
      $supExamples = [];
      if (!empty($purchasesList)) {
        foreach ($purchasesList as $r) { if (count($supExamples)>=3) break; if (!empty($r->supplier_name)) $supExamples[] = $r->supplier_name; }
      }

      // Returns & refunds examples from totals if available
      $overall = $overallTotals ?? [];
      $totReturns = (float)($overall['returns'] ?? ($pageTotals['returns'] ?? 0));
      $totRefunds = (float)($overall['refunds'] ?? ($pageTotals['refunds'] ?? 0));
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
      {{-- Purchasing pace (trend) --}}
      @if($trendDir)
        <div class="rec-item">
          <div class="rec-icon primary"><i class="fas fa-chart-area"></i></div>
          <div>
            <div><strong>Purchasing pace:</strong>
              @if($trendDir==='up')
                Rising <span class="rec-badge">{{ is_null($trendPct)? 'up' : number_format($trendPct,1) . '% up' }}</span> vs earlier in the period.
                @if(!is_null($sampleStart) && !is_null($sampleEnd))
                  <div class="rec-example mt-1">e.g., avg order Tsh {{ number_format($sampleStart,2) }} → {{ number_format($sampleEnd,2) }}</div>
                @endif
                <div class="small text-muted mt-1">Action: validate demand; avoid overbuying by tightening reorder points and checking aging stock.</div>
              @elseif($trendDir==='down')
                Slowing <span class="rec-badge">{{ is_null($trendPct)? 'down' : number_format($trendPct,1) . '% down' }}</span> compared to the start.
                @if(!is_null($sampleStart) && !is_null($sampleEnd))
                  <div class="rec-example mt-1">e.g., avg order Tsh {{ number_format($sampleStart,2) }} → {{ number_format($sampleEnd,2) }}</div>
                @endif
                <div class="small text-muted mt-1">Action: ensure critical SKUs remain covered; consider consolidating POs to get better terms.</div>
              @else
                Stable purchasing across the period.
                <div class="small text-muted mt-1">Action: maintain cadence; periodically review MOQ and lead times.</div>
              @endif
            </div>
          </div>
        </div>
      @endif

      {{-- A/P exposure --}}
      <div class="rec-item">
        <div class="rec-icon warning"><i class="fas fa-hand-holding-usd"></i></div>
        <div>
          <div><strong>Accounts Payable:</strong> Outstanding <span class="rec-badge">Tsh {{ number_format($outAP,2) }}</span>.</div>
          <div class="small text-muted mt-1">Action: prioritize payments on older buckets; negotiate terms or early-payment discounts with key suppliers.</div>
        </div>
      </div>

      {{-- Order pattern --}}
      <div class="rec-item">
        <div class="rec-icon success"><i class="fas fa-balance-scale"></i></div>
        <div>
          <div><strong>Order pattern:</strong> {{ number_format($orders) }} orders, APV <span class="rec-badge">Tsh {{ number_format($apv,2) }}</span>.</div>
          <div class="small text-muted mt-1">Action: if many small POs, consolidate to reduce logistics cost; if few large POs, monitor overstock risk.</div>
        </div>
      </div>

      {{-- Supplier focus --}}
      <div class="rec-item">
        <div class="rec-icon primary"><i class="fas fa-truck"></i></div>
        <div>
          <div><strong>Supplier focus:</strong> keep SLAs tight for top suppliers to protect availability and margin.</div>
          @if(!empty($supExamples))
            <div class="rec-example mt-1">e.g., {{ implode(', ', array_slice($supExamples,0,3)) }}</div>
          @endif
          <div class="small text-muted mt-1">Action: review lead times, on-time delivery, and defect rates; diversify if single-supplier risk is high.</div>
        </div>
      </div>

      {{-- Returns & refunds --}}
      <div class="rec-item">
        <div class="rec-icon danger"><i class="fas fa-undo"></i></div>
        <div>
          <div><strong>Returns & refunds:</strong> Returns <span class="rec-badge">Tsh {{ number_format($totReturns,2) }}</span> • Refunds <span class="rec-badge">Tsh {{ number_format($totRefunds,2) }}</span>.</div>
          <div class="small text-muted mt-1">Action: address root causes with suppliers; adjust specs/MOQs; strengthen inbound QC.</div>
        </div>
      </div>
    </div>
  </div>
</div>
@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
      .small-box.kpi-box { margin-bottom: .5rem; }
      .small-box.kpi-box .inner { padding: .5rem .5rem .25rem .5rem; }
      .small-box.kpi-box .inner h3 { font-size: 1.05rem; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .small-box.kpi-box .inner p { font-size: .75rem; margin: .25rem 0 0; }
      .small-box.kpi-box .icon { font-size: 1.8rem; top: .5rem; right: .5rem; opacity: .3; }
      @media (min-width: 576px) { .small-box.kpi-box .inner h3 { font-size: 1.15rem; } }
      @media (min-width: 768px) { .small-box.kpi-box .inner h3 { font-size: 1.2rem; } }
    </style>
@endpush
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (function(){
    const params = {
      date_from: '{{ $dateFrom }}',
      date_to: '{{ $dateTo }}',
      subshop_id: '{{ $selectedSubshopId ?? '' }}'
    };
    const qs = new URLSearchParams(Object.fromEntries(Object.entries(params).filter(([k,v])=>v!=='' && v!==null && v!==undefined)) ).toString();
    const endpoints = {
      spend: '{{ route('reports.purchases.analytics.spend') }}' + (qs ? ('?' + qs) : ''),
      orders: '{{ route('reports.purchases.analytics.orders') }}' + (qs ? ('?' + qs) : ''),
      aging: '{{ route('reports.purchases.analytics.apaging') }}' + (qs ? ('?' + qs) : ''),
      pareto: '{{ route('reports.purchases.analytics.pareto') }}' + (qs ? ('?' + qs) : ''),
      returns: '{{ route('reports.purchases.analytics.returns_rate') }}' + (qs ? ('?' + qs) : ''),
    };

    const fmt = (n)=>({
      style:'currency', currency:'TZS', maximumFractionDigits:2,
    });

    fetch(endpoints.spend).then(r=>r.json()).then(d=>{
      const ctx = document.getElementById('chartSpend'); if(!ctx) return;
      new Chart(ctx, {
        type: 'line',
        data: { labels: d.labels, datasets: [
          {label:'Net Spend', data: d.net_spend, borderColor:'#0d6efd', backgroundColor:'rgba(13,110,253,.2)', fill:true, tension:.3},
          {label:'Paid', data: d.paid, borderColor:'#198754', backgroundColor:'rgba(25,135,84,.2)', fill:true, tension:.3},
          {label:'Remaining', data: d.remaining, borderColor:'#dc3545', backgroundColor:'rgba(220,53,69,.2)', fill:true, tension:.3},
        ]},
        options: { responsive:true, plugins:{legend:{position:'bottom'}} }
      });
    });

    fetch(endpoints.orders).then(r=>r.json()).then(d=>{
      const ctx = document.getElementById('chartOrdersApv'); if(!ctx) return;
      new Chart(ctx, {
        data: { labels: d.labels, datasets: [
          {type:'bar', label:'Orders', data: d.orders, backgroundColor:'#6c757d'},
          {type:'line', label:'APV', data: d.apv, borderColor:'#0d6efd', yAxisID:'y1', tension:.3},
        ]},
        options: { responsive:true, scales:{ y:{ beginAtZero:true }, y1:{ position:'right', beginAtZero:true } }, plugins:{legend:{position:'bottom'}} }
      });
    });

    fetch(endpoints.aging).then(r=>r.json()).then(d=>{
      const ctx = document.getElementById('chartAging'); if(!ctx) return;
      new Chart(ctx, {
        type:'bar',
        data:{ labels:d.labels, datasets:[{label:'Outstanding', data:d.data, backgroundColor:'#ffc107'}] },
        options:{ responsive:true, plugins:{legend:{position:'bottom'}} }
      });
    });

    fetch(endpoints.pareto).then(r=>r.json()).then(d=>{
      const ctx = document.getElementById('chartPareto'); if(!ctx) return;
      new Chart(ctx, {
        data:{ labels:d.labels, datasets:[
          { type:'bar', label:'Net Spend', data:d.spend, backgroundColor:'#20c997', yAxisID:'y' },
          { type:'line', label:'Cumulative %', data:d.cumulative, borderColor:'#dc3545', yAxisID:'y1', tension:.3 },
        ]},
        options:{ responsive:true, scales:{ y:{ beginAtZero:true }, y1:{ position:'right', beginAtZero:true, max:100 } }, plugins:{legend:{position:'bottom'}} }
      });
    });

    fetch(endpoints.returns).then(r=>r.json()).then(d=>{
      const ctx = document.getElementById('chartReturns'); if(!ctx) return;
      new Chart(ctx, {
        data:{ labels:d.labels, datasets:[
          { type:'bar', label:'Returns (Tsh)', data:d.returns, backgroundColor:'#fd7e14', yAxisID:'y' },
          { type:'line', label:'Returns Rate %', data:d.returns_rate, borderColor:'#6f42c1', yAxisID:'y1', tension:.3 },
        ]},
        options:{ responsive:true, scales:{ y:{ beginAtZero:true }, y1:{ position:'right', beginAtZero:true, max:100 } }, plugins:{legend:{position:'bottom'}} }
      });
    });
  })();
</script>
@stop