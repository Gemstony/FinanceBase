<?php

namespace App\Http\Controllers;

use App\Models\PrinterSetting;
use App\Models\SubShop;
use App\Services\PrinterService;
use App\Services\ReceiptPrinter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PrinterSettingsController extends Controller
{
    public function index(Request $request)
    {
        $subshopId = (int) $request->session()->get('subshop_id');
        $user = $request->user();
        if (!$subshopId || !method_exists($user, 'canAccessSubshop') || !$user->canAccessSubshop($subshopId)) {
            return redirect()->route('subshops.choose')->with('error', 'Please choose a valid shop.');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $printers = PrinterSetting::where('subshop_id', $subshopId)->orderByDesc('is_default')->orderBy('name')->get();

        return view('printers.settings', compact('subshop', 'printers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable','string','max:255'],
            'ip_address' => ['required','ip'],
            'port' => ['required','integer','min:1','max:65535'],
            'is_default' => ['sometimes','boolean'],
        ]);

        $subshopId = (int) $request->session()->get('subshop_id');
        $user = $request->user();
        if (!$subshopId || !method_exists($user, 'canAccessSubshop') || !$user->canAccessSubshop($subshopId)) {
            return redirect()->route('subshops.choose')->with('error', 'Please choose a valid shop.');
        }

        if (!empty($data['is_default'])) {
            PrinterSetting::where('subshop_id', $subshopId)->update(['is_default' => false]);
        }

        PrinterSetting::create([
            'subshop_id' => $subshopId,
            'created_by' => Auth::id(),
            'name' => $data['name'] ?? null,
            'ip_address' => $data['ip_address'],
            'port' => $data['port'],
            'is_default' => (bool) ($data['is_default'] ?? false),
        ]);

        return redirect()->back()->with('success', 'Printer saved.');
    }

    public function update(Request $request, PrinterSetting $printer)
    {
        $subshopId = (int) $request->session()->get('subshop_id');
        $user = $request->user();
        if ($printer->subshop_id !== $subshopId || !method_exists($user, 'canAccessSubshop') || !$user->canAccessSubshop($subshopId)) {
            return redirect()->route('subshops.choose')->with('error', 'Unauthorized.');
        }

        $data = $request->validate([
            'name' => ['nullable','string','max:255'],
            'ip_address' => ['required','ip'],
            'port' => ['required','integer','min:1','max:65535'],
            'is_default' => ['sometimes','boolean'],
        ]);

        if (!empty($data['is_default'])) {
            PrinterSetting::where('subshop_id', $subshopId)->where('id', '!=', $printer->id)->update(['is_default' => false]);
        }

        $printer->update([
            'name' => $data['name'] ?? null,
            'ip_address' => $data['ip_address'],
            'port' => $data['port'],
            'is_default' => (bool) ($data['is_default'] ?? false),
        ]);

        return redirect()->back()->with('success', 'Printer updated.');
    }

    public function destroy(Request $request, PrinterSetting $printer)
    {
        $subshopId = (int) $request->session()->get('subshop_id');
        $user = $request->user();
        if ($printer->subshop_id !== $subshopId || !method_exists($user, 'canAccessSubshop') || !$user->canAccessSubshop($subshopId)) {
            return redirect()->route('subshops.choose')->with('error', 'Unauthorized.');
        }

        $printer->delete();
        return redirect()->back()->with('success', 'Printer deleted.');
    }

    public function test(Request $request, PrinterService $service)
    {
        $data = $request->validate([
            'ip_address' => ['required','ip'],
            'port' => ['required','integer','min:1','max:65535'],
        ]);
        $result = $service->testConnection($data['ip_address'], (int) $data['port']);
        return response()->json($result);
    }

    public function autodetect(Request $request, PrinterService $service)
    {
        $clientIp = $request->ip();
        // Use a shorter timeout to keep UX responsive; scanning a /24 may still take several seconds
        $found = $service->autoDetect(9100, 0.08, $clientIp);
        return response()->json(['printers' => $found]);
    }

    public function testPrint(Request $request, PrinterSetting $printer, ReceiptPrinter $receiptPrinter)
    {
        $subshopId = (int) $request->session()->get('subshop_id');
        $user = $request->user();
        if ($printer->subshop_id !== $subshopId || !method_exists($user, 'canAccessSubshop') || !$user->canAccessSubshop($subshopId)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }
        try {
            $dummy = (bool) $request->boolean('dummy');
            $data = $receiptPrinter->printTest($printer, $dummy);
            return response()->json(['ok' => true, 'dummy' => $dummy, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function testPrintDefault(Request $request, ReceiptPrinter $receiptPrinter)
    {
        $subshopId = (int) $request->session()->get('subshop_id');
        $user = $request->user();
        if (!$subshopId || !method_exists($user, 'canAccessSubshop') || !$user->canAccessSubshop($subshopId)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }
        $printer = PrinterSetting::where('subshop_id', $subshopId)->orderByDesc('is_default')->first();
        if (!$printer) {
            return response()->json(['ok' => false, 'error' => 'No printer configured for this shop'], 422);
        }
        try {
            $dummy = (bool) $request->boolean('dummy');
            $data = $receiptPrinter->printTest($printer, $dummy);
            return response()->json(['ok' => true, 'dummy' => $dummy, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
