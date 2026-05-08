@extends('adminlte::page')

@section('title', 'Loan Outstanding Balance Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-balance-scale"></i> Loan Outstanding Balance</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-balance-scale"></i> Loan Outstanding</h1>
      <div class="small text-light-50">As At: {{ $asAtDate ?? '' }}</div>
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
        <li class="breadcrumb-item active" aria-current="page">Loan Outstanding</li>
    </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">

  <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
    <div class="card-body">
      <form method="get" action="{{ route('reports.loan_outstanding.index') }}" class="mb-3">
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
            <div class="form-group col-md-3">
              <label for="customer_id">Customer ID (optional)</label>
              <input type="number" class="form-control form-control-sm" id="customer_id" name="customer_id" value="{{ request('customer_id') }}">
              @if(!empty($customer))
                <div class="small text-muted mt-1">Selected: {{ $customer->name }}</div>
              @endif
            </div>
            <div class="form-group col-md-3">
              <label for="per_page">Per Page</label>
              <select class="form-control form-control-sm" id="per_page" name="per_page">
                @foreach([25,50,100,200] as $pp)
                  <option value="{{ $pp }}" {{ (int)request('per_page', 50) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-3 d-flex align-items-end">
              <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply Filters</button>
              <a href="{{ route('reports.loan_outstanding.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
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

      @php $summary = $report['summary'] ?? []; @endphp

      <div class="row mb-3">
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-success">
            <div class="card-body">
              <div class="text-uppercase small">Total Outstanding</div>
              <div class="h4 mb-0">Tsh {{ number_format($summary['total_outstanding'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-primary">
            <div class="card-body">
              <div class="text-uppercase small">Principal Outstanding</div>
              <div class="h4 mb-0">Tsh {{ number_format($summary['principal_outstanding'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-info">
            <div class="card-body">
              <div class="text-uppercase small">Interest Outstanding</div>
              <div class="h4 mb-0">Tsh {{ number_format($summary['interest_outstanding'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-secondary">
            <div class="card-body">
              <div class="text-uppercase small">Active Loans</div>
              <div class="h4 mb-0">{{ number_format($summary['active_loans'] ?? 0) }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row"></div>
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Outstanding Trend (Last 12 Months)</strong></div>
            <div class="card-body">
              <canvas id="outstandingTrend" height="90"></canvas>
            </div>
          </div>
        </div>
      </div>



      <div class="row">
        <div class="col-md-6 mb-3">
          <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title">Outstanding by Loan Product</h3></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Principal O/S</th>
                      <th class="text-right">Interest O/S</th>
                      <th class="text-right">Total O/S</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_product'] ?? []) as $r)
                      <tr>
                        <td>
                          <a href="{{ route('reports.loan_outstanding.index', array_filter(request()->except('page') + ['loan_product_id' => $r['product_id'] ?? null], fn ($v) => !is_null($v) && $v !== '')) }}">{{ $r['product'] ?? '' }}</a>
                        </td>
                        <td class="text-right">{{ number_format($r['loans_count'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['principal_outstanding'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['interest_outstanding'] ?? 0, 2) }}</td>
                        <td class="text-right font-weight-bold">{{ number_format($r['total_outstanding'] ?? 0, 2) }}</td>
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

        <div class="col-md-6 mb-3">
          <div class="card card-outline card-primary">
            <div class="card-header"><h3 class="card-title">Outstanding by Branch</h3></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Branch</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Total O/S</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_branch'] ?? []) as $r)
                      <tr>
                        <td>
                          <a href="{{ route('reports.loan_outstanding.index', array_filter(request()->except('page') + ['subshop_id' => $r['subshop_id'] ?? null], fn ($v) => !is_null($v) && $v !== '')) }}">{{ $r['branch'] ?? '' }}</a>
                        </td>
                        <td class="text-right">{{ number_format($r['loans_count'] ?? 0) }}</td>
                        <td class="text-right font-weight-bold">{{ number_format($r['total_outstanding'] ?? 0, 2) }}</td>
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
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <div class="card card-outline card-info">
            <div class="card-header"><h3 class="card-title">Outstanding by Loan Officer</h3></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Officer</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Total O/S</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_officer'] ?? []) as $r)
                      <tr>
                        <td>
                          <a href="{{ route('reports.loan_outstanding.index', array_filter(request()->except('page') + ['loan_officer_id' => $r['officer_id'] ?? null], fn ($v) => !is_null($v) && $v !== '')) }}">{{ $r['officer'] ?? '' }}</a>
                        </td>
                        <td class="text-right">{{ number_format($r['loans_count'] ?? 0) }}</td>
                        <td class="text-right font-weight-bold">{{ number_format($r['total_outstanding'] ?? 0, 2) }}</td>
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

        <div class="col-md-6 mb-3">
          <div class="card card-outline card-secondary">
            <div class="card-header"><h3 class="card-title">Outstanding Distribution</h3></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Range</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Total O/S</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['distribution'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['range'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['loans_count'] ?? 0) }}</td>
                        <td class="text-right font-weight-bold">{{ number_format($r['total_outstanding'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['distribution'] ?? []))
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
        <div class="col-md-6 mb-3">
          <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title">Top Borrowers (Top 10)</h3></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Customer</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Total O/S</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['top_borrowers'] ?? []) as $r)
                      <tr>
                        <td>
                          <a href="{{ route('reports.loan_outstanding.index', array_filter(request()->except('page') + ['customer_id' => $r['customer_id'] ?? null], fn ($v) => !is_null($v) && $v !== '')) }}">{{ $r['customer'] ?? '' }}</a>
                        </td>
                        <td class="text-right">{{ number_format($r['loans_count'] ?? 0) }}</td>
                        <td class="text-right font-weight-bold">{{ number_format($r['total_outstanding'] ?? 0, 2) }}</td>
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

        <div class="col-md-6 mb-3">
          <div class="card card-outline card-dark">
            <div class="card-header"><h3 class="card-title">Loan Status Breakdown</h3></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Status</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Total O/S</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['status_breakdown'] ?? []) as $r)
                      <tr>
                        <td>
                          <a href="{{ route('reports.loan_outstanding.index', array_filter(request()->except('page') + ['loan_status' => $r['status'] ?? null], fn ($v) => !is_null($v) && $v !== '')) }}">{{ ucfirst(str_replace('_',' ', $r['status'] ?? '')) }}</a>
                        </td>
                        <td class="text-right">{{ number_format($r['loans_count'] ?? 0) }}</td>
                        <td class="text-right font-weight-bold">{{ number_format($r['total_outstanding'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['status_breakdown'] ?? []))
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
        <div class="col-md-6 mb-3">
          <div class="card card-outline card-primary">
            <div class="card-header"><h3 class="card-title">Outstanding vs Disbursed</h3></div>
            <div class="card-body">
              @php $vd = $report['vs_disbursed'] ?? []; @endphp
              <div class="row">
                <div class="col-6"><strong>Total Disbursed</strong></div>
                <div class="col-6 text-right">{{ number_format($vd['total_disbursed'] ?? 0, 2) }}</div>
                <div class="col-6"><strong>Total Outstanding</strong></div>
                <div class="col-6 text-right">{{ number_format($vd['total_outstanding'] ?? 0, 2) }}</div>
                <div class="col-6"><strong>Total Recovered</strong></div>
                <div class="col-6 text-right">{{ number_format($vd['total_recovered'] ?? 0, 2) }}</div>
                <div class="col-6"><strong>Recovery Rate</strong></div>
                <div class="col-6 text-right">{{ number_format($vd['recovery_rate_pct'] ?? 0, 2) }}%</div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-6 mb-3">
          <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title">Principal vs Interest Composition</h3></div>
            <div class="card-body">
              @php $cmp = $report['composition'] ?? []; @endphp
              <div class="row">
                <div class="col-6"><strong>Principal %</strong></div>
                <div class="col-6 text-right">{{ number_format($cmp['principal_pct'] ?? 0, 2) }}%</div>
                <div class="col-6"><strong>Interest %</strong></div>
                <div class="col-6 text-right">{{ number_format($cmp['interest_pct'] ?? 0, 2) }}%</div>
                <div class="col-6"><strong>Fees %</strong></div>
                <div class="col-6 text-right">{{ number_format($cmp['fees_pct'] ?? 0, 2) }}%</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card card-outline card-secondary mb-3">
        <div class="card-header"><h3 class="card-title">Time-Based Snapshot (Last 12 Months)</h3></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead>
                <tr>
                  <th>Date</th>
                  <th class="text-right">Total Outstanding</th>
                </tr>
              </thead>
              <tbody>
                @foreach(($report['snapshot'] ?? []) as $r)
                  <tr>
                    <td>{{ $r['date'] ?? '' }}</td>
                    <td class="text-right font-weight-bold">{{ number_format($r['total_outstanding'] ?? 0, 2) }}</td>
                  </tr>
                @endforeach
                @if(empty($report['snapshot'] ?? []))
                  <tr><td colspan="2" class="text-center text-muted p-3">No data</td></tr>
                @endif
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="card card-outline card-primary mb-3">
        <div class="card-header">
          <h3 class="card-title">Loan-Level Outstanding</h3>
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
                  <th>Disbursement</th>
                  <th class="text-right">Principal O/S</th>
                  <th class="text-right">Interest O/S</th>
                  <th class="text-right">Fees O/S</th>
                  <th class="text-right">Total O/S</th>
                  <th>Last Payment</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @php $loans = $report['loans'] ?? null; @endphp
                @if($loans instanceof \Illuminate\Pagination\LengthAwarePaginator)
                  @foreach($loans as $r)
                    <tr>
                      <td>
                        <a href="{{ route('loans.loans.show', ['loan' => $r->loan_code]) }}">{{ $r->loan_code }}</a>
                      </td>
                      <td>
                        @if(!empty($r->customer_id))
                          <a href="{{ route('customers.show', ['customer' => $r->customer_id]) }}">{{ $r->customer_name ?? $r->customer_id }}</a>
                        @else
                          -
                        @endif
                      </td>
                      <td>
                        <a href="{{ route('reports.loan_outstanding.index', array_filter(request()->except('page') + ['loan_product_id' => $r->loan_product_id], fn ($v) => !is_null($v) && $v !== '')) }}">{{ $r->loan_product_name ?? $r->loan_product_id }}</a>
                      </td>
                      <td>
                        <a href="{{ route('reports.loan_outstanding.index', array_filter(request()->except('page') + ['subshop_id' => $r->subshop_id], fn ($v) => !is_null($v) && $v !== '')) }}">{{ $r->branch_name ?? $r->subshop_id }}</a>
                      </td>
                      <td>
                        @if(!empty($r->officer_id))
                          <a href="{{ route('reports.loan_outstanding.index', array_filter(request()->except('page') + ['loan_officer_id' => $r->officer_id], fn ($v) => !is_null($v) && $v !== '')) }}">{{ $r->officer_name ?? $r->officer_id }}</a>
                        @else
                          -
                        @endif
                      </td>
                      <td>{{ $r->disbursement_date }}</td>
                      <td class="text-right">{{ number_format($r->principal_outstanding ?? 0, 2) }}</td>
                      <td class="text-right">{{ number_format($r->interest_outstanding ?? 0, 2) }}</td>
                      <td class="text-right">{{ number_format($r->fees_outstanding ?? 0, 2) }}</td>
                      <td class="text-right font-weight-bold">{{ number_format($r->total_outstanding ?? 0, 2) }}</td>
                      <td>{{ $r->last_payment_date }}</td>
                      <td>{{ $r->loan_status }}</td>
                    </tr>
                  @endforeach
                @else
                  <tr><td colspan="12" class="text-center text-muted p-3">No data</td></tr>
                @endif
              </tbody>
            </table>
          </div>
        </div>
        @if($loans instanceof \Illuminate\Pagination\LengthAwarePaginator)
          <div class="card-footer mt-3 d-flex justify-content-center">
            {{ $loans->links() }}
          </div>
        @endif
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
                    <h6 class="font-weight-bold text-primary">Outstanding Balance Calculation</h6>
                    <ul class="small">
                      <li><strong>Total Outstanding:</strong> Sum of loan_installments.outstanding_amount for all active installments with due_date <= as-at date. Uses loan_installments as the single source of truth.</li>
                      <li><strong>Principal/Interest/Fees Outstanding:</strong> Component breakdown from loan_installments fields (principal_outstanding, interest_outstanding, fees_outstanding).</li>
                      <li><strong>Expected Amounts:</strong> Original schedule amounts (principal_due, interest_due, fees_due) from loan_installments.</li>
                      <li><strong>Paid Amounts:</strong> Sum of payment allocations up to the as-at date.</li>
                    </ul>

                    <h6 class="font-weight-bold text-primary mt-3">Included Loan Statuses</h6>
                    <ul class="small">
                      <li>Active loans: disbursed, partially_paid, defaulted, written_off.</li>
                      <li>Written-off loans are included because they still have accounting value and outstanding amounts.</li>
                      <li>Excluded: pending, approved, rejected, paid_off (no outstanding).</li>
                    </ul>
                  </div>
                  <div class="col-md-6">
                    <h6 class="font-weight-bold text-primary">Key Metrics</h6>
                    <ul class="small">
                      <li><strong>Recovery Rate:</strong> (Total Disbursed - Total Outstanding) / Total Disbursed × 100. Shows percentage of disbursed amount that has been recovered.</li>
                      <li><strong>Composition %:</strong> Principal, Interest, and Fees as percentage of total outstanding.</li>
                      <li><strong>Avg Outstanding per Loan:</strong> Total Outstanding / Number of loans.</li>
                    </ul>

                    <h6 class="font-weight-bold text-primary mt-3">Officer Attribution</h6>
                    <ul class="small">
                      <li>Officer is determined by who processed the latest disbursement (processed_by field in loan_disbursements).</li>
                      <li>For loans with multiple disbursements, only the latest disbursement processor is considered.</li>
                    </ul>

                    <h6 class="font-weight-bold text-primary mt-3">Time-Based Snapshot</h6>
                    <ul class="small">
                      <li>Shows outstanding balance at the end of each month for the last 12 months.</li>
                      <li>Only months with loan activity (disbursements or repayments) show non-zero values, plus the current month.</li>
                    </ul>
                  </div>
                </div>
                <div class="alert alert-light border mt-3 mb-0 small">
                  <strong>Note:</strong> This report uses loan_installments as the single source of truth for outstanding balances, consistent with LoanDelinquencyEngine and other portfolio reports. Outstanding amounts are snapshot values as of the selected "As At" date.
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

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (function() {
    const snapshot = @json($report['snapshot'] ?? []);

    const labels = (snapshot || []).map(r => (r.date || '').slice(0, 7));
    const data = (snapshot || []).map(r => Number(r.total_outstanding || 0));

    const ctx = document.getElementById('outstandingTrend');
    if (ctx) {
      new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [
            {
              label: 'Total Outstanding',
              data,
              borderColor: '#007bff',
              backgroundColor: 'rgba(0,123,255,.10)',
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
