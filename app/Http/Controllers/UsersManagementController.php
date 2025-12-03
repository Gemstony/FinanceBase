<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SubShop;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\Role;
use App\Services\SmsService;

class UsersManagementController extends Controller
{
    public function index(Request $request)
    {
        $auth = $request->user();
        if (!$auth || !$this->isOwner($auth)) {
            abort(403);
        }

        $shopId = $this->currentShopId($request);
        if (!$shopId) {
            return redirect()->route('items.subshops')->with('info', 'Please choose a shop first.');
        }

        // Only users assigned to subshops of the active shop
        $users = User::whereHas('subshops', function($q) use ($shopId) {
                $q->where('sub_shops.shop_id', $shopId);
            })
            ->with(['subshops' => function($q) use ($shopId){
                $q->where('sub_shops.shop_id', $shopId);
            }])
            ->orderByDesc('id')
            ->get();

        // Subshops limited to active shop
        $subshops = SubShop::where('shop_id', $shopId)->orderBy('name')->get(['id','name']);

        // Roles available for assignment
        $roles = Role::where(function($q) use ($shopId) {
            $q->whereNull('shop_id')->orWhere('shop_id', $shopId);
        })->get();

        return view('users.users', compact('users','subshops','roles'));
    }

    public function store(Request $request)
    {
        $auth = $request->user();
        if (!$auth || !$this->isOwner($auth)) {
            abort(403);
        }

        $shopId = $this->currentShopId($request);
        if (!$shopId) {
            return redirect()->route('items.subshops')->with('info', 'Please choose a shop first.');
        }

        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255', Rule::unique('users','email')],
            'phone_number' => ['nullable','string','max:50'],
            'role' => ['required', 'exists:roles,name'],
            'password' => ['required','confirmed','min:6'],
            'subshop_ids' => ['nullable','array'],
            'subshop_ids.*' => [
                'integer',
                Rule::exists('sub_shops','id')->where(function($q) use ($shopId){
                    $q->where('shop_id', $shopId);
                }),
            ],
        ]);

        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone_number = $data['phone_number'] ?? null;
        $user->password = Hash::make($data['password']);
        $user->save();

        // Assign role (Spatie)
        $user->assignRole($data['role']);

        // Optional initial subshop assignments
        if (!empty($data['subshop_ids'])) {
            $user->subshops()->sync($data['subshop_ids']);
        }

        return redirect()->back()->with('success', 'User created successfully.');
    }

    public function assignSubshops(Request $request, User $user)
    {
        $auth = $request->user();
        if (!$auth || !$this->isOwner($auth)) {
            abort(403);
        }

        $shopId = $this->currentShopId($request);
        if (!$shopId) {
            return redirect()->route('items.subshops')->with('info', 'Please choose a shop first.');
        }

        $data = $request->validate([
            'subshop_ids' => ['required','array'],
            'subshop_ids.*' => [
                'integer',
                Rule::exists('sub_shops','id')->where(function($q) use ($shopId){
                    $q->where('shop_id', $shopId);
                }),
            ],
        ]);

        $user->subshops()->sync($data['subshop_ids']);

        return redirect()->back()->with('success', 'Assignments updated.');
    }

    public function show(Request $request, User $user)
    {
        try {
            $auth = $request->user();
            if (!$auth || !$this->isOwner($auth)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $shopId = $this->currentShopId($request);
            if (!$shopId) {
                return response()->json(['error' => 'Please choose a shop first.'], 400);
            }

            // Ensure user belongs to the shop
            if (!$user->subshops()->where('sub_shops.shop_id', $shopId)->exists()) {
                return response()->json(['error' => 'User not found in this shop.'], 404);
            }

            $subshops = $user->subshops()->where('sub_shops.shop_id', $shopId)->select('sub_shops.id', 'sub_shops.name')->get();

            $stats = [];
            foreach ($subshops as $subshop) {
                $subshopId = $subshop->id;

                // Calculate stats for this subshop
                $totalShopRevenue = \App\Models\SalesOrders::where('subshop_id', $subshopId)->sum('grand_total');
                $userRevenue = \App\Models\SalesOrders::where('subshop_id', $subshopId)->where('created_by', $user->id)->sum('grand_total');
                $participationPercentage = $totalShopRevenue > 0 ? round(($userRevenue / $totalShopRevenue) * 100, 2) : 0;

                $itemsSold = \App\Models\SalesOrdersItems::whereHas('order', function($q) use ($subshopId, $user) {
                    $q->where('subshop_id', $subshopId)->where('created_by', $user->id);
                })->sum('quantity');

                $salesTransactions = \App\Models\SalesOrders::where('subshop_id', $subshopId)->where('created_by', $user->id)->count();

                $purchaseTransactions = \App\Models\PurchaseOrders::where('subshop_id', $subshopId)->where('created_by', $user->id)->count();

                $writeoffs = \App\Models\WriteOff::where('created_by', $user->id)->where('subshop_id', $subshopId)->count();

                $expenses = \App\Models\Expenses::where('created_by', $user->id)->where('subshop_id', $subshopId)->count();

                $stats[] = [
                    'subshop_name' => $subshop->name,
                    'participation_percentage' => $participationPercentage,
                    'items_sold' => $itemsSold,
                    'sales_value' => $userRevenue,
                    'sales_transactions' => $salesTransactions,
                    'purchase_transactions' => $purchaseTransactions,
                    'writeoffs' => $writeoffs,
                    'expenses' => $expenses,
                ];
            }

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'role' => $user->getRoleNames()->first(),
                ],
                'subshops' => $user->subshops()->where('sub_shops.shop_id', $shopId)->select('sub_shops.id', 'sub_shops.name')->get(),
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in UsersManagementController@show: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);
            $errorMessage = config('app.debug') ? $e->getMessage() : 'Internal server error';
            return response()->json(['error' => $errorMessage], 500);
        }
    }

    public function edit(Request $request, User $user)
    {
        $auth = $request->user();
        if (!$auth || !$this->isOwner($auth)) {
            abort(403);
        }

        $shopId = $this->currentShopId($request);
        if (!$shopId) {
            return redirect()->route('items.subshops')->with('info', 'Please choose a shop first.');
        }

        // Ensure user belongs to the shop
        if (!$user->subshops()->where('sub_shops.shop_id', $shopId)->exists()) {
            abort(404);
        }

        $subshops = SubShop::where('shop_id', $shopId)->orderBy('name')->get(['id','name']);

        if ($request->expectsJson()) {
            return response()->json([
                'user' => $user->load('subshops'),
                'subshops' => $subshops
            ]);
        }

        // For non-AJAX, return view if needed
        return view('users.edit', compact('user', 'subshops'));
    }

    public function update(Request $request, User $user)
    {
        $auth = $request->user();
        if (!$auth || !$this->isOwner($auth)) {
            abort(403);
        }

        $shopId = $this->currentShopId($request);
        if (!$shopId) {
            return redirect()->route('items.subshops')->with('info', 'Please choose a shop first.');
        }

        // Ensure user belongs to the shop
        if (!$user->subshops()->where('sub_shops.shop_id', $shopId)->exists()) {
            abort(404);
        }

        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'phone_number' => ['nullable','string','max:50'],
            'role' => ['required', 'exists:roles,name'],
            'subshop_ids' => ['nullable','array'],
            'subshop_ids.*' => [
                'integer',
                Rule::exists('sub_shops','id')->where(function($q) use ($shopId){
                    $q->where('shop_id', $shopId);
                }),
            ],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone_number = $data['phone_number'] ?? null;
        $user->save();

        // Update role if changed
        if ($user->getRoleNames()->first() !== $data['role']) {
            $user->syncRoles([$data['role']]);
        }

        // Update subshop assignments
        if (isset($data['subshop_ids'])) {
            $user->subshops()->sync($data['subshop_ids']);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'User updated successfully.']);
        }

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        $auth = $request->user();
        if (!$auth || !$this->isOwner($auth)) {
            abort(403);
        }

        $shopId = $this->currentShopId($request);
        if (!$shopId) {
            return redirect()->route('items.subshops')->with('info', 'Please choose a shop first.');
        }

        // Ensure user belongs to the shop
        if (!$user->subshops()->where('sub_shops.shop_id', $shopId)->exists()) {
            abort(404);
        }

        // Prevent deleting owners or self
        if ($user->hasRole('owner') || $user->id === $auth->id) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Cannot delete this user.'], 403);
            }
            return redirect()->back()->with('error', 'Cannot delete this user.');
        }

        $user->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'User deleted successfully.']);
        }

        return redirect()->back()->with('success', 'User deleted successfully.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $auth = $request->user();
        if (!$auth || !$this->isOwner($auth)) {
            abort(403);
        }

        $shopId = $this->currentShopId($request);
        if (!$shopId) {
            return redirect()->route('items.subshops')->with('info', 'Please choose a shop first.');
        }

        // Ensure user belongs to the shop
        if (!$user->subshops()->where('sub_shops.shop_id', $shopId)->exists()) {
            abort(404);
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            // Send SMS notification if user has phone number
            if ($user->phone_number) {
                $smsService = new SmsService();
                $smsService->sendPasswordResetSms($user->phone_number, $request->password);
            }

            return redirect()->back()->with('success', 'Password reset successfully for ' . $user->name . '.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to reset password: ' . $e->getMessage());
        }
    }

    private function isOwner(User $user): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('owner')) {
            return true;
        }
        // Treat users who own at least one shop as owners
        return Shop::where('user_id', $user->id)->exists();
    }

    private function currentShopId(Request $request): ?int
    {
        $subshopId = (int) $request->session()->get('subshop_id');
        if (!$subshopId) return null;

        $sub = SubShop::select('shop_id')->find($subshopId);
        return $sub?->shop_id;
    }
}
