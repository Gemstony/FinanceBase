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

    /**
     * Auto-generate account_code before creating
     */
    // protected static function boot()
    // {
    //     parent::boot();

    //     static::creating(function ($account) {

    //         // Generate code only if not set
    //         if (empty($account->account_code)) {

    //             // Group-based prefix (optional smart generation)
    //             $prefix = static::generatePrefix($account->account_class_id);

    //             // Find last account in this group
    //             $last = static::where('account_class_id', $account->account_class_id)
    //                           ->orderBy('account_code', 'DESC')
    //                           ->first();

    //             if ($last) {
    //                 // Increase last code by 1
    //                 $number = intval(substr($last->account_code, strlen($prefix))) + 1;
    //             } else {
    //                 // Start from 1
    //                 $number = 1;
    //             }

    //             // Format: PREFIX + padded number
    //             // Example: Class 1 → 1001, Class 2 → 2001
    //             $account->account_code = $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);
    //         }
    //     });
    // }

    // /**
    //  * Generate prefix based on account class ID
    //  */
    // public static function generatePrefix($accountClassId)
    // {
    //     // Generate prefix based on account class ID
    //     // This can be customized based on your account classes structure
    //     return match ($accountClassId) {
    //         1 => '1',  // Assets
    //         2 => '2',  // Liabilities
    //         3 => '3',  // Income
    //         4 => '4',  // Expenses
    //         5 => '5',  // Equity
    //         default => (string) $accountClassId, // Use the class ID as prefix
    //     };
    // }
}
