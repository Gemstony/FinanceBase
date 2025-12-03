@extends('adminlte::page')

@section('title', 'Inventory Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-warehouse"></i> Inventory Overview</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-warehouse"></i> Inventory</h1>
      <div class="small text-light-50">Multi-location overview across your subshops</div>
    </div>
    <a href="{{ route('dashboard') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
  </div>
 </div>
@stop

@section('content')

<div class="container-fluid">
  <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
    <div class="card-body">
      <form method="get" action="{{ route('reports.inventory') }}" class="mb-3">
        <div class="bg-light p-2 rounded border">
          <div class="form-row align-items-end">
            <div class="form-group col-md-4">
              <label class="small mb-1">Subshop</label>
              <select name="subshop_id" class="form-control">
                <option value="">All subshops</option>
                @foreach(($subshops ?? []) as $s)
                  <option value="{{ $s->id }}" {{ (int)($selectedSubshopId ?? 0) === (int)$s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-6">
              <div class="form-row">
                <div class="form-group col-md-4">
                  <label class="small mb-1">Date From</label>
                  <input type="date" name="date_from" class="form-control" value="{{ $dateFrom ?? '' }}">
                </div>
                <div class="form-group col-md-4">
                  <label class="small mb-1">Date To</label>
                  <input type="date" name="date_to" class="form-control" value="{{ $dateTo ?? '' }}">
                </div>
                <div class="form-group col-md-4">
                  <label class="small mb-1">As of (Snapshot)</label>
                  <input type="date" name="as_of" class="form-control" value="{{ $asOf ?? '' }}">
                </div>
              </div>
            </div>
            <div class="form-group col-md-2">
              <label class="small mb-1">Category</label>
              <select name="category_id" class="form-control">
                <option value="">All</option>
                @foreach(($categories ?? []) as $c)
                  <option value="{{ $c->id }}" {{ (int)($categoryId ?? 0) === (int)$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-2">
              <label class="small mb-1">Supplier</label>
              <select name="supplier_id" class="form-control">
                <option value="">All</option>
                @foreach(($suppliers ?? []) as $sp)
                  <option value="{{ $sp->id }}" {{ (int)($supplierId ?? 0) === (int)$sp->id ? 'selected' : '' }}>{{ $sp->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-2">
              <label class="small mb-1">ABC Class
                <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="ABC ranks items by share of total inventory value: A≈top 80%, B≈next 15%, C≈last 5%. Use to prioritize controls and purchasing."></i>
              </label>
              <select name="abc" class="form-control">
                <option value="">All</option>
                <option value="A" {{ ($abcClass ?? '') === 'A' ? 'selected' : '' }}>A (0–80%)</option>
                <option value="B" {{ ($abcClass ?? '') === 'B' ? 'selected' : '' }}>B (80–95%)</option>
                <option value="C" {{ ($abcClass ?? '') === 'C' ? 'selected' : '' }}>C (95–100%)</option>
              </select>
            </div>
            <div class="form-group col-md-2">
              <button class="btn btn-primary mr-1" type="submit"><i class="fas fa-filter"></i> Apply</button>
              <a class="btn btn-light border" href="{{ route('reports.inventory') }}"><i class="fas fa-undo"></i> Reset</a>
            </div>
          </div>

          <div class="form-row align-items-end mt-2">
            <div class="form-group col-md-4">
              <label class="small mb-1">Saved Views</label>
              <div class="input-group">
                <select id="saved_view_select" class="form-control">
                  <option value="">-- Choose Saved View --</option>
                  @foreach((array)($savedViews ?? []) as $name => $filters)
                    <option value="{{ $name }}">{{ $name }}</option>
                  @endforeach
                </select>
                <div class="input-group-append">
                  <button class="btn btn-outline-primary" type="button" onclick="handleViewAction('load')"><i class="fas fa-download"></i> Load</button>
                  <button class="btn btn-outline-danger" type="button" onclick="handleViewAction('delete')"><i class="fas fa-trash"></i></button>
                </div>
              </div>
            </div>
            <div class="form-group col-md-4">
              <label class="small mb-1">Save Current Filters As</label>
              <div class="input-group">
                <input type="text" id="save_view_name" class="form-control" placeholder="e.g. Cost View - A Class">
                <div class="input-group-append">
                  <button class="btn btn-success" type="button" onclick="handleViewAction('save')"><i class="fas fa-save"></i> Save</button>
                </div>
              </div>
            </div>
            <input type="hidden" name="view_action" id="view_action_field" value="">
            <input type="hidden" name="view_name" id="view_name_field" value="">
          </div>
        </div>
      </form>

      <div class="row mb-3">
        <div class="col-md-3 col-6">
          <div class="small-box bg-primary">
            <div class="inner">
              <h3 class="mb-0">{{ number_format($kpi['soh_qty'] ?? 0) }}</h3>
              <p>Stock on Hand (Qty)</p>
            </div>
            <div class="icon"><i class="fas fa-boxes"></i></div>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="small-box bg-success">
            <div class="inner">
              <h3 class="mb-0">Tsh {{ number_format($kpi['soh_value_cost'] ?? 0, 2) }}</h3>
              <p>Inventory Value (Cost)</p>
            </div>
            <div class="icon"><i class="fas fa-coins"></i></div>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="small-box bg-info">
            <div class="inner">
              <h3 class="mb-0">Tsh {{ number_format($kpi['soh_value_retail'] ?? 0, 2) }}</h3>
              <p>Potential Retail Value</p>
            </div>
            <div class="icon"><i class="fas fa-tags"></i></div>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="small-box bg-warning">
            <div class="inner">
              <h3 class="mb-0">{{ number_format($kpi['low_stock_count'] ?? 0) }}</h3>
              <p>Low Stock Items</p>
            </div>
            <div class="icon"><i class="fas fa-level-down-alt"></i></div>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="small-box bg-danger">
            <div class="inner">
              <h3 class="mb-0">{{ number_format($kpi['out_of_stock_count'] ?? 0) }}</h3>
              <p>Out of Stock Items</p>
            </div>
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="small-box bg-orange">
            <div class="inner">
              <h3 class="mb-0">{{ number_format($kpi['expiring_soon_count'] ?? 0) }}</h3>
              <p>Expiring Soon (30d)</p>
            </div>
            <div class="icon"><i class="fas fa-hourglass-half"></i></div>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="small-box bg-secondary">
            <div class="inner">
              <h3 class="mb-0">{{ number_format($kpi['expired_count'] ?? 0) }}</h3>
              <p>Expired Batches</p>
            </div>
            <div class="icon"><i class="fas fa-skull-crossbones"></i></div>
          </div>
        </div>
      </div>

      <div class="row mt-3">
        <div class="col-lg-7 mb-3">
          <div class="card shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="fas fa-chart-area text-primary mr-1"></i> Inventory Value Trend
                <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Tracks total inventory value over time at cost/retail to spot build-ups or stockouts."></i>
              </span>
              <div class="btn-group btn-group-sm" role="group" aria-label="chart mode">
                <button type="button" class="btn btn-outline-primary chart-mode-btn active" data-mode="cost">Cost</button>
                <button type="button" class="btn btn-outline-primary chart-mode-btn" data-mode="retail">Retail</button>
              </div>
            </div>
            <div class="card-body">
              <canvas id="trendChart" height="180"></canvas>
            </div>
          </div>
        </div>
        <div class="col-lg-5 mb-3">
          <div class="card shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="fas fa-chart-bar text-info mr-1"></i> Subshop Comparison
                <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Compare quantity and value across subshops to balance stock and plan transfers."></i>
              </span>
              <div class="btn-group btn-group-sm" role="group" aria-label="chart mode">
                <button type="button" class="btn btn-outline-info subshop-mode-btn active" data-mode="cost">Cost</button>
                <button type="button" class="btn btn-outline-info subshop-mode-btn" data-mode="retail">Retail</button>
              </div>
            </div>
            <div class="card-body">
              <canvas id="subshopBarChart" height="200"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="row mt-3">
        <div class="col-lg-6 mb-3">
          <div class="card shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="fas fa-exclamation-circle text-warning mr-1"></i> Low Stock (Top 25)
                <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Items below their minimum stock. Use Create PO to replenish quickly."></i>
              </span>
              @can('export_inventory_reports')
              <div>
                <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'csv','type'=>'low_stock']) }}">CSV</a>
                <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'xlsx','type'=>'low_stock']) }}">Excel</a>
                <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'pdf','type'=>'low_stock']) }}">PDF</a>
              </div>
              @endcan
            </div>
            <div class="card-body p-0">
              <div class="table-responsive stock-scroll">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Subshop</th>
                      <th class="text-center">ABC</th>
                      <th class="text-right">Qty</th>
                      <th class="text-right">Min</th>
                      <th class="text-right">Deficit</th>
                      <th class="text-right">Days of Supply</th>
                      <th class="text-right">Reorder Suggestion</th>
                      <th class="text-center">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse(($lowStockItems ?? []) as $it)
                      <tr>
                        <td>
                          <a href="{{ route('items.index', ['q' => $it->name]) }}" >{{ $it->name }}</a>
                        </td>
                        <td>{{ ($subshopNames[$it->subshop_id] ?? '—') }}</td>
                        <td class="text-center">
                          @php $abc = $it->abc_class ?? null; @endphp
                          @if($abc === 'A')<span class="badge badge-danger">A</span>
                          @elseif($abc === 'B')<span class="badge badge-warning">B</span>
                          @elseif($abc === 'C')<span class="badge badge-success">C</span>
                          @else <span class="text-muted">—</span>@endif
                        </td>
                        <td class="text-right">{{ number_format($it->qty ?? 0) }}</td>
                        <td class="text-right">{{ number_format($it->min_quantity ?? 0) }}</td>
                        <td class="text-right text-danger">{{ number_format(max(0, ($it->min_quantity ?? 0) - ($it->qty ?? 0))) }}</td>
                        <td class="text-right">{{ is_null($it->days_of_supply ?? null) ? '—' : number_format($it->days_of_supply, 1) }}</td>
                        <td class="text-right">{{ number_format($it->reorder_suggestion ?? 0) }}</td>
                        <td class="text-center">
                          <a class="btn btn-xs btn-outline-success" href="{{ route('purchases.index', ['add_item_name' => $it->name, 'add_qty' => max(1, (int)($it->reorder_suggestion ?? (($it->min_quantity ?? 0) - ($it->qty ?? 0)))) ]) }}">
                            <i class="fas fa-cart-plus"></i> Create PO
                          </a>
                        </td>
                      </tr>
                    @empty
                      <tr><td colspan="9" class="text-center text-muted py-3"><i class="fas fa-check"></i> No low stock items</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6 mb-3">
          <div class="card shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="fas fa-times-circle text-danger mr-1"></i> Out of Stock (Top 25)
                <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Items with zero or negative stock. Consider urgent replenishment or substitutes."></i>
              </span>
              @can('export_inventory_reports')
              <div>
                <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'csv','type'=>'oos']) }}">CSV</a>
                <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'xlsx','type'=>'oos']) }}">Excel</a>
                <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'pdf','type'=>'oos']) }}">PDF</a>
              </div>
              @endcan
            </div>
            <div class="card-body p-0">
              <div class="table-responsive stock-scroll">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Subshop</th>
                      <th class="text-center">ABC</th>
                      <th class="text-right">Qty</th>
                      <th class="text-right">Days of Supply</th>
                      <th class="text-right">Reorder Suggestion</th>
                      <th class="text-center">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse(($oosItems ?? []) as $it)
                      <tr>
                        <td>
                          <a href="{{ route('items.index', ['q' => $it->name]) }}" >{{ $it->name }}</a>
                        </td>
                        <td>{{ ($subshopNames[$it->subshop_id] ?? '—') }}</td>
                        <td class="text-right">{{ number_format($it->qty ?? 0) }}</td>
                        <td class="text-right">{{ is_null($it->days_of_supply ?? null) ? '—' : number_format($it->days_of_supply, 1) }}</td>
                        <td class="text-right">{{ number_format(max(0, ($it->min_quantity ?? 0) - ($it->qty ?? 0))) }}</td>
                        <td class="text-center">
                          <a class="btn btn-xs btn-outline-success" href="{{ route('purchases.index', ['add_item_name' => $it->name, 'add_qty' => max(1, (int)max(0, ($it->min_quantity ?? 0) - ($it->qty ?? 0))) ]) }}">
                            <i class="fas fa-cart-plus"></i> Create PO
                          </a>
                        </td>
                      </tr>
                    @empty
                      <tr><td colspan="7" class="text-center text-muted py-3"><i class="fas fa-check"></i> No out of stock items</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row mt-3">
        <div class="col-lg-6 mb-3">
          <div class="card shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="fas fa-hourglass-half text-warning mr-1"></i> Inventory Aging
                <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Groups stock by days since last movement. Click a slice to drill into ledger and investigate slow movers."></i>
              </span>
              @can('export_inventory_reports')
              <div>
                <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'csv','type'=>'aging']) }}">CSV</a>
                <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'xlsx','type'=>'aging']) }}">Excel</a>
                <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'pdf','type'=>'aging']) }}">PDF</a>
              </div>
              @endcan
            </div>
            <div class="card-body">
              <canvas id="agingChart" height="180"></canvas>
            </div>
          </div>
        </div>
        <div class="col-lg-6 mb-3">
          <div class="card shadow-sm border-0">
            <div class="card-header"><i class="fas fa-star text-warning mr-1"></i> Top Items by Value (Cost)
              <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Highest-value SKUs at cost. Focus accuracy and availability here."></i>
            </div>
            <div class="card-body">
              <canvas id="topItemsChart" height="180"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="row mt-3">
        <div class="col-lg-6 mb-3">
          <div class="card shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="fas fa-percentage text-purple mr-1"></i> ABC Distribution
                <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Share of SKUs in A, B, C classes by inventory value. Indicates where value is concentrated."></i>
              </span>
              @can('export_inventory_reports')
              <div>
                <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'csv','type'=>'abc']) }}">CSV</a>
                <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'xlsx','type'=>'abc']) }}">Excel</a>
                <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'pdf','type'=>'abc']) }}">PDF</a>
              </div>
              @endcan
            </div>
            <div class="card-body">
              <canvas id="abcChart" height="160"></canvas>
            </div>
          </div>
        </div>
        <div class="col-lg-6 mb-3">
          <div class="card shadow-sm border-0">
            <div class="card-header"><i class="fas fa-layer-group text-info mr-1"></i> Top Categories by Value (Cost)
              <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Category contribution to inventory value (cost). Helps direct assortment decisions."></i>
            </div>
            <div class="card-body">
              <canvas id="topCategoriesChart" height="160"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm border-0">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0"><i class="fas fa-store-alt mr-1 text-info"></i> Per Subshop Summary
            <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Quick totals per subshop: quantities and values to spot imbalances."></i>
          </h3>
          @can('export_inventory_reports')
          <div>
            <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'csv','type'=>'summary']) }}">CSV</a>
            <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'xlsx','type'=>'summary']) }}">Excel</a>
            <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'pdf','type'=>'summary']) }}">PDF</a>
          </div>
          @endcan
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="thead-light">
                <tr>
                  <th>Subshop</th>
                  <th class="text-right">Qty</th>
                  <th class="text-right">Value (Cost)</th>
                  <th class="text-right">Value (Retail)</th>
                </tr>
              </thead>
              <tbody>
                @forelse(($subshopSummary ?? []) as $row)
                  <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="text-right">{{ number_format($row['qty'] ?? 0) }}</td>
                    <td class="text-right">Tsh {{ number_format($row['value_cost'] ?? 0, 2) }}</td>
                    <td class="text-right">Tsh {{ number_format($row['value_retail'] ?? 0, 2) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="3" class="text-center text-muted py-4"><i class="fas fa-inbox"></i> No data</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Recommendations -->
      <div class="card shadow-sm border-0 mt-4">
        <div class="card-header d-flex justify-content-between align-items-center" style="background:linear-gradient(135deg,#fff7e6,#fff);">
          <h3 class="card-title mb-0"><i class="fas fa-lightbulb mr-1 text-warning"></i> Recommendations</h3>
          <span class="text-muted small">Auto-generated from current filters and data</span>
        </div>
        <div class="card-body">
          @php
            // Helpers
            $k = $kpi ?? [];
            $trendArr = $trend ?? [];
            $abc = $abcCounts ?? [];
            $aging = $agingBuckets ?? [];
            $subS = $subshopSummary ?? [];
            $lowCount = (int)($k['low_stock_count'] ?? 0);
            $oosCount = (int)($k['out_of_stock_count'] ?? 0);
            $expSoon = (int)($k['expiring_soon_count'] ?? 0);
            $expiredCnt = (int)($k['expired_count'] ?? 0);

            // Trend direction (cost)
            $trendDir = null; $trendPct = null; $firstVal = null; $lastVal = null;
            if (is_array($trendArr) && count($trendArr) >= 2) {
              $firstEntry = $trendArr[0];
              $lastEntry = $trendArr[count($trendArr)-1];
              $firstVal = (float)(is_array($firstEntry) ? ($firstEntry['value_cost'] ?? 0) : ($firstEntry->value_cost ?? 0));
              $lastVal  = (float)(is_array($lastEntry)  ? ($lastEntry['value_cost'] ?? 0)  : ($lastEntry->value_cost ?? 0));
              if ($firstVal > 0) { $trendPct = (($lastVal - $firstVal)/$firstVal)*100; }
              if ($lastVal > $firstVal) $trendDir = 'up'; elseif ($lastVal < $firstVal) $trendDir = 'down'; else $trendDir = 'flat';
            }

            // ABC mix percentages
            $abcTotal = max(1, array_sum(array_map(fn($v)=> (int)$v, $abc)));
            $aPct = round(((int)($abc['A'] ?? 0)) / $abcTotal * 100);
            $bPct = round(((int)($abc['B'] ?? 0)) / $abcTotal * 100);
            $cPct = round(((int)($abc['C'] ?? 0)) / $abcTotal * 100);

            // Aging mix
            $agingTotal = 0; foreach(($aging ?? []) as $v){ $agingTotal += (int)$v; }
            $agePct = function($label) use ($aging, $agingTotal){ $n = (int)($aging[$label] ?? 0); return $agingTotal>0 ? round($n/$agingTotal*100) : 0; };

            // Subshop imbalance (by cost value)
            $vals = array_map(function($r){ return (float)(is_array($r) ? ($r['value_cost'] ?? 0) : ($r->value_cost ?? 0)); }, $subS);
            $imbalance = 0; $maxShop = null; $minShop = null;
            if (count($vals)) {
              $maxVal = max($vals); $minVal = min($vals);
              $imbalance = $maxVal - $minVal;
              // find names
              if ($imbalance > 0) {
                $maxIdx = array_search($maxVal, $vals);
                $minIdx = array_search($minVal, $vals);
                $maxRow = $subS[$maxIdx] ?? null;
                $minRow = $subS[$minIdx] ?? null;
                $maxShop = is_array($maxRow) ? ($maxRow['name'] ?? null) : ($maxRow->name ?? null);
                $minShop = is_array($minRow) ? ($minRow['name'] ?? null) : ($minRow->name ?? null);
              }
            }

            // Simple examples (few names)
            $lowNames = [];
            if (!empty($lowStockItems)) { foreach ($lowStockItems as $it) { if (count($lowNames) >= 3) break; $lowNames[] = $it->name; } }
            $oosNames = [];
            if (!empty($oosItems)) { foreach ($oosItems as $it) { if (count($oosNames) >= 3) break; $oosNames[] = $it->name; } }
            $topItemNames = [];
            if (!empty($topItems)) {
              foreach ($topItems as $it) {
                if (count($topItemNames) >= 3) break;
                $name = is_array($it) ? ($it['name'] ?? null) : ($it->name ?? null);
                $topItemNames[] = $name ?: '';
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
            {{-- Trend insight --}}
            @if($trendDir)
              <div class="rec-item">
                <div class="rec-icon primary"><i class="fas fa-chart-line"></i></div>
                <div>
                  <div><strong>Inventory value trend:</strong>
                @if($trendDir==='up')
                  Total inventory (cost) is rising <span class="rec-badge">{{ is_null($trendPct)? 'up' : number_format($trendPct,1) . '% up' }}</span>. This may reflect purchasing build‑up or slower sales.
                  <div class="rec-example mt-1">e.g., {{ 'Tsh ' . number_format((float)($firstVal ?? 0),2) }} → {{ 'Tsh ' . number_format((float)($lastVal ?? 0),2) }}</div>
                  <div class="small text-muted mt-1">Action: review slow movers and transfer excess stock to high‑demand subshops; align purchase frequency with sales trend.</div>
                @elseif($trendDir==='down')
                  Total inventory (cost) is declining <span class="rec-badge">{{ is_null($trendPct)? 'down' : number_format($trendPct,1) . '% down' }}</span>. Good for cash flow if service levels are maintained.
                  <div class="rec-example mt-1">e.g., {{ 'Tsh ' . number_format((float)($firstVal ?? 0),2) }} → {{ 'Tsh ' . number_format((float)($lastVal ?? 0),2) }}</div>
                  <div class="small text-muted mt-1">Action: ensure A‑class items remain in stock; use Low Stock and OOS lists to avoid lost sales.</div>
                @else
                  Inventory value is stable.
                  <div class="small text-muted mt-1">Action: keep current purchasing cadence; fine‑tune mins/reorder points.</div>
                @endif
                  </div>
                </div>
              </div>
            @endif

            {{-- ABC focus --}}
            <div class="rec-item">
              <div class="rec-icon success"><i class="fas fa-percentage"></i></div>
              <div>
                <div><strong>ABC focus:</strong> A={{ $aPct }}%, B={{ $bPct }}%, C={{ $cPct }}% of SKUs.</div>
                <div class="rec-example mt-1">e.g., keep A-items always available; keep C-items lean.</div>
                <div class="small text-muted mt-1">Action: prioritize availability and cycle counts for <strong>A</strong>; tighten mins. Keep <strong>C</strong> items light to reduce carrying cost.</div>
              </div>
            </div>

            {{-- Aging risk --}}
            <div class="rec-item">
              <div class="rec-icon warning"><i class="fas fa-hourglass-half"></i></div>
              <div>
                <div><strong>Aging risk:</strong> <span class="rec-badge">{{ $agePct('61-90') + $agePct('91+') }}%</span> of stock hasn’t moved for 61+ days.</div>
                <div class="rec-example mt-1">e.g., 61–90d: {{ (int)($aging['61-90'] ?? 0) }} SKUs, 91+d: {{ (int)($aging['91+'] ?? 0) }} SKUs</div>
                <div class="small text-muted mt-1">Action: discount or bundle slow movers; transfer to branches with demand; review mins and assortment.</div>
              </div>
            </div>

            {{-- Stock health --}}
            <div class="rec-item">
              <div class="rec-icon danger"><i class="fas fa-exclamation-triangle"></i></div>
              <div>
                <div><strong>Stock health:</strong> <span class="rec-badge">{{ number_format($lowCount) }} low</span> • <span class="rec-badge">{{ number_format($oosCount) }} OOS</span> items.</div>
                @if(!empty($lowNames))<div class="rec-example mt-1">e.g., Low: {{ implode(', ', array_slice($lowNames,0,3)) }}</div>@endif
                @if(!empty($oosNames))<div class="rec-example">e.g., OOS: {{ implode(', ', array_slice($oosNames,0,3)) }}</div>@endif
                <div class="small text-muted mt-1">Action: use “Create PO” to replenish; for repeated OOS, raise mins; for A-items frequently low, shorten lead times or add safety stock.</div>
              </div>
            </div>

            {{-- Expiry control --}}
            <div class="rec-item">
              <div class="rec-icon warning"><i class="fas fa-skull-crossbones"></i></div>
              <div>
                <div><strong>Expiry control:</strong> <span class="rec-badge">{{ number_format($expSoon) }} expiring (≤30d)</span> • <span class="rec-badge">{{ number_format($expiredCnt) }} expired</span>.</div>
                <div class="rec-example mt-1">e.g., promote near‑expiry, enforce FEFO in stores.</div>
                <div class="small text-muted mt-1">Action: run clearance, rotate stock (FIFO/FEFO), tighten purchase quantities for perishables.</div>
              </div>
            </div>

            {{-- Subshop balancing --}}
            @if($imbalance > 0 && $maxShop && $minShop)
            <div class="rec-item">
              <div class="rec-icon primary"><i class="fas fa-balance-scale"></i></div>
              <div>
                <div><strong>Subshop balance:</strong> Imbalance <span class="rec-badge">Tsh {{ number_format($imbalance, 2) }}</span> ({{ $maxShop }} high vs {{ $minShop }} low).</div>
                <div class="rec-example mt-1">e.g., move excess from {{ $maxShop }} to {{ $minShop }} for faster sell-through.</div>
                <div class="small text-muted mt-1">Action: transfer surplus to demand centers; align minimums per location.</div>
              </div>
            </div>
            @endif

            {{-- Top value items guard --}}
            @if(!empty($topItems))
            <div class="rec-item">
              <div class="rec-icon success"><i class="fas fa-star"></i></div>
              <div>
                <div><strong>Guard top‑value SKUs:</strong> Focus controls on highest‑value items to protect working capital.</div>
                @if(!empty($topItemNames))<div class="rec-example mt-1">e.g., {{ implode(', ', array_slice($topItemNames,0,3)) }}</div>@endif
                <div class="small text-muted mt-1">Action: weekly cycle counts; enforce barcode scanning at receipt/sale; track supplier SLAs.</div>
              </div>
            </div>
            @endif
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

@push('css')
  <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
  <style>
    .small-box .inner h3{ font-size: 1.6rem; line-height: 1.2; word-break: break-word; white-space: normal; }
    .small-box .inner p{ margin-bottom: 0; }
    .small-box{ position: relative; overflow: hidden; }
    .small-box .icon{ position:absolute; right:10px; top:8px; font-size:36px; opacity:.35; z-index:0; line-height:1; }
    .small-box .inner{ position: relative; z-index:1; }
    .stock-scroll{ max-height: 360px; overflow-y: auto; -webkit-overflow-scrolling: touch; }
  </style>
@endpush
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
  const trend = @json($trend ?? []);
  const subshops = @json($subshopSummary ?? []);
  const agingBuckets = @json($agingBuckets ?? []);
  const topItems = @json($topItems ?? []);
  const topCategories = @json($topCategories ?? []);
  const ledgerUrl = "{{ route('reports.inventory.ledger') }}";
  const abcCounts = @json($abcCounts ?? []);

  // Trend chart with toggle
  const tctx = document.getElementById('trendChart');
  let trendChart = null;
  function buildTrend(mode){
    if (!tctx || !trend.length) return;
    const labels = trend.map(p => p.date);
    const series = trend.map(p => parseFloat((mode === 'retail' ? (p.value_retail||0) : (p.value_cost||0))));
    // gradient
    const ctx2d = tctx.getContext('2d');
    const grad = ctx2d.createLinearGradient(0, 0, 0, tctx.height);
    grad.addColorStop(0, 'rgba(13,110,253,0.25)');
    grad.addColorStop(1, 'rgba(13,110,253,0.02)');
    const cfg = {
      type: 'line',
      data: { labels, datasets: [{ label: 'Value (' + (mode==='retail'?'Retail':'Cost') + ')', data: series, fill: true, borderColor: '#0d6efd', backgroundColor: grad, pointRadius: 2.5, pointHoverRadius: 4, tension: .25 }]},
      options: { 
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top', labels: { boxWidth: 10 } },
          tooltip: { callbacks: { label: ctx => 'Tsh ' + Number(ctx.parsed.y).toLocaleString() } }
        },
        scales: {
          x: { grid: { color: 'rgba(0,0,0,0.045)' } },
          y: { grid: { color: 'rgba(0,0,0,0.045)' }, ticks: { callback: v => 'Tsh ' + Number(v).toLocaleString() } }
        }
      }
    };
    if (trendChart) { trendChart.destroy(); }
    trendChart = new Chart(tctx, cfg);
  }

  // Subshop comparison with toggle
  const bctx = document.getElementById('subshopBarChart');
  let subshopChart = null;
  function buildSubshop(mode){
    if (!bctx || !subshops.length) return;
    const labels = subshops.map(s => s.name);
    const series = subshops.map(s => parseFloat((mode === 'retail' ? (s.value_retail||0) : (s.value_cost||0))));
    const cfg = {
      type: 'bar',
      data: { labels, datasets: [{ label: 'Value (' + (mode==='retail'?'Retail':'Cost') + ')', data: series, backgroundColor: (mode==='retail'?'#0dcaf0':'#20c997'), borderRadius: 6, barThickness: 18 }]},
      options: { 
        responsive: true, maintainAspectRatio: false, indexAxis: 'y',
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => 'Tsh ' + Number(ctx.parsed.x).toLocaleString() } },
          // Inline value labels for bars
          afterDatasetsDraw(chart, args, pluginOptions) {
            const {ctx} = chart;
            ctx.save();
            ctx.font = '12px system-ui, -apple-system, Segoe UI, Roboto, Arial';
            ctx.fillStyle = '#334155';
            chart.getDatasetMeta(0).data.forEach((bar, i) => {
              const val = series[i];
              if (val == null) return;
              const x = bar.x + 6; const y = bar.y + 4;
              ctx.fillText('Tsh ' + Number(val).toLocaleString(), x, y);
            });
            ctx.restore();
          }
        },
        scales: {
          x: { grid: { color: 'rgba(0,0,0,0.045)' }, ticks: { callback: v => 'Tsh ' + Number(v).toLocaleString() } },
          y: { grid: { display: false } }
        }
      }
    };
    if (subshopChart) { subshopChart.destroy(); }
    subshopChart = new Chart(bctx, cfg);
  }

  // Initial build
  buildTrend('cost');
  buildSubshop('cost');

  // Aging doughnut
  const agingEl = document.getElementById('agingChart');
  if (agingEl && agingBuckets){
    const labels = Object.keys(agingBuckets);
    const data = Object.values(agingBuckets);
    const chart = new Chart(agingEl, {
      type: 'doughnut',
      data: { labels, datasets: [{ data, backgroundColor: ['#4ade80','#fbbf24','#fb7185','#94a3b8'] }] },
      options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom' } } }
    });

    // Drilldown to ledger by date bucket
    agingEl.onclick = function(evt){
      const points = chart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, false);
      if (!points || !points.length) return;
      const idx = points[0].index;
      const label = labels[idx];
      const now = new Date();
      let fromDays = 0, toDays = 0;
      if (label === '0-30') { fromDays = 0; toDays = 30; }
      else if (label === '31-60') { fromDays = 31; toDays = 60; }
      else if (label === '61-90') { fromDays = 61; toDays = 90; }
      else { fromDays = 91; toDays = 9999; }
      function fmt(d){ const y=d.getFullYear(); const m=('0'+(d.getMonth()+1)).slice(-2); const dd=('0'+d.getDate()).slice(-2); return `${y}-${m}-${dd}`; }
      const dateTo = fmt(now);
      const from = new Date(); from.setDate(now.getDate() - toDays);
      const to = new Date(); to.setDate(now.getDate() - fromDays);
      const dateFrom = fmt(from);
      const url = `${ledgerUrl}?date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}`;
      window.location.href = url;
    };
  }

  // Top items bar
  const itemsEl = document.getElementById('topItemsChart');
  if (itemsEl && topItems && topItems.length){
    new Chart(itemsEl, {
      type: 'bar',
      data: {
        labels: topItems.map(r => r.name),
        datasets: [{ label: 'Value (Cost)', data: topItems.map(r => parseFloat(r.value_cost||0)), backgroundColor: '#60a5fa', borderRadius: 6 }]
      },
      options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', scales: { x: { ticks: { callback: v => 'Tsh ' + Number(v).toLocaleString() } } }, plugins: { legend: { display:false } } }
    });
  }

  // ABC pie
  const abcEl = document.getElementById('abcChart');
  if (abcEl && abcCounts){
    const labels = ['A','B','C'];
    const data = labels.map(l => parseInt(abcCounts[l] || 0, 10));
    new Chart(abcEl, {
      type: 'doughnut',
      data: { labels, datasets: [{ data, backgroundColor: ['#ef4444','#f59e0b','#10b981'] }] },
      options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom' } } }
    });
  }

  // Top categories bar
  const catEl = document.getElementById('topCategoriesChart');
  if (catEl && topCategories && topCategories.length){
    new Chart(catEl, {
      type: 'bar',
      data: {
        labels: topCategories.map(r => r.name),
        datasets: [{ label: 'Value (Cost)', data: topCategories.map(r => parseFloat(r.value_cost||0)), backgroundColor: '#34d399', borderRadius: 6 }]
      },
      options: { responsive: true, maintainAspectRatio: false, scales: { y: { ticks: { callback: v => 'Tsh ' + Number(v).toLocaleString() } } }, plugins: { legend: { display:false } } }
    });
  }

  // Toggle handlers
  document.querySelectorAll('.chart-mode-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.chart-mode-btn').forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      buildTrend(btn.dataset.mode === 'retail' ? 'retail' : 'cost');
    });
  });
  document.querySelectorAll('.subshop-mode-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.subshop-mode-btn').forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      buildSubshop(btn.dataset.mode === 'retail' ? 'retail' : 'cost');
    });
  });

  // Saved Views handlers
  window.handleViewAction = function(action){
    const actField = document.getElementById('view_action_field');
    const nameField = document.getElementById('view_name_field');
    if(!actField || !nameField) return;
    actField.value = action;
    if(action === 'save'){
      const nm = (document.getElementById('save_view_name')?.value || '').trim();
      if(!nm) { alert('Please provide a name'); return; }
      nameField.value = nm;
    } else {
      const sel = document.getElementById('saved_view_select');
      const val = sel ? sel.value : '';
      if(!val){ alert('Choose a saved view first'); return; }
      nameField.value = val;
    }
    // submit the form
    const form = document.querySelector('form[action="{{ route('reports.inventory') }}"]');
    if(form) form.submit();
  };

  // Initialize Bootstrap tooltips for info icons
  try {
    if (window.$ && $.fn && $.fn.tooltip) {
      $('[data-toggle="tooltip"]').tooltip({ container: 'body' });
    }
  } catch (e) { /* noop */ }
})();
</script>
@stop