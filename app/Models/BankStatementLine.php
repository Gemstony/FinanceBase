<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankStatementLine extends Model
{
    protected $table = 'bank_statement_lines';

    protected $fillable = [
        'bank_statement_id',
        'transaction_date',
        'reference',
        'description',
        'debit',
        'credit',
        'amount',
        'is_matched',
        'matched_journal_entry_id',
        'notes',
    ];

    protected $casts = [
        'bank_statement_id' => 'integer',
        'transaction_date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'amount' => 'decimal:2',
        'is_matched' => 'boolean',
        'matched_journal_entry_id' => 'integer',
    ];

    public function statement()
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function matchedJournalEntry()
    {
        return $this->belongsTo(JournalEntries::class, 'matched_journal_entry_id');
    }
}
