<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCollaterals extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'subshop_id',
        'customer_id',
        'collateral_type_id',
        'reference_number',
        'description',
        'collateral_image',
        'location',
        'estimated_value',
        'valuation_date',
        'valued_by',
        'is_insured',
        'insurance_expiry_date',
        'status',
        'is_active',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'valuation_date' => 'date',
        'insurance_expiry_date' => 'date',
        'is_insured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function subshop()
    {
        return $this->belongsTo(SubShop::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customers::class);
    }

    public function collateralType()
    {
        return $this->belongsTo(CollateralTypes::class);
    }

    public function documents()
    {
        return $this->hasMany(CollateralDocuments::class, 'customer_collateral_id');
    }
}
