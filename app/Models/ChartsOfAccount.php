<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChartsOfAccount extends Model
{
    protected $table = 'charts_of_accounts';

    protected $fillable = [
        'subshop_id',
        'account_code',
        'account_name',
        'description',
        'account_class_id',
        'account_group_id',
        'cash_flow_impact',
        'cash_flow_category',
        'equity_impact',
        'equity_category',
        'is_customer_account',
        'is_system_account',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the account class that owns the chart of account.
     */
    public function accountClass()
    {
        return $this->belongsTo(AccountClass::class, 'account_class_id');
    }

    /**
     * Get the account group that owns the chart of account.
     */
    public function accountGroup()
    {
        return $this->belongsTo(AccountGroups::class, 'account_group_id');
    }

    /**
     * Get the subshop that owns the chart of account.
     */
    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    /**
     * Get the user who created the chart of account.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who updated the chart of account.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

   
}
