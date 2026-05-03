<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Loans extends Model
{
    use SoftDeletes;

    protected $table = 'loans';

    protected static string $randomType = 'uppercase';

    protected static int $randomLength = 4;

    protected $fillable = [
        'loan_code',
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
        'is_written_off',
        'fee_income_account_id',
        'customer_savings_account_id',
        'customer_security_deposit_account_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $loan) {
            if (! empty($loan->loan_code)) {
                return;
            }

            do {
                $loan->loan_code = self::generateLoanCode();
            } while (self::query()->where('loan_code', $loan->loan_code)->exists());
        });
    }

    public static function generateLoanCode(): string
    {
        $datePart = Carbon::now()->format('ymd');
        $randomPart = self::generateRandomString();

        return 'LN-'.$datePart.'-'.$randomPart;
    }

    protected static function generateRandomString(): string
    {
        $chars = match (self::$randomType) {
            'numeric' => '0123456789',
            'alphanumeric' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
            'uppercase' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
            default => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        };

        $result = '';
        $charsLength = Str::length($chars);

        for ($i = 0; $i < self::$randomLength; $i++) {
            $result .= $chars[random_int(0, $charsLength - 1)];
        }

        return $result;
    }

    public static function setRandomType(string $type): void
    {
        self::$randomType = $type;
    }

    public static function setRandomLength(int $length): void
    {
        self::$randomLength = $length;
    }

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
        'is_written_off' => 'boolean',
        'requires_collateral' => 'boolean',
        'collateral_value' => 'decimal:2',
        'collateral_coverage_ratio' => 'decimal:2',
        'requires_security_deposit' => 'boolean',
        'security_deposit_amount' => 'decimal:2',
        'approval_completed' => 'boolean',
        'approval_history' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'loan_code';
    }

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

    /**
     * Get the borrower ID for this loan.
     * For individual loans: returns customer_id
     * For group loans: returns the group leader/treasurer's customer_id
     */
    public function getBorrowerId(): ?int
    {
        if ($this->borrower_type === 'group') {
            // Return group leader or treasurer customer_id as representative
            $representative = $this->loanGroup?->members()
                ->where('is_active', true)
                ->whereIn('role', ['leader', 'treasurer', 'secretary'])
                ->first();
            return $representative?->customer_id;
        }
        return $this->customer_id;
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

    public function installments()
    {
        return $this->hasMany(LoanInstallments::class, 'loan_id');
    }

    public function collaterals()
    {
        return $this->hasMany(LoanCollaterals::class, 'loan_id');
    }

    public function guarantors()
    {
        return $this->hasMany(loanGuarantors::class, 'loan_id');
    }

    public function disbursements()
    {
        return $this->hasMany(LoanDisbursements::class, 'loan_id');
    }

    public function latestDisbursement()
    {
        return $this->hasOne(LoanDisbursements::class, 'loan_id')->latestOfMany();
    }

    public function repayments()
    {
        return $this->hasMany(LoanPayments::class, 'loan_id');
    }

    public function approvals()
    {
        return $this->hasMany(LoanApprovals::class, 'loan_id');
    }

    public function restructures()
    {
        return $this->hasMany(LoanRestructures::class, 'loan_id');
    }

    public function writeoffs()
    {
        return $this->hasMany(LoanWriteoffs::class, 'loan_id');
    }

    public function writeoffRecoveries()
    {
        return $this->hasMany(LoanWriteoffRecoveries::class, 'loan_id');
    }

    public function feeApplications()
    {
        return $this->hasMany(LoanFeeApplications::class, 'loan_id');
    }

    public function penaltyApplications()
    {
        return $this->hasMany(LoanPenaltyApplications::class, 'loan_id');
    }
}
