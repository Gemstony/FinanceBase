<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanGroupMembers extends Model
{
    protected $table = 'loan_group_members';

    protected $fillable = [
        'loan_group_id',
        'customer_id',
        'role',
        'joined_at',
        'left_at',
        'is_active',
    ];

    protected $casts = [
        'joined_at' => 'date',
        'left_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(LoanGroups::class, 'loan_group_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }
}
