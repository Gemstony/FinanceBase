<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerCreditBalances extends Model
{
    protected $table = 'customer_credit_balances';

    protected $fillable = [
        'subshop_id',
        'customer_id',
        'loan_id',
        'payment_id',
        'amount',
        'status',
        'applied_to_loan_id',
        'applied_at',
        'refunded_at',
        'refunded_by',
        'refund_method',
        'bank_account_id',
        'notes',
    ];

    protected $casts = [
        'subshop_id' => 'integer',
        'amount' => 'decimal:2',
        'applied_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function payment()
    {
        return $this->belongsTo(LoanPayments::class, 'payment_id');
    }

    public function appliedToLoan()
    {
        return $this->belongsTo(Loans::class, 'applied_to_loan_id');
    }

    public function refundedBy()
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccounts::class, 'bank_account_id');
    }
}
