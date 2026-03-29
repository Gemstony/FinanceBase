<?php

namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Models\SubShop;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * Display SMS analytics dashboard.
     */
    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');

        $query = SmsLog::query();

        // Filter by current shop only
        $query->where('shop_id', $shopId);

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Basic stats
        $totalSms = $query->count();
        $sentSms = (clone $query)->where('status', 'sent')->count();
        $failedSms = (clone $query)->where('status', 'failed')->count();
        $errorSms = (clone $query)->where('status', 'error')->count();
        $queuedSms = (clone $query)->where('status', 'queued')->count();
        $retryingSms = (clone $query)->where('status', 'retrying')->count();


        // Delivery rate
        $deliveryRate = $totalSms > 0 ? round(($sentSms / $totalSms) * 100, 2) : 0;
        $failureRate = $totalSms > 0 ? round((($failedSms + $errorSms) / $totalSms) * 100, 2) : 0;
        $retryRate = $totalSms > 0 ? round(($retryingSms / $totalSms) * 100, 2) : 0;

        // Daily stats for the last 30 days
        $dailyStats = SmsLog::query()
            ->where('shop_id', $shopId)
            ->when($dateFrom, function ($q, $dateFrom) {
                return $q->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($q, $dateTo) {
                return $q->whereDate('created_at', '<=', $dateTo);
            })
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END) as sent')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        // Provider stats
        $providerStats = SmsLog::query()
            ->where('shop_id', $shopId)
            ->when($dateFrom, function ($q, $dateFrom) {
                return $q->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($q, $dateTo) {
                return $q->whereDate('created_at', '<=', $dateTo);
            })
            ->selectRaw('provider, COUNT(*) as total, SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END) as sent, SUM(CASE WHEN status = \'failed\' OR status = \'error\' THEN 1 ELSE 0 END) as failed')
            ->groupBy('provider')
            ->get();

        // Event stats
        $eventStats = SmsLog::query()
            ->where('shop_id', $shopId)
            ->when($dateFrom, function ($q, $dateFrom) {
                return $q->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($q, $dateTo) {
                return $q->whereDate('created_at', '<=', $dateTo);
            })
            ->whereNotNull('event')
            ->selectRaw('event, COUNT(*) as total, SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END) as sent')
            ->groupBy('event')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $data = compact(
            'totalSms',
            'sentSms',
            'failedSms',
            'errorSms',
            'queuedSms',
            'retryingSms',
            'deliveryRate',
            'failureRate',
            'retryRate',
            'dailyStats',
            'providerStats',
            'eventStats'
        );

        $data['filters'] = [
            'date_from' => $dateFrom?->format('Y-m-d'),
            'date_to' => $dateTo?->format('Y-m-d'),
        ];

        return view('sms.analytics.index', $data);
    }
}
