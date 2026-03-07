<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanInterestPostings extends Model
{
    use SoftDeletes;

    protected $table = 'loan_interest_postings';

    protected $fillable = [
        'loan_id',
        'installment_id',
        'posting_date',
        'interest_amount',
        'reference_number',
        'description',
        'is_successful',
        'is_active',
    ];

    protected $casts = [
        'loan_id' => 'integer',
        'installment_id' => 'integer',
        'posting_date' => 'date',
        'interest_amount' => 'decimal:6',
        'is_successful' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function installment()
    {
        return $this->belongsTo(LoanInstallments::class, 'installment_id');
    }

    public function accruals()
    {
        return $this->hasMany(LoanInterestAccruals::class, 'posting_id');
    }
}
