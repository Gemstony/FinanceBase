<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\SmsTemplate;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentSettingsController extends Controller
{
        public function index()
    {
        $subshopId = session('subshop_id');

        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('settings.payment_settings.index')]);
        }

        $subshop = SubShop::findOrFail($subshopId);

        return view('payments.payment_settings', compact('subshop'));
    }
}
