<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'shop_id',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
