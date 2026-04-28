@extends('adminlte::page')

@section('title', 'Loan Performance Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-chart-line"></i> Loan Performance Overview</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-chart-line"></i> Loan Performance</h1>
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
    <li class="breadcrumb-item active" aria-current="page">Loan Performance</li>
  </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">

  <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
    <div class="card-body">

      <form method="get" action="{{ route('reports.loan_performance.index') }}" class="mb-3">
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
              <a href="{{ route('reports.loan_performance.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
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
        $tr = $report['trends']['chart'] ?? [];
      @endphp

      <div class="row mb-3">
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-success">
            <div class="card-body">
              <div class="text-uppercase small">Expected Repayments</div>
              <div class="h4 mb-0">{{ number_format($s['total_expected'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-info">
            <div class="card-body">
              <div class="text-uppercase small">Collected</div>
              <div class="h4 mb-0">{{ number_format($s['total_collected'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-primary">
            <div class="card-body">
              <div class="text-uppercase small">Collection Rate</div>
              <div class="h4 mb-0">{{ number_format($s['collection_rate_pct'] ?? 0, 2) }}%</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-warning">
            <div class="card-body">
              <div class="text-uppercase small">Default Rate</div>
              <div class="h4 mb-0">{{ number_format($s['default_rate_pct'] ?? 0, 2) }}%</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Repayment Trend (Expected vs Collected)</strong></div>
            <div class="card-body">
              <canvas id="repaymentTrend" height="90"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>On-Time vs Late vs Missed</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Category</th>
                      <th class="text-right">Count</th>
                      <th class="text-right">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>On-Time</td>
                      <td class="text-right">{{ number_format($report['on_time_late']['on_time']['count'] ?? 0) }}</td>
                      <td class="text-right">{{ number_format($report['on_time_late']['on_time']['amount'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                      <td>Late</td>
                      <td class="text-right">{{ number_format($report['on_time_late']['late']['count'] ?? 0) }}</td>
                      <td class="text-right">{{ number_format($report['on_time_late']['late']['amount'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                      <td>Missed</td>
                      <td class="text-right">{{ number_format($report['on_time_late']['missed']['count'] ?? 0) }}</td>
                      <td class="text-right">{{ number_format($report['on_time_late']['missed']['amount'] ?? 0, 2) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Collection Efficiency</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <tbody>
                    <tr><td>Expected</td><td class="text-right">{{ number_format($report['collection_efficiency']['expected'] ?? 0, 2) }}</td></tr>
                    <tr><td>Collected</td><td class="text-right">{{ number_format($report['collection_efficiency']['collected'] ?? 0, 2) }}</td></tr>
                    <tr><td>Efficiency</td><td class="text-right">{{ number_format($report['collection_efficiency']['efficiency_pct'] ?? 0, 2) }}%</td></tr>
                    <tr><td>Missed Payments</td><td class="text-right">{{ number_format($report['collection_efficiency']['missed_payments_count'] ?? 0) }} ({{ number_format($report['collection_efficiency']['missed_payments_amount'] ?? 0, 2) }})</td></tr>
                    <tr><td>Avg Days Late</td><td class="text-right">{{ number_format($s['avg_days_late'] ?? 0, 2) }}</td></tr>
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
            <div class="card-header"><strong>Performance by Loan Product</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Collected</th>
                      <th class="text-right">Efficiency</th>
                      <th class="text-right">PAR30</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_product'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['product_name'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['total_loans'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['collected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['efficiency_pct'] ?? 0, 2) }}%</td>
                        <td class="text-right">{{ number_format($row['par30'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['by_product'] ?? []))
                      <tr><td colspan="5" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Performance by Officer</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Officer</th>
                      <th class="text-right">Loans Managed</th>
                      <th class="text-right">Collected</th>
                      <th class="text-right">Efficiency</th>
                      <th class="text-right">PAR30</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_officer'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['officer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['loans_managed'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['collected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['efficiency_pct'] ?? 0, 2) }}%</td>
                        <td class="text-right">{{ number_format($row['par30'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['by_officer'] ?? []))
                      <tr><td colspan="5" class="text-center text-muted p-3">No data</td></tr>
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
            <div class="card-header"><strong>Delinquency & Defaults</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <tbody>
                    <tr><td>Overdue Loans</td><td class="text-right">{{ number_format($report['delinquency']['overdue_loans'] ?? 0) }}</td></tr>
                    <tr><td>Overdue Amount</td><td class="text-right">{{ number_format($report['delinquency']['overdue_amount'] ?? 0, 2) }}</td></tr>
                    <tr><td>Loans &gt; 90 Days</td><td class="text-right">{{ number_format($report['delinquency']['loans_over_90_days'] ?? 0) }}</td></tr>
                    <tr><td>Default Rate</td><td class="text-right">{{ number_format($report['delinquency']['default_rate_pct'] ?? 0, 2) }}%</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Write-Off & Recovery</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <tbody>
                    <tr><td>Loans Written Off</td><td class="text-right">{{ number_format($report['write_off']['written_off_loans'] ?? 0) }}</td></tr>
                    <tr><td>Amount Written Off</td><td class="text-right">{{ number_format($report['write_off']['amount_written_off'] ?? 0, 2) }}</td></tr>
                    <tr><td>Recoveries After Write-Off</td><td class="text-right">{{ number_format($report['write_off']['recoveries_after_writeoff'] ?? 0, 2) }}</td></tr>
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
            <div class="card-header"><strong>Top Performing Loans</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Loan</th>
                      <th>Customer</th>
                      <th>Product</th>
                      <th class="text-right">Efficiency</th>
                      <th class="text-right">Late %</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['top_worst_loans']['top'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['loan_code'] ?? '' }}</td>
                        <td>{{ $row['customer'] ?? '' }}</td>
                        <td>{{ $row['product'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['efficiency_pct'] ?? 0, 2) }}%</td>
                        <td class="text-right">{{ number_format($row['late_pct_of_collected'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['top_worst_loans']['top'] ?? []))
                      <tr><td colspan="5" class="text-center text-muted p-3">No data</td></tr>
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
            <div class="card-header"><strong>Worst Performing Loans</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Loan</th>
                      <th>Customer</th>
                      <th>Product</th>
                      <th class="text-right">Outstanding</th>
                      <th class="text-right">Efficiency</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['top_worst_loans']['worst'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['loan_code'] ?? '' }}</td>
                        <td>{{ $row['customer'] ?? '' }}</td>
                        <td>{{ $row['product'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['outstanding_in_period'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['efficiency_pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['top_worst_loans']['worst'] ?? []))
                      <tr><td colspan="5" class="text-center text-muted p-3">No data</td></tr>
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
@stop


@push('css')
  <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const labels = @json($tr['labels'] ?? []);
  const expected = @json($tr['expected'] ?? []);
  const collected = @json($tr['collected'] ?? []);

  const ctx = document.getElementById('repaymentTrend');
  if(!ctx) return;

  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Expected',
          data: expected,
          borderColor: 'rgba(54,162,235,1)',
          backgroundColor: 'rgba(54,162,235,0.08)',
          tension: 0.3,
          fill: true
        },
        {
          label: 'Collected',
          data: collected,
          borderColor: 'rgba(75,192,192,1)',
          backgroundColor: 'rgba(75,192,192,0.08)',
          tension: 0.3,
          fill: true
        }
      ]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom' }
      },
      scales: {
        y: { beginAtZero: true }
      }
    }
  });
})();
</script>
@stop
