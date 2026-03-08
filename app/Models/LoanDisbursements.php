<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanDisbursements extends Model
{
    protected $table = 'loan_disbursements';

    protected $fillable = [
        'loan_id',
        'disbursement_date',
        'amount',
        'disbursement_method',
        'transaction_reference',
        'processed_by',
        'notes',
    ];

    protected $casts = [
        'loan_id' => 'integer',
        'processed_by' => 'integer',
        'amount' => 'decimal:2',
        'disbursement_date' => 'date',
    ];

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
