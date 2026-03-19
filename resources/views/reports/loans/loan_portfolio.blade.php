@extends('adminlte::page')

@section('title', 'Loan Portfolio Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-chart-pie"></i> Loan Portfolio Overview</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-chart-pie"></i> Loan Portfolio</h1>
      <div class="small text-light-50">Period: {{ $dateFrom ?? '' }} to {{ $dateTo ?? '' }}</div>
    </div>
    <a href="{{ route('reports.loan_reports.index') }}" class="btn btn-light">
      <i class="fas fa-arrow-left"></i> Back
    </a>
  </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('reports.loan_reports.index') }}"><i class="fas fa-university"></i> Loan Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">Loan Portfolio</li>
    </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">

  <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
    <div class="card-body">
      <form method="get" action="{{ route('reports.loan_portfolio.index') }}" class="mb-3">
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
                  <option value="{{ $p->id }}" {{ (request('loan_product_id') == $p->id) ? 'selected' : '' }}>{{ $p->name }}</option>
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
                  <option value="{{ $o->id }}" {{ (request('loan_officer_id') == $o->id) ? 'selected' : '' }}>{{ $o->name }}</option>
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
            <div class="form-group col-md-4 d-flex align-items-end">
              <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply Filters</button>
              <a href="{{ route('reports.loan_portfolio.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
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

      <div class="row mb-3">
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-success">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-uppercase small">Total Outstanding</div>
                  <div class="h4 mb-0">Tsh {{ number_format($report['summary']['total_outstanding'] ?? 0, 2) }}</div>
                </div>
                <i class="fas fa-wallet fa-2x"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3 mb-3">
          <div class="card text-white bg-info">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-uppercase small">Active Loans</div>
                  <div class="h4 mb-0">{{ number_format($report['summary']['active_loans'] ?? 0) }}</div>
                </div>
                <i class="fas fa-file-invoice-dollar fa-2x"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3 mb-3">
          <div class="card text-white bg-warning">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-uppercase small">Active Borrowers</div>
                  <div class="h4 mb-0">{{ number_format($report['summary']['active_borrowers'] ?? 0) }}</div>
                </div>
                <i class="fas fa-users fa-2x"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3 mb-3">
          <div class="card text-white bg-primary">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-uppercase small">Disbursed (Period)</div>
                  <div class="h4 mb-0">Tsh {{ number_format($report['summary']['total_disbursed_period'] ?? 0, 2) }}</div>
                </div>
                <i class="fas fa-arrow-up fa-2x"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3 mb-3">
          <div class="card text-white bg-secondary">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-uppercase small">Repayments (Period)</div>
                  <div class="h4 mb-0">Tsh {{ number_format($report['summary']['total_repayments_period'] ?? 0, 2) }}</div>
                </div>
                <i class="fas fa-arrow-down fa-2x"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3 mb-3">
          <div class="card bg-light">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-uppercase small">Average Loan Size</div>
                  <div class="h4 mb-0">Tsh {{ number_format($report['summary']['avg_loan_size'] ?? 0, 2) }}</div>
                </div>
                <i class="fas fa-calculator fa-2x text-muted"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Portfolio by Loan Product</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Outstanding</th>
                      <th class="text-right">%</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['composition']['by_product'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['product_name'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['loans_count'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['outstanding'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['composition']['by_product'] ?? []))
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
            <div class="card-header"><strong>Portfolio at Risk (PAR)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Bucket</th>
                      <th class="text-right">Outstanding</th>
                      <th class="text-right">%</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['par'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['bucket'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['outstanding'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['par'] ?? []))
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
            <div class="card-header"><strong>Portfolio by Branch</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Branch</th>
                      <th class="text-right">Active Loans</th>
                      <th class="text-right">Outstanding</th>
                      <th class="text-right">PAR30</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['composition']['by_branch'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['branch'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['active_loans'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['outstanding'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['par30'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['composition']['by_branch'] ?? []))
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
            <div class="card-header"><strong>Portfolio Aging</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Aging Bucket</th>
                      <th class="text-right">Outstanding</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['aging'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['bucket'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['outstanding'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['aging'] ?? []))
                      <tr><td colspan="2" class="text-center text-muted p-3">No data</td></tr>
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
            <div class="card-header"><strong>Portfolio by Loan Officer (Disbursement Processor)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Officer</th>
                      <th class="text-right">Loans Managed</th>
                      <th class="text-right">Outstanding</th>
                      <th class="text-right">Repayments Collected (Period)</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['composition']['by_officer'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['officer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['loans_managed'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['outstanding'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['repayments_collected'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['composition']['by_officer'] ?? []))
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
            <div class="card-header"><strong>Disbursement Analysis</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Month</th>
                      <th class="text-right">Loans Disbursed</th>
                      <th class="text-right">Amount</th>
                      <th class="text-right">Avg Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['disbursement_analysis'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['month'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['loans_disbursed'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['amount'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['avg_amount'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['disbursement_analysis'] ?? []))
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
            <div class="card-header"><strong>Repayment Performance</strong></div>
            <div class="card-body">
              <div class="row">
                <div class="col-12 col-md-4">
                  <div class="border rounded p-2 bg-light">
                    <div class="text-muted small">Expected</div>
                    <div><strong>{{ number_format($report['repayment_performance']['expected'] ?? 0, 2) }}</strong></div>
                  </div>
                </div>
                <div class="col-12 col-md-4">
                  <div class="border rounded p-2 bg-light">
                    <div class="text-muted small">Collected</div>
                    <div><strong>{{ number_format($report['repayment_performance']['collected'] ?? 0, 2) }}</strong></div>
                  </div>
                </div>
                <div class="col-12 col-md-4">
                  <div class="border rounded p-2 bg-light">
                    <div class="text-muted small">Efficiency</div>
                    <div><strong>{{ number_format($report['repayment_performance']['efficiency_pct'] ?? 0, 2) }}%</strong></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Top Borrowers by Outstanding</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Customer</th>
                      <th class="text-right">Loan Count</th>
                      <th class="text-right">Outstanding</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['top_borrowers'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['customer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['loan_count'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['outstanding'] ?? 0, 2) }}</td>
                      </tr>
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

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Portfolio Trends</strong></div>
            <div class="card-body">
              <div class="row">
                <div class="col-12">
                  <canvas id="chartOutstanding" height="110"></canvas>
                </div>
                <div class="col-12 mt-3">
                  <canvas id="chartFlows" height="110"></canvas>
                </div>
                <div class="col-12 mt-3">
                  <canvas id="chartPar30" height="110"></canvas>
                </div>
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
    const trend = @json($report['trends'] ?? []);
    const labels = trend.labels || [];

    const ctx1 = document.getElementById('chartOutstanding');
    if (ctx1) {
      new Chart(ctx1, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'Portfolio Outstanding',
            data: trend.portfolio_outstanding || [],
            borderColor: '#28a745',
            backgroundColor: 'rgba(40,167,69,.15)',
            tension: 0.25,
            fill: true,
          }]
        },
        options: { responsive: true, plugins: { legend: { display: true } } }
      });
    }

    const ctx2 = document.getElementById('chartFlows');
    if (ctx2) {
      new Chart(ctx2, {
        type: 'bar',
        data: {
          labels,
          datasets: [
            {
              label: 'Disbursements',
              data: trend.disbursements || [],
              backgroundColor: 'rgba(0,123,255,.6)',
            },
            {
              label: 'Repayments',
              data: trend.repayments || [],
              backgroundColor: 'rgba(108,117,125,.6)',
            }
          ]
        },
        options: { responsive: true, plugins: { legend: { display: true } }, scales: { x: { stacked: false }, y: { beginAtZero: true } } }
      });
    }

    const ctx3 = document.getElementById('chartPar30');
    if (ctx3) {
      new Chart(ctx3, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'PAR30 Trend',
            data: trend.par30 || [],
            borderColor: '#dc3545',
            backgroundColor: 'rgba(220,53,69,.12)',
            tension: 0.25,
            fill: true,
          }]
        },
        options: { responsive: true, plugins: { legend: { display: true } } }
      });
    }
  })();
</script>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
