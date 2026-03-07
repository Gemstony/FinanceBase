<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanWriteoffs extends Model
{
    use SoftDeletes;

    protected $table = 'loan_writeoffs';

    protected $fillable = [
        'loan_id',
        'writeoff_date',
        'principal_written_off',
        'interest_written_off',
        'fees_written_off',
        'penalties_written_off',
        'total_written_off',
        'reason',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'writeoff_date' => 'date',
        'principal_written_off' => 'decimal:2',
        'interest_written_off' => 'decimal:2',
        'fees_written_off' => 'decimal:2',
        'penalties_written_off' => 'decimal:2',
        'total_written_off' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function recoveries()
    {
        return $this->hasMany(LoanWriteoffRecoveries::class, 'writeoff_id');
    }
}
