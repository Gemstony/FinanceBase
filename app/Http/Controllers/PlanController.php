<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:plans,slug',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'billing_cycle' => 'required|in:monthly,2_months,3_months,6_months,yearly,one_time',
            'status' => 'required|in:active,inactive,archived',
            'features' => 'nullable|json',
            'limits' => 'nullable|json',
            'is_popular' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        try {
            $plan = Plan::create([
                'name' => $request->name,
                'slug' => $request->slug,
                'description' => $request->description,
                'price' => $request->price,
                'currency' => strtoupper($request->currency),
                'billing_cycle' => $request->billing_cycle,
                'status' => $request->status,
                'features' => $request->features ? json_decode($request->features, true) : null,
                'limits' => $request->limits ? json_decode($request->limits, true) : null,
                'is_popular' => $request->boolean('is_popular', false),
                'sort_order' => $request->integer('sort_order', 0)
            ]);

            return redirect()->back()->with('success', 'Plan created successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create plan: ' . $e->getMessage())->withInput();
        }
    }

    public function update(Request $request, Plan $plan)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:plans,slug,' . $plan->id,
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'billing_cycle' => 'required|in:monthly,2_months,3_months,6_months,yearly,one_time',
            'status' => 'required|in:active,inactive,archived',
            'features' => 'nullable|json',
            'limits' => 'nullable|json',
            'is_popular' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        try {
            $plan->update([
                'name' => $request->name,
                'slug' => $request->slug,
                'description' => $request->description,
                'price' => $request->price,
                'currency' => strtoupper($request->currency),
                'billing_cycle' => $request->billing_cycle,
                'status' => $request->status,
                'features' => $request->features ? json_decode($request->features, true) : null,
                'limits' => $request->limits ? json_decode($request->limits, true) : null,
                'is_popular' => $request->boolean('is_popular', false),
                'sort_order' => $request->integer('sort_order', 0)
            ]);

            return redirect()->back()->with('success', 'Plan updated successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update plan: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(Plan $plan)
    {
        try {
            // Check if plan has active subscriptions
            if ($plan->activeSubscriptions()->count() > 0) {
                return redirect()->back()->with('error', 'Cannot delete plan with active subscriptions.');
            }

            $plan->delete();
            return redirect()->back()->with('success', 'Plan deleted successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete plan: ' . $e->getMessage());
        }
    }}
