<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\PurchasesTransactions;
use App\Models\SubShop;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class TransactionsController extends Controller
{
    public function subshops()
    {
        return redirect()->route('subshops.choose', ['intended' => route('sales.transactions.index')]);
    }

    public function index(Request $request)
    {
        $subshopId = session('subshop_id');

        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('sales.transactions.index')]);
        }

        $subshop = SubShop::findOrFail($subshopId);
        if ($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('sales.transactions.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }

        $q = $request->query('q');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $minAmount = $request->query('min_amount');
        $maxAmount = $request->query('max_amount');
        $transactionType = $request->query('transaction_type');
        $paymentMethod = $request->query('payment_method');
        $sort = $request->query('sort', 'date_desc');

        $base = Transaction::with(['user', 'order.customer'])
            ->whereHas('order', function($query) use ($subshopId) {
                $query->where('subshop_id', $subshopId);
            })
            ->where('transaction_type', 'payment') // Include both payments and refunds
            ->when($q, function($query) use ($q) {
                $query->where(function($qq) use ($q) {
                    $qq->where('reference_number', 'like', "%{$q}%")
                       ->orWhere('notes', 'like', "%{$q}%")
                       ->orWhereHas('order', function($oq) use ($q) {
                           $oq->where('order_no', 'like', "%{$q}%");
                       })
                       ->orWhereHas('order.customer', function($cq) use ($q) {
                           $cq->where('name', 'like', "%{$q}%");
                       });
                });
            })
            ->when($dateFrom, function($query) use ($dateFrom) {
                $query->whereDate('transaction_date', '>=', $dateFrom);
            })
            ->when($dateTo, function($query) use ($dateTo) {
                $query->whereDate('transaction_date', '<=', $dateTo);
            })
            ->when(is_numeric($minAmount), function($query) use ($minAmount) {
                $query->where('total_amount', '>=', $minAmount);
            })
            ->when(is_numeric($maxAmount), function($query) use ($maxAmount) {
                $query->where('total_amount', '<=', $maxAmount);
            })
            ->when($transactionType, function($query) use ($transactionType) {
                if ($transactionType === 'payment') {
                    $query->where('total_amount', '>=', 0);
                } elseif ($transactionType === 'refund') {
                    $query->where('total_amount', '<', 0);
                }
            })
            ->when($paymentMethod, function($query) use ($paymentMethod) {
                $query->where('payment_method', $paymentMethod);
            });

        // Sorting
        switch ($sort) {
            case 'date_asc': $base->orderBy('transaction_date', 'asc'); break;
            case 'amount_desc': $base->orderBy('total_amount', 'desc'); break;
            case 'amount_asc': $base->orderBy('total_amount', 'asc'); break;
            default: $base->orderBy('transaction_date', 'desc');
        }

        // Get all transactions for display
        $transactions = $base->paginate(15)->appends($request->query());

        // Get unique payment methods for filter dropdown
        $paymentMethods = Transaction::whereHas('order', function($query) use ($subshopId) {
                $query->where('subshop_id', $subshopId);
            })
            ->distinct()
            ->pluck('payment_method')
            ->filter()
            ->sort()
            ->values();

        return view('sales.transactions.transactions', compact(
            'transactions',
            'subshop',
            'q',
            'dateFrom',
            'dateTo',
            'minAmount',
            'maxAmount',
            'transactionType',
            'paymentMethod',
            'sort',
            'paymentMethods'
        ));
    }

    public function export(Request $request, $format)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('sales.transactions.index')])
                ->with('error', 'Please select a shop first');
        }

        $base = Transaction::with(['user', 'order.customer'])
            ->whereHas('order', function($query) use ($subshopId) {
                $query->where('subshop_id', $subshopId);
            })
            ->where('transaction_type', 'payment'); // Include both payments and refunds

        // Apply same filters as index
        $q = $request->query('q');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $minAmount = $request->query('min_amount');
        $maxAmount = $request->query('max_amount');
        $transactionType = $request->query('transaction_type');
        $paymentMethod = $request->query('payment_method');

        $base->when($q, function($query) use ($q) {
                $query->where(function($qq) use ($q) {
                    $qq->where('reference_number', 'like', "%{$q}%")
                       ->orWhere('notes', 'like', "%{$q}%")
                       ->orWhereHas('order', function($oq) use ($q) {
                           $oq->where('order_no', 'like', "%{$q}%");
                       })
                       ->orWhereHas('order.customer', function($cq) use ($q) {
                           $cq->where('name', 'like', "%{$q}%");
                       });
                });
            })
            ->when($dateFrom, function($query) use ($dateFrom) {
                $query->whereDate('transaction_date', '>=', $dateFrom);
            })
            ->when($dateTo, function($query) use ($dateTo) {
                $query->whereDate('transaction_date', '<=', $dateTo);
            })
            ->when(is_numeric($minAmount), function($query) use ($minAmount) {
                $query->where('total_amount', '>=', $minAmount);
            })
            ->when(is_numeric($maxAmount), function($query) use ($maxAmount) {
                $query->where('total_amount', '<=', $maxAmount);
            })
            ->when($transactionType, function($query) use ($transactionType) {
                if ($transactionType === 'payment') {
                    $query->where('total_amount', '>=', 0);
                } elseif ($transactionType === 'refund') {
                    $query->where('total_amount', '<', 0);
                }
            })
            ->when($paymentMethod, function($query) use ($paymentMethod) {
                $query->where('payment_method', $paymentMethod);
            })
            ->orderByDesc('transaction_date');

        $rows = $base->get();

        if ($format === 'csv') {
            return response()->stream(function () use ($rows) {
                $h = fopen('php://output', 'w');
                fputcsv($h, ['Date', 'Order No', 'Customer', 'Type', 'Amount', 'Payment Method', 'Reference', 'Recorded By', 'Notes']);
                foreach ($rows as $txn) {
                    fputcsv($h, [
                        optional($txn->transaction_date)->format('Y-m-d'),
                        optional($txn->order)->order_no ?? '-',
                        optional($txn->order->customer)->name ?? '-',
                        $txn->total_amount < 0 ? 'Refund' : 'Payment',
                        number_format((float)$txn->total_amount, 2, '.', ''),
                        $txn->payment_method,
                        $txn->reference_number,
                        optional($txn->user)->name ?? '-',
                        $txn->notes,
                    ]);
                }
                fclose($h);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="transactions_'.now()->format('Y-m-d_H-i-s').'.csv"',
            ]);
        }

        if ($format === 'excel') {
            $exportRows = $rows->map(function($txn) {
                return [
                    'Date' => optional($txn->transaction_date)->format('Y-m-d'),
                    'Order No' => optional($txn->order)->order_no ?? '-',
                    'Customer' => optional($txn->order->customer)->name ?? '-',
                    'Type' => $txn->total_amount < 0 ? 'Refund' : 'Payment',
                    'Amount' => (float)$txn->total_amount,
                    'Payment Method' => $txn->payment_method,
                    'Reference' => $txn->reference_number,
                    'Recorded By' => optional($txn->user)->name ?? '-',
                    'Notes' => $txn->notes,
                ];
            });
            return Excel::download(new \App\Exports\GenericArrayExport($exportRows->toArray(), 'Transactions'), 'transactions_'.now()->format('Y-m-d_H-i-s').'.xlsx');
        }

        if ($format === 'pdf') {
            $subshop = SubShop::find($subshopId);
            $summary = [
                'count' => $rows->count(),
                'total_payments' => (float) $rows->where('total_amount', '>=', 0)->sum('total_amount'),
                'total_refunds' => (float) $rows->where('total_amount', '<', 0)->sum('total_amount'),
                'net_amount' => (float) $rows->sum('total_amount'),
            ];
            $pdf = PDF::loadView('exports.transactions_pdf', [
                'rows' => $rows,
                'subshop' => $subshop,
                'summary' => $summary,
                'generatedBy' => optional(auth()->user())->name ?? 'System',
            ]);
            return $pdf->download('transactions_'.now()->format('Y-m-d_H-i-s').'.pdf');
        }

        return redirect()->back()->with('error', 'Unsupported export format');
    }

    // Purchase Transactions Methods
    public function purchaseIndex(Request $request)
    {
        $subshopId = session('subshop_id');

        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('purchase.transactions.index')]);
        }

        $subshop = SubShop::findOrFail($subshopId);
        if ($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('purchase.transactions.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }

        $q = $request->query('q');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $minAmount = $request->query('min_amount');
        $maxAmount = $request->query('max_amount');
        $transactionType = $request->query('transaction_type');
        $paymentMethod = $request->query('payment_method');
        $sort = $request->query('sort', 'date_desc');

        $base = PurchasesTransactions::with(['user', 'purchaseOrder.supplier'])
            ->whereHas('purchaseOrder', function($query) use ($subshopId) {
                $query->where('subshop_id', $subshopId);
            })
            ->where('transaction_type', 'payment') // Include both payments and refunds
            ->when($q, function($query) use ($q) {
                $query->where(function($qq) use ($q) {
                    $qq->where('reference_number', 'like', "%{$q}%")
                       ->orWhere('notes', 'like', "%{$q}%")
                       ->orWhereHas('purchaseOrder', function($poq) use ($q) {
                           $poq->where('order_no', 'like', "%{$q}%");
                       })
                       ->orWhereHas('purchaseOrder.supplier', function($sq) use ($q) {
                           $sq->where('name', 'like', "%{$q}%");
                       });
                });
            })
            ->when($dateFrom, function($query) use ($dateFrom) {
                $query->whereDate('transaction_date', '>=', $dateFrom);
            })
            ->when($dateTo, function($query) use ($dateTo) {
                $query->whereDate('transaction_date', '<=', $dateTo);
            })
            ->when(is_numeric($minAmount), function($query) use ($minAmount) {
                $query->where('total_amount', '>=', $minAmount);
            })
            ->when(is_numeric($maxAmount), function($query) use ($maxAmount) {
                $query->where('total_amount', '<=', $maxAmount);
            })
            ->when($transactionType, function($query) use ($transactionType) {
                if ($transactionType === 'payment') {
                    $query->where('total_amount', '>=', 0);
                } elseif ($transactionType === 'refund') {
                    $query->where('total_amount', '<', 0);
                }
            })
            ->when($paymentMethod, function($query) use ($paymentMethod) {
                $query->where('payment_method', $paymentMethod);
            });

        // Sorting
        switch ($sort) {
            case 'date_asc': $base->orderBy('transaction_date', 'asc'); break;
            case 'amount_desc': $base->orderBy('total_amount', 'desc'); break;
            case 'amount_asc': $base->orderBy('total_amount', 'asc'); break;
            default: $base->orderBy('transaction_date', 'desc');
        }

        // Get all transactions for display
        $transactions = $base->paginate(15)->appends($request->query());

        // Get unique payment methods for filter dropdown
        $paymentMethods = PurchasesTransactions::whereHas('purchaseOrder', function($query) use ($subshopId) {
                $query->where('subshop_id', $subshopId);
            })
            ->distinct()
            ->pluck('payment_method')
            ->filter()
            ->sort()
            ->values();

        return view('purchases.transactions.purchase_transactions', compact(
            'transactions',
            'subshop',
            'q',
            'dateFrom',
            'dateTo',
            'minAmount',
            'maxAmount',
            'transactionType',
            'paymentMethod',
            'sort',
            'paymentMethods'
        ));
    }

    public function purchaseExport(Request $request, $format)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('purchase.transactions.index')])
                ->with('error', 'Please select a shop first');
        }

        $base = PurchasesTransactions::with(['user', 'purchaseOrder.supplier'])
            ->whereHas('purchaseOrder', function($query) use ($subshopId) {
                $query->where('subshop_id', $subshopId);
            })
            ->where('transaction_type', 'payment'); // Include both payments and refunds

        // Apply same filters as index
        $q = $request->query('q');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $minAmount = $request->query('min_amount');
        $maxAmount = $request->query('max_amount');
        $transactionType = $request->query('transaction_type');
        $paymentMethod = $request->query('payment_method');

        $base->when($q, function($query) use ($q) {
                $query->where(function($qq) use ($q) {
                    $qq->where('reference_number', 'like', "%{$q}%")
                       ->orWhere('notes', 'like', "%{$q}%")
                       ->orWhereHas('purchaseOrder', function($poq) use ($q) {
                           $poq->where('order_no', 'like', "%{$q}%");
                       })
                       ->orWhereHas('purchaseOrder.supplier', function($sq) use ($q) {
                           $sq->where('name', 'like', "%{$q}%");
                       });
                });
            })
            ->when($dateFrom, function($query) use ($dateFrom) {
                $query->whereDate('transaction_date', '>=', $dateFrom);
            })
            ->when($dateTo, function($query) use ($dateTo) {
                $query->whereDate('transaction_date', '<=', $dateTo);
            })
            ->when(is_numeric($minAmount), function($query) use ($minAmount) {
                $query->where('total_amount', '>=', $minAmount);
            })
            ->when(is_numeric($maxAmount), function($query) use ($maxAmount) {
                $query->where('total_amount', '<=', $maxAmount);
            })
            ->when($transactionType, function($query) use ($transactionType) {
                if ($transactionType === 'payment') {
                    $query->where('total_amount', '>=', 0);
                } elseif ($transactionType === 'refund') {
                    $query->where('total_amount', '<', 0);
                }
            })
            ->when($paymentMethod, function($query) use ($paymentMethod) {
                $query->where('payment_method', $paymentMethod);
            })
            ->orderByDesc('transaction_date');

        $rows = $base->get();

        if ($format === 'csv') {
            return response()->stream(function () use ($rows) {
                $h = fopen('php://output', 'w');
                fputcsv($h, ['Date', 'Order No', 'Supplier', 'Type', 'Amount', 'Payment Method', 'Reference', 'Recorded By', 'Notes']);
                foreach ($rows as $txn) {
                    fputcsv($h, [
                        optional($txn->transaction_date)->format('Y-m-d'),
                        optional($txn->purchaseOrder)->order_no ?? '-',
                        optional($txn->purchaseOrder->supplier)->name ?? '-',
                        $txn->total_amount < 0 ? 'Refund' : 'Payment',
                        number_format((float)$txn->total_amount, 2, '.', ''),
                        $txn->payment_method,
                        $txn->reference_number,
                        optional($txn->user)->name ?? '-',
                        $txn->notes,
                    ]);
                }
                fclose($h);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="purchase_transactions_'.now()->format('Y-m-d_H-i-s').'.csv"',
            ]);
        }

        if ($format === 'excel') {
            $exportRows = $rows->map(function($txn) {
                return [
                    'Date' => optional($txn->transaction_date)->format('Y-m-d'),
                    'Order No' => optional($txn->purchaseOrder)->order_no ?? '-',
                    'Supplier' => optional($txn->purchaseOrder->supplier)->name ?? '-',
                    'Type' => $txn->total_amount < 0 ? 'Refund' : 'Payment',
                    'Amount' => (float)$txn->total_amount,
                    'Payment Method' => $txn->payment_method,
                    'Reference' => $txn->reference_number,
                    'Recorded By' => optional($txn->user)->name ?? '-',
                    'Notes' => $txn->notes,
                ];
            });
            return Excel::download(new \App\Exports\GenericArrayExport($exportRows->toArray(), 'Purchase Transactions'), 'purchase_transactions_'.now()->format('Y-m-d_H-i-s').'.xlsx');
        }

        if ($format === 'pdf') {
            $subshop = SubShop::find($subshopId);
            $summary = [
                'count' => $rows->count(),
                'total_payments' => (float) $rows->where('total_amount', '>=', 0)->sum('total_amount'),
                'total_refunds' => (float) $rows->where('total_amount', '<', 0)->sum('total_amount'),
                'net_amount' => (float) $rows->sum('total_amount'),
            ];
            $pdf = PDF::loadView('exports.purchase_transactions_pdf', [
                'rows' => $rows,
                'subshop' => $subshop,
                'summary' => $summary,
                'generatedBy' => optional(auth()->user())->name ?? 'System',
            ]);
            return $pdf->download('purchase_transactions_'.now()->format('Y-m-d_H-i-s').'.pdf');
        }

        return redirect()->back()->with('error', 'Unsupported export format');
    }
}
