<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanInterestAccruals extends Model
{
    use SoftDeletes;

    protected $table = 'loan_interest_accruals';

    protected $fillable = [
        'loan_id',
        'installment_id',
        'accrual_date',
        'principal_balance',
        'interest_rate',
        'daily_interest',
        'is_posted',
        'posting_id',
        'is_active',
        'is_npl_reversal',
        'npl_reversal_reason',
    ];

    protected $casts = [
        'loan_id' => 'integer',
        'installment_id' => 'integer',
        'accrual_date' => 'date',
        'principal_balance' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'daily_interest' => 'decimal:6',
        'is_posted' => 'boolean',
        'posting_id' => 'integer',
        'is_active' => 'boolean',
        'is_npl_reversal' => 'boolean',
    ];

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function installment()
    {
        return $this->belongsTo(LoanInstallments::class, 'installment_id');
    }

    public function posting()
    {
        return $this->belongsTo(LoanInterestPostings::class, 'posting_id');
    }
}
