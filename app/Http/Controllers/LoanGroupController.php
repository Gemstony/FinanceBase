<?php

namespace App\Http\Controllers;

use App\Models\Customers;
use App\Models\LoanGroupMembers;
use App\Models\LoanGroups;
use App\Models\Shop;
use App\Models\SubShop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanGroupController extends Controller
{
    private function getShopSubshopIds(SubShop $subshop)
    {
        return SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $subshopId = session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = $this->getShopSubshopIds($subshop);

        $groups = LoanGroups::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->with([
                'members' => function ($q) {
                    $q->where('is_active', true)
                    ->with('customer');
                },
            ])
            ->orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Prepare JSON-ready member data (BEST PRACTICE)
        |--------------------------------------------------------------------------
        */
        $groups->each(function ($group) {
            $group->members_json = $group->members->map(function ($m) {
                return [
                    'id' => $m->id,
                    'role' => $m->role,
                    'joined_at' => $m->joined_at
                        ? $m->joined_at->format('Y-m-d')
                        : null,
                    'customer_name' => $m->customer
                        ? $m->customer->name
                        : null,
                ];
            });
        });


        $customers = Customers::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('loans.loan_groups.loan_groups', compact('subshop', 'groups', 'customers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $subshopId = session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = $this->getShopSubshopIds($subshop);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('loan_groups', 'code')->where(fn ($q) => $q->whereIn('subshop_id', $shopSubshopIds)),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'formation_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['subshop_id'] = $subshopId;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        LoanGroups::create($validated);

        return redirect()->back()->with('success', 'Loan group created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $subshopId = session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = $this->getShopSubshopIds($subshop);
        $group = LoanGroups::whereIn('subshop_id', $shopSubshopIds)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('loan_groups', 'code')
                    ->where(fn ($q) => $q->whereIn('subshop_id', $shopSubshopIds))
                    ->ignore($group->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'formation_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $group->update($validated);

        return redirect()->back()->with('success', 'Loan group updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        $subshopId = session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = $this->getShopSubshopIds($subshop);
        $group = LoanGroups::whereIn('subshop_id', $shopSubshopIds)->findOrFail($id);

        $group->delete();

        return redirect()->back()->with('success', 'Loan group deleted successfully.');
    }

    public function addMember(Request $request, string $groupId): RedirectResponse
    {
        $subshopId = session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = $this->getShopSubshopIds($subshop);
        $group = LoanGroups::whereIn('subshop_id', $shopSubshopIds)->findOrFail($groupId);

        $validated = $request->validate([
            'members' => ['required', 'array', 'min:1'],
            'members.*.customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where(fn ($q) => $q->whereIn('subshop_id', $shopSubshopIds))],
            'members.*.role' => ['required', Rule::in(['member', 'leader', 'secretary', 'treasurer'])],
            'members.*.joined_at' => ['nullable', 'date'],
        ]);

        $addedCount = 0;
        $duplicates = 0;

        DB::transaction(function () use ($group, $validated, &$addedCount, &$duplicates) {
            foreach ($validated['members'] as $memberData) {
                // Check if member already exists in the group
                $existingMember = LoanGroupMembers::where('loan_group_id', $group->id)
                    ->where('customer_id', $memberData['customer_id'])
                    ->where('is_active', true)
                    ->first();

                if ($existingMember) {
                    $duplicates++;
                    continue;
                }

                LoanGroupMembers::updateOrCreate(
                    [
                        'loan_group_id' => $group->id,
                        'customer_id' => $memberData['customer_id'],
                    ],
                    [
                        'role' => $memberData['role'],
                        'joined_at' => $memberData['joined_at'] ?? now()->toDateString(),
                        'left_at' => null,
                        'is_active' => true,
                    ]
                );

                $addedCount++;
            }
        });

        $message = "Added {$addedCount} member(s) to the group successfully.";
        if ($duplicates > 0) {
            $message .= " {$duplicates} member(s) were already in the group and were skipped.";
        }

        return redirect()->back()->with('success', $message);
    }

    public function removeMember(string $memberId): RedirectResponse
    {
        $subshopId = session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = $this->getShopSubshopIds($subshop);

        $member = LoanGroupMembers::query()
            ->whereHas('group', fn ($q) => $q->whereIn('subshop_id', $shopSubshopIds))
            ->findOrFail($memberId);

        $member->update([
            'left_at' => now()->toDateString(),
            'is_active' => false,
        ]);

        return redirect()->back()->with('success', 'Member marked as left the group.');
    }
}
