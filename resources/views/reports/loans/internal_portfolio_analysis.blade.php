@extends('adminlte::page')

@section('title', 'Internal Portfolio Analysis')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-chart-line"></i> Internal Portfolio Analysis</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-chart-line"></i> Portfolio Analysis</h1>
      <div class="small text-light-50">Period: {{ $dateFrom ?? '' }} to {{ $dateTo ?? '' }}</div>
    </div>
    <a href="{{ url()->previous() }}" class="btn btn-light">
      <i class="fas fa-arrow-left"></i> Back
    </a>
  </div>
</div>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.loan_reports.index') }}"><i class="fas fa-university"></i> Loan Reports</a></li>
    <li class="breadcrumb-item active" aria-current="page">Internal Portfolio Analysis</li>
  </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
  <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
    <div class="card-body">

      <form method="get" action="{{ route('reports.internal_portfolio_analysis.index') }}" class="mb-3">
        <div class="bg-light p-2 rounded border">
          <div class="form-row">
            <div class="form-group col-md-3">
              <label for="date_from">Date From</label>
              <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="{{ $dateFrom ?? '' }}">
            </div>
            <div class="form-group col-md-3">
              <label for="date_to">Date To</label>
              <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="{{ $dateTo ?? '' }}">
            </div>
            <div class="form-group col-md-3">
              <label for="subshop_id">Branch</label>
              <select class="form-control form-control-sm" id="subshop_id" name="subshop_id">
                <option value="">All Accessible</option>
                @foreach(($subshops ?? []) as $s)
                  <option value="{{ $s->id }}" {{ ($selectedSubshopId ?? null) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-3">
              <label for="loan_product_id">Loan Product</label>
              <select class="form-control form-control-sm" id="loan_product_id" name="loan_product_id">
                <option value="">All Products</option>
                @foreach(($loanProducts ?? []) as $p)
                  @php
                    $pid = is_object($p) ? ($p->id ?? null) : ($p['id'] ?? null);
                    $pname = is_object($p) ? ($p->name ?? '') : ($p['name'] ?? '');
                  @endphp
                  <option value="{{ $pid }}" {{ request('loan_product_id') == $pid ? 'selected' : '' }}>{{ $pname }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-4">
              <label for="loan_officer_id">Loan Officer (Disbursement Processor)</label>
              <select class="form-control form-control-sm" id="loan_officer_id" name="loan_officer_id">
                <option value="">All Officers</option>
                @foreach(($officers ?? []) as $o)
                  @php
                    $oid = is_object($o) ? ($o->id ?? null) : ($o['id'] ?? null);
                    $oname = is_object($o) ? ($o->name ?? '') : ($o['name'] ?? '');
                  @endphp
                  <option value="{{ $oid }}" {{ request('loan_officer_id') == $oid ? 'selected' : '' }}>{{ $oname }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-4">
              <label for="customer_segment">Customer Segment</label>
              <select class="form-control form-control-sm" id="customer_segment" name="customer_segment">
                <option value="">All Segments</option>
                @foreach(['New Borrowers','Repeat Borrowers','High-Risk Borrowers','VIP'] as $seg)
                  <option value="{{ $seg }}" {{ request('customer_segment') === $seg ? 'selected' : '' }}>{{ $seg }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-4">
              <label for="loan_status">Loan Status</label>
              <select class="form-control form-control-sm" id="loan_status" name="loan_status">
                <option value="">All Statuses</option>
                @foreach(['pending','approved','rejected','disbursed','partially_paid','paid_off','defaulted','written_off'] as $st)
                  <option value="{{ $st }}" {{ request('loan_status') == $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ', $st)) }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-2 d-flex align-items-end">
              <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply</button>
              <a href="{{ route('reports.internal_portfolio_analysis.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
            </div>
          </div>
        </div>
      </form>

      <div class="row m-3">
        <div class="col-12 text-center">
          <div class="btn-group" role="group">
            <a href="{{ $exportUrl ?? '#' }}" class="btn btn-success"><i class="fas fa-file-excel"></i> Export to Excel</a>
            <a href="{{ $pdfUrl ?? '#' }}" class="btn btn-danger"><i class="fas fa-file-pdf"></i> Export to PDF</a>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
          </div>
        </div>
      </div>

      @php
        $s = $report['summary'] ?? [];
        $charts = $report['charts'] ?? [];
      @endphp

      <div class="row mb-3">
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-success">
            <div class="card-body">
              <div class="text-uppercase small">Portfolio Outstanding</div>
              <div class="h4 mb-0">{{ number_format($s['portfolio_outstanding'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-info">
            <div class="card-body">
              <div class="text-uppercase small">Health Score</div>
              <div class="h4 mb-0">{{ number_format($s['health_score_pct'] ?? 0, 2) }}%</div>
              <div class="small">{{ $s['health_category'] ?? '' }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-warning">
            <div class="card-body">
              <div class="text-uppercase small">Collection Efficiency</div>
              <div class="h4 mb-0">{{ number_format($s['collection_efficiency_pct'] ?? 0, 2) }}%</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-danger">
            <div class="card-body">
              <div class="text-uppercase small">PAR30</div>
              <div class="h4 mb-0">{{ number_format($s['par30_pct'] ?? 0, 2) }}%</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Portfolio Growth vs PAR30 Trend</strong></div>
            <div class="card-body">
              <canvas id="growthVsRisk" height="90"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Product Profitability</strong></div>
            <div class="card-body">
              <canvas id="profitByProduct" height="110"></canvas>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th class="text-right">Interest</th>
                      <th class="text-right">Fees</th>
                      <th class="text-right">Penalties</th>
                      <th class="text-right">Cost</th>
                      <th class="text-right">Revenue</th>
                      <th class="text-right">Profit</th>
                      <th class="text-right">PAR30</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['profitability_by_product'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['product'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['interest_earned'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['fees_collected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['penalties_collected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['estimated_cost'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['revenue'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['profit'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['par30_pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['profitability_by_product'] ?? []))
                      <tr><td colspan="8" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Officer Performance</strong></div>
            <div class="card-body">
              <canvas id="officerPerformance" height="110"></canvas>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Officer</th>
                      <th class="text-right">Score</th>
                      <th class="text-right">Total Portfolio</th>
                      <th class="text-right">Loans Disbursed</th>
                      <th class="text-right">PAR30</th>
                      <th class="text-right">Efficiency</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['officer_performance'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['officer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['score_pct'] ?? 0, 2) }}%</td>
                        <td class="text-right">{{ number_format($row['total_portfolio'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['loans_disbursed'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['par30_pct'] ?? 0, 2) }}%</td>
                        <td class="text-right">{{ number_format($row['collection_efficiency_pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['officer_performance'] ?? []))
                      <tr><td colspan="6" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Risk vs Return</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th class="text-right">Profit</th>
                      <th class="text-right">PAR30</th>
                      <th>Risk Level</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['risk_vs_return'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['product'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['profit'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['par30_pct'] ?? 0, 2) }}%</td>
                        <td>{{ $row['risk_level'] ?? '' }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['risk_vs_return'] ?? []))
                      <tr><td colspan="4" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Customer Segmentation</strong></div>
            <div class="card-body">
              <canvas id="customerSegments" height="120"></canvas>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Segment</th>
                      <th class="text-right">Customers</th>
                      <th class="text-right">Portfolio</th>
                      <th class="text-right">PAR30</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['customer_segmentation'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['segment'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['customers'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['portfolio'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['par30_pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['customer_segmentation'] ?? []))
                      <tr><td colspan="4" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Early Warning Indicators</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <tbody>
                    <tr>
                      <td>Increasing PAR Trend</td>
                      <td class="text-right">{{ !empty(($report['early_warning']['flags']['increasing_par_trend'] ?? false)) ? 'YES' : 'NO' }}</td>
                    </tr>
                    <tr>
                      <td>Rising Average DPD</td>
                      <td class="text-right">{{ !empty(($report['early_warning']['flags']['rising_avg_dpd'] ?? false)) ? 'YES' : 'NO' }}</td>
                    </tr>
                    <tr>
                      <td>Declining Collection Efficiency</td>
                      <td class="text-right">{{ !empty(($report['early_warning']['flags']['declining_collection_efficiency'] ?? false)) ? 'YES' : 'NO' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="card-footer">
              @foreach(($report['strategic_insights'] ?? []) as $ins)
                <div class="small">{{ $ins }}</div>
              @endforeach
              @if(empty($report['strategic_insights'] ?? []))
                <div class="small text-muted">No insights</div>
              @endif
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Loan Cycle Analysis</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Cycle</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Avg Loan Size</th>
                      <th class="text-right">PAR30</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['loan_cycle_analysis'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['cycle'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['loans'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['avg_loan_size'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['par30_pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['loan_cycle_analysis'] ?? []))
                      <tr><td colspan="4" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Income vs Portfolio (Yield)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <tbody>
                    <tr>
                      <td>Interest Income</td>
                      <td class="text-right">{{ number_format(($report['income_vs_portfolio']['interest_income'] ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                      <td>Average Portfolio</td>
                      <td class="text-right">{{ number_format(($report['income_vs_portfolio']['avg_portfolio'] ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                      <td>Yield (%)</td>
                      <td class="text-right">{{ number_format(($report['income_vs_portfolio']['yield_pct'] ?? 0), 2) }}%</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Cohort Analysis (Disbursement Month)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Month</th>
                      <th class="text-right">Loans Disbursed</th>
                      <th class="text-right">Portfolio Outstanding</th>
                      <th class="text-right">PAR30</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['cohort_analysis'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['cohort_month'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['loans_disbursed'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['portfolio_outstanding'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['par30_pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['cohort_analysis'] ?? []))
                      <tr><td colspan="4" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Behavioral Risk (Repeat Late Payers)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Customer</th>
                      <th class="text-right">Late Payments</th>
                      <th class="text-right">Avg Days Late</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['behavioral_risk']['repeat_late_payers'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['customer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['late_payments'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['avg_days_late'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['behavioral_risk']['repeat_late_payers'] ?? []))
                      <tr><td colspan="3" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Concentration Risk (Top Exposure)</strong></div>
            <div class="card-body">
              <div class="row">
                <div class="col-12 col-md-4">
                  <div class="small text-muted mb-1">Top Customers</div>
                  <ul class="pl-3 mb-0">
                    @foreach(($report['concentration_risk']['top_customers'] ?? []) as $r)
                      <li>{{ $r['label'] ?? '' }} - {{ number_format($r['pct'] ?? 0, 2) }}%</li>
                    @endforeach
                    @if(empty($report['concentration_risk']['top_customers'] ?? []))
                      <li class="text-muted">No data</li>
                    @endif
                  </ul>
                </div>
                <div class="col-12 col-md-4">
                  <div class="small text-muted mb-1">Top Branches</div>
                  <ul class="pl-3 mb-0">
                    @foreach(($report['concentration_risk']['top_branches'] ?? []) as $r)
                      <li>{{ $r['label'] ?? '' }} - {{ number_format($r['pct'] ?? 0, 2) }}%</li>
                    @endforeach
                    @if(empty($report['concentration_risk']['top_branches'] ?? []))
                      <li class="text-muted">No data</li>
                    @endif
                  </ul>
                </div>
                <div class="col-12 col-md-4">
                  <div class="small text-muted mb-1">Top Products</div>
                  <ul class="pl-3 mb-0">
                    @foreach(($report['concentration_risk']['top_products'] ?? []) as $r)
                      <li>{{ $r['label'] ?? '' }} - {{ number_format($r['pct'] ?? 0, 2) }}%</li>
                    @endforeach
                    @if(empty($report['concentration_risk']['top_products'] ?? []))
                      <li class="text-muted">No data</li>
                    @endif
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Cross-Analysis (Product + Branch + Officer)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th>Branch</th>
                      <th>Officer</th>
                      <th class="text-right">PAR30</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['cross_analysis'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['product'] ?? '' }}</td>
                        <td>{{ $row['branch'] ?? '' }}</td>
                        <td>{{ $row['officer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['par30_pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['cross_analysis'] ?? []))
                      <tr><td colspan="4" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (function() {
    const charts = @json($report['charts'] ?? []);

    const growthCtx = document.getElementById('growthVsRisk');
    if (growthCtx && charts.growth_vs_risk) {
      new Chart(growthCtx, {
        type: 'line',
        data: {
          labels: charts.growth_vs_risk.labels || [],
          datasets: [
            {
              label: 'Portfolio Outstanding',
              data: charts.growth_vs_risk.portfolio || [],
              borderColor: '#0dcaf0',
              backgroundColor: 'rgba(13,202,240,.08)',
              tension: 0.25,
              fill: true,
              yAxisID: 'y1',
            },
            {
              label: 'PAR30 (%)',
              data: charts.growth_vs_risk.par30 || [],
              borderColor: '#dc3545',
              backgroundColor: 'rgba(220,53,69,.10)',
              tension: 0.25,
              fill: false,
              yAxisID: 'y',
            }
          ]
        },
        options: {
          responsive: true,
          scales: {
            y: { beginAtZero: true, title: { display: true, text: 'PAR %' } },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Amount' } }
          }
        }
      });
    }

    const profitCtx = document.getElementById('profitByProduct');
    if (profitCtx && charts.profitability_by_product) {
      new Chart(profitCtx, {
        type: 'bar',
        data: {
          labels: charts.profitability_by_product.labels || [],
          datasets: [
            {
              label: 'Profit',
              data: charts.profitability_by_product.profit || [],
              backgroundColor: 'rgba(40,167,69,.45)'
            },
            {
              label: 'Revenue',
              data: charts.profitability_by_product.revenue || [],
              backgroundColor: 'rgba(0,123,255,.30)'
            }
          ]
        },
        options: { responsive: true, plugins: { legend: { display: true } } }
      });
    }

    const officerCtx = document.getElementById('officerPerformance');
    if (officerCtx && charts.officer_performance) {
      new Chart(officerCtx, {
        type: 'bar',
        data: {
          labels: charts.officer_performance.labels || [],
          datasets: [
            {
              label: 'Score %',
              data: charts.officer_performance.score || [],
              backgroundColor: 'rgba(111,66,193,.35)'
            }
          ]
        },
        options: { responsive: true, plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true } } }
      });
    }

    const segCtx = document.getElementById('customerSegments');
    if (segCtx && charts.customer_segments) {
      new Chart(segCtx, {
        type: 'doughnut',
        data: {
          labels: charts.customer_segments.labels || [],
          datasets: [
            {
              label: 'Portfolio',
              data: charts.customer_segments.portfolio || [],
              backgroundColor: ['#0dcaf0','#198754','#ffc107','#dc3545']
            }
          ]
        },
        options: { responsive: true }
      });
    }
  })();
</script>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
