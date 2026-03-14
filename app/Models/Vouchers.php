<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vouchers extends Model
{
    use SoftDeletes;

    protected $table = 'vouchers';

    protected $fillable = [
        'voucher_number',
        'voucher_type',
        'source_type',
        'reference_type',
        'reference_id',
        'total_amount',
        'payment_method',
        'bank_account_id',
        'description',
        'status',
        'subshop_id',
        'created_by',
        'approved_by',
        'voucher_date',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'total_amount' => 'decimal:2',
        'bank_account_id' => 'integer',
        'subshop_id' => 'integer',
        'created_by' => 'integer',
        'approved_by' => 'integer',
        'voucher_date' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function lines()
    {
        return $this->hasMany(VoucherLines::class, 'voucher_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccounts::class, 'bank_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
