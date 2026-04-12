<?php

namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsEvent;
use App\Models\SmsTemplate;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SmsEventController extends Controller
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

        $validated = $request->validate([
            'event_name' => ['nullable', 'string', 'max:255'],
        ]);

        $query = SmsEvent::with(['shop', 'template']);

        // Filter by current shop only
        $query->where('shop_id', $shopId);

        // Filter by event name (flexible search)
        if (!empty($validated['event_name'])) {
            $query->where('event_name', 'like', '%' . $validated['event_name'] . '%');
        }

        $events = $query
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        // Load only necessary fields (optimization)
        $templates = SmsTemplate::select('id', 'name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('sms.events.index', compact('events', 'templates'));
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
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;


        $templates = SmsTemplate::where('shop_id', $shopId)
        ->where('is_active', true)->get();
        return view('sms.events.create', compact('templates'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $validator = Validator::make($request->all(), [
            'event_name' => 'required|string|max:255',
            'template_id' => 'nullable|exists:sms_templates,id',
            'is_enabled' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        $event = SmsEvent::create([
            'shop_id' => $shopId,
            'event_name' => $request->event_name,
            'template_id' => $request->template_id,
            'is_enabled' => $request->boolean('is_enabled'),
        ]);
        
        return redirect()->route('sms.events.index')
            ->with('success', 'SMS event mapping created successfully.');
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

        $smsEvent = SmsEvent::with('shop', 'template')
            ->where('shop_id', $shopId)
            ->findOrFail($id);

        return view('sms.events.show', compact('smsEvent'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $smsEvent = SmsEvent::with('shop', 'template')
            ->where('shop_id', $shopId)
            ->findOrFail($id);

        $templates = SmsTemplate::where('is_active', true)->get();
        return view('sms.events.edit', compact('smsEvent', 'templates'));
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $smsEvent = SmsEvent::where('shop_id', $shopId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'event_name' => 'required|string|max:255',
            'template_id' => 'nullable|exists:sms_templates,id',
            'is_enabled' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        $smsEvent->update($request->only([
            'event_name',
            'template_id',
            'is_enabled',
        ]));
        
        return redirect()->route('sms.events.index')
            ->with('success', 'SMS event mapping updated successfully.');
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

        $smsEvent = SmsEvent::where('shop_id', $shopId)->findOrFail($id);
        $smsEvent->delete();
        
        return redirect()->route('sms.events.index')
            ->with('success', 'SMS event mapping deleted successfully.');
    }
}
