<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanInstallmentPayments extends Model
{
    use SoftDeletes;

    protected $table = 'loan_installment_payments';

    protected $fillable = [
        'installment_id',
        'loan_id',
        'subshop_id',
        'customer_id',
        'total_paid',
        'payment_method',
        'payment_date',
        'reference_number',
        'is_successful',
        'is_active',
    ];

    protected $casts = [
        'total_paid' => 'decimal:2',
        'payment_date' => 'date',
        'is_successful' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function installment()
    {
        return $this->belongsTo(LoanInstallments::class, 'installment_id');
    }

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }
}
