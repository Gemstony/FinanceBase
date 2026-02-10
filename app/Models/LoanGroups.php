<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanGroups extends Model
{
    protected $table = 'loan_groups';

    protected $fillable = [
        'subshop_id',
        'name',
        'code',
        'description',
        'formation_date',
        'is_active',
    ];

    protected $casts = [
        'formation_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function members()
    {
        return $this->hasMany(LoanGroupMembers::class, 'loan_group_id');
    }
}
