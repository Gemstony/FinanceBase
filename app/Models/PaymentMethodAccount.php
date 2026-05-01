<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethodAccount extends Model
{
    protected $table = 'payment_method_accounts';

    protected $fillable = [
        'shop_id',
        'subshop_id',
        'payment_method',
        'chart_of_account_id',
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'subshop_id' => 'integer',
        'chart_of_account_id' => 'integer',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'chart_of_account_id');
    }
}
