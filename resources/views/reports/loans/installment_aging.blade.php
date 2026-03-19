@extends('adminlte::page')

@section('title', 'Installment Aging Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-list"></i> Installment Aging Report</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-list"></i> Installment Aging</h1>
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
    <li class="breadcrumb-item active" aria-current="page">Installment Aging</li>
  </ol>
</nav>
@stop

@section('content')
@php
  $summary = $report['summary'] ?? [];
  $p = $report['installments'] ?? null;
  $chartBuckets = array_map(fn ($r) => (string) ($r['bucket'] ?? ''), $report['aging_buckets'] ?? []);
  $chartOutstanding = array_map(fn ($r) => (float) ($r['outstanding'] ?? 0), $report['aging_buckets'] ?? []);
@endphp

<div class="container-fluid">
  <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
    <div class="card-body">

      <form method="get" action="{{ route('reports.installment_aging.index') }}" class="mb-3">
        <div class="bg-light p-2 rounded border">
          <div class="form-row">
            <div class="form-group col-md-3">
              <label for="as_at_date">As At Date</label>
              <input type="date" class="form-control form-control-sm" id="as_at_date" name="as_at_date" value="{{ $asAtDate ?? '' }}">
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
                @foreach(($loanProducts ?? []) as $prod)
                  @php
                    $pid = is_object($prod) ? ($prod->id ?? null) : ($prod['id'] ?? null);
                    $pname = is_object($prod) ? ($prod->name ?? '') : ($prod['name'] ?? '');
                  @endphp
                  <option value="{{ $pid }}" {{ request('loan_product_id') == $pid ? 'selected' : '' }}>{{ $pname }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-3">
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
              <label for="customer">Customer (Name)</label>
              <input type="text" class="form-control form-control-sm" id="customer" name="customer" value="{{ request('customer') }}" placeholder="Search by customer name">
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
              <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply</button>
              <a href="{{ route('reports.installment_aging.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
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



      <div class="row">
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-info">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-uppercase small">Outstanding Installments</div>
                  <div class="h4 mb-0">{{ number_format($summary['total_outstanding_installments'] ?? 0) }}</div>
                </div>
                <i class="fas fa-list-ol fa-2x"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3 mb-3">
          <div class="card text-white bg-success">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-uppercase small">Outstanding Amount</div>
                  <div class="h4 mb-0">{{ number_format($summary['total_outstanding_amount'] ?? 0, 2) }}</div>
                </div>
                <i class="fas fa-coins fa-2x"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3 mb-3">
          <div class="card text-white bg-warning">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-uppercase small">Overdue Installments</div>
                  <div class="h4 mb-0">{{ number_format($summary['total_overdue_installments'] ?? 0) }}</div>
                </div>
                <i class="fas fa-exclamation-triangle fa-2x"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3 mb-3">
          <div class="card text-white bg-danger">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-uppercase small">Overdue Amount</div>
                  <div class="h4 mb-0">{{ number_format($summary['total_overdue_amount'] ?? 0, 2) }}</div>
                </div>
                <i class="fas fa-hand-holding-usd fa-2x"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3 mb-3">
          <div class="card bg-light">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-uppercase small">Average DPD</div>
                  <div class="h4 mb-0">{{ number_format($summary['avg_dpd'] ?? 0, 2) }}</div>
                </div>
                <i class="fas fa-chart-line fa-2x text-muted"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3 mb-3">
          <div class="card bg-light">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-uppercase small">Maximum DPD</div>
                  <div class="h4 mb-0">{{ number_format($summary['max_dpd'] ?? 0) }}</div>
                </div>
                <i class="fas fa-flag-checkered fa-2x text-muted"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Aging Buckets Chart</strong></div>
            <div class="card-body">
              <canvas id="installmentAgingBucketsChart" height="110"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Installment Aging Buckets</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Bucket</th>
                      <th class="text-right">Installments</th>
                      <th class="text-right">Outstanding</th>
                      <th class="text-right">%</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['aging_buckets'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['bucket'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['installments'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['outstanding'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['aging_buckets'] ?? []))
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
            <div class="card-header"><strong>Partial Payment Analysis</strong></div>
            <div class="card-body">
              @php $pp = $report['partial_payment'] ?? []; @endphp
              <div class="row">
                <div class="col-6 col-md-4 mb-2"><div class="small text-muted">Partial Installments</div><div class="h5 mb-0">{{ number_format($pp['partial_installments'] ?? 0) }}</div></div>
                <div class="col-6 col-md-4 mb-2"><div class="small text-muted">Total Partial Paid</div><div class="h5 mb-0">{{ number_format($pp['total_partial_paid_amount'] ?? 0, 2) }}</div></div>
                <div class="col-6 col-md-4 mb-2"><div class="small text-muted">Partial Outstanding</div><div class="h5 mb-0">{{ number_format($pp['total_partial_outstanding_amount'] ?? 0, 2) }}</div></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-header"><strong>Aging by Product</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th class="text-right">Current</th>
                      <th class="text-right">1-30</th>
                      <th class="text-right">31-60</th>
                      <th class="text-right">61-90</th>
                      <th class="text-right">90+</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_product'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['product'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['current'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['d1_30'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['d31_60'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['d61_90'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['d90p'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['by_product'] ?? []))
                      <tr><td colspan="6" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-header"><strong>Aging by Branch</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Branch</th>
                      <th class="text-right">Current</th>
                      <th class="text-right">1-30</th>
                      <th class="text-right">31-60</th>
                      <th class="text-right">61-90</th>
                      <th class="text-right">90+</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_branch'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['branch'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['current'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['d1_30'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['d31_60'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['d61_90'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['d90p'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['by_branch'] ?? []))
                      <tr><td colspan="6" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-header"><strong>Aging by Officer</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Officer</th>
                      <th class="text-right">Current</th>
                      <th class="text-right">1-30</th>
                      <th class="text-right">31-60</th>
                      <th class="text-right">61-90</th>
                      <th class="text-right">90+</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_officer'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['officer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['current'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['d1_30'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['d31_60'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['d61_90'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['d90p'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['by_officer'] ?? []))
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
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>High-Risk Installments (Top 10)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Loan</th>
                      <th>Customer</th>
                      <th class="text-right">Installment #</th>
                      <th class="text-right">DPD</th>
                      <th class="text-right">Outstanding</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['high_risk_installments'] ?? []) as $row)
                      <tr>
                        <td>
                          @if(!empty($row['loan_code'] ?? null))
                            <a href="{{ route('loans.loans.show', $row['loan_code']) }}" target="_blank">{{ $row['loan_code'] }}</a>
                          @else
                            {{ $row['loan_code'] ?? '' }}
                          @endif
                        </td>
                        <td>{{ $row['customer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['installment_number'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['dpd'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['outstanding_balance'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['high_risk_installments'] ?? []))
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
            <div class="card-header"><strong>Recovery Priority Segmentation</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Segment</th>
                      <th class="text-right">Installments</th>
                      <th class="text-right">Outstanding</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['recovery_segmentation'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['risk'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['installments'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['outstanding'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['recovery_segmentation'] ?? []))
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
            <div class="card-header"><strong>Missed Installments Analysis (By Loan)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Loan</th>
                      <th>Customer</th>
                      <th class="text-right">Missed</th>
                      <th class="text-right">Overdue Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['missed_installments'] ?? []) as $row)
                      <tr>
                        <td>
                          @if(!empty($row['loan_code'] ?? null))
                            <a href="{{ route('loans.loans.show', $row['loan_code']) }}" target="_blank">{{ $row['loan_code'] }}</a>
                          @else
                            {{ $row['loan_code'] ?? '' }}
                          @endif
                        </td>
                        <td>{{ $row['customer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['missed_installments'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['overdue_amount'] ?? 0, 2) }}</td>
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

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>DPD Distribution</strong></div>
            <div class="card-body">
              @php $dd = $report['dpd_distribution'] ?? []; @endphp
              <div class="row">
                <div class="col-6 mb-2"><div class="small text-muted">Average DPD</div><div class="h5 mb-0">{{ number_format($dd['avg_dpd'] ?? 0, 2) }}</div></div>
                <div class="col-6 mb-2"><div class="small text-muted">Maximum DPD</div><div class="h5 mb-0">{{ number_format($dd['max_dpd'] ?? 0) }}</div></div>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Bucket</th>
                      <th class="text-right">Installments</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($dd['distribution'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['bucket'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['installments'] ?? 0) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($dd['distribution'] ?? []))
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
            <div class="card-header"><strong>Installment-Level Aging</strong></div>
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
                      <th class="text-right">Inst #</th>
                      <th>Due Date</th>
                      <th class="text-right">Amount</th>
                      <th class="text-right">Paid</th>
                      <th class="text-right">Outstanding</th>
                      <th class="text-right">DPD</th>
                      <th>Bucket</th>
                      <th>Status</th>
                      <th>Allocation</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($p ? $p->items() : []) as $r)
                      @php
                        $loanCode = $r->loan_code ?? '';
                        $customerId = $r->customer_id ?? null;
                      @endphp
                      <tr>
                        <td>
                          @if($loanCode)
                            <a href="{{ route('loans.loans.show', $loanCode) }}" target="_blank">{{ $loanCode }}</a>
                          @else
                            {{ $loanCode }}
                          @endif
                        </td>
                        <td>
                          @if($customerId)
                            <a href="{{ route('customers.show', $customerId) }}" target="_blank">{{ $r->customer ?? '' }}</a>
                          @else
                            {{ $r->customer ?? '' }}
                          @endif
                        </td>
                        <td>{{ $r->product ?? '' }}</td>
                        <td>{{ $r->branch ?? '' }}</td>
                        <td>{{ $r->officer ?? '' }}</td>
                        <td class="text-right">{{ number_format((int) ($r->installment_number ?? 0)) }}</td>
                        <td>{{ $r->due_date ?? '' }}</td>
                        <td class="text-right">{{ number_format((float) ($r->installment_amount ?? 0), 2) }}</td>
                        <td class="text-right">{{ number_format((float) ($r->paid_amount ?? 0), 2) }}</td>
                        <td class="text-right">{{ number_format((float) ($r->outstanding_balance ?? 0), 2) }}</td>
                        <td class="text-right">{{ number_format((int) ($r->dpd ?? 0)) }}</td>
                        <td>{{ $r->aging_bucket ?? '' }}</td>
                        <td>{{ ucfirst((string) ($r->installment_status ?? '')) }}</td>
                        <td>
                          @if((int) ($r->allocation_issue ?? 0) === 1)
                            Allocation Issue
                          @else
                            OK
                          @endif
                        </td>
                      </tr>
                    @endforeach
                    @if(!$p || count($p->items() ?? []) === 0)
                      <tr><td colspan="14" class="text-center text-muted p-3">No installments found</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
            @if($p)
              <div class="card-footer">
                {{ $p->appends(request()->query())->links() }}
              </div>
            @endif
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const labels = @json($chartBuckets ?? []);
  const outstanding = @json($chartOutstanding ?? []);

  const ctx = document.getElementById('installmentAgingBucketsChart');
  if(!ctx) return;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Outstanding',
        data: outstanding,
        backgroundColor: [
          'rgba(54,162,235,0.7)',
          'rgba(255,206,86,0.7)',
          'rgba(255,159,64,0.7)',
          'rgba(255,99,132,0.7)',
          'rgba(153,102,255,0.7)'
        ],
        borderColor: [
          'rgba(54,162,235,1)',
          'rgba(255,206,86,1)',
          'rgba(255,159,64,1)',
          'rgba(255,99,132,1)',
          'rgba(153,102,255,1)'
        ],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
})();
</script>
@stop
