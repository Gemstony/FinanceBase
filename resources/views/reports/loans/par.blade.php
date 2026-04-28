@extends('adminlte::page')

@section('title', 'PAR Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-shield-alt"></i> Portfolio At Risk (PAR) Report</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-shield-alt"></i> PAR Report</h1>
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
    <li class="breadcrumb-item active" aria-current="page">PAR Report</li>
  </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
  <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
    <div class="card-body">

      <form method="get" action="{{ route('reports.par.index') }}" class="mb-3">
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
                @foreach(($loanProducts ?? []) as $p)
                  @php
                    $pid = is_object($p) ? ($p->id ?? null) : ($p['id'] ?? null);
                    $pname = is_object($p) ? ($p->name ?? '') : ($p['name'] ?? '');
                  @endphp
                  <option value="{{ $pid }}" {{ (request('loan_product_id') == $pid) ? 'selected' : '' }}>{{ $pname }}</option>
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
                  <option value="{{ $oid }}" {{ (request('loan_officer_id') == $oid) ? 'selected' : '' }}>{{ $oname }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="form-row">
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
            <div class="form-group col-md-2">
              <label for="per_page">Per Page</label>
              <select class="form-control form-control-sm" id="per_page" name="per_page">
                @foreach([10,25,50,100,200] as $pp)
                  <option value="{{ $pp }}" {{ (int)request('per_page',25) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-2 d-flex align-items-end">
              <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply</button>
              <a href="{{ route('reports.par.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
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
        $trend = $report['trends'] ?? [];
        $hr = $report['high_risk_portfolio'] ?? [];
        $c = $report['concentration'] ?? [];
        $w = $report['writeoff_exposure'] ?? [];
        $rec = $report['recovery_impact'] ?? [];
      @endphp

      <div class="row mb-3">
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-success">
            <div class="card-body">
              <div class="text-uppercase small">Total Portfolio Outstanding</div>
              <div class="h4 mb-0">{{ number_format($s['total_portfolio_outstanding'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-warning">
            <div class="card-body">
              <div class="text-uppercase small">Total At-Risk Amount</div>
              <div class="h4 mb-0">{{ number_format($s['total_at_risk_amount'] ?? 0, 2) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-info">
            <div class="card-body">
              <div class="text-uppercase small">PAR30</div>
              <div class="h4 mb-0">{{ number_format($s['par30_pct'] ?? 0, 2) }}%</div>
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="card text-white bg-danger">
            <div class="card-body">
              <div class="text-uppercase small">NPL Ratio (DPD &gt; 90)</div>
              <div class="h4 mb-0">{{ number_format($s['npl_ratio_pct'] ?? 0, 2) }}%</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>PAR Trends</strong></div>
            <div class="card-body">
              <canvas id="parTrend" height="90"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>PAR Aging Analysis</strong></div>
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
                    @foreach(($report['aging_buckets'] ?? []) as $row)
                      @php
                        $bucket = $row['bucket'] ?? '';
                        $linkParams = request()->query();
                        unset($linkParams['page']);
                        if ($bucket === 'Current') { $linkParams['dpd_min'] = 0; $linkParams['dpd_max'] = 0; }
                        if ($bucket === '1-30') { $linkParams['dpd_min'] = 1; $linkParams['dpd_max'] = 30; }
                        if ($bucket === '31-60') { $linkParams['dpd_min'] = 31; $linkParams['dpd_max'] = 60; }
                        if ($bucket === '61-90') { $linkParams['dpd_min'] = 61; $linkParams['dpd_max'] = 90; }
                        if ($bucket === '90+') { $linkParams['dpd_min'] = 91; unset($linkParams['dpd_max']); }
                      @endphp
                      <tr>
                        <td><a href="{{ route('reports.par.index', $linkParams) }}">{{ $bucket }}</a></td>
                        <td class="text-right">{{ number_format($row['loans'] ?? 0) }}</td>
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
            <div class="card-header"><strong>Summary Ratios</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <tbody>
                    <tr><td>PAR30</td><td class="text-right">{{ number_format($s['par30_pct'] ?? 0, 2) }}%</td></tr>
                    <tr><td>PAR60</td><td class="text-right">{{ number_format($s['par60_pct'] ?? 0, 2) }}%</td></tr>
                    <tr><td>PAR90</td><td class="text-right">{{ number_format($s['par90_pct'] ?? 0, 2) }}%</td></tr>
                    <tr><td>NPL Loans</td><td class="text-right">{{ number_format($s['npl_loans'] ?? 0) }}</td></tr>
                    <tr><td>NPL Outstanding</td><td class="text-right">{{ number_format($s['npl_outstanding'] ?? 0, 2) }}</td></tr>
                    <tr><td>NPL Ratio</td><td class="text-right">{{ number_format($s['npl_ratio_pct'] ?? 0, 2) }}%</td></tr>
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
            <div class="card-header"><strong>PAR by Loan Product</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th class="text-right">Total</th>
                      <th class="text-right">PAR30</th>
                      <th class="text-right">PAR60</th>
                      <th class="text-right">PAR90</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_product'] ?? []) as $row)
                      @php
                        $linkParams = request()->query();
                        unset($linkParams['page']);
                        $linkParams['loan_product_id'] = $row['product_id'] ?? null;
                      @endphp
                      <tr>
                        <td><a href="{{ route('reports.par.index', array_filter($linkParams, fn ($v) => $v !== null && $v !== '')) }}">{{ $row['product'] ?? '' }}</a></td>
                        <td class="text-right">{{ number_format($row['total_portfolio'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['par30_pct'] ?? 0, 2) }}%</td>
                        <td class="text-right">{{ number_format($row['par60_pct'] ?? 0, 2) }}%</td>
                        <td class="text-right">{{ number_format($row['par90_pct'] ?? 0, 2) }}%</td>
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
            <div class="card-header"><strong>PAR by Branch</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Branch</th>
                      <th class="text-right">Total</th>
                      <th class="text-right">PAR30</th>
                      <th class="text-right">PAR90</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_branch'] ?? []) as $row)
                      @php
                        $linkParams = request()->query();
                        unset($linkParams['page']);
                        $linkParams['subshop_id'] = $row['subshop_id'] ?? null;
                      @endphp
                      <tr>
                        <td><a href="{{ route('reports.par.index', array_filter($linkParams, fn ($v) => $v !== null && $v !== '')) }}">{{ $row['branch'] ?? '' }}</a></td>
                        <td class="text-right">{{ number_format($row['total_portfolio'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['par30_pct'] ?? 0, 2) }}%</td>
                        <td class="text-right">{{ number_format($row['par90_pct'] ?? 0, 2) }}%</td>
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

        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-header"><strong>PAR by Officer</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Officer</th>
                      <th class="text-right">Total</th>
                      <th class="text-right">PAR30</th>
                      <th class="text-right">PAR90</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['by_officer'] ?? []) as $row)
                      @php
                        $linkParams = request()->query();
                        unset($linkParams['page']);
                        $linkParams['loan_officer_id'] = $row['officer_id'] ?? null;
                      @endphp
                      <tr>
                        <td><a href="{{ route('reports.par.index', array_filter($linkParams, fn ($v) => $v !== null && $v !== '')) }}">{{ $row['officer'] ?? '' }}</a></td>
                        <td class="text-right">{{ number_format($row['total_portfolio'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['par30_pct'] ?? 0, 2) }}%</td>
                        <td class="text-right">{{ number_format($row['par90_pct'] ?? 0, 2) }}%</td>
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
            <div class="card-header"><strong>High-Risk Portfolio</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Category</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Outstanding</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Loans DPD &gt; 60</td>
                      <td class="text-right">{{ number_format($hr['over_60']['loans'] ?? 0) }}</td>
                      <td class="text-right">{{ number_format($hr['over_60']['outstanding'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                      <td>Loans DPD &gt; 90</td>
                      <td class="text-right">{{ number_format($hr['over_90']['loans'] ?? 0) }}</td>
                      <td class="text-right">{{ number_format($hr['over_90']['outstanding'] ?? 0, 2) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Top Risky Loans (Top 10)</strong></div>
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
                    @foreach(($report['top_risky_loans'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['loan_code'] ?? '' }}</td>
                        <td>{{ $row['customer'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['dpd'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($row['outstanding'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                    @if(empty($report['top_risky_loans'] ?? []))
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
            <div class="card-header"><strong>Risk Concentration (PAR30+)</strong></div>
            <div class="card-body">
              <div class="mb-2"><strong>Risk Outstanding:</strong> {{ number_format($c['risk_outstanding'] ?? 0, 2) }} ({{ number_format($c['risk_pct_of_portfolio'] ?? 0, 2) }}%)</div>

              <div class="row">
                <div class="col-12 col-md-4">
                  <div class="small text-muted mb-1">Top Customers</div>
                  <ul class="pl-3 mb-0">
                    @foreach(($c['top_customers'] ?? []) as $r)
                      <li>{{ $r['customer'] ?? '' }} - {{ number_format($r['pct_of_risk'] ?? 0, 2) }}%</li>
                    @endforeach
                    @if(empty($c['top_customers'] ?? []))
                      <li class="text-muted">No data</li>
                    @endif
                  </ul>
                </div>
                <div class="col-12 col-md-4">
                  <div class="small text-muted mb-1">Top Branches</div>
                  <ul class="pl-3 mb-0">
                    @foreach(($c['top_branches'] ?? []) as $r)
                      <li>{{ $r['branch'] ?? '' }} - {{ number_format($r['pct_of_risk'] ?? 0, 2) }}%</li>
                    @endforeach
                    @if(empty($c['top_branches'] ?? []))
                      <li class="text-muted">No data</li>
                    @endif
                  </ul>
                </div>
                <div class="col-12 col-md-4">
                  <div class="small text-muted mb-1">Top Products</div>
                  <ul class="pl-3 mb-0">
                    @foreach(($c['top_products'] ?? []) as $r)
                      <li>{{ $r['product'] ?? '' }} - {{ number_format($r['pct_of_risk'] ?? 0, 2) }}%</li>
                    @endforeach
                    @if(empty($c['top_products'] ?? []))
                      <li class="text-muted">No data</li>
                    @endif
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Write-Off Exposure</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Category</th>
                      <th class="text-right">Loans</th>
                      <th class="text-right">Outstanding</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>DPD &gt; 90</td>
                      <td class="text-right">{{ number_format($w['dpd_over_90']['loans'] ?? 0) }}</td>
                      <td class="text-right">{{ number_format($w['dpd_over_90']['outstanding'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                      <td>DPD &gt; 120</td>
                      <td class="text-right">{{ number_format($w['dpd_over_120']['loans'] ?? 0) }}</td>
                      <td class="text-right">{{ number_format($w['dpd_over_120']['outstanding'] ?? 0, 2) }}</td>
                    </tr>
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
            <div class="card-header"><strong>Recovery Impact</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <tbody>
                    <tr><td>Previous As At</td><td class="text-right">{{ $rec['previous_as_at'] ?? '' }}</td></tr>
                    <tr><td>Current As At</td><td class="text-right">{{ $rec['current_as_at'] ?? '' }}</td></tr>
                    <tr><td>Previous PAR30</td><td class="text-right">{{ number_format($rec['previous_par30_pct'] ?? 0, 2) }}%</td></tr>
                    <tr><td>Current PAR30</td><td class="text-right">{{ number_format($rec['current_par30_pct'] ?? 0, 2) }}%</td></tr>
                    <tr><td>Change (pp)</td><td class="text-right">{{ number_format($rec['par30_change_pct_points'] ?? 0, 2) }}</td></tr>
                    <tr><td>Recovered Amount</td><td class="text-right">{{ number_format($rec['recovered_amount'] ?? 0, 2) }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card">
            <div class="card-header"><strong>Portfolio Segmentation</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Segment</th>
                      <th class="text-right">Amount</th>
                      <th class="text-right">%</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($report['segmentation'] ?? []) as $row)
                      <tr>
                        <td>{{ $row['segment'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['amount'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['pct'] ?? 0, 2) }}%</td>
                      </tr>
                    @endforeach
                    @if(empty($report['segmentation'] ?? []))
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
            <div class="card-header"><strong>Loan Drill-Down (Filtered List)</strong></div>
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
                      <th class="text-right">Overdue</th>
                      <th class="text-right">DPD</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    @php $p = $report['loans'] ?? null; @endphp
                    @foreach(($p && method_exists($p,'items') ? $p->items() : []) as $r)
                      <tr>
                        <td>{{ $r->loan_code ?? '' }}</td>
                        <td>{{ $r->customer ?? '' }}</td>
                        <td>{{ $r->product ?? '' }}</td>
                        <td>{{ $r->branch ?? '' }}</td>
                        <td>{{ $r->officer ?? '' }}</td>
                        <td class="text-right">{{ number_format((float) ($r->outstanding_balance ?? 0), 2) }}</td>
                        <td class="text-right">{{ number_format((float) ($r->overdue_amount ?? 0), 2) }}</td>
                        <td class="text-right">{{ number_format((int) ($r->dpd ?? 0)) }}</td>
                        <td>{{ ucfirst(str_replace('_',' ', (string) ($r->loan_status ?? ''))) }}</td>
                      </tr>
                    @endforeach
                    @if(!$p || count($p->items() ?? []) === 0)
                      <tr><td colspan="9" class="text-center text-muted p-3">No loans found</td></tr>
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

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (function() {
    const trend = @json($report['trends'] ?? []);
    const labels = trend.labels || [];

    const ctx = document.getElementById('parTrend');
    if (ctx) {
      new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [
            {
              label: 'PAR30 (%)',
              data: trend.par30 || [],
              borderColor: '#dc3545',
              backgroundColor: 'rgba(220,53,69,.10)',
              tension: 0.25,
              fill: true,
            },
            {
              label: 'PAR60 (%)',
              data: trend.par60 || [],
              borderColor: '#0dcaf0',
              backgroundColor: 'rgba(13,202,240,.08)',
              tension: 0.25,
              fill: true,
            },
            {
              label: 'PAR90 (%)',
              data: trend.par90 || [],
              borderColor: '#6f42c1',
              backgroundColor: 'rgba(111,66,193,.08)',
              tension: 0.25,
              fill: true,
            },
            {
              label: 'At-Risk Amount',
              data: trend.at_risk_amount || [],
              borderColor: '#fd7e14',
              backgroundColor: 'rgba(253,126,20,.08)',
              tension: 0.25,
              fill: false,
              yAxisID: 'y1',
            }
          ]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: true } },
          scales: {
            y: { beginAtZero: true, title: { display: true, text: 'PAR %' } },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Amount' } }
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
