@extends('adminlte::page')

@section('title', 'Delinquency Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-exclamation-triangle"></i> Delinquency Report</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-exclamation-triangle"></i> Delinquency</h1>
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
    <li class="breadcrumb-item active" aria-current="page">Delinquency</li>
  </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
  <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
    <div class="card-body">

      <form method="get" action="{{ route('reports.delinquency.index') }}" class="mb-3">
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
            <div class="form-group col-md-2">
              <label for="dpd_min">DPD Min</label>
              <input type="number" class="form-control form-control-sm" id="dpd_min" name="dpd_min" value="{{ request('dpd_min') }}" min="0" step="1">
            </div>
            <div class="form-group col-md-2">
              <label for="dpd_max">DPD Max</label>
              <input type="number" class="form-control form-control-sm" id="dpd_max" name="dpd_max" value="{{ request('dpd_max') }}" min="0" step="1">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-4">
              <label for="per_page">Per Page</label>
              <select class="form-control form-control-sm" id="per_page" name="per_page">
                @foreach([10,25,50,100,200] as $pp)
                  <option value="{{ $pp }}" {{ (int)request('per_page',25) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-8 d-flex align-items-end">
              <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply Filters</button>
              <a href="{{ route('reports.delinquency.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
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
        $t = $report['trends']['chart'] ?? [];
      @endphp

      <div class="row mb-3">
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-danger">
            <div class="card-body">
              <div class="text-uppercase small">Overdue Loans</div>
              <div class="h4 mb-0">{{ number_format($s['total_overdue_loans'] ?? 0) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-warning">
            <div class="card-body">
              <div class="text-uppercase small">Overdue Amount</div>
              <div class="h4 mb-0">{{ number_format($s['total_overdue_amount'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-primary">
            <div class="card-body">
              <div class="text-uppercase small">PAR30</div>
              <div class="h4 mb-0">{{ number_format($s['par30_pct'] ?? 0, 2) }}%</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-info">
            <div class="card-body">
              <div class="text-uppercase small">Average DPD</div>
              <div class="h4 mb-0">{{ number_format($s['avg_dpd'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Delinquency Trends</strong></div>
            <div class="card-body">
              <canvas id="parTrend" height="90"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Aging Analysis</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Bucket</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Outstanding</th>
                      <th class="text-right">%</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['aging'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['bucket'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['loans'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['outstanding'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['aging'] ?? []))
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
            <div class="card-header"><strong>Recovery Tracking</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <tbody>
                    <tr><td>Recovered Amount (Period)</td><td class="text-right">{{ number_format($report['recovery']['recovered_amount'] ?? 0, 2) }}</td></tr>
                    <tr><td>Recovery Rate</td><td class="text-right">{{ number_format($report['recovery']['recovery_rate_pct'] ?? 0, 2) }}%</td></tr>
                    <tr><td>PAR60</td><td class="text-right">{{ number_format($s['par60_pct'] ?? 0, 2) }}%</td></tr>
                    <tr><td>PAR90</td><td class="text-right">{{ number_format($s['par90_pct'] ?? 0, 2) }}%</td></tr>
                    <tr><td>NPL Loans (&gt; 90 DPD)</td><td class="text-right">{{ number_format($s['npl_loans'] ?? 0) }}</td></tr>
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
            <div class="card-header"><strong>Delinquent Loan List</strong></div>
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
                      <th class="text-right">Overdue</th>
                      <th class="text-right">DPD</th>
                      <th>Last Payment</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['delinquent_loans'] ?? []) as $row)
                      <tr>
                        <td>{{ $row->loan_code ?? '' }}</td>
                        <td>{{ $row->customer ?? '' }}</td>
                        <td>{{ $row->product ?? '' }}</td>
                        <td>{{ $row->branch ?? '' }}</td>
                        <td>{{ $row->officer ?? '' }}</td>
                        <td class="text-right">{{ number_format($row->overdue_amount ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row->dpd ?? 0) }}</td>
                        <td>{{ $row->last_payment_date ?? '' }}</td>
                        <td>{{ $row->loan_status ?? '' }}</td>
                      </tr>
                    @endforeach
                    @if(empty(($report['delinquent_loans'] ?? null)) || (method_exists(($report['delinquent_loans'] ?? null), 'total') && ($report['delinquent_loans']->total() === 0)))
                      <tr><td colspan="9" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
              <div class="p-2">
                @if(method_exists(($report['delinquent_loans'] ?? null), 'links'))
                  {{ $report['delinquent_loans']->appends(request()->query())->links() }}
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-header"><strong>Delinquency by Officer</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Officer</th>
                      <th class="text-right">Overdue</th>
                      <th class="text-right">PAR30</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_officer'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['officer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['overdue_amount'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['by_officer'] ?? []))
                      <tr><td colspan="3" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-header"><strong>Delinquency by Branch</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Branch</th>
                      <th class="text-right">Overdue</th>
                      <th class="text-right">PAR30</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_branch'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['branch'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['overdue_amount'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['by_branch'] ?? []))
                      <tr><td colspan="3" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-header"><strong>Delinquency by Product</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th class="text-right">Overdue</th>
                      <th class="text-right">PAR30</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_product'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['product'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['overdue_amount'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['par30_pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['by_product'] ?? []))
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
            <div class="card-header"><strong>High-Risk Loans (Top 10)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Loan</th>
                      <th>Customer</th>
                      <th class="text-right">DPD</th>
                      <th class="text-right">Outstanding</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['high_risk'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['loan_code'] ?? '' }}</td>
                        <td>{{ $r['customer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['dpd'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['high_risk'] ?? []))
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
            <div class="card-header"><strong>Write-Off Candidates</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Loan</th>
                      <th>Customer</th>
                      <th class="text-right">DPD</th>
                      <th class="text-right">Outstanding</th>
                      <th>Last Payment</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['writeoff_candidates'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['loan_code'] ?? '' }}</td>
                        <td>{{ $r['customer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['dpd'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['outstanding'] ?? 0, 2) }}</td>
                        <td>{{ $r['last_payment_date'] ?? '' }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['writeoff_candidates'] ?? []))
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
            <div class="card-header"><strong>DPD Distribution</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Bucket</th>
                      <th class="text-right">Loans</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['dpd_analysis']['distribution'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['bucket'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['dpd_analysis']['distribution'] ?? []))
                      <tr><td colspan="2" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Missed Installments (Top 10 Loans)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Loan</th>
                      <th class="text-right">Missed</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['missed_installments'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['loan_code'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['missed_installments'] ?? 0) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['missed_installments'] ?? []))
                      <tr><td colspan="2" class="text-center text-muted p-3">No data</td></tr>
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
  const labels = @json($t['labels'] ?? []);
  const par30 = @json($t['par30'] ?? []);
  const par60 = @json($t['par60'] ?? []);
  const par90 = @json($t['par90'] ?? []);

  const ctx = document.getElementById('parTrend');
  if(!ctx) return;

  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: 'PAR30 %', data: par30, borderColor: 'rgba(54,162,235,1)', tension: 0.3 },
        { label: 'PAR60 %', data: par60, borderColor: 'rgba(255,159,64,1)', tension: 0.3 },
        { label: 'PAR90 %', data: par90, borderColor: 'rgba(255,99,132,1)', tension: 0.3 }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'bottom' } },
      scales: { y: { beginAtZero: true } }
    }
  });
})();
</script>
@stop
