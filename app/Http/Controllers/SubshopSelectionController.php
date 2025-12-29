<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\SubShop;
use Illuminate\Http\Request;

class SubshopSelectionController extends Controller
{
    // Show choose subshop page
    public function index(Request $request)
    {
        $user = $request->user();
        $intended = $request->query('intended', url()->previous());

        $subshops = $this->accessibleSubshops($user);

        // If only one, auto-select and redirect
        if ($subshops->count() === 1) {
            $request->session()->put('subshop_id', $subshops->first()->id);
            return redirect(route('dashboard'));
        }

        return view('subshops.choose', [
            'subshops' => $subshops,
            'intended' => $intended,
        ]);
    }

    // Handle choose
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'subshop_id' => ['required','integer','exists:sub_shops,id'],
            'intended' => ['nullable','string'],
        ]);

        // Validate selected subshop is accessible to the user
        $subshops = $this->accessibleSubshops($user);
        if (!$subshops->contains('id', (int) $data['subshop_id'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to access that branch.'
                ], 403);
            }
            return redirect()->back()->with('error', 'You are not allowed to access that branch.');
        }

        $request->session()->put('subshop_id', (int) $data['subshop_id']);

        $target = $data['intended'] ?: route('dashboard');
        if ($target === route('subshops.choose')) {
            $target = route('dashboard');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Active branch updated.',
                'redirect' => $target
            ]);
        }

        return redirect($target)->with('success', 'Active branch updated.');
    }

    private function accessibleSubshops($user)
    {
        if (!$user) return collect();

        // Owner: all subshops of shops they own
        $ownsShop = method_exists($user, 'hasShop') ? $user->hasShop() : false;
        if ($ownsShop) {
            $shopIds = Shop::where('user_id', $user->id)->pluck('id');
            return SubShop::whereIn('shop_id', $shopIds)->active()->orderBy('name')->get(['id','name','shop_id']);
        }

        // Shopkeeper: assigned subshops
        if (method_exists($user, 'subshops')) {
            return $user->subshops()->active()->orderBy('name')->get(['sub_shops.id','sub_shops.name','sub_shops.shop_id']);
        }

        return collect();
    }
}
