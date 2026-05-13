@extends('adminlte::page')

@section('title', 'Expected vs Collected Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-balance-scale"></i> Expected vs Collected Report</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-balance-scale"></i> Expected vs Collected</h1>
      <div class="small text-light-50">Period: {{ $startDate ?? '' }} to {{ $endDate ?? '' }}</div>
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
    <li class="breadcrumb-item active" aria-current="page">Expected vs Collected</li>
  </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
  <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
    <div class="card-body">

      <form method="get" action="{{ route('reports.expected_vs_collected.index') }}" class="mb-3">
        <div class="bg-light p-2 rounded border">
          <div class="form-row">
            <div class="form-group col-md-3">
              <label for="start_date">Start Date</label>
              <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" value="{{ $startDate ?? '' }}" required>
            </div>
            <div class="form-group col-md-3">
              <label for="end_date">End Date</label>
              <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" value="{{ $endDate ?? '' }}" required>
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
            <div class="form-group col-md-3">
              <label for="loan_officer_id">Loan Officer (Disbursement Processor)</label>
              <select class="form-control form-control-sm" id="loan_officer_id" name="loan_officer_id">
                <option value="">All Officers</option>
                @foreach(($officers ?? []) as $o)
                  <option value="{{ $o->id }}" {{ (request('loan_officer_id') == $o->id) ? 'selected' : '' }}>{{ $o->name }}</option>
                @endforeach
              </select>
            </div>
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
              <label for="group_by">Group By</label>
              <select class="form-control form-control-sm" id="group_by" name="group_by">
                @foreach(['auto' => 'Auto', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $k => $lbl)
                  <option value="{{ $k }}" {{ request('group_by', 'auto') == $k ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-2">
              <label for="per_page">Per Page</label>
              <select class="form-control form-control-sm" id="per_page" name="per_page">
                @foreach([10,25,50,100,200] as $pp)
                  <option value="{{ $pp }}" {{ (int)request('per_page',25) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                @endforeach
              </select>
            </div>
            <!-- <div class="form-group col-md-2">
              <label for="customer_id">Customer ID (optional)</label>
              <input type="number" class="form-control form-control-sm" id="customer_id" name="customer_id" value="{{ request('customer_id') }}">
              @if(!empty($customer))
                <div class="small text-muted mt-1">Selected: {{ $customer->name }}</div>
              @endif
            </div> -->
          </div>

          <div class="form-row">
            <div class="form-group col-md-12 d-flex align-items-end">
              <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply Filters</button>
              <a href="{{ route('reports.expected_vs_collected.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
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
        $t = $report['period_breakdown']['chart'] ?? [];
      @endphp

      <div class="row mb-3">
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-success">
            <div class="card-body">
              <div class="text-uppercase small">Total Expected</div>
              <div class="h4 mb-0">{{ number_format($s['total_expected'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-info">
            <div class="card-body">
              <div class="text-uppercase small">Total Collected</div>
              <div class="h4 mb-0">{{ number_format($s['total_collected'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-danger">
            <div class="card-body">
              <div class="text-uppercase small">Variance (Shortfall)</div>
              <div class="h4 mb-0">{{ number_format($s['total_variance'] ?? 0, 2) }}</div>
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
      </div>

      <div class="row mb-3">
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-secondary">
            <div class="card-body">
              <div class="text-uppercase small">Due Installments</div>
              <div class="h4 mb-0">{{ number_format($s['total_due_installments'] ?? 0) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-dark">
            <div class="card-body">
              <div class="text-uppercase small">Paid Installments</div>
              <div class="h4 mb-0">{{ number_format($s['total_paid_installments'] ?? 0) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-6 mb-3">
          <div class="card">
            <div class="card-body">
              <div class="text-uppercase small text-muted">Arrears Contribution (Shortfall)</div>
              <div class="h5 mb-0">{{ number_format($report['arrears_contribution']['shortfall'] ?? 0, 2) }} ({{ number_format($report['arrears_contribution']['shortfall_pct_of_expected'] ?? 0, 2) }}%)</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Expected vs Collected Trend ({{ ucfirst($report['period_breakdown']['group_by'] ?? 'auto') }})</strong></div>
            <div class="card-body">
              <canvas id="evcTrend" height="90"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Period Breakdown</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Period</th>
                      <th class="text-right">Expected</th>
                      <th class="text-right">Collected</th>
                      <th class="text-right">Variance</th>
                      <th class="text-right">Rate (%)</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['period_breakdown']['rows'] ?? []) as $r)
                      @php
                        $p = $r['period'] ?? '';
                        $gb = $report['period_breakdown']['group_by'] ?? 'auto';
                        $periodUrl = null;
                        if ($gb === 'daily' && !empty($p)) {
                          $periodUrl = route('reports.expected_vs_collected.index', array_merge(request()->query(), ['start_date' => $p, 'end_date' => $p, 'page' => 1, 'installments_page' => 1]));
                        }
                        if ($gb === 'monthly' && !empty($p)) {
                          try {
                            $sd = \Carbon\Carbon::createFromFormat('Y-m', $p)->startOfMonth()->toDateString();
                            $ed = \Carbon\Carbon::createFromFormat('Y-m', $p)->endOfMonth()->toDateString();
                            $periodUrl = route('reports.expected_vs_collected.index', array_merge(request()->query(), ['start_date' => $sd, 'end_date' => $ed, 'page' => 1, 'installments_page' => 1]));
                          } catch (\Throwable $e) {
                            $periodUrl = null;
                          }
                        }
                      @endphp
                      <tr>
                        <td>
                          @if(!empty($periodUrl))
                            <a href="{{ $periodUrl }}">{{ $p }}</a>
                          @else
                            {{ $p }}
                          @endif
                        </td>
                        <td class="text-right">{{ number_format($r['expected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                        <td class="text-right font-weight-bold">{{ number_format($r['variance'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['collection_rate_pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['period_breakdown']['rows'] ?? []))
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
        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-header"><strong>By Loan Product</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th class="text-right">Expected</th>
                      <th class="text-right">Collected</th>
                      <th class="text-right">Variance</th>
                      <th class="text-right">Rate</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_product'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['product'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['expected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['variance'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['collection_rate_pct'] ?? 0, 2) }}%</td>
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

        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-header"><strong>By Branch</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Branch</th>
                      <th class="text-right">Expected</th>
                      <th class="text-right">Collected</th>
                      <th class="text-right">Variance</th>
                      <th class="text-right">Rate</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_branch'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['branch'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['expected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['variance'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['collection_rate_pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['by_branch'] ?? []))
                      <tr><td colspan="5" class="text-center text-muted p-3">No data</td></tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-header"><strong>By Loan Officer</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Officer</th>
                      <th class="text-right">Expected</th>
                      <th class="text-right">Collected</th>
                      <th class="text-right">Variance</th>
                      <th class="text-right">Rate</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_officer'] ?? []) as $r)
                      <tr>
                        <td>{{ $r['officer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['expected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['variance'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['collection_rate_pct'] ?? 0, 2) }}%</td>
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
            <div class="card-header"><strong>Top Performing Loans (Top 10)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Loan</th>
                      <th>Customer</th>
                      <th class="text-right">Expected</th>
                      <th class="text-right">Collected</th>
                      <th class="text-right">Rate</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['top_underperforming']['top'] ?? []) as $r)
                      <tr>
                        <td><a href="{{ route('loans.loans.show', ['loan' => $r['loan_code'] ?? '']) }}">{{ $r['loan_code'] ?? '' }}</a></td>
                        <td>{{ $r['customer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['expected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['collection_rate_pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['top_underperforming']['top'] ?? []))
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
            <div class="card-header"><strong>Underperforming Loans (Bottom 10)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Loan</th>
                      <th>Customer</th>
                      <th class="text-right">Expected</th>
                      <th class="text-right">Collected</th>
                      <th class="text-right">Rate</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['top_underperforming']['underperforming'] ?? []) as $r)
                      <tr>
                        <td><a href="{{ route('loans.loans.show', ['loan' => $r['loan_code'] ?? '']) }}">{{ $r['loan_code'] ?? '' }}</a></td>
                        <td>{{ $r['customer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['expected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['collection_rate_pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['top_underperforming']['underperforming'] ?? []))
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
            <div class="card-header"><strong>Missed Collections (Due but Not Paid)</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Loan</th>
                      <th>Customer</th>
                      <th class="text-right">Expected</th>
                      <th class="text-right">Collected</th>
                      <th class="text-right">Missed</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['missed_collections'] ?? []) as $r)
                      <tr>
                        <td><a href="{{ route('loans.loans.show', ['loan' => $r['loan_code'] ?? '']) }}">{{ $r['loan_code'] ?? '' }}</a></td>
                        <td>{{ $r['customer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['expected'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($r['collected'] ?? 0, 2) }}</td>
                        <td class="text-right font-weight-bold">{{ number_format($r['missed_amount'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['missed_collections'] ?? []))
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
            <div class="card-header"><strong>Partial Payments</strong></div>
            <div class="card-body">
              <div><strong>Partial installments:</strong> {{ number_format($report['partial_payments']['partial_installments'] ?? 0) }}</div>
              <div><strong>Total remaining amount:</strong> {{ number_format($report['partial_payments']['remaining_amount'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card card-outline card-danger mb-3">
        <div class="card-header">
          <h3 class="card-title">Loan-Level Performance</h3>
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
                  <th class="text-right">Expected</th>
                  <th class="text-right">Collected</th>
                  <th class="text-right">Variance</th>
                  <th class="text-right">Rate</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @php $loans = $report['loan_level'] ?? null; @endphp
                @if($loans instanceof \Illuminate\Pagination\LengthAwarePaginator)
                  @foreach($loans as $r)
                    @php
                      $expected = (float) ($r->expected ?? 0);
                      $collected = (float) ($r->collected ?? 0);
                      $rate = $expected > 0 ? round(($collected / $expected) * 100, 2) : 0;
                    @endphp
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
                      <td class="text-right">{{ number_format($expected, 2) }}</td>
                      <td class="text-right">{{ number_format($collected, 2) }}</td>
                      <td class="text-right font-weight-bold">
                        <a href="{{ route('reports.expected_vs_collected.index', array_merge(request()->query(), ['loan_id' => $r->loan_id, 'installments_page' => 1])) }}">{{ number_format((float) ($r->variance ?? 0), 2) }}</a>
                      </td>
                      <td class="text-right">{{ number_format($rate, 2) }}%</td>
                      <td>{{ $r->loan_status ?? '' }}</td>
                    </tr>
                  @endforeach
                @else
                  <tr><td colspan="10" class="text-center text-muted p-3">No data</td></tr>
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

      <div class="card card-outline card-secondary mb-3">
        <div class="card-header">
          <h3 class="card-title">Installment-Level Comparison (In Period Only)</h3>
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
                  <th class="text-right">Expected</th>
                  <th class="text-right">Collected</th>
                  <th class="text-right">Variance</th>
                  <th>Status</th>
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
                      <td class="text-right">{{ number_format($r->expected ?? 0, 2) }}</td>
                      <td class="text-right">{{ number_format($r->collected ?? 0, 2) }}</td>
                      <td class="text-right font-weight-bold">{{ number_format($r->variance ?? 0, 2) }}</td>
                      <td>{{ $r->status ?? '' }}</td>
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
          <div class="card-footer mt-3 d-flex justify-content-center">
            {{ $inst->links() }}
          </div>
        @endif
      </div>

      {{-- Calculation Documentation --}}
      <div class="card card-outline card-info mt-4">
        <div class="card-header" data-toggle="collapse" data-target="#calculationDocs" style="cursor: pointer;">
          <h3 class="card-title"><i class="fas fa-info-circle"></i> How are these calculations performed?</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-tool"><i class="fas fa-chevron-down"></i></button>
          </div>
        </div>
        <div id="calculationDocs" class="collapse">
          <div class="card-body">
            <h5>Expected vs Collected Report Methodology</h5>
            <ul>
              <li><strong>Expected Amount:</strong> Sum of <code>loan_installments.total_due</code> for installments with due dates within the selected period. Only active installments (is_active=true) are included.</li>
              <li><strong>Collected Amount:</strong> Sum of allocated payment amounts from <code>loan_payment_allocations</code> (principal + interest + fees + penalties) where:
                <ul>
                  <li>The parent payment is confirmed (status='confirmed')</li>
                  <li>Payment date falls within the selected period</li>
                  <li>The allocation is applied to an installment with due date within the selected period</li>
                </ul>
              </li>
              <li><strong>Variance:</strong> Expected minus Collected. Positive values indicate shortfall.</li>
              <li><strong>Collection Rate:</strong> (Collected / Expected) × 100. Calculated per loan, product, branch, officer, and period.</li>
              <li><strong>Paid Installments:</strong> Count of installments where allocated amount >= expected amount.</li>
              <li><strong>Partial Payments:</strong> Installments with some allocation but less than expected amount.</li>
              <li><strong>Missed Collections:</strong> Loans with due installments in period but zero allocated payments.</li>
            </ul>
            <h6>Active Portfolio Scope</h6>
            <p>Loans included in this report must be:</p>
            <ul>
              <li>Active (is_active = true)</li>
              <li>Not written off (is_written_off = false)</li>
              <li>Status: disbursed, partially_paid, or defaulted</li>
            </ul>
            <p class="text-muted">Note: This methodology ensures consistency across all reports using loan_installments as the single source of truth for expected amounts and payment allocations for collected amounts.</p>
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
    const labels = @json($t['labels'] ?? []);
    const expected = @json($t['expected'] ?? []);
    const collected = @json($t['collected'] ?? []);

    const ctx = document.getElementById('evcTrend');
    if (ctx) {
      new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [
            { label: 'Expected', data: expected, borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,.10)', tension: 0.25, fill: true },
            { label: 'Collected', data: collected, borderColor: '#17a2b8', backgroundColor: 'rgba(23,162,184,.10)', tension: 0.25, fill: true },
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
