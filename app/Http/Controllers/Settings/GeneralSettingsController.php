<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\SubShop;
class GeneralSettingsController extends Controller
{
    public function index()
    {
        $subshopId = session('subshop_id');

        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('settings.general_settings.index')]);
        }

        $subshop = SubShop::findOrFail($subshopId);

        return view('settings.general_settings', compact('subshop'));
    }
}
