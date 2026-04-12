<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethodAccount extends Model
{
    protected $table = 'payment_method_accounts';

    protected $fillable = [
        'subshop_id',
        'payment_method',
        'chart_of_account_id',
    ];

    protected $casts = [
        'subshop_id' => 'integer',
        'chart_of_account_id' => 'integer',
    ];

    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'chart_of_account_id');
    }
}
