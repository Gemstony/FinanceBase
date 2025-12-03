<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\SubShop;
use App\Models\SmsLog;
use Illuminate\Http\Request;

class SmsManagementController extends Controller
{
    public function index(Request $request)
    {
        // Filters
        $shopId = $request->integer('shop_id');
        $subshopId = $request->integer('subshop_id');
        $status = $request->string('status')->toString() ?: null;
        $type = $request->string('type')->toString() ?: null;
        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');

        $query = SmsLog::query()->with(['shop', 'subshop', 'owner']);

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }
        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($type) {
            $query->where('type', $type);
        }
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->latest()->paginate(25)->withQueryString();

        // Summary stats
        $summaryBase = SmsLog::query();
        if ($shopId) $summaryBase->where('shop_id', $shopId);
        if ($subshopId) $summaryBase->where('subshop_id', $subshopId);
        if ($dateFrom) $summaryBase->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo) $summaryBase->whereDate('created_at', '<=', $dateTo);

        $total = (clone $summaryBase)->count();
        $sent = (clone $summaryBase)->where('status', 'sent')->count();
        $failed = (clone $summaryBase)->where('status', 'failed')->count();
        $errors = (clone $summaryBase)->where('status', 'error')->count();
        $queued = (clone $summaryBase)->where('status', 'queued')->count();

        $shops = Shop::with('subShops')->orderBy('name')->get();

        // Distinct types for filter
        $types = SmsLog::select('type')->distinct()->pluck('type')->filter()->values();

        return view('shops_management.sms_management', [
            'logs' => $logs,
            'shops' => $shops,
            'types' => $types,
            'filters' => [
                'shop_id' => $shopId,
                'subshop_id' => $subshopId,
                'status' => $status,
                'type' => $type,
                'date_from' => $dateFrom?->format('Y-m-d'),
                'date_to' => $dateTo?->format('Y-m-d'),
            ],
            'summary' => [
                'total' => $total,
                'sent' => $sent,
                'failed' => $failed,
                'errors' => $errors,
                'queued' => $queued,
            ],
        ]);
    }
}
