@extends('adminlte::page')

@section('title', 'Sales Transactions - ' . $subshop->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-exchange-alt"></i> Charts of Account</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-exchange-alt"></i> Charts of Account</h1>
                <div class="small text-light-50">Branch: {{ $subshop->name }}</div>
            </div>
            <a href="{{ route('accounting.accounting_settings.index') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-cog"></i> Settings</a>
        </div>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">



            <div class="d-flex justify-content-between align-items-center mb-2">
                <button class="btn btn-primary" data-toggle="modal" data-target="#addAccountModal">
                    <i class="fas fa-plus"></i> Add Account
                </button> 
                @can('export_sales_transactions')
                <div class="dropdown">
                    <!-- Export Dropdown -->
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="{{ route('accounting.charts_of_account.export', ['format' => 'csv'] + request()->query()) }}">
                            <i class="fas fa-file-csv mr-1 text-success"></i> CSV
                        </a>
                        <a class="dropdown-item" href="{{ route('accounting.charts_of_account.export', ['format' => 'excel'] + request()->query()) }}">
                            <i class="fas fa-file-excel mr-1 text-success"></i> Excel
                        </a>
                        <a class="dropdown-item " href="{{ route('accounting.charts_of_account.export', ['format' => 'pdf'] + request()->query()) }}" >
                            <i class="fas fa-file-pdf mr-1 text-danger"></i> PDF
                        </a>
                    </div>
                </div>
                @endcan
            </div>

            <!-- Summary Cards -->
            <div class="row mb-3">
                <div class="col-6 col-md-3">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3>{{ $total_accounts ?? 0 }}</h3>
                            <p>Total Accounts</p>
                        </div>
                        <div class="icon"><i class="fas fa-list"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>{{ $active_accounts ?? 0 }}</h3>
                            <p>Active</p>
                        </div>
                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>{{ $system_accounts ?? 0 }}</h3>
                            <p>System Accounts</p>
                        </div>
                        <div class="icon"><i class="fas fa-cogs"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small-box bg-secondary">
                        <div class="inner">
                            <h3>{{ $user_accounts ?? 0 }}</h3>
                            <p>User Accounts</p>
                        </div>
                        <div class="icon"><i class="fas fa-user"></i></div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="TransactionsTable">
                    <thead class="thead-light" style="background: linear-gradient(90deg, #f7f9fc, #eef3fb); border-bottom: 1px solid #e5ecf6;">
                        <tr>
                            <th><i class="fas fa-hashtag mr-1"></i> Account Code</th>
                            <th><i class="fas fa-file-alt mr-1"></i> Account Name</th>
                            <th><i class="fas fa-layer-group mr-1"></i> Account Class</th>
                            <th><i class="fas fa-sitemap mr-1"></i> Account Group</th>
                            <th><i class="fas fa-water mr-1"></i> Cash Flow</th>
                            <th><i class="fas fa-balance-scale mr-1"></i> Equity Impact</th>
                            <th><i class="fas fa-user mr-1"></i> Customer</th>
                            <th><i class="fas fa-cogs mr-1"></i> System</th>
                            <th><i class="fas fa-toggle-on mr-1"></i> Status</th>
                            <th><i class="fas fa-tools mr-1"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($charts_of_accounts as $charts_of_account)
                        <tr>
                            <td>{{ $charts_of_account->account_code ?? '-' }}</td>
                            <td>{{ $charts_of_account->account_name ?? '-' }}</td>
                            <td>{{ $charts_of_account->accountClass->name ?? '-' }}</td>
                            <td>{{ $charts_of_account->accountGroup->name ?? '-' }}</td>
                            <td>
                                <span class="badge badge-{{ $charts_of_account->cash_flow_impact === 'IN' ? 'success' : ($charts_of_account->cash_flow_impact === 'OUT' ? 'danger' : 'secondary') }}">
                                    {{ $charts_of_account->cash_flow_impact ?? 'NONE' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-{{ $charts_of_account->equity_impact === 'INCREASE' ? 'success' : ($charts_of_account->equity_impact === 'DECREASE' ? 'danger' : 'secondary') }}">
                                    {{ $charts_of_account->equity_impact ?? 'NONE' }}
                                </span>
                            </td>
                            <td>
                                @if($charts_of_account->is_customer_account)
                                    <span class="badge badge-info">Yes</span>
                                @else
                                    <span class="badge badge-secondary">No</span>
                                @endif
                            </td>
                            <td>
                                @if($charts_of_account->is_system_account)
                                    <span class="badge badge-warning">System</span>
                                @else
                                    <span class="badge badge-secondary">User</span>
                                @endif
                            </td>
                            <td>
                                @if(($charts_of_account->is_active ?? 1) == 1)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-danger">Inactive</span>
                                @endif
                            </td>
                            <td class="d-flex">
                                <button 
                                    class="btn btn-sm btn-info mr-2 view-account-btn"
                                    data-toggle="modal" data-target="#viewAccountModal"
                                    data-id="{{ $charts_of_account->id }}"
                                    data-account_code="{{ $charts_of_account->account_code }}"
                                    data-account_name="{{ $charts_of_account->account_name }}"
                                    data-description="{{ $charts_of_account->description ?? '' }}"
                                    data-account_class="{{ $charts_of_account->accountClass->name ?? '-' }}"
                                    data-account_group="{{ $charts_of_account->accountGroup->name ?? '-' }}"
                                    data-cash_flow_impact="{{ $charts_of_account->cash_flow_impact ?? 'NONE' }}"
                                    data-cash_flow_category="{{ $charts_of_account->cash_flow_category ?? '-' }}"
                                    data-equity_impact="{{ $charts_of_account->equity_impact ?? 'NONE' }}"
                                    data-equity_category="{{ $charts_of_account->equity_category ?? '-' }}"
                                    data-is_customer_account="{{ $charts_of_account->is_customer_account }}"
                                    data-is_system_account="{{ $charts_of_account->is_system_account }}"
                                    data-is_active="{{ $charts_of_account->is_active }}"
                                    data-created_at="{{ $charts_of_account->created_at ? $charts_of_account->created_at->format('Y-m-d H:i') : '-' }}"
                                    data-updated_at="{{ $charts_of_account->updated_at ? $charts_of_account->updated_at->format('Y-m-d H:i') : '-' }}">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button 
                                    class="btn btn-sm btn-warning mr-2 edit-account-btn"
                                    data-toggle="modal" data-target="#editAccountModal"
                                    data-id="{{ $charts_of_account->id }}"
                                    data-account_name="{{ $charts_of_account->account_name }}"
                                    data-description="{{ $charts_of_account->description ?? '' }}"
                                    data-account_class_id="{{ $charts_of_account->account_class_id }}"
                                    data-account_group_id="{{ $charts_of_account->account_group_id }}"
                                    data-cash_flow_impact="{{ $charts_of_account->cash_flow_impact ?? 'NONE' }}"
                                    data-cash_flow_category="{{ $charts_of_account->cash_flow_category ?? '' }}"
                                    data-equity_impact="{{ $charts_of_account->equity_impact ?? 'NONE' }}"
                                    data-equity_category="{{ $charts_of_account->equity_category ?? '' }}"
                                    data-is_customer_account="{{ $charts_of_account->is_customer_account }}"
                                    data-is_system_account="{{ $charts_of_account->is_system_account }}"
                                    data-is_active="{{ $charts_of_account->is_active }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                @if($charts_of_account->is_system_account)
                                    <button type="button" class="btn btn-sm btn-danger" disabled title="System accounts cannot be deleted">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('accounting.charts_of_account.destroy', $charts_of_account->id) }}" class="delete-account-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-danger delete-account-btn"><i class="fas fa-trash"></i> </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-center mt-3">
                {{ $charts_of_accounts->appends(request()->query())->links() }}
            </div>
        </div>
    </div>



</div>

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1" role="dialog" aria-labelledby="addAccountModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"> 
    <div class="modal-content">
      <div class="modal-header bg-primary">
        <h5 class="modal-title text-light" id="addAccountModalLabel">Add Chart of Account</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" action="{{ route('accounting.charts_of_account.store') }}">
        @csrf
        <div class="modal-body">
            <div class="form-group">
                <label for="account_name">Account Name *</label>
                <input type="text" name="account_name" id="account_name" class="form-control" value="{{ old('account_name') }}" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" class="form-control" rows="2">{{ old('description') }}</textarea>
            </div>



            <div class="form-group">
                <label for="account_group_id">Account Group *</label>
                <select name="account_group_id" id="account_group_id" class="form-control" required>
                    <option value="">-- Select Account Group --</option>
                    @foreach($accountGroups as $group)
                        <option value="{{ $group->id }}"
                                data-class-id="{{ $group->class_id }}"
                                data-class-name="{{ optional($group->class)->name }}{{ optional($group->class) && optional($group->class)->code ? ' ('.optional($group->class)->code.')' : '' }}"
                                {{ old('account_group_id') == $group->id ? 'selected' : '' }}>
                            {{ $group->code }}: {{ $group->name }} ({{ $group->class->name }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="account_class_name">Account Class *</label>
                <input type="text" id="account_class_name" class="form-control" value="" readonly>
                <input type="hidden" name="account_class_id" id="account_class_id" value="{{ old('account_class_id') }}" required>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="cash_flow_impact">Cash Flow Impact *</label>
                        <select name="cash_flow_impact" id="cash_flow_impact" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="IN" {{ old('cash_flow_impact') == 'IN' ? 'selected' : '' }}>IN</option>
                            <option value="OUT" {{ old('cash_flow_impact') == 'OUT' ? 'selected' : '' }}>OUT</option>
                            <option value="NONE" {{ old('cash_flow_impact') == 'NONE' ? 'selected' : '' }}>NONE</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="cash_flow_category">Cash Flow Category</label>
                        <select name="cash_flow_category" id="cash_flow_category" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="OPERATING" {{ old('cash_flow_category') == 'OPERATING' ? 'selected' : '' }}>Operating</option>
                            <option value="INVESTING" {{ old('cash_flow_category') == 'INVESTING' ? 'selected' : '' }}>Investing</option>
                            <option value="FINANCING" {{ old('cash_flow_category') == 'FINANCING' ? 'selected' : '' }}>Financing</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="equity_impact">Equity Impact *</label>
                        <select name="equity_impact" id="equity_impact" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="INCREASE" {{ old('equity_impact') == 'INCREASE' ? 'selected' : '' }}>Increase</option>
                            <option value="DECREASE" {{ old('equity_impact') == 'DECREASE' ? 'selected' : '' }}>Decrease</option>
                            <option value="NONE" {{ old('equity_impact') == 'NONE' ? 'selected' : '' }}>NONE</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="equity_category">Equity Category</label>
                        <select name="equity_category" id="equity_category" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="CAPITAL" {{ old('equity_category') == 'CAPITAL' ? 'selected' : '' }}>Capital</option>
                            <option value="RETAINED_EARNINGS" {{ old('equity_category') == 'RETAINED_EARNINGS' ? 'selected' : '' }}>Retained Earnings</option>
                            <option value="RESERVES" {{ old('equity_category') == 'RESERVES' ? 'selected' : '' }}>Reserves</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="is_customer_account_create" name="is_customer_account" value="1" {{ old('is_customer_account') ? 'checked' : '' }}>
                <label class="form-check-label" for="is_customer_account_create">Customer Account</label>
            </div>

            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="is_system_account_create" name="is_system_account" value="1" {{ old('is_system_account') ? 'checked' : '' }}>
                <label class="form-check-label" for="is_system_account_create">System Account</label>
            </div>

            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="is_active_create" name="is_active" value="1" {{ old('is_active') ? 'checked' : 'checked' }}>
                <label class="form-check-label" for="is_active_create">Active</label>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
  </div>

<!-- Edit Account Modal -->
<div class="modal fade" id="editAccountModal" tabindex="-1" role="dialog" aria-labelledby="editAccountModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary">
        <h5 class="modal-title text-light" id="editAccountModalLabel">Edit Chart of Account</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" id="editAccountForm">
        @csrf
        @method('PUT')
        <div class="modal-body">
            <div class="form-group">
                <label for="edit_account_name">Account Name *</label>
                <input type="text" name="account_name" id="edit_account_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="edit_description">Description</label>
                <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
            </div>

            <div class="form-group">
                <label for="edit_account_class_name">Account Class *</label>
                <input type="text" id="edit_account_class_name" class="form-control" value="" readonly>
                <input type="hidden" name="account_class_id" id="edit_account_class_id" value="" required>
            </div>

            <div class="form-group">
                <label for="edit_account_group_id">Account Group *</label>
                <select name="account_group_id" id="edit_account_group_id" class="form-control" required>
                    <option value="">-- Select Account Group --</option>
                    @foreach($accountGroups as $group)
                        <option value="{{ $group->id }}"
                                data-class-id="{{ $group->class_id }}"
                                data-class-name="{{ optional($group->class)->name }}{{ optional($group->class) && optional($group->class)->code ? ' ('.optional($group->class)->code.')' : '' }}">
                            {{ $group->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="edit_cash_flow_impact">Cash Flow Impact *</label>
                        <select name="cash_flow_impact" id="edit_cash_flow_impact" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="IN">IN</option>
                            <option value="OUT">OUT</option>
                            <option value="NONE">NONE</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="edit_cash_flow_category">Cash Flow Category</label>
                        <select name="cash_flow_category" id="edit_cash_flow_category" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="OPERATING">Operating</option>
                            <option value="INVESTING">Investing</option>
                            <option value="FINANCING">Financing</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="edit_equity_impact">Equity Impact *</label>
                        <select name="equity_impact" id="edit_equity_impact" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="INCREASE">Increase</option>
                            <option value="DECREASE">Decrease</option>
                            <option value="NONE">NONE</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="edit_equity_category">Equity Category</label>
                        <select name="equity_category" id="edit_equity_category" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="CAPITAL">Capital</option>
                            <option value="RETAINED_EARNINGS">Retained Earnings</option>
                            <option value="RESERVES">Reserves</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="edit_is_customer_account" name="is_customer_account" value="1">
                <label class="form-check-label" for="edit_is_customer_account">Customer Account</label>
            </div>

            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="edit_is_system_account" name="is_system_account" value="1">
                <label class="form-check-label" for="edit_is_system_account">System Account</label>
            </div>

            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" value="1">
                <label class="form-check-label" for="edit_is_active">Active</label>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
  </div>

<!-- View Account Modal -->
<div class="modal fade" id="viewAccountModal" tabindex="-1" role="dialog" aria-labelledby="viewAccountModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="viewAccountModalLabel">
          <i class="fas fa-eye mr-2"></i>Chart of Account Details
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <div class="card border-left-info shadow-sm h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Account Code</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="view_account_code">-</div>
                  </div>
                  <div class="col-auto">
                    <i class="fas fa-hashtag fa-2x text-gray-300"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card border-left-primary shadow-sm h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Account Name</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="view_account_name">-</div>
                  </div>
                  <div class="col-auto">
                    <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row mt-3">
          <div class="col-12">
            <div class="card border-left-secondary shadow-sm py-2">
              <div class="card-body">
                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Description</div>
                <div class="text-gray-800" id="view_description">-</div>
              </div>
            </div>
          </div>
        </div>

        <div class="row mt-3">
          <div class="col-md-6">
            <div class="card border-left-success shadow-sm h-100 py-2">
              <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Account Class</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800" id="view_account_class">-</div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card border-left-warning shadow-sm h-100 py-2">
              <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Account Group</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800" id="view_account_group">-</div>
              </div>
            </div>
          </div>
        </div>

        <div class="row mt-3">
          <div class="col-md-6">
            <div class="card border-left-info shadow-sm h-100 py-2">
              <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Cash Flow Impact</div>
                <div class="mb-2">
                  <span class="badge badge-lg" id="view_cash_flow_impact">-</span>
                </div>
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Cash Flow Category</div>
                <div class="text-gray-800" id="view_cash_flow_category">-</div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card border-left-primary shadow-sm h-100 py-2">
              <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Equity Impact</div>
                <div class="mb-2">
                  <span class="badge badge-lg" id="view_equity_impact">-</span>
                </div>
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Equity Category</div>
                <div class="text-gray-800" id="view_equity_category">-</div>
              </div>
            </div>
          </div>
        </div>

        <div class="row mt-3">
          <div class="col-md-4">
            <div class="card border-left-success shadow-sm h-100 py-2">
              <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Customer Account</div>
                <div class="mb-2">
                  <span class="badge badge-lg" id="view_is_customer_account">-</span>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-left-warning shadow-sm h-100 py-2">
              <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">System Account</div>
                <div class="mb-2">
                  <span class="badge badge-lg" id="view_is_system_account">-</span>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-left-primary shadow-sm h-100 py-2">
              <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Status</div>
                <div class="mb-2">
                  <span class="badge badge-lg" id="view_is_active">-</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row mt-3">
          <div class="col-md-6">
            <div class="card border-left-secondary shadow-sm h-100 py-2">
              <div class="card-body">
                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Created At</div>
                <div class="text-gray-800" id="view_created_at">-</div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card border-left-secondary shadow-sm h-100 py-2">
              <div class="card-body">
                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Last Updated</div>
                <div class="text-gray-800" id="view_updated_at">-</div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
    /* Responsive tweaks for summary cards */
    .small-box .inner h3{
        font-size: 1.6rem;
        line-height: 1.2;
        word-break: break-word;
        white-space: normal;
    }
    .small-box .inner p{ margin-bottom: 0; }
    /* Prevent icon overflow */
    .small-box{ position: relative; overflow: hidden; }
    .small-box .icon{ position:absolute; right:10px; top:8px; font-size:36px; opacity:.35; z-index:0; line-height:1; }
    .small-box .inner{ position: relative; z-index:1; }
    @media (max-width: 992px){ .small-box .inner h3{ font-size: 1.4rem; } }
    @media (max-width: 768px){ .small-box .inner h3{ font-size: 1.2rem; } }
    @media (max-width: 576px){ .small-box .inner h3{ font-size: 1.05rem; } .small-box .inner p{ font-size: .8rem; } .small-box .icon{ font-size:28px; right:8px; top:8px; } }
    @media (max-width: 360px){ .small-box .inner h3{ font-size: .95rem; } .small-box .icon{ font-size:24px; right:6px; top:6px; } }

    /* Border left card styles */
    .border-left-primary { border-left: 0.25rem solid #4e73df !important; }
    .border-left-success { border-left: 0.25rem solid #1cc88a !important; }
    .border-left-info { border-left: 0.25rem solid #36b9cc !important; }
    .border-left-warning { border-left: 0.25rem solid #f6c23e !important; }
    .border-left-danger { border-left: 0.25rem solid #e74a3b !important; }
    .border-left-secondary { border-left: 0.25rem solid #858796 !important; }

    .badge-lg {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
    }
    </style>
@endpush
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const flashSuccess = @json(session('success'));
    const flashError = @json(session('error'));
    if (flashSuccess) { Swal.fire({ icon:'success', title: flashSuccess, timer: 1800, timerProgressBar: true, showConfirmButton:false }); }
    if (flashError) { Swal.fire({ icon:'error', title: flashError, timer: 2200, showConfirmButton:true }); }

    const validationErrors = @json($errors->all() ?? []);
    if (validationErrors && validationErrors.length) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            html: '<ul style="text-align:left">' + validationErrors.map(function(e){ return '<li>'+e+'</li>'; }).join('') + '</ul>'
        });
    }

    // Populate Edit Modal
    $(document).on('click', '.edit-account-btn', function(){
        const id = $(this).data('id');
        $('#editAccountForm').attr('action', `{{ url('/accounting/charts_of_account') }}/${id}`);
        $('#edit_account_name').val($(this).data('account_name'));
        $('#edit_description').val($(this).data('description'));
        // Set group then sync class from option data
        $('#edit_account_group_id').val($(this).data('account_group_id')).trigger('change');
        $('#edit_cash_flow_impact').val($(this).data('cash_flow_impact'));
        $('#edit_cash_flow_category').val($(this).data('cash_flow_category'));
        $('#edit_equity_impact').val($(this).data('equity_impact'));
        $('#edit_equity_category').val($(this).data('equity_category'));
        $('#edit_is_customer_account').prop('checked', String($(this).data('is_customer_account')) === '1');
        $('#edit_is_system_account').prop('checked', String($(this).data('is_system_account')) === '1');
        $('#edit_is_active').prop('checked', String($(this).data('is_active')) === '1');
    });

    // Populate View Modal
    $(document).on('click', '.view-account-btn', function(){
        $('#view_account_code').text($(this).data('account_code'));
        $('#view_account_name').text($(this).data('account_name'));
        $('#view_description').text($(this).data('description'));
        $('#view_account_class').text($(this).data('account_class'));
        $('#view_account_group').text($(this).data('account_group'));
        
        // Cash Flow Impact Badge
        const cashFlowImpact = $(this).data('cash_flow_impact');
        const cashFlowBadge = $('#view_cash_flow_impact');
        cashFlowBadge.text(cashFlowImpact);
        cashFlowBadge.removeClass('badge-success badge-danger badge-secondary')
            .addClass(cashFlowImpact === 'IN' ? 'badge-success' : (cashFlowImpact === 'OUT' ? 'badge-danger' : 'badge-secondary'));
        
        $('#view_cash_flow_category').text($(this).data('cash_flow_category'));
        
        // Equity Impact Badge
        const equityImpact = $(this).data('equity_impact');
        const equityBadge = $('#view_equity_impact');
        equityBadge.text(equityImpact);
        equityBadge.removeClass('badge-success badge-danger badge-secondary')
            .addClass(equityImpact === 'INCREASE' ? 'badge-success' : (equityImpact === 'DECREASE' ? 'badge-danger' : 'badge-secondary'));
        
        $('#view_equity_category').text($(this).data('equity_category'));
        
        // Customer Account Badge
        const isCustomer = String($(this).data('is_customer_account')) === '1';
        const customerBadge = $('#view_is_customer_account');
        customerBadge.text(isCustomer ? 'YES' : 'NO');
        customerBadge.removeClass('badge-success badge-secondary')
            .addClass(isCustomer ? 'badge-success' : 'badge-secondary');
        
        // System Account Badge
        const isSystem = String($(this).data('is_system_account')) === '1';
        const systemBadge = $('#view_is_system_account');
        systemBadge.text(isSystem ? 'SYSTEM' : 'USER');
        systemBadge.removeClass('badge-success badge-secondary')
            .addClass(isSystem ? 'badge-secondary' : 'badge-success');
        
        // Status Badge
        const isActive = String($(this).data('is_active')) === '1';
        const activeBadge = $('#view_is_active');
        activeBadge.text(isActive ? 'ACTIVE' : 'INACTIVE');
        activeBadge.removeClass('badge-success badge-danger')
            .addClass(isActive ? 'badge-success' : 'badge-danger');
        
        $('#view_created_at').text($(this).data('created_at'));
        $('#view_updated_at').text($(this).data('updated_at'));
    });

    // Delete confirmation
    $(document).on('click', '.delete-account-btn', function(){
        const form = $(this).closest('form');
        Swal.fire({
            title: 'Delete this account?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it'
        }).then((result) => {
            if (result.isConfirmed) {
                form.trigger('submit');
            }
        });
    });
});

$(function () {
    // Initialize DataTable with guard against double init
    var $txTable = $('#TransactionsTable');
    if ($.fn.DataTable.isDataTable($txTable)) {
        $txTable.DataTable().clear().destroy();
    }
    $txTable.DataTable({
        "order": [],
        "pageLength": 15,
        "language": {
            "search": "Search accounts:",
            "lengthMenu": "Show _MENU_ accounts per page",
            "zeroRecords": "No accounts found",
            "emptyTable": "No accounts available",
            "info": "Showing _START_ to _END_ of _TOTAL_ accounts",
            "infoEmpty": "No accounts available",
            "infoFiltered": "(filtered from _MAX_ total accounts)"
        }
    });

    function syncClassFromGroup(groupSelector, classIdSelector, classNameSelector){
        const $sel = $(groupSelector);
        const $opt = $sel.find('option:selected');
        const clsId = $opt.data('class-id') || '';
        const clsName = $opt.data('class-name') || '';
        $(classIdSelector).val(clsId);
        $(classNameSelector).val(clsName);
    }

    // Add modal: when group changes, set class hidden + display
    $('#account_group_id').on('change', function(){
        syncClassFromGroup('#account_group_id', '#account_class_id', '#account_class_name');
    });
    // On load, if there's a selected group (old input), sync once
    syncClassFromGroup('#account_group_id', '#account_class_id', '#account_class_name');

    // Edit modal: sync when the group changes
    $('#edit_account_group_id').on('change', function(){
        syncClassFromGroup('#edit_account_group_id', '#edit_account_class_id', '#edit_account_class_name');
    });
});
</script>
@stop