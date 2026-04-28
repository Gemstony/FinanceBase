@extends('adminlte::page')

@section('title', 'Loan Repayments Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-money-check-alt"></i> Loan Repayments Report</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-money-check-alt"></i> Repayments</h1>
      <div class="small text-light-50">Period: {{ $dateFrom ?? '' }} to {{ $dateTo ?? '' }}</div>
    </div>
    <a href="{{ url()->previous() }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.loan_reports.index') }}"><i class="fas fa-university"></i> Loan Reports</a></li>
    <li class="breadcrumb-item active" aria-current="page">Loan Repayments</li>
  </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
  <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
    <div class="card-body">

      <form method="get" action="{{ route('reports.loan_repayment.index') }}" class="mb-3">
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
              <label>Payment Method</label>
              <select class="form-control form-control-sm" name="payment_method">
                <option value="">All Methods</option>
                @foreach(($paymentMethods ?? []) as $pm)
                  <option value="{{ $pm }}" {{ request('payment_method') == $pm ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ', $pm)) }}</option>
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
            <div class="form-group col-md-3">
              <label>Customer</label>
              <select class="form-control form-control-sm" name="customer_id">
                <option value="">All Customers</option>
                @foreach(($customers ?? []) as $c)
                  <option value="{{ $c->id ?? '' }}" {{ request('customer_id') == ($c->id ?? null) ? 'selected' : '' }}>{{ $c->name ?? '' }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-3">
              <label>Per Page</label>
              <select class="form-control form-control-sm" name="per_page">
                @foreach([10,15,25,50,100] as $pp)
                  <option value="{{ $pp }}" {{ (int)request('per_page',15) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply Filters</button>
          <a href="{{ route('reports.loan_repayment.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
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

      @php $s = $report['summary'] ?? []; @endphp

      <div class="row mb-3">
        <div class="col-md-4 mb-3">
          <div class="card text-white bg-success"><div class="card-body"><div class="text-uppercase small">Total Collected</div><div class="h4 mb-0">{{ number_format($s['total_repayments_collected'] ?? 0, 2) }}</div></div></div>
        </div>
        <div class="col-md-4 mb-3">
          <div class="card text-white bg-info"><div class="card-body"><div class="text-uppercase small">Transactions</div><div class="h4 mb-0">{{ number_format($s['repayment_transactions'] ?? 0) }}</div></div></div>
        </div>
        <div class="col-md-4 mb-3">
          <div class="card text-white bg-primary"><div class="card-body"><div class="text-uppercase small">Avg Payment</div><div class="h4 mb-0">{{ number_format($s['average_payment_amount'] ?? 0, 2) }}</div></div></div>
        </div>
      </div>

            @php
        $trendGranularity = request('trend_granularity', 'auto');
        $tAll = $report['trends'] ?? [];
        $t = $tAll[$trendGranularity] ?? ($tAll['auto'] ?? []);
      @endphp

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <strong>Repayment Trends</strong>
              <form method="get" action="{{ route('reports.loan_repayment.index') }}" class="m-0">
                @foreach(request()->except('trend_granularity','page','installments_page') as $k => $v)
                  <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <select name="trend_granularity" class="form-control form-control-sm" onchange="this.form.submit()" style="width: 160px;">
                  @foreach(['auto' => 'Auto', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $k => $lbl)
                    <option value="{{ $k }}" {{ $trendGranularity === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                  @endforeach
                </select>
              </form>
            </div>
            <div class="card-body">
              <canvas id="repaymentTrendsChart" height="120"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Repayments by Branch</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Branch</th><th class="text-right">Payments</th><th class="text-right">Amount</th></tr></thead>
                  <tbody>
                    @foreach(($report['by_branch'] ?? []) as $row)
                      <tr>
                        <td>
                          <a href="{{ route('reports.loan_repayment.index', array_merge(request()->except('page','installments_page'), ['subshop_id' => $row['subshop_id'] ?? null])) }}">
                            {{ $row['branch'] ?? '' }}
                          </a>
                        </td>
                        <td class="text-right">{{ number_format($row['payments'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['amount'] ?? 0, 2) }}</td>
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

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Payment Methods</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Method</th><th class="text-right">Payments</th><th class="text-right">Amount</th></tr></thead>
                  <tbody>
                    @foreach(($report['payment_methods'] ?? []) as $row)
                      <tr>
                        <td>
                          <a href="{{ route('reports.loan_repayment.index', array_merge(request()->except('page','installments_page'), ['payment_method' => $row['payment_method'] ?? null])) }}">
                            {{ ucfirst(str_replace('_',' ', $row['payment_method'] ?? '')) }}
                          </a>
                        </td>
                        <td class="text-right">{{ number_format($row['payments'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['amount'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['payment_methods'] ?? []))
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
            <div class="card-header"><strong>On-Time vs Late</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Metric</th><th class="text-right">Value</th></tr></thead>
                  <tbody>
                    <tr><td>On-Time Payments</td><td class="text-right">{{ number_format($report['on_time_vs_late']['on_time_payments'] ?? 0) }}</td></tr>
                    <tr><td>Late Payments</td><td class="text-right">{{ number_format($report['on_time_vs_late']['late_payments'] ?? 0) }}</td></tr>
                    <tr><td>On-Time Amount</td><td class="text-right">{{ number_format($report['on_time_vs_late']['on_time_amount'] ?? 0, 2) }}</td></tr>
                    <tr><td>Late Amount</td><td class="text-right">{{ number_format($report['on_time_vs_late']['late_amount'] ?? 0, 2) }}</td></tr>
                    <tr><td>On-Time Rate</td><td class="text-right">{{ number_format($report['on_time_vs_late']['on_time_rate'] ?? 0, 2) }}%</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Scheduled vs Actual</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Metric</th><th class="text-right">Value</th></tr></thead>
                  <tbody>
                    <tr><td>Scheduled Amount</td><td class="text-right">{{ number_format($report['scheduled_vs_actual']['scheduled_amount'] ?? 0, 2) }}</td></tr>
                    <tr><td>Actual Collected</td><td class="text-right">{{ number_format($report['scheduled_vs_actual']['actual_collected'] ?? 0, 2) }}</td></tr>
                    <tr><td>Variance (Scheduled - Actual)</td><td class="text-right">{{ number_format($report['scheduled_vs_actual']['variance'] ?? 0, 2) }}</td></tr>
                    <tr><td>Collection Efficiency</td><td class="text-right">{{ number_format($report['scheduled_vs_actual']['collection_efficiency'] ?? 0, 2) }}%</td></tr>
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
            <div class="card-header"><strong>Repayments by Product</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Product</th><th class="text-right">Payments</th><th class="text-right">Amount</th></tr></thead>
                  <tbody>
                    @foreach(($report['by_product'] ?? []) as $row)
                      <tr>
                        <td>
                          <a href="{{ route('reports.loan_repayment.index', array_merge(request()->except('page','installments_page'), ['loan_product_id' => $row['loan_product_id'] ?? null])) }}">
                            {{ $row['product'] ?? '' }}
                          </a>
                        </td>
                        <td class="text-right">{{ number_format($row['payments'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['amount'] ?? 0, 2) }}</td>
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

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Repayments by Officer</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Officer</th><th class="text-right">Payments</th><th class="text-right">Amount</th></tr></thead>
                  <tbody>
                    @foreach(($report['by_officer'] ?? []) as $row)
                      <tr>
                        <td>
                          <a href="{{ route('reports.loan_repayment.index', array_merge(request()->except('page','installments_page'), ['loan_officer_id' => $row['user_id'] ?? null])) }}">
                            {{ $row['officer'] ?? '' }}
                          </a>
                        </td>
                        <td class="text-right">{{ number_format($row['payments'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['amount'] ?? 0, 2) }}</td>
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
            <div class="card-header"><strong>Repayment Aging (Late Payments)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Bucket (Days Late)</th><th class="text-right">Payments</th><th class="text-right">Late Amount</th></tr></thead>
                  <tbody>
                    @foreach(($report['aging']['buckets'] ?? []) as $b)
                      <tr>
                        <td>{{ $b['bucket'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($b['payments'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($b['amount'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['aging']['buckets'] ?? []))
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
            <div class="card-header"><strong>Partial vs Full</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Metric</th><th class="text-right">Value</th></tr></thead>
                  <tbody>
                    <tr><td>Full Payments (Count)</td><td class="text-right">{{ number_format($report['partial_vs_full']['full_payments_count'] ?? 0) }}</td></tr>
                    <tr><td>Partial Payments (Count)</td><td class="text-right">{{ number_format($report['partial_vs_full']['partial_payments_count'] ?? 0) }}</td></tr>
                    <tr><td>Full Payments (Amount)</td><td class="text-right">{{ number_format($report['partial_vs_full']['full_payments_amount'] ?? 0, 2) }}</td></tr>
                    <tr><td>Partial Payments (Amount)</td><td class="text-right">{{ number_format($report['partial_vs_full']['partial_payments_amount'] ?? 0, 2) }}</td></tr>
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
            <div class="card-header"><strong>Recovery Tracking (Overdue-Based)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Metric</th><th class="text-right">Value</th></tr></thead>
                  <tbody>
                    <tr><td>Total Overdue Amount</td><td class="text-right">{{ number_format($report['recovery']['total_overdue_amount'] ?? 0, 2) }}</td></tr>
                    <tr><td>Recovered Amount</td><td class="text-right">{{ number_format($report['recovery']['recovery_collected'] ?? 0, 2) }}</td></tr>
                    <tr><td>Recovery Transactions</td><td class="text-right">{{ number_format($report['recovery']['recovery_transactions'] ?? 0) }}</td></tr>
                    <tr><td>Recovery Rate</td><td class="text-right">{{ number_format($report['recovery']['recovery_rate_pct'] ?? 0, 2) }}%</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Top Paying Customers</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Customer</th><th class="text-right">Payments</th><th class="text-right">Amount</th></tr></thead>
                  <tbody>
                    @foreach(($report['top_customers'] ?? []) as $row)
                      <tr><td>{{ $row['customer'] ?? '' }}</td><td class="text-right">{{ number_format($row['payments'] ?? 0) }}</td><td class="text-right">{{ number_format($row['amount'] ?? 0, 2) }}</td></tr>
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
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Officer Performance</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead><tr><th>Officer</th><th class="text-right">Payments</th><th class="text-right">Amount</th><th class="text-right">On-Time Rate</th><th class="text-right">Recovery Rate</th></tr></thead>
                  <tbody>
                    @foreach(($report['officer_performance'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['officer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['payments'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['amount'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['on_time_rate'] ?? 0, 2) }}%</td>
                        <td class="text-right">{{ number_format($row['recovery_rate_pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['officer_performance'] ?? []))
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
            <div class="card-header"><strong>Loan-Level Repayment Table</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Loan Code</th>
                      <th>Customer</th>
                      <th>Product</th>
                      <th>Branch</th>
                      <th>Status</th>
                      <th class="text-right">Total Due</th>
                      <th class="text-right">Paid (Period)</th>
                      <th class="text-right">Total Paid</th>
                      <th class="text-right">Outstanding</th>
                      <th>Last Payment</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['loan_level'] ?? []) as $row)
                      <tr>
                        <td>{{ $row->loan_code ?? '' }}</td>
                        <td>{{ $row->customer ?? '' }}</td>
                        <td>{{ $row->product ?? '' }}</td>
                        <td>{{ $row->branch ?? '' }}</td>
                        <td>{{ ucfirst(str_replace('_',' ', $row->status ?? '')) }}</td>
                        <td class="text-right">{{ number_format($row->total_due ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row->period_paid ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row->total_paid ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row->outstanding ?? 0, 2) }}</td>
                        <td>{{ $row->last_payment_date ?? '' }}</td>
                      </tr>
                    @endforeach
                    @if(($report['loan_level'] ?? null) && ($report['loan_level']->total() ?? 0) === 0)
                      <tr><td colspan="10" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
              <div class="p-2 small-pagination">@if($report['loan_level'] ?? null) {{ $report['loan_level']->onEachSide(1)->appends(request()->query())->links('pagination::bootstrap-4') }} @endif</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Installment-Level Tracking</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Customer</th>
                      <th class="text-right">Installment #</th>
                      <th>Due Date</th>
                      <th>Paid Date</th>
                      <th>Status</th>
                      <th class="text-right">Total Due</th>
                      <th class="text-right">Amount Paid</th>
                      <th class="text-right">Outstanding</th>
                      <th class="text-right">Paid (Period)</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['installment_level'] ?? []) as $row)
                      <tr>
                        <td>{{ $row->customer ?? '' }}</td>
                        <td class="text-right">{{ number_format($row->installment_number ?? 0) }}</td>
                        <td>{{ $row->due_date ?? '' }}</td>
                        <td>{{ $row->paid_date ?? '' }}</td>
                        <td>{{ ucfirst(str_replace('_',' ', $row->status ?? '')) }}</td>
                        <td class="text-right">{{ number_format($row->total_due ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row->amount_paid ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row->outstanding_amount ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row->paid_in_period ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(($report['installment_level'] ?? null) && ($report['installment_level']->total() ?? 0) === 0)
                      <tr><td colspan="9" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
              <div class="p-2 small-pagination">@if($report['installment_level'] ?? null) {{ $report['installment_level']->onEachSide(1)->appends(request()->query())->links('pagination::bootstrap-4') }} @endif</div>
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
  <style>
    .small-pagination nav { margin: 0 !important; }
    .small-pagination .pagination { margin: 0 !important; }
    .small-pagination .page-link { padding: .15rem .45rem !important; font-size: .75rem !important; line-height: 1.1 !important; }
    .small-pagination .page-item { margin: 0 !important; }
  </style>
@endpush

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const t = @json($t['chart'] ?? []);
  const labels = t.labels || [];
  const amounts = t.amount || [];
  const payments = t.payments || [];

  const el = document.getElementById('repaymentTrendsChart');
  if (!el) return;

  new Chart(el, {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: 'Amount', data: amounts, borderColor: 'rgba(54, 162, 235, 1)', backgroundColor: 'rgba(54, 162, 235, 0.12)', fill: true, tension: 0.3 },
        { label: 'Payments', data: payments, borderColor: 'rgba(75, 192, 192, 1)', backgroundColor: 'rgba(75, 192, 192, 0.08)', fill: false, tension: 0.3, yAxisID: 'y1' },
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: { beginAtZero: true },
        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
      },
      plugins: { legend: { position: 'bottom' } }
    }
  });
})();
</script>
@endsection
