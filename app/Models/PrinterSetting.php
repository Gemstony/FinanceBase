<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrinterSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'subshop_id',
        'created_by',
        'name',
        'ip_address',
        'port',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'port' => 'integer',
    ];

    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
