<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisbursementMethods extends Model
{
    protected $table = 'disbursement_methods';

    protected $fillable = [
        'subshop_id',
        'name',
        'code',
        'description',
        'requires_reference',
        'requires_account_details',
        'is_active',
        'is_system_method',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'subshop_id' => 'integer',
        'requires_reference' => 'boolean',
        'requires_account_details' => 'boolean',
        'is_active' => 'boolean',
        'is_system_method' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
