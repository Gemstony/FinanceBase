<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanDisbursements extends Model
{
    protected $table = 'loan_disbursements';

    protected $fillable = [
        'loan_id',
        'disbursement_method_id',
        'bank_account_id',
        'disbursement_date',
        'amount',
        'transaction_reference',
        'processed_by',
        'notes',
    ];

    protected $casts = [
        'loan_id' => 'integer',
        'disbursement_method_id' => 'integer',
        'bank_account_id' => 'integer',
        'processed_by' => 'integer',
        'amount' => 'decimal:2',
        'disbursement_date' => 'date',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccounts::class, 'bank_account_id');
    }

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function disbursementMethod()
    {
        return $this->belongsTo(DisbursementMethods::class, 'disbursement_method_id');
    }
}
