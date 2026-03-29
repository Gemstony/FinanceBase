<?php

namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsTemplate;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SmsTemplateController extends Controller
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

        $event = $request->input('event');
        
        $query = SmsTemplate::query()->with('shop');
        
        // Filter by current shop only
        $query->where('shop_id', $shopId);
        
        if ($event) {
            $query->where('event', $event);
        }
        
        $templates = $query->latest()->paginate(25)->withQueryString();
        
        $events = SmsTemplate::select('event')
            ->where('shop_id', $shopId)
            ->distinct()
            ->pluck('event')
            ->filter()
            ->values();
        
        return view('sms.templates.index', compact('templates', 'events'));
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

        return view('sms.templates.create');
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
            'name' => 'required|string|max:255',
            'event' => 'required|string|max:255',
            'message_template' => 'required|string',
            'variables' => 'sometimes|string',
            'is_active' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        // Convert comma-separated string to array
        $variables = $request->input('variables', '');
        $variablesArray = $variables ? array_map('trim', explode(',', $variables)) : [];
        
        $template = SmsTemplate::create([
            'shop_id' => $shopId,
            'name' => $request->name,
            'event' => $request->event,
            'message_template' => $request->message_template,
            'variables' => $variablesArray,
            'is_active' => $request->boolean('is_active'),
        ]);
        
        return redirect()->route('sms.templates.index')
            ->with('success', 'SMS template created successfully.');
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

        $smsTemplate = SmsTemplate::with('shop')
            ->where('shop_id', $shopId)
            ->findOrFail($id);
        
        return view('sms.templates.show', compact('smsTemplate'));
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

        $smsTemplate = SmsTemplate::with('shop')
            ->where('shop_id', $shopId)
            ->findOrFail($id);

        return view('sms.templates.edit', compact('smsTemplate'));
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

        $smsTemplate = SmsTemplate::where('shop_id', $shopId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'event' => 'required|string|max:255',
            'message_template' => 'required|string',
            'variables' => 'sometimes|string',
            'is_active' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        // Convert comma-separated string to array
        $variables = $request->input('variables', '');
        $variablesArray = $variables ? array_map('trim', explode(',', $variables)) : [];
        
        $smsTemplate->update([
            'name' => $request->name,
            'event' => $request->event,
            'message_template' => $request->message_template,
            'variables' => $variablesArray,
            'is_active' => $request->boolean('is_active'),
        ]);
        
        return redirect()->route('sms.templates.index')
            ->with('success', 'SMS template updated successfully.');
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

        $smsTemplate = SmsTemplate::where('shop_id', $shopId)->findOrFail($id);
        $smsTemplate->delete();
        
        return redirect()->route('sms.templates.index')
            ->with('success', 'SMS template deleted successfully.');
    }

    /**
     * Preview template with sample data
     */
    public function preview(Request $request, $id)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $smsTemplate = SmsTemplate::where('shop_id', $shopId)->findOrFail($id);

        $sampleData = [];
        foreach ($smsTemplate->variables as $variable) {
            $sampleData[$variable] = 'Sample ' . ucfirst($variable);
        }
        
        $previewMessage = app(\App\Services\Sms\SmsTemplateEngine::class)
            ->render($smsTemplate->message_template, $sampleData);
        
        return response()->json([
            'preview' => $previewMessage,
            'variables' => $smsTemplate->variables
        ]);
    }
}
