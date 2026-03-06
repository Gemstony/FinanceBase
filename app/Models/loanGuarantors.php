<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class loanGuarantors extends Model
{
    use SoftDeletes;

    protected $table = 'loan_guarantors';

    protected $fillable = [
        'loan_id',
        'guarantor_id',
        'is_joint_liability',
    ];

    protected $casts = [
        'is_joint_liability' => 'boolean',
    ];

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function guarantor()
    {
        return $this->belongsTo(Customers::class, 'guarantor_id');
    }
}
