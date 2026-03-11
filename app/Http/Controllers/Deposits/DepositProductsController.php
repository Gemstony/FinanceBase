<?php

declare(strict_types=1);

namespace App\Http\Controllers\Deposits;

use App\Http\Controllers\Controller;
use App\Models\DepositProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepositProductsController extends Controller
{
    public function index(Request $request): View
    {
        $subshopId = (int) session('subshop_id');

        $query = DepositProduct::query()
            ->where('subshop_id', $subshopId)
            ->withCount('depositAccounts')
            ->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', (string) $request->string('type'));
        }

        if ($request->filled('status')) {
            $status = $request->string('status') === 'active';
            $query->where('is_active', $status);
        }

        $products = $query->paginate(20)->withQueryString();

        return view('customer_deposits.products.index', compact('products'));
    }

    public function create(): View
    {
        return view('customer_deposits.products.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:savings,current,term_deposit'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'minimum_balance' => ['required', 'numeric', 'min:0'],
            'withdrawal_fee' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);

        DepositProduct::create([
            'subshop_id' => (int) session('subshop_id'),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'interest_rate' => (float) $validated['interest_rate'],
            'minimum_balance' => (float) $validated['minimum_balance'],
            'withdrawal_fee' => (float) $validated['withdrawal_fee'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('deposits.products.index')->with('success', 'Deposit product created successfully.');
    }

    public function edit(DepositProduct $product): View
    {
        if ((int) $product->subshop_id !== (int) session('subshop_id')) {
            abort(403);
        }

        return view('customer_deposits.products.edit', compact('product'));
    }

    public function update(Request $request, DepositProduct $product): RedirectResponse
    {
        if ((int) $product->subshop_id !== (int) session('subshop_id')) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:savings,current,term_deposit'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'minimum_balance' => ['required', 'numeric', 'min:0'],
            'withdrawal_fee' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);

        $product->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'interest_rate' => (float) $validated['interest_rate'],
            'minimum_balance' => (float) $validated['minimum_balance'],
            'withdrawal_fee' => (float) $validated['withdrawal_fee'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('deposits.products.index')->with('success', 'Deposit product updated successfully.');
    }

    public function destroy(DepositProduct $product): RedirectResponse
    {
        if ((int) $product->subshop_id !== (int) session('subshop_id')) {
            abort(403);
        }

        if ($product->depositAccounts()->exists()) {
            return back()->with('error', 'Cannot delete product with existing accounts.');
        }

        $product->delete();

        return redirect()->route('deposits.products.index')->with('success', 'Deposit product deleted successfully.');
    }
}
