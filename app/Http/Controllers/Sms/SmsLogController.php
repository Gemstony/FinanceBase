<?php

namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Models\SubShop;
use Illuminate\Http\Request;

class SmsLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        // Normalize filters safely
        $filters = [
            'status'      => $request->filled('status') ? $request->input('status') : null,
            'event'       => $request->filled('event') ? $request->input('event') : null,
            'date_from'   => $request->input('date_from'),
            'date_to'     => $request->input('date_to'),
        ];

        // Base query (single source of truth) - filter by current shop only
        $baseQuery = SmsLog::query()
            ->where('shop_id', $shopId)
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['event'], fn ($q, $v) => $q->where('event', $v))
            ->when($filters['date_from'], fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'], fn ($q, $v) => $q->whereDate('created_at', '<=', $v));

        // Logs with eager loading (avoid N+1)
        $logs = (clone $baseQuery)
            ->with(['shop:id,name', 'subshop:id,name', 'user:id,name', 'template:id,name'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        // ✅ Optimized summary (single query instead of multiple clones)
        $summary = (clone $baseQuery)
            ->selectRaw("
                COUNT(*) as total,
                SUM(status = 'sent') as sent,
                SUM(status = 'failed') as failed,
                SUM(status = 'error') as errors,
                SUM(status = 'queued') as queued,
                SUM(status = 'retrying') as retrying
            ")
            ->first();

        // Extract safely
        $total    = (int) $summary->total;
        $sent     = (int) $summary->sent;
        $failed   = (int) $summary->failed;
        $errors   = (int) $summary->errors;
        $queued   = (int) $summary->queued;
        $retrying = (int) $summary->retrying;

        // Events (cached for performance)
        $events = cache()->remember('sms_log_events', 300, function () {
            return SmsLog::whereNotNull('event')
                ->distinct()
                ->pluck('event')
                ->values();
        });

        // Normalize dates for UI
        $filters['date_from'] = $filters['date_from'] ? date('Y-m-d', strtotime($filters['date_from'])) : null;
        $filters['date_to']   = $filters['date_to'] ? date('Y-m-d', strtotime($filters['date_to'])) : null;

        return view('sms.logs.index', compact(
            'logs',
            'events',
            'total',
            'sent',
            'failed',
            'errors',
            'queued',
            'retrying',
            'filters'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        // Manual SMS sending form
        return view('sms.logs.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // This would typically be handled via the SmsManager for event-based SMS
        // For manual SMS, we could implement it here, but the spec focuses on event-driven
        return redirect()->route('sms.logs.index')
            ->with('info', 'Manual SMS sending is not implemented. Use event-based SMS instead.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $smsLog = SmsLog::with(['shop:id,name', 'subshop:id,name', 'user:id,name', 'template:id,name'])
            ->where('shop_id', $shopId)
            ->findOrFail($id);

        return view('sms.logs.show', compact('smsLog'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $smsLog = SmsLog::where('shop_id', $shopId)->findOrFail($id);
        $smsLog->delete();
        
        return redirect()->route('sms.logs.index')
            ->with('success', 'SMS log deleted successfully.');
    }
}
