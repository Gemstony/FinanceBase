<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherLines extends Model
{
    protected $table = 'voucher_lines';

    protected $fillable = [
        'voucher_id',
        'account_id',
        'debit',
        'credit',
        'description',
    ];

    protected $casts = [
        'voucher_id' => 'integer',
        'account_id' => 'integer',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function voucher()
    {
        return $this->belongsTo(Vouchers::class, 'voucher_id');
    }

    public function account()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'account_id');
    }
}
