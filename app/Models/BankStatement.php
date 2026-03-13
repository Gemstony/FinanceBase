<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankStatement extends Model
{
    protected $table = 'bank_statements';

    protected $fillable = [
        'bank_account_id',
        'statement_date',
        'opening_balance',
        'closing_balance',
        'reference_number',
        'file_path',
        'status',
        'notes',
        'reconciled_at',
    ];

    protected $casts = [
        'bank_account_id' => 'integer',
        'statement_date' => 'date',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccounts::class, 'bank_account_id');
    }

    public function lines()
    {
        return $this->hasMany(BankStatementLine::class, 'bank_statement_id');
    }
}
