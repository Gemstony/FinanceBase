@extends('adminlte::page')

@section('title', 'View Loan Product - ' . ($loanProduct->name ?? ''))

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-eye"></i> View Loan Product</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-eye"></i> View Product</h1>
                <div class="small text-light-50">Shop: {{ $subshop->name }}</div>
            </div>
            <div class="d-flex">
                <a href="{{ route('loans.loan_products.index') }}" class="btn btn-outline-light mr-2"><i class="fas fa-arrow-left"></i> Back</a>
                <a href="{{ route('loans.loan_products.edit', $loanProduct->id) }}" class="btn btn-light"><i class="fas fa-edit"></i> Edit</a>
            </div>
        </div>
    </div>
@stop

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="h4 mb-1">{{ $loanProduct->name }}</div>
                    <div class="text-muted">Code: <span class="badge badge-secondary">{{ $loanProduct->code }}</span></div>
                    @if($loanProduct->description)
                        <div class="mt-2 text-muted">{{ $loanProduct->description }}</div>
                    @endif
                </div>
                <div class="text-right">
                    @if($loanProduct->is_active)
                        <span class="badge badge-success">Active</span>
                    @else
                        <span class="badge badge-secondary">Inactive</span>
                    @endif
                    @if(!$loanProduct->is_visible)
                        <span class="badge badge-dark">Hidden</span>
                    @else
                        <span class="badge badge-primary">Visible</span>
                    @endif
                    @if($loanProduct->supports_collateral)
                        <span class="badge badge-info">Collateral</span>
                    @endif
                    @if($loanProduct->requires_approval)
                        <span class="badge badge-warning">Requires Approval</span>
                    @endif
                    <div class="small text-muted mt-2">Updated: {{ $loanProduct->updated_at ? $loanProduct->updated_at->format('d M Y, H:i') : '-' }}</div>
                </div>
            </div>

            <hr>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-info-circle mr-1"></i> Basic Information
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Product Type</dt>
                                <dd class="col-sm-7">{{ $loanProduct->type->name ?? '-' }}</dd>

                                <dt class="col-sm-5">Interest Method</dt>
                                <dd class="col-sm-7">{{ $loanProduct->interestMethod->name ?? '-' }}</dd>

                                <dt class="col-sm-5">Interest Cycle</dt>
                                <dd class="col-sm-7">{{ $loanProduct->interestCycle->name ?? '-' }}</dd>

                                <dt class="col-sm-5">Repayment Frequency</dt>
                                <dd class="col-sm-7">{{ $loanProduct->repaymentFrequency->name ?? '-' }}</dd>

                                <dt class="col-sm-5">Installments</dt>
                                <dd class="col-sm-7">
                                    {{ $loanProduct->min_installments ?? '-' }} - {{ $loanProduct->max_installments ?? '-' }}
                                    @if($loanProduct->default_installments)
                                        (Default: {{ $loanProduct->default_installments }})
                                    @endif
                                </dd>

                                <dt class="col-sm-5">Default Loan Amount</dt>
                                <dd class="col-sm-7">{{ $loanProduct->default_loan_amount !== null ? number_format((float)$loanProduct->default_loan_amount, 2) : '-' }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card mb-3">
                        <div class="card-header" style="background:#f0f3f5;">
                            <i class="fas fa-gavel mr-1"></i> Rules
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Loan Amount Range</dt>
                                <dd class="col-sm-7">
                                    {{ $loanProductRules && $loanProductRules->min_loan_amount !== null ? number_format((float)$loanProductRules->min_loan_amount, 2) : '-' }}
                                    -
                                    {{ $loanProductRules && $loanProductRules->max_loan_amount !== null ? number_format((float)$loanProductRules->max_loan_amount, 2) : '-' }}
                                </dd>

                                <dt class="col-sm-5">Age Range</dt>
                                <dd class="col-sm-7">{{ $loanProductRules->min_age ?? '-' }} - {{ $loanProductRules->max_age ?? '-' }}</dd>

                                <dt class="col-sm-5">Grace Period (Days)</dt>
                                <dd class="col-sm-7">{{ $loanProductRules->grace_period_days ?? 0 }}</dd>

                                <dt class="col-sm-5">Penalty Start Day</dt>
                                <dd class="col-sm-7">{{ $loanProductRules->penalty_start_day ?? '-' }}</dd>

                                <dt class="col-sm-5">Min / Max Interest Rate (%)</dt>
                                <dd class="col-sm-7">{{ $loanProductRules->min_interest_rate ?? '-' }} - {{ $loanProductRules->max_interest_rate ?? '-' }}</dd>

                                <dt class="col-sm-5">Requires Active Savings</dt>
                                <dd class="col-sm-7">{{ !empty($loanProductRules->requires_active_savings) ? 'Yes' : 'No' }}</dd>

                                <dt class="col-sm-5">Min Savings Balance</dt>
                                <dd class="col-sm-7">{{ $loanProductRules && $loanProductRules->min_savings_balance !== null ? number_format((float)$loanProductRules->min_savings_balance, 2) : '-' }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card mb-3">
                        <div class="card-header" style="background:#f0f3f5;">
                            <i class="fas fa-coins mr-1"></i> Cash Configuration
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Deposit Requirement</dt>
                                <dd class="col-sm-7">{{ $loanProductCashConfig->deposit_requirement ?? '-' }}</dd>

                                <dt class="col-sm-5">Deposit Value</dt>
                                <dd class="col-sm-7">{{ $loanProductCashConfig && $loanProductCashConfig->deposit_value !== null ? number_format((float)$loanProductCashConfig->deposit_value, 2) : '-' }}</dd>

                                <dt class="col-sm-5">Deposit Basis</dt>
                                <dd class="col-sm-7">{{ $loanProductCashConfig->deposit_basis ?? '-' }}</dd>

                                <dt class="col-sm-5">Use Customer Savings</dt>
                                <dd class="col-sm-7">{{ !empty($loanProductCashConfig->use_customer_savings) ? 'Yes' : 'No' }}</dd>

                                <dt class="col-sm-5">Lock Period (Days)</dt>
                                <dd class="col-sm-7">{{ $loanProductCashConfig->lock_period_days ?? '-' }}</dd>

                                <dt class="col-sm-5">Allow Withdrawal During Loan</dt>
                                <dd class="col-sm-7">{{ !empty($loanProductCashConfig->allow_withdrawal_during_loan) ? 'Yes' : 'No' }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card mb-3">
                        <div class="card-header" style="background:#f0f3f5;">
                            <i class="fas fa-book mr-1"></i> Accounting Mapping
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-6">Principal Account</dt>
                                <dd class="col-sm-6">{{ $loanProductAccounts->principalAccount->account_name ?? '-' }}</dd>

                                <dt class="col-sm-6">Interest Receivable</dt>
                                <dd class="col-sm-6">{{ $loanProductAccounts->interestReceivableAccount->account_name ?? '-' }}</dd>

                                <dt class="col-sm-6">Interest Income</dt>
                                <dd class="col-sm-6">{{ $loanProductAccounts->interestIncomeAccount->account_name ?? '-' }}</dd>

                                <dt class="col-sm-6">Penalty Receivable</dt>
                                <dd class="col-sm-6">{{ $loanProductAccounts->penaltyReceivableAccount->account_name ?? '-' }}</dd>

                                <dt class="col-sm-6">Penalty Income</dt>
                                <dd class="col-sm-6">{{ $loanProductAccounts->penaltyIncomeAccount->account_name ?? '-' }}</dd>

                                <dt class="col-sm-6">Fee Income</dt>
                                <dd class="col-sm-6">{{ $loanProductAccounts->feeIncomeAccount->account_name ?? '-' }}</dd>

                                <dt class="col-sm-6">Write-off Expense</dt>
                                <dd class="col-sm-6">{{ $loanProductAccounts->writeOffExpenseAccount->account_name ?? '-' }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header" style="background:#f0f3f5;">
                    <i class="fas fa-receipt mr-1"></i> Default Fees
                </div>
                <div class="card-body">
                    @if(($loanProductFees ?? collect())->count())
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Fee</th>
                                        <th>Charge Event</th>
                                        <th>Payment Method</th>
                                        <th class="text-right">Max Applications</th>
                                        <th class="text-center">Auto Apply</th>
                                        <th class="text-center">Waivable</th>
                                        <th class="text-center">Mandatory</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($loanProductFees as $f)
                                        <tr>
                                            <td>{{ $f->loanFee->name ?? ($f->loan_fee_id ?? '-') }}</td>
                                            <td>{{ $f->charge_event ?? '-' }}</td>
                                            <td>{{ $f->payment_method ?? '-' }}</td>
                                            <td class="text-right">{{ $f->max_applications ?? '-' }}</td>
                                            <td class="text-center">{{ !empty($f->auto_apply) ? 'Yes' : 'No' }}</td>
                                            <td class="text-center">{{ !empty($f->is_waivable) ? 'Yes' : 'No' }}</td>
                                            <td class="text-center">{{ !empty($f->is_mandatory) ? 'Yes' : 'No' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-muted">No default fees configured.</div>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header" style="background:#f0f3f5;">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Penalties
                </div>
                <div class="card-body">
                    @if(($loanProductPenalties ?? collect())->count())
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Penalty</th>
                                        <th class="text-right">Max Applications</th>
                                        <th class="text-right">Grace Days Override</th>
                                        <th class="text-center">Auto Apply</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($loanProductPenalties as $p)
                                        <tr>
                                            <td>{{ $p->loanPenalty->name ?? ($p->loan_penalty_id ?? '-') }}</td>
                                            <td class="text-right">{{ $p->max_applications ?? '-' }}</td>
                                            <td class="text-right">{{ $p->grace_days_override ?? '-' }}</td>
                                            <td class="text-center">{{ !empty($p->auto_apply) ? 'Yes' : 'No' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-muted">No penalties configured.</div>
                    @endif
                </div>
            </div>

            @if($loanProduct->requires_approval)
                <div class="card mb-0">
                    <div class="card-header" style="background:#f0f3f5;">
                        <i class="fas fa-user-check mr-1"></i> Approval Workflow
                    </div>
                    <div class="card-body">
                        @if(($loanProductApprovalLevels ?? collect())->count())
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="text-right">Level</th>
                                            <th>Role</th>
                                            <th class="text-right">Min Amount</th>
                                            <th class="text-right">Max Amount</th>
                                            <th class="text-center">Mandatory</th>
                                            <th class="text-center">Override Rules</th>
                                            <th class="text-center">Can Reject</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($loanProductApprovalLevels->sortBy('level_order') as $a)
                                            <tr>
                                                <td class="text-right">{{ $a->level_order ?? '-' }}</td>
                                                <td>{{ $a->role->name ?? ($a->role_id ?? '-') }}</td>
                                                <td class="text-right">{{ $a->min_loan_amount !== null ? number_format((float)$a->min_loan_amount, 2) : '-' }}</td>
                                                <td class="text-right">{{ $a->max_loan_amount !== null ? number_format((float)$a->max_loan_amount, 2) : '-' }}</td>
                                                <td class="text-center">{{ !empty($a->mandatory) ? 'Yes' : 'No' }}</td>
                                                <td class="text-center">{{ !empty($a->can_override_rules) ? 'Yes' : 'No' }}</td>
                                                <td class="text-center">{{ !empty($a->can_reject) ? 'Yes' : 'No' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-muted">No approval levels configured.</div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@stop