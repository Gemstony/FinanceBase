<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanWriteoffRecoveries extends Model
{
    protected $table = 'loan_writeoff_recoveries';

    protected $fillable = [
        'loan_id',
        'writeoff_id',
        'payment_id',
        'recovery_date',
        'recovered_principal',
        'recovered_interest',
        'recovered_fees',
        'recovered_penalties',
        'total_recovered',
        'notes',
    ];

    protected $casts = [
        'recovery_date' => 'date',
        'recovered_principal' => 'decimal:2',
        'recovered_interest' => 'decimal:2',
        'recovered_fees' => 'decimal:2',
        'recovered_penalties' => 'decimal:2',
        'total_recovered' => 'decimal:2',
    ];

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function writeoff()
    {
        return $this->belongsTo(LoanWriteoffs::class, 'writeoff_id');
    }

    public function payment()
    {
        return $this->belongsTo(LoanPayments::class, 'payment_id');
    }
}
