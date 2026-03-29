<?php

namespace App\Http\Controllers\Sms_settings;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\SubShop;

class SmsSettingsController extends Controller
{
    
    public function index()
    {
        $subshopId = session('subshop_id');

        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('settings.sms_settings.index')]);
        }

        $subshop = SubShop::findOrFail($subshopId);

        return view('sms.sms_settings', compact('subshop'));
    }
}
