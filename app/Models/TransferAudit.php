<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferAudit extends Model
{
    protected $fillable = [
        'transfer_id',
        'action',
        'user_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }
}
