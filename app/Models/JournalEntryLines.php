<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntryLines extends Model
{
    protected $table = 'journal_entry_lines';

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'description',
    ];

    protected $casts = [
        'journal_entry_id' => 'integer',
        'account_id' => 'integer',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntries::class, 'journal_entry_id');
    }

    public function account()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'account_id');
    }
}
