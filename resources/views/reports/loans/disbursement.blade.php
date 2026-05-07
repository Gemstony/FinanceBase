@extends('adminlte::page')

@section('title', 'Loan Disbursement Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-hand-holding-usd"></i> Loan Disbursement Report</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-hand-holding-usd"></i> Disbursement</h1>
      <div class="small text-light-50">Period: {{ $dateFrom ?? '' }} to {{ $dateTo ?? '' }}</div>
    </div>
    <a href="{{ route('reports.loan_reports.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.loan_reports.index') }}"><i class="fas fa-university"></i> Loan Reports</a></li>
    <li class="breadcrumb-item active" aria-current="page">Loan Disbursement</li>
  </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
  <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
    <div class="card-body">

      <form method="get" action="{{ route('reports.loan_disbursement.index') }}" class="mb-3">
        <div class="bg-light p-2 rounded border">
          <div class="form-row">
            <div class="form-group col-md-3">
              <label>Date From</label>
              <input type="date" class="form-control form-control-sm" name="date_from" value="{{ $dateFrom ?? '' }}">
            </div>
            <div class="form-group col-md-3">
              <label>Date To</label>
              <input type="date" class="form-control form-control-sm" name="date_to" value="{{ $dateTo ?? '' }}">
            </div>
            <div class="form-group col-md-3">
              <label>Branch</label>
              <select class="form-control form-control-sm" name="subshop_id">
                <option value="">All Accessible</option>
                @foreach(($subshops ?? []) as $s)
                  <option value="{{ $s->id ?? '' }}" {{ ($selectedSubshopId ?? null) == ($s->id ?? null) ? 'selected' : '' }}>{{ $s->name ?? '' }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-3">
              <label>Loan Product</label>
              <select class="form-control form-control-sm" name="loan_product_id">
                <option value="">All Products</option>
                @foreach(($loanProducts ?? []) as $p)
                  <option value="{{ $p->id ?? '' }}" {{ request('loan_product_id') == ($p->id ?? null) ? 'selected' : '' }}>{{ $p->name ?? '' }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-3">
              <label>Loan Officer</label>
              <select class="form-control form-control-sm" name="loan_officer_id">
                <option value="">All Officers</option>
                @foreach(($officers ?? []) as $o)
                  <option value="{{ $o->id ?? '' }}" {{ request('loan_officer_id') == ($o->id ?? null) ? 'selected' : '' }}>{{ $o->name ?? '' }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-3">
              <label>Loan Status</label>
              <select class="form-control form-control-sm" name="loan_status">
                <option value="">All Statuses</option>
                @foreach(['pending','approved','rejected','disbursed','partially_paid','paid_off','defaulted','written_off'] as $st)
                  <option value="{{ $st }}" {{ request('loan_status') == $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ', $st)) }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-4">
              <label>Disbursement Method</label>
              <select class="form-control form-control-sm" name="disbursement_method_id">
                <option value="">All Methods</option>
                @foreach(($methods ?? []) as $m)
                  <option value="{{ $m->id ?? '' }}" {{ request('disbursement_method_id') == ($m->id ?? null) ? 'selected' : '' }}>{{ $m->name ?? '' }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-2">
              <label>Per Page</label>
              <select class="form-control form-control-sm" name="per_page">
                @foreach([10,25,50,100,200] as $pp)
                  <option value="{{ $pp }}" {{ (int)request('per_page',25) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply Filters</button>
          <a href="{{ route('reports.loan_disbursement.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
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
        $t = $report['trends']['chart'] ?? [];
        $nvr = $report['new_vs_repeat'] ?? [];
        $sa = $report['status_analysis'] ?? [];
        $dvr = $report['disbursement_vs_repayment'] ?? [];
        $eff = $report['efficiency'] ?? [];
      @endphp

      <div class="row mb-3">
        <div class="col-md-3 mb-3"><div class="card text-white bg-success"><div class="card-body"><div class="text-uppercase small">Loans Disbursed</div><div class="h4 mb-0">{{ number_format($s['total_loans_disbursed'] ?? 0) }}</div></div></div></div>
        <div class="col-md-3 mb-3"><div class="card text-white bg-primary"><div class="card-body"><div class="text-uppercase small">Total Amount</div><div class="h4 mb-0">{{ number_format($s['total_disbursement_amount'] ?? 0, 2) }}</div></div></div></div>
        <div class="col-md-3 mb-3"><div class="card text-white bg-info"><div class="card-body"><div class="text-uppercase small">Average Loan Size</div><div class="h4 mb-0">{{ number_format($s['average_loan_size'] ?? 0, 2) }}</div></div></div></div>
        <div class="col-md-3 mb-3"><div class="card text-white bg-warning"><div class="card-body"><div class="text-uppercase small">Growth %</div><div class="h4 mb-0">{{ number_format($s['disbursement_growth_pct'] ?? 0, 2) }}%</div></div></div></div>
      </div>

      <div class="card">
        <div class="card-header"><strong>Disbursement Trends</strong></div>
        <div class="card-body"><canvas id="disbTrend" height="90"></canvas></div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Disbursement by Product</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Product</th><th class="text-right">Loans</th><th class="text-right">Amount</th><th class="text-right">Avg</th></tr></thead>
                  <tbody>
                    @foreach(($report['by_product'] ?? []) as $r)
                      <tr>
                        <td><a href="{{ route('reports.loan_disbursement.index', array_merge(request()->query(), ['dd_product_id' => $r['product_id'] ?? null])) }}">{{ $r['product'] ?? '' }}</a></td>
                        <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['amount'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['avg_loan_size'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['by_product'] ?? []))
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
            <div class="card-header"><strong>Disbursement by Branch</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Branch</th><th class="text-right">Loans</th><th class="text-right">Amount</th><th class="text-right">Avg</th></tr></thead>
                  <tbody>
                    @foreach(($report['by_branch'] ?? []) as $r)
                      <tr>
                        <td><a href="{{ route('reports.loan_disbursement.index', array_merge(request()->query(), ['dd_branch_id' => $r['branch_id'] ?? null])) }}">{{ $r['branch'] ?? '' }}</a></td>
                        <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['amount'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['avg_loan_size'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['by_branch'] ?? []))
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
            <div class="card-header"><strong>Disbursement by Officer</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Officer</th><th class="text-right">Loans</th><th class="text-right">Amount</th><th class="text-right">Avg</th></tr></thead>
                  <tbody>
                    @foreach(($report['by_officer'] ?? []) as $r)
                      <tr>
                        <td><a href="{{ route('reports.loan_disbursement.index', array_merge(request()->query(), ['dd_officer_id' => $r['officer_id'] ?? null])) }}">{{ $r['officer'] ?? '' }}</a></td>
                        <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['amount'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['avg_loan_size'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['by_officer'] ?? []))
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
            <div class="card-header"><strong>New vs Repeat Borrowers</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <tbody>
                    <tr><td>New Borrowers</td><td class="text-right">{{ number_format($nvr['new']['count'] ?? 0) }}</td><td class="text-right">{{ number_format($nvr['new']['amount'] ?? 0, 2) }}</td></tr>
                    <tr><td>Repeat Borrowers</td><td class="text-right">{{ number_format($nvr['repeat']['count'] ?? 0) }}</td><td class="text-right">{{ number_format($nvr['repeat']['amount'] ?? 0, 2) }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Loan Size Distribution</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Bucket</th><th class="text-right">Loans</th><th class="text-right">Amount</th></tr></thead>
                  <tbody>
                    @foreach(($report['loan_size_distribution'] ?? []) as $r)
                      <tr><td>{{ $r['bucket'] ?? '' }}</td><td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td><td class="text-right">{{ number_format($r['amount'] ?? 0, 2) }}</td></tr>
                    @endforeach
                    @if(empty($report['loan_size_distribution'] ?? []))
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
            <div class="card-header"><strong>Status Analysis</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Status</th><th class="text-right">Count</th><th class="text-right">Amount</th></tr></thead>
                  <tbody>
                    <tr><td>Approved Not Disbursed</td><td class="text-right">{{ number_format($sa['approved_not_disbursed']['count'] ?? 0) }}</td><td class="text-right">{{ number_format($sa['approved_not_disbursed']['amount'] ?? 0, 2) }}</td></tr>
                    <tr><td>Disbursed</td><td class="text-right">{{ number_format($sa['disbursed']['count'] ?? 0) }}</td><td class="text-right">{{ number_format($sa['disbursed']['amount'] ?? 0, 2) }}</td></tr>
                    <tr><td>Cancelled</td><td class="text-right">{{ number_format($sa['cancelled']['count'] ?? 0) }}</td><td class="text-right">{{ number_format($sa['cancelled']['amount'] ?? 0, 2) }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Method Analysis</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Method</th><th class="text-right">Loans</th><th class="text-right">Amount</th></tr></thead>
                  <tbody>
                    @foreach(($report['method_analysis'] ?? []) as $r)
                      <tr><td>{{ $r['method'] ?? '' }}</td><td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td><td class="text-right">{{ number_format($r['amount'] ?? 0, 2) }}</td></tr>
                    @endforeach
                    @if(empty($report['method_analysis'] ?? []))
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
            <div class="card-header"><strong>Disbursement vs Repayment</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <tbody>
                    <tr><td>Total Disbursed</td><td class="text-right">{{ number_format($dvr['total_disbursed'] ?? 0, 2) }}</td></tr>
                    <tr><td>Total Repaid</td><td class="text-right">{{ number_format($dvr['total_repaid'] ?? 0, 2) }}</td></tr>
                    <tr><td><strong>Net Portfolio Growth</strong></td><td class="text-right"><strong>{{ number_format($dvr['net_portfolio_growth'] ?? 0, 2) }}</strong></td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Efficiency Metrics</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <tbody>
                    <tr><td>Average Time to Disburse (days)</td><td class="text-right">{{ number_format($eff['avg_time_to_disburse_days'] ?? 0, 2) }}</td></tr>
                    <tr><td>Approval Conversion Rate</td><td class="text-right">{{ number_format($eff['approval_conversion_rate_pct'] ?? 0, 2) }}%</td></tr>
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
            <div class="card-header"><strong>Top Borrowers (Top 10)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Customer</th><th class="text-right">Loans</th><th class="text-right">Total Disbursed</th></tr></thead>
                  <tbody>
                    @foreach(($report['top_borrowers'] ?? []) as $r)
                      <tr><td>{{ $r['customer'] ?? '' }}</td><td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td><td class="text-right">{{ number_format($r['amount'] ?? 0, 2) }}</td></tr>
                    @endforeach
                    @if(empty($report['top_borrowers'] ?? []))
                      <tr><td colspan="3" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><strong>Detailed Disbursement List</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead>
                <tr>
                  <th>Loan</th>
                  <th>Customer</th>
                  <th>Product</th>
                  <th>Branch</th>
                  <th>Officer</th>
                  <th>Disbursement Date</th>
                  <th class="text-right">Amount</th>
                  <th>Method</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @foreach(($report['detailed_list'] ?? []) as $row)
                  <tr>
                    <td>{{ $row->loan_code ?? '' }}</td>
                    <td>{{ $row->customer ?? '' }}</td>
                    <td>{{ $row->product ?? '' }}</td>
                    <td>{{ $row->branch ?? '' }}</td>
                    <td>{{ $row->officer ?? '' }}</td>
                    <td>{{ $row->disbursement_date ?? '' }}</td>
                    <td class="text-right">{{ number_format($row->amount ?? 0, 2) }}</td>
                    <td>{{ $row->disbursement_method ?? '' }}</td>
                    <td>{{ $row->loan_status ?? '' }}</td>
                  </tr>
                @endforeach
                @if(empty(($report['detailed_list'] ?? null)) || (method_exists(($report['detailed_list'] ?? null), 'total') && ($report['detailed_list']->total() === 0)))
                  <tr><td colspan="9" class="text-center text-muted p-3">No data</td></tr>
                @endif
              </tbody>
            </table>
          </div>
          <div class="p-2">
            @if(method_exists(($report['detailed_list'] ?? null), 'links'))
              {{ $report['detailed_list']->appends(request()->query())->links() }}
            @endif
          </div>
        </div>
      </div>

      {{-- Report Documentation / Calculation Methodology --}}
      <div class="row mt-4">
        <div class="col-12">
          <div class="card border-info">
            <div class="card-header bg-info text-white" data-toggle="collapse" data-target="#calculationDocs" style="cursor: pointer;">
              <div class="d-flex justify-content-between align-items-center">
                <span><i class="fas fa-info-circle"></i> How are these calculations performed?</span>
                <i class="fas fa-chevron-down"></i>
              </div>
            </div>
            <div id="calculationDocs" class="collapse">
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6">
                    <h6 class="font-weight-bold text-primary">Summary KPIs</h6>
                    <ul class="small">
                      <li><strong>Loans Disbursed:</strong> Count of distinct loans with disbursements in the period. Top-up loans (multiple disbursements on same loan) are de-duplicated for the count but all disbursement amounts are summed.</li>
                      <li><strong>Total Disbursement Amount:</strong> Sum of all disbursement amounts in the period, including top-ups.</li>
                      <li><strong>Average Loan Size:</strong> Total Amount / Loans Disbursed.</li>
                      <li><strong>Disbursement Growth %:</strong> Compares current period disbursements vs same-length previous period. Formula: ((Current - Previous) / Previous) × 100.</li>
                    </ul>

                    <h6 class="font-weight-bold text-primary mt-3">New vs Repeat Borrowers</h6>
                    <ul class="small">
                      <li><strong>New Borrowers:</strong> Customers whose FIRST-EVER disbursement occurred in this period (based on customer’s entire history, not filtered scope).</li>
                      <li><strong>Repeat Borrowers:</strong> Customers who had previous disbursements before this period.</li>
                      <li><strong>Note:</strong> A customer with multiple disbursements in the period is still counted once in their respective category.</li>
                    </ul>
                  </div>
                  <div class="col-md-6">
                    <h6 class="font-weight-bold text-primary">Disbursement vs Repayment</h6>
                    <ul class="small">
                      <li><strong>Total Disbursed:</strong> All disbursements in the period matching filters (product, officer, branch, method).</li>
                      <li><strong>Total Repaid:</strong> All payments (principal + interest + fees) received in the period for loans matching the filter criteria, regardless of when those loans were disbursed. This includes repayments on pre-existing loans.</li>
                      <li><strong>Net Portfolio Growth:</strong> Total Disbursed - Total Repaid. Positive = net growth, Negative = net contraction.</li>
                      <li><strong>Top-up handling:</strong> Loans with top-ups are included in repayment calculations based on all their disbursement history matching the filters.</li>
                    </ul>

                    <h6 class="font-weight-bold text-primary mt-3">Efficiency Metrics</h6>
                    <ul class="small">
                      <li><strong>Avg Time to Disburse:</strong> Average days between loan approval and first disbursement for loans disbursed in the period.</li>
                      <li><strong>Approval Conversion Rate:</strong> Percentage of loans approved in the period that were subsequently disbursed. Formula: (Disbursed / Approved) × 100. Respects all filters including officer.</li>
                    </ul>

                    <h6 class="font-weight-bold text-primary mt-3">Breakdowns (Product/Branch/Officer)</h6>
                    <ul class="small">
                      <li>All breakdowns respect the applied filters (date range, product, officer, status, method).</li>
                      <li>Officer is determined by who processed the disbursement (processed_by field).</li>
                      <li>Drill-down links apply additional filters while preserving existing ones.</li>
                    </ul>
                  </div>
                </div>
                <div class="alert alert-light border mt-3 mb-0 small">
                  <strong>Note:</strong> This report focuses on disbursement origination flow. Unlike portfolio quality reports, it does not use LoanDelinquencyEngine or loan_installments for calculations. All amounts are based on disbursement and payment transactions within the selected period.
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
@stop

@push('css')
  <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const labels = @json($t['labels'] ?? []);
  const loans = @json($t['loans'] ?? []);
  const amount = @json($t['amount'] ?? []);

  const ctx = document.getElementById('disbTrend');
  if(!ctx) return;

  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: 'Loans', data: loans, borderColor: 'rgba(54,162,235,1)', tension: 0.3 },
        { label: 'Amount', data: amount, borderColor: 'rgba(34,197,94,1)', tension: 0.3 }
      ]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
  });
})();
</script>
@stop
