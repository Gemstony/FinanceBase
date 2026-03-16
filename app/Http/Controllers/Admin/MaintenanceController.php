<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function enable(Request $request): RedirectResponse
    {
        SystemSetting::setMaintenanceMode(true);

        return redirect()->back()->with('success', 'Maintenance mode enabled.');
    }

    public function disable(Request $request): RedirectResponse
    {
        SystemSetting::setMaintenanceMode(false);

        return redirect()->back()->with('success', 'Maintenance mode disabled.');
    }
}
