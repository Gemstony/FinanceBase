<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UiSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'sidebar_bg',
        'navbar_bg',
    ];
}
