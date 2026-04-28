@extends('adminlte::page')

@section('title', 'NPL Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-ban"></i> Non-Performing Loan (NPL) Report</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-ban"></i> NPL Report</h1>
      <div class="small text-light-50">As-of: {{ $asOf ?? '' }}</div>
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
    <li class="breadcrumb-item active" aria-current="page">NPL</li>
  </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
  <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
    <div class="card-body">

      <form method="get" action="{{ route('reports.npl.index') }}" class="mb-3">
        <div class="bg-light p-2 rounded border">
          <div class="form-row">
            <div class="form-group col-md-3">
              <label for="as_of">As-of Date</label>
              <input type="date" class="form-control form-control-sm" id="as_of" name="as_of" value="{{ $asOf ?? '' }}">
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
                  <option value="{{ data_get($p, 'id', '') }}" {{ (request('loan_product_id') == data_get($p, 'id')) ? 'selected' : '' }}>{{ data_get($p, 'name', '') }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-3">
              <label for="loan_officer_id">Loan Officer (Disbursement Processor)</label>
              <select class="form-control form-control-sm" id="loan_officer_id" name="loan_officer_id">
                <option value="">All Officers</option>
                @foreach(($officers ?? []) as $o)
                  <option value="{{ data_get($o, 'id', '') }}" {{ (request('loan_officer_id') == data_get($o, 'id')) ? 'selected' : '' }}>{{ data_get($o, 'name', '') }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-3">
              <label for="loan_status">Loan Status</label>
              <select class="form-control form-control-sm" id="loan_status" name="loan_status">
                <option value="">All Statuses</option>
                @foreach(['pending','approved','rejected','disbursed','partially_paid','paid_off','defaulted','written_off'] as $st)
                  <option value="{{ $st }}" {{ request('loan_status') == $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ', $st)) }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-3">
              <label for="dpd_threshold">DPD Threshold (NPL)</label>
              <input type="number" class="form-control form-control-sm" id="dpd_threshold" name="dpd_threshold" value="{{ request('dpd_threshold', 90) }}" min="0" step="1">
            </div>
            <div class="form-group col-md-3">
              <label for="dpd_min">DPD Min</label>
              <input type="number" class="form-control form-control-sm" id="dpd_min" name="dpd_min" value="{{ request('dpd_min') }}" min="0" step="1">
            </div>
            <div class="form-group col-md-3">
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
              <a href="{{ route('reports.npl.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
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
              <div class="text-uppercase small">NPL Loans</div>
              <div class="h4 mb-0">{{ number_format($s['total_npl_loans'] ?? 0) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-warning">
            <div class="card-body">
              <div class="text-uppercase small">NPL Amount</div>
              <div class="h4 mb-0">{{ number_format($s['total_npl_amount'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-primary">
            <div class="card-body">
              <div class="text-uppercase small">Portfolio Outstanding</div>
              <div class="h4 mb-0">{{ number_format($report['portfolio_outstanding'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-info">
            <div class="card-body">
              <div class="text-uppercase small">NPL Ratio</div>
              <div class="h4 mb-0">{{ number_format($s['npl_ratio_pct'] ?? 0, 2) }}%</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>NPL Trends (last 12 months)</strong></div>
            <div class="card-body">
              <canvas id="nplTrend" height="90"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>NPL Aging Breakdown</strong></div>
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
                    <tr><td>Recovered Amount (last 30 days)</td><td class="text-right">{{ number_format($report['recovery']['recovered_amount'] ?? 0, 2) }}</td></tr>
                    <tr><td>Recovery Rate</td><td class="text-right">{{ number_format($report['recovery']['recovery_rate_pct'] ?? 0, 2) }}%</td></tr>
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
            <div class="card-header"><strong>NPL Loan List</strong></div>
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
                      <th class="text-right">Outstanding</th>
                      <th class="text-right">DPD</th>
                      <th>Last Payment</th>
                      <th class="text-right">Days Since</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['npl_loans'] ?? []) as $row)
                      <tr>
                        <td>{{ $row->loan_code ?? '' }}</td>
                        <td>{{ $row->customer ?? '' }}</td>
                        <td>{{ $row->product ?? '' }}</td>
                        <td>{{ $row->branch ?? '' }}</td>
                        <td>{{ $row->officer ?? '' }}</td>
                        <td class="text-right">{{ number_format($row->outstanding_balance ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row->dpd ?? 0) }}</td>
                        <td>{{ $row->last_payment_date ?? '' }}</td>
                        <td class="text-right">{{ number_format($row->days_since_last_payment ?? 0) }}</td>
                        <td>{{ $row->loan_status ?? '' }}</td>
                      </tr>
                    @endforeach
                    @if(empty(($report['npl_loans'] ?? null)) || (method_exists(($report['npl_loans'] ?? null), 'total') && ($report['npl_loans']->total() === 0)))
                      <tr><td colspan="10" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
              <div class="p-2">
                @if(method_exists(($report['npl_loans'] ?? null), 'links'))
                  {{ $report['npl_loans']->appends(request()->query())->links() }}
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-header"><strong>NPL by Officer</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Officer</th>
                      <th class="text-right">NPL Amount</th>
                      <th class="text-right">Ratio</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_officer'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['officer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['npl_amount'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['npl_ratio_pct'] ?? 0, 2) }}%</td>
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
            <div class="card-header"><strong>NPL by Branch</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Branch</th>
                      <th class="text-right">NPL Amount</th>
                      <th class="text-right">Ratio</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_branch'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['branch'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['npl_amount'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['npl_ratio_pct'] ?? 0, 2) }}%</td>
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
            <div class="card-header"><strong>NPL by Product</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th class="text-right">NPL Amount</th>
                      <th class="text-right">Ratio</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_product'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['product'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['npl_amount'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['npl_ratio_pct'] ?? 0, 2) }}%</td>
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
            <div class="card-header"><strong>Top NPL Customers (Top 10)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Customer</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">NPL Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['top_customers'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['customer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['npl_loans'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['npl_amount'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['top_customers'] ?? []))
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
            <div class="card-header"><strong>Write-Off Analysis</strong></div>
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
  const nplRatio = @json($t['npl_ratio'] ?? []);
  const nplAmount = @json($t['npl_amount'] ?? []);

  const ctx = document.getElementById('nplTrend');
  if(!ctx) return;

  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: 'NPL Ratio %', data: nplRatio, borderColor: 'rgba(255,99,132,1)', tension: 0.3, yAxisID: 'y' },
        { label: 'NPL Amount', data: nplAmount, borderColor: 'rgba(54,162,235,1)', tension: 0.3, yAxisID: 'y1' },
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'bottom' } },
      scales: {
        y: { beginAtZero: true, position: 'left', ticks: { callback: (v) => v + '%' } },
        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
      }
    }
  });
})();
</script>
@stop
