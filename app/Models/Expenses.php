<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\SubShop;
use App\Models\Banks;

class Expenses extends Model
{
    protected $table = 'expenses';

    protected $fillable = [
        'subshop_id',
        'title',
        'amount',
        'expense_date',
        'category',
        'description',
        'payment_method',
        'status',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'reviewed_at' => 'datetime',
        'amount' => 'float',
    ];

    public function subshop(): BelongsTo
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function paymentBank(): BelongsTo
    {
        return $this->belongsTo(Banks::class, 'payment_method');
    }
}
