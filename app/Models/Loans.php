<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loans extends Model
{
    use SoftDeletes;

    protected $table = 'loans';

    protected $fillable = [
        'subshop_id',
        'loan_product_id',
        'borrower_type',
        'customer_id',
        'loan_group_id',
        'principal_amount',
        'interest_rate',
        'installments',
        'installments_paid',
        'outstanding_balance',
        'next_installment_amount',
        'disbursement_date',
        'maturity_date',
        'repayment_frequency_code',
        'supports_collateral',
        'requires_approval',
        'status',
        'is_active',
        'allow_top_up',
        'requires_collateral',
        'collateral_value',
        'collateral_coverage_ratio',
        'requires_security_deposit',
        'security_deposit_amount',
        'approval_completed',
        'approval_history',
        'principal_account_id',
        'interest_receivable_account_id',
        'interest_income_account_id',
        'penalty_receivable_account_id',
        'penalty_income_account_id',
        'write_off_expense_account_id',
        'fee_income_account_id',
        'customer_savings_account_id',
        'customer_security_deposit_account_id',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'installments' => 'integer',
        'installments_paid' => 'integer',
        'outstanding_balance' => 'decimal:2',
        'next_installment_amount' => 'decimal:2',
        'disbursement_date' => 'date',
        'maturity_date' => 'date',
        'supports_collateral' => 'boolean',
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
        'allow_top_up' => 'boolean',
        'requires_collateral' => 'boolean',
        'collateral_value' => 'decimal:2',
        'collateral_coverage_ratio' => 'decimal:2',
        'requires_security_deposit' => 'boolean',
        'security_deposit_amount' => 'decimal:2',
        'approval_completed' => 'boolean',
        'approval_history' => 'array',
    ];

    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function loanProduct()
    {
        return $this->belongsTo(LoanProducts::class, 'loan_product_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }

    public function loanGroup()
    {
        return $this->belongsTo(LoanGroups::class, 'loan_group_id');
    }

    public function principalAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'principal_account_id');
    }

    public function interestReceivableAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'interest_receivable_account_id');
    }

    public function interestIncomeAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'interest_income_account_id');
    }

    public function penaltyReceivableAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'penalty_receivable_account_id');
    }

    public function penaltyIncomeAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'penalty_income_account_id');
    }

    public function writeOffExpenseAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'write_off_expense_account_id');
    }

    public function feeIncomeAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'fee_income_account_id');
    }

    public function customerSavingsAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'customer_savings_account_id');
    }

    public function customerSecurityDepositAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'customer_security_deposit_account_id');
    }
}
