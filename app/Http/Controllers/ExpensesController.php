<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Expenses;
use App\Models\Banks;
use App\Models\SubShop;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Services\SmsService;

class ExpensesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('expenses.index')]);
        }

        $subshop = SubShop::findOrFail($subshopId);
        if ($subshop->is_active != 1) {
            return redirect()->route('items.subshops')->with('error', 'Shop is not active please contact the owner to activate it.');
        }

        $banks = Banks::where('subshop_id', $subshopId)->where('is_active', 1)->orderBy('name')->get();

        $query = Expenses::with(['subshop', 'creator', 'reviewed', 'paymentBank'])
            ->where('subshop_id', $subshopId);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && in_array($request->status, ['pending','approved','rejected'])) {
            $query->where('status', $request->status);
        }

        // Date range filters
        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->input('date_to'));
        }

        // Amount range filters
        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', (float) $request->input('min_amount'));
        }
        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', (float) $request->input('max_amount'));
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        // Recorded by (creator name) filter
        if ($request->filled('recorded_by')) {
            $name = $request->input('recorded_by');
            $query->whereHas('creator', function($q) use ($name) {
                $q->where('name', 'like', "%{$name}%");
            });
        }

        // Sorting
        $sort = $request->input('sort');
        if ($sort === 'date_asc') {
            $query->orderBy('expense_date', 'asc');
        } elseif ($sort === 'date_desc' || !$sort) {
            // Default: date desc if column exists, else id desc
            if (Schema::hasColumn('expenses', 'expense_date')) {
                $query->orderBy('expense_date', 'desc');
            } else {
                $query->orderBy('id', 'desc');
            }
        } elseif ($sort === 'amount_asc') {
            $query->orderBy('amount', 'asc');
        } elseif ($sort === 'amount_desc') {
            $query->orderBy('amount', 'desc');
        } else {
            // Fallback default
            if (Schema::hasColumn('expenses', 'expense_date')) {
                $query->orderBy('expense_date', 'desc');
            } else {
                $query->orderBy('id', 'desc');
            }
        }

        $expenses = $query->paginate(15)->appends($request->query());

        return view('expenses.expenses', compact('expenses', 'subshop', 'banks'));
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subshop_id' => 'required|exists:sub_shops,id',
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'payment_method' => 'required|exists:banks,id',
        ]);

        // Ensure the selected bank belongs to this subshop
        $bank = Banks::where('id', $validated['payment_method'] ?? null)
            ->where('subshop_id', $validated['subshop_id'])
            ->first();
        if (!$bank) {
            return back()->withErrors(['payment_method' => 'Invalid payment method for this shop.'])->withInput();
        }

        return DB::transaction(function () use ($validated, $request) {
            $expense = new Expenses([
                'subshop_id' => $validated['subshop_id'],
                'title' => $validated['title'],
                'amount' => (float)$validated['amount'],
                'expense_date' => $validated['expense_date'],
                'category' => $validated['category'] ?? null,
                'description' => $validated['description'] ?? null,
                'payment_method' => $validated['payment_method'],
                'status' => 'pending',
                'created_by' => Auth::id(),
            ]);
            $expense->save();

            // Send SMS notification to shop owner about new expense
            try {
                $expense->load(['subshop.shop.user', 'creator', 'paymentBank']);
                $this->sendExpenseNotificationToOwner($expense);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send expense SMS notification: ' . $e->getMessage());
                // Don't fail the expense creation if SMS fails
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Expense recorded successfully and pending approval.',
                        'redirect' => route('expenses.index')
                ]);
            }

            return redirect()
                ->route('expenses.index')
                ->with('success', 'Expense recorded successfully and pending approval.');
        });
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
    public function update(Request $request, string $id)
    {
        // Not used currently
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $expense = Expenses::findOrFail($id);
        if ($expense->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending expenses can be deleted.'
            ], 422);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense has been deleted.'
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'notes' => 'nullable|string',
        ]);

        $expense = Expenses::findOrFail($id);
        if ($expense->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending expenses can be updated.'
            ], 422);
        }

        return DB::transaction(function () use ($expense, $validated) {
            $expense->status = $validated['status'];
            $expense->reviewed_by = Auth::id();
            $expense->reviewed_at = now();
            $expense->review_notes = $validated['notes'] ?? null;
            $expense->save();

            return response()->json([
                'success' => true,
                'message' => 'Expense has been ' . $validated['status'] . '.',
                'status' => $validated['status']
            ]);
        });
    }

    public function subshops()
    {
        return redirect()->route('subshops.choose', ['intended' => route('expenses.index')]);
    }

    /**
     * Send SMS notification to shop owner about new expense creation
     */
    private function sendExpenseNotificationToOwner(Expenses $expense)
    {
        \Illuminate\Support\Facades\Log::info('=== START sendExpenseNotificationToOwner ===', [
            'expense_id' => $expense->id,
            'expense_title' => $expense->title,
            'amount' => $expense->amount,
        ]);
        
        // Get shop owner
        $subshop = $expense->subshop;
        \Illuminate\Support\Facades\Log::info('Subshop check', [
            'subshop_exists' => $subshop ? true : false,
            'subshop_id' => $subshop ? $subshop->id : null,
            'shop_exists' => $subshop && $subshop->shop ? true : false,
            'user_exists' => $subshop && $subshop->shop && $subshop->shop->user ? true : false
        ]);
        
        if (!$subshop || !$subshop->shop || !$subshop->shop->user) {
            \Illuminate\Support\Facades\Log::warning('No shop owner found for expense', ['expense_id' => $expense->id]);
            return; // No owner found
        }

        $owner = $subshop->shop->user;
        \Illuminate\Support\Facades\Log::info('Owner check', [
            'owner_id' => $owner->id,
            'owner_name' => $owner->name,
            'phone_number' => $owner->phone_number
        ]);
        
        if (!$owner->phone_number) {
            \Illuminate\Support\Facades\Log::info('Shop owner has no phone number', ['expense_id' => $expense->id]);
            return; // Owner has no phone number
        }

        // Get expense recorder
        $recorder = $expense->creator;

        // Get payment method details
        $paymentMethod = $expense->paymentBank;

        // Format SMS message
        $message = "NEW EXPENSE RECORDED!\n" .
                  "Shop: {$subshop->name}\n" .
                  "Title: {$expense->title}\n" .
                  "Amount: " . number_format((float)$expense->amount, 2) . " TZS\n" .
                  "Category: " . ($expense->category ?: 'N/A') . "\n" .
                  "Payment Method: " . ($paymentMethod ? $paymentMethod->name : 'N/A') . "\n" .
                  "Recorded by: " . ($recorder ? $recorder->name : 'System') . "\n" .
                  "Expense Date: " . optional($expense->expense_date)->format('d/m/Y') . "\n" .
                  "Status: " . ucfirst($expense->status) . "\n" .
                  "Description: " . ($expense->description ?: 'No description') . "\n" .
                  "Date Recorded: " . now()->format('d/m/Y') . "\n" .
                  "Time Recorded: " . now()->format('d/m/Y H:i');

        \Illuminate\Support\Facades\Log::info('SMS message formatted', [
            'message_length' => strlen($message),
            'message_preview' => substr($message, 0, 100) . '...',
            'phone_number' => $owner->phone_number
        ]);

        // Send SMS
        $smsService = new SmsService();
        $result = $smsService->sendSms($owner->phone_number, $message, [
            'shop_id' => $subshop->shop_id ?? null,
            'subshop_id' => $subshop->id ?? null,
            'owner_id' => $owner->id ?? null,
            'type' => 'expense',
        ]);
        
        \Illuminate\Support\Facades\Log::info('SMS send result', [
            'result' => $result,
            'phone_number' => $owner->phone_number,
            'message_length' => strlen($message)
        ]);
        
        // Log for debugging
        \Illuminate\Support\Facades\Log::info('Expense SMS notification attempted', [
            'expense_id' => $expense->id,
            'expense_title' => $expense->title,
            'amount' => $expense->amount,
            'category' => $expense->category,
            'payment_method' => $paymentMethod ? $paymentMethod->name : 'N/A',
            'recorded_by' => $recorder ? $recorder->name : 'System',
            'expense_date' => optional($expense->expense_date)->format('Y-m-d'),
            'status' => $expense->status,
            'owner_phone' => $owner->phone_number,
            'sms_result' => $result
        ]);
    }

    public function export(Request $request, $format)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('expenses.index')])
                ->with('error', 'Please select a shop first');
        }

        $base = Expenses::with(['subshop', 'creator', 'paymentBank'])
            ->where('subshop_id', $subshopId);

        if ($request->filled('search')) {
            $search = $request->search;
            $base->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($request->filled('status') && in_array($request->status, ['pending','approved','rejected'])) {
            $base->where('status', $request->status);
        }
        if ($request->filled('date_from')) { $base->whereDate('expense_date', '>=', $request->input('date_from')); }
        if ($request->filled('date_to'))   { $base->whereDate('expense_date', '<=', $request->input('date_to')); }
        if ($request->filled('min_amount')){ $base->where('amount', '>=', (float)$request->input('min_amount')); }
        if ($request->filled('max_amount')){ $base->where('amount', '<=', (float)$request->input('max_amount')); }
        if ($request->filled('category'))  { $base->where('category', $request->input('category')); }
        if ($request->filled('recorded_by')){
            $name = $request->input('recorded_by');
            $base->whereHas('creator', function($q) use ($name){ $q->where('name','like', "%{$name}%"); });
        }

        $base->orderByDesc('expense_date');
        $rows = $base->get();

        if ($format === 'csv') {
            return response()->stream(function () use ($rows) {
                $h = fopen('php://output', 'w');
                fputcsv($h, ['Date','Title','Category','Amount','Payment Method','Status','Recorded By','Notes']);
                foreach ($rows as $e) {
                    fputcsv($h, [
                        optional($e->expense_date)->format('Y-m-d'),
                        $e->title,
                        $e->category,
                        number_format((float)$e->amount, 2, '.', ''),
                        optional($e->paymentBank)->name ?? '-',
                        strtoupper($e->status),
                        optional($e->creator)->name ?? '-',
                        $e->description,
                    ]);
                }
                fclose($h);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="expenses_'.now()->format('Y-m-d_H-i-s').'.csv"',
            ]);
        }

        if ($format === 'excel') {
            $exportRows = $rows->map(function($e){
                return [
                    'Date' => optional($e->expense_date)->format('Y-m-d'),
                    'Title' => $e->title,
                    'Category' => $e->category,
                    'Amount' => (float)$e->amount,
                    'Payment Method' => optional($e->paymentBank)->name ?? '-',
                    'Status' => strtoupper($e->status),
                    'Recorded By' => optional($e->creator)->name ?? '-',
                    'Notes' => $e->description,
                ];
            });
            return Excel::download(new \App\Exports\GenericArrayExport($exportRows->toArray(), 'Expenses'), 'expenses_'.now()->format('Y-m-d_H-i-s').'.xlsx');
        }

        if ($format === 'pdf') {
            $subshop = SubShop::find($subshopId);
            $summary = [
                'count' => $rows->count(),
                'total_amount' => (float) $rows->sum('amount'),
                'approved_total' => (float) $rows->where('status','approved')->sum('amount'),
                'pending_total' => (float) $rows->where('status','pending')->sum('amount'),
                'rejected_total' => (float) $rows->where('status','rejected')->sum('amount'),
                'approved_count' => (int) $rows->where('status','approved')->count(),
                'pending_count' => (int) $rows->where('status','pending')->count(),
                'rejected_count' => (int) $rows->where('status','rejected')->count(),
            ];
            $pdf = PDF::loadView('exports.expenses_pdf', [
                'rows' => $rows,
                'subshop' => $subshop,
                'summary' => $summary,
                'generatedBy' => optional(auth()->user())->name ?? 'System',
            ]);
            return $pdf->download('expenses_'.now()->format('Y-m-d_H-i-s').'.pdf');
        }

        return redirect()->back()->with('error', 'Unsupported export format');
    }
}
