<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntries extends Model
{
    protected $table = 'journal_entries';

    protected $fillable = [
        'subshop_id',
        'reference_type',
        'reference_id',
        'transaction_date',
        'description',
        'created_by',
    ];

    protected $casts = [
        'subshop_id' => 'integer',
        'reference_id' => 'integer',
        'created_by' => 'integer',
        'transaction_date' => 'date',
    ];

    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines()
    {
        return $this->hasMany(JournalEntryLines::class, 'journal_entry_id');
    }
}
