<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanApprovals extends Model
{
    use SoftDeletes;

    protected $table = 'loan_approvals';

    protected $fillable = [
        'subshop_id',
        'loan_id',
        'loan_product_approval_level_id',
        'approved_by',
        'level_order',
        'status',
        'approved_at',
        'comments',
        'is_active',
    ];

    protected $casts = [
        'level_order' => 'integer',
        'approved_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function loanProductApprovalLevel()
    {
        return $this->belongsTo(LoanProductApprovalLevels::class, 'loan_product_approval_level_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
