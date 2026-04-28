@extends('adminlte::page')

@section('title', 'Loan Arrears Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-exclamation-circle"></i> Loan Arrears Report</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-exclamation-circle"></i> Loan Arrears</h1>
      <div class="small text-light-50">As At: {{ $asAtDate ?? '' }}</div>
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
    <li class="breadcrumb-item active" aria-current="page">Loan Arrears</li>
  </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
  <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
    <div class="card-body">

      <form method="get" action="{{ route('reports.loan_arrears.index') }}" class="mb-3">
        <div class="bg-light p-2 rounded border">
          <div class="form-row">
            <div class="form-group col-md-3">
              <label for="as_at_date">As At Date</label>
              <input type="date" class="form-control form-control-sm" id="as_at_date" name="as_at_date" value="{{ $asAtDate ?? '' }}" required>
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
            <div class="form-group col-md-3">
              <label for="loan_officer_id">Loan Officer (Disbursement Processor)</label>
              <select class="form-control form-control-sm" id="loan_officer_id" name="loan_officer_id">
                <option value="">All Officers</option>
                @foreach(($officers ?? []) as $o)
                  <option value="{{ $o->id }}" {{ (request('loan_officer_id') == $o->id) ? 'selected' : '' }}>{{ $o->name }}</option>
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
            <div class="form-group col-md-2">
              <label for="dpd_min">DPD Min</label>
              <input type="number" class="form-control form-control-sm" id="dpd_min" name="dpd_min" value="{{ request('dpd_min') }}" min="0" step="1">
            </div>
            <div class="form-group col-md-2">
              <label for="dpd_max">DPD Max</label>
              <input type="number" class="form-control form-control-sm" id="dpd_max" name="dpd_max" value="{{ request('dpd_max') }}" min="0" step="1">
            </div>
            <div class="form-group col-md-3">
              <label for="customer_id">Customer ID (optional)</label>
              <input type="number" class="form-control form-control-sm" id="customer_id" name="customer_id" value="{{ request('customer_id') }}">
              @if(!empty($customer))
                <div class="small text-muted mt-1">Selected: {{ $customer->name }}</div>
              @endif
            </div>
            <div class="form-group col-md-2">
              <label for="per_page">Per Page</label>
              <select class="form-control form-control-sm" id="per_page" name="per_page">
                @foreach([10,25,50,100,200] as $pp)
                  <option value="{{ $pp }}" {{ (int)request('per_page',25) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-12 d-flex align-items-end">
              <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply Filters</button>
              <a href="{{ route('reports.loan_arrears.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
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
      @endphp

      <div class="row mb-3">
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-danger">
            <div class="card-body">
              <div class="text-uppercase small">Total Arrears</div>
              <div class="h4 mb-0">{{ number_format($s['total_arrears'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-warning">
            <div class="card-body">
              <div class="text-uppercase small">Loans In Arrears</div>
              <div class="h4 mb-0">{{ number_format($s['loans_in_arrears'] ?? 0) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-info">
            <div class="card-body">
              <div class="text-uppercase small">Overdue Installments</div>
              <div class="h4 mb-0">{{ number_format($s['overdue_installments'] ?? 0) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-primary">
            <div class="card-body">
              <div class="text-uppercase small">Avg Arrears / Loan</div>
              <div class="h4 mb-0">{{ number_format($s['avg_arrears_per_loan'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-dark">
            <div class="card-body">
              <div class="text-uppercase small">Max Arrears</div>
              <div class="h4 mb-0">{{ number_format($s['max_arrears'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-secondary">
            <div class="card-body">
              <div class="text-uppercase small">Arrears Ratio (%)</div>
              <div class="h4 mb-0">{{ number_format($report['arrears_ratio_pct'] ?? 0, 2) }}%</div>
            </div>
          </div>
        </div>
        <div class="col-md-6 mb-3">
          <div class="card">
            <div class="card-body">
              <div class="text-uppercase small text-muted">Outstanding Portfolio (Denominator)</div>
              <div class="h5 mb-0">{{ number_format($report['portfolio_outstanding'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Arrears Trend (Last 12 Months)</strong></div>
            <div class="card-body">
              <canvas id="arrearsTrend" height="90"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Arrears Trend Table</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Month</th>
                      <th class="text-right">Total Arrears</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['trend'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['date'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['total_arrears'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['trend'] ?? []))
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
        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-header"><strong>Arrears by Loan Product</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Total Arrears</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_product'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['product'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['arrears'] ?? 0, 2) }}</td>
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

        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-header"><strong>Arrears by Branch</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Branch</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Total Arrears</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_branch'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['branch'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['arrears'] ?? 0, 2) }}</td>
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
            <div class="card-header"><strong>Arrears by Loan Officer</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Officer</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Total Arrears</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_officer'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['officer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['arrears'] ?? 0, 2) }}</td>
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
      </div>

      <div class="row">
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Arrears Aging Buckets</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Bucket</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Total Arrears</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['aging_buckets'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['bucket'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['arrears'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['aging_buckets'] ?? []))
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
            <div class="card-header"><strong>Top Defaulters (Top 10)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Customer</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Arrears</th>
                      <th class="text-right">DPD</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['top_defaulters'] ?? []) as $r)
                      <tr>
                        <td>
                          @if(!empty($r['customer_id']))
                            <a href="{{ route('customers.show', ['customer' => $r['customer_id']]) }}">{{ $r['customer'] ?? '' }}</a>
                          @else
                            {{ $r['customer'] ?? '' }}
                          @endif
                        </td>
                        <td class="text-right">{{ number_format($r['loans'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['arrears'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['dpd'] ?? 0) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['top_defaulters'] ?? []))
                      <tr><td colspan="4" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card card-outline card-danger mb-3">
        <div class="card-header">
          <h3 class="card-title">Loan-Level Arrears</h3>
        </div>
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
                  <th class="text-right">Arrears</th>
                  <th class="text-right">Overdue Inst.</th>
                  <th>Oldest Due</th>
                  <th class="text-right">DPD</th>
                  <th>Last Payment</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @php $loans = $report['loan_level'] ?? null; @endphp
                @if($loans instanceof \Illuminate\Pagination\LengthAwarePaginator)
                  @foreach($loans as $r)
                    <tr>
                      <td><a href="{{ route('loans.loans.show', ['loan' => $r->loan_code]) }}">{{ $r->loan_code ?? '' }}</a></td>
                      <td>
                        @if(!empty($r->customer_id))
                          <a href="{{ route('customers.show', ['customer' => $r->customer_id]) }}">{{ $r->customer ?? '' }}</a>
                        @else
                          {{ $r->customer ?? '' }}
                        @endif
                      </td>
                      <td>{{ $r->product ?? '' }}</td>
                      <td>{{ $r->branch ?? '' }}</td>
                      <td>{{ $r->officer ?? '' }}</td>
                      <td class="text-right font-weight-bold">
                        <a href="{{ route('reports.loan_arrears.index', array_merge(request()->query(), ['loan_id' => $r->loan_id, 'installments_page' => 1])) }}">{{ number_format($r->arrears_amount ?? 0, 2) }}</a>
                      </td>
                      <td class="text-right">{{ number_format($r->overdue_installments ?? 0) }}</td>
                      <td>{{ $r->oldest_due_date ?? '' }}</td>
                      <td class="text-right">{{ number_format($r->dpd ?? 0) }}</td>
                      <td>{{ $r->last_payment_date ?? '' }}</td>
                      <td>{{ $r->loan_status ?? '' }}</td>
                    </tr>
                  @endforeach
                @else
                  <tr><td colspan="11" class="text-center text-muted p-3">No data</td></tr>
                @endif
              </tbody>
            </table>
          </div>
        </div>
        @if($loans instanceof \Illuminate\Pagination\LengthAwarePaginator)
          <div class="card-footer">
            {{ $loans->links() }}
          </div>
        @endif
      </div>

            <div class="row">
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Partial Payment Detection</strong></div>
            <div class="card-body">
              @php $pp = $report['partial_overdue'] ?? []; @endphp
              <div><strong>Partial overdue installments:</strong> {{ number_format($pp['partial_overdue_installments'] ?? 0) }}</div>
              <div><strong>Total partial arrears:</strong> {{ number_format($pp['partial_arrears'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
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
                      <th class="text-right">Arrears</th>
                      <th class="text-right">Missed</th>
                      <th class="text-right">DPD</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['high_risk'] ?? []) as $r)
                      <tr>
                        <td><a href="{{ route('loans.loans.show', ['loan' => $r['loan_code'] ?? '']) }}">{{ $r['loan_code'] ?? '' }}</a></td>
                        <td>{{ $r['customer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['arrears'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['missed_installments'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['dpd'] ?? 0) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['high_risk'] ?? []))
                      <tr><td colspan="5" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Missed Installments Analysis</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Loan</th>
                      <th>Customer</th>
                      <th class="text-right">Missed Installments</th>
                      <th class="text-right">Total Arrears</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['missed_installments'] ?? []) as $r)
                      <tr>
                        <td>
                          @if(!empty($r['loan_code']))
                            <a href="{{ route('loans.loans.show', ['loan' => $r['loan_code']]) }}">{{ $r['loan_code'] }}</a>
                          @else
                            {{ $r['loan_code'] ?? '' }}
                          @endif
                        </td>
                        <td>{{ $r['customer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['missed_installments'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['arrears'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['missed_installments'] ?? []))
                      <tr><td colspan="4" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card card-outline card-secondary mb-3">
        <div class="card-header">
          <h3 class="card-title">Installment-Level Arrears Breakdown (Overdue Only)</h3>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead>
                <tr>
                  <th>Loan</th>
                  <th>Customer</th>
                  <th class="text-right">Inst. #</th>
                  <th>Due Date</th>
                  <th class="text-right">Inst. Amount</th>
                  <th class="text-right">Paid</th>
                  <th class="text-right">Arrears</th>
                  <th class="text-right">DPD</th>
                </tr>
              </thead>
              <tbody>
                @php $inst = $report['installment_level'] ?? null; @endphp
                @if($inst instanceof \Illuminate\Pagination\LengthAwarePaginator)
                  @foreach($inst as $r)
                    <tr>
                      <td><a href="{{ route('loans.loans.show', ['loan' => $r->loan_code]) }}">{{ $r->loan_code ?? '' }}</a></td>
                      <td>
                        @if(!empty($r->customer_id))
                          <a href="{{ route('customers.show', ['customer' => $r->customer_id]) }}">{{ $r->customer ?? '' }}</a>
                        @else
                          {{ $r->customer ?? '' }}
                        @endif
                      </td>
                      <td class="text-right">{{ $r->installment_number ?? '' }}</td>
                      <td>{{ $r->due_date ?? '' }}</td>
                      <td class="text-right">{{ number_format($r->installment_amount ?? 0, 2) }}</td>
                      <td class="text-right">{{ number_format($r->paid_amount ?? 0, 2) }}</td>
                      <td class="text-right font-weight-bold">{{ number_format($r->arrears_amount ?? 0, 2) }}</td>
                      <td class="text-right">{{ number_format($r->dpd ?? 0) }}</td>
                    </tr>
                  @endforeach
                @else
                  <tr><td colspan="8" class="text-center text-muted p-3">No data</td></tr>
                @endif
              </tbody>
            </table>
          </div>
        </div>
        @if($inst instanceof \Illuminate\Pagination\LengthAwarePaginator)
          <div class="card-footer">
            {{ $inst->links() }}
          </div>
        @endif
      </div>



    </div>
  </div>
</div>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (function() {
    const trend = @json($report['trend'] ?? []);
    const labels = (trend || []).map(r => (r.date || '').slice(0, 7));
    const data = (trend || []).map(r => Number(r.total_arrears || 0));

    const ctx = document.getElementById('arrearsTrend');
    if (ctx) {
      new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [
            {
              label: 'Total Arrears',
              data,
              borderColor: '#dc3545',
              backgroundColor: 'rgba(220,53,69,.10)',
              tension: 0.25,
              fill: true,
            }
          ]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: true } },
          scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Amount' } }
          }
        }
      });
    }
  })();
</script>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
