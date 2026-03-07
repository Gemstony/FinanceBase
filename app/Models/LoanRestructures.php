<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanRestructures extends Model
{
    use SoftDeletes;

    protected $table = 'loan_restructures';

    protected $fillable = [
        'loan_id',
        'restructure_type',
        'old_term_months',
        'old_interest_rate',
        'new_term_months',
        'new_interest_rate',
        'restructure_effective_date',
        'remaining_principal',
        'remaining_interest',
        'remaining_fees',
        'remaining_penalties',
        'reason',
        'approved_by',
        'approved_at',
        'is_active',
    ];

    protected $casts = [
        'old_term_months' => 'integer',
        'new_term_months' => 'integer',
        'old_interest_rate' => 'decimal:4',
        'new_interest_rate' => 'decimal:4',
        'restructure_effective_date' => 'date',
        'remaining_principal' => 'decimal:2',
        'remaining_interest' => 'decimal:2',
        'remaining_fees' => 'decimal:2',
        'remaining_penalties' => 'decimal:2',
        'approved_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function installmentSnapshots()
    {
        return $this->hasMany(LoanRestructureInstallments::class, 'restructure_id');
    }
}
