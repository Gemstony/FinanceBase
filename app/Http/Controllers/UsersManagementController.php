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
    public function create(Request $request)
    {
        $auth = $request->user();
        if (!$auth || !$this->isOwner($auth)) {
            abort(403);
        }

        $shopId = $this->currentShopId($request);
        if (!$shopId) {
            return redirect()->route('items.subshops')->with('info', 'Please choose a shop first.');
        }

        $subshops = SubShop::where('shop_id', $shopId)->orderBy('name')->get(['id','name']);
        $roles = Role::where(function($q) use ($shopId) {
            $q->whereNull('shop_id')->orWhere('shop_id', $shopId);
        })->get();

        return view('users.create', compact('subshops','roles'));
    }

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
        $roles = Role::where(function($q) use ($shopId) {
            $q->whereNull('shop_id')->orWhere('shop_id', $shopId);
        })->get();

        // Get performance stats for each subshop (Microfinance)
        $stats = [];
        $userSubshops = $user->subshops()->where('sub_shops.shop_id', $shopId)->get();

        foreach ($userSubshops as $subshop) {
            $subshopId = $subshop->id;

            // Loans processed by this user at this subshop
            $loansDisbursed = \App\Models\Loans::where('subshop_id', $subshopId)
                ->whereHas('disbursements', function($q) use ($user) {
                    $q->where('processed_by', $user->id);
                })->count();

            $disbursementAmount = \App\Models\LoanDisbursements::whereHas('loan', function($q) use ($subshopId) {
                    $q->where('subshop_id', $subshopId);
                })->where('processed_by', $user->id)->sum('amount');

            // Repayments collected by this user
            $repaymentsCount = \App\Models\LoanPayments::whereHas('loan', function($q) use ($subshopId) {
                    $q->where('subshop_id', $subshopId);
                })->where('user_id', $user->id)->count();

            $repaymentsAmount = \App\Models\LoanPayments::whereHas('loan', function($q) use ($subshopId) {
                    $q->where('subshop_id', $subshopId);
                })->where('user_id', $user->id)->sum('amount');

            // Active loans (disbursed and not fully paid)
            $activeLoans = \App\Models\Loans::where('subshop_id', $subshopId)
                ->where('status', 'disbursed')
                ->where('outstanding_balance', '>', 0)
                ->whereHas('disbursements', function($q) use ($user) {
                    $q->where('processed_by', $user->id);
                })->count();

            // Pending approvals
            $pendingApprovals = \App\Models\Loans::where('subshop_id', $subshopId)
                ->where('status', 'pending')
                ->whereHas('approvals', function($q) use ($user) {
                    $q->where('approved_by', $user->id)->where('status', 'pending');
                })->count();

            // Write-offs processed
            $writeOffs = \App\Models\LoanWriteoffs::whereHas('loan', function($q) use ($subshopId) {
                    $q->where('subshop_id', $subshopId);
                })->where('approved_by', $user->id)->count();

            $writeOffAmount = \App\Models\LoanWriteoffs::whereHas('loan', function($q) use ($subshopId) {
                    $q->where('subshop_id', $subshopId);
                })->where('approved_by', $user->id)->sum('total_written_off');

            // Overdue loans
            $overdueLoans = \App\Models\Loans::where('subshop_id', $subshopId)
                ->where('status', 'disbursed')
                ->where('outstanding_balance', '>', 0)
                ->where('maturity_date', '<', now())
                ->whereHas('disbursements', function($q) use ($user) {
                    $q->where('processed_by', $user->id);
                })->count();

            $overdueAmount = \App\Models\Loans::where('subshop_id', $subshopId)
                ->where('status', 'disbursed')
                ->where('outstanding_balance', '>', 0)
                ->where('maturity_date', '<', now())
                ->whereHas('disbursements', function($q) use ($user) {
                    $q->where('processed_by', $user->id);
                })->sum('outstanding_balance');

            // Total portfolio value for this user at this subshop
            $portfolioValue = \App\Models\Loans::where('subshop_id', $subshopId)
                ->where('status', 'disbursed')
                ->whereHas('disbursements', function($q) use ($user) {
                    $q->where('processed_by', $user->id);
                })->sum('outstanding_balance');

            $stats[] = [
                'subshop_name' => $subshop->name,
                'loans_disbursed' => $loansDisbursed,
                'disbursement_amount' => $disbursementAmount,
                'repayments_count' => $repaymentsCount,
                'repayments_amount' => $repaymentsAmount,
                'active_loans' => $activeLoans,
                'pending_approvals' => $pendingApprovals,
                'writeoffs_count' => $writeOffs,
                'writeoffs_amount' => $writeOffAmount,
                'overdue_loans' => $overdueLoans,
                'overdue_amount' => $overdueAmount,
                'portfolio_value' => $portfolioValue,
            ];
        }

        // For AJAX requests, return JSON
        if ($request->expectsJson()) {
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
        }

        return view('users.show', compact('user', 'subshops', 'roles', 'stats'));
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
        $roles = Role::where(function($q) use ($shopId) {
            $q->whereNull('shop_id')->orWhere('shop_id', $shopId);
        })->get();

        return view('users.edit', compact('user', 'subshops', 'roles'));
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
            // if ($user->phone_number) {
            //     $smsService = new SmsService();
            //     $smsService->sendPasswordResetSms($user->phone_number, $request->password);
            // }

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
