<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanProductApprovalLevels extends Model
{
    protected $fillable = [
        'subshop_id',
        'loan_product_id',
        'level_order',
        'role_id',
        'min_loan_amount',
        'max_loan_amount',
        'mandatory',
        'can_override_rules',
        'can_reject',
        'is_active',
    ];

    protected $casts = [
        'level_order' => 'integer',
        'min_loan_amount' => 'decimal:2',
        'max_loan_amount' => 'decimal:2',
        'mandatory' => 'boolean',
        'can_override_rules' => 'boolean',
        'can_reject' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * The subshop this approval level belongs to.
     */
    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    /**
     * The loan product this approval level is associated with.
     */
    public function loanProduct()
    {
        return $this->belongsTo(LoanProducts::class, 'loan_product_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
