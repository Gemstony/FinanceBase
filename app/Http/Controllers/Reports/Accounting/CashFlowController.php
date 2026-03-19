<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports\Accounting;

use App\Exports\Reports\Accounting\CashFlowExport;
use App\Http\Controllers\Controller;
use App\Models\BankAccounts;
use App\Models\ChartsOfAccount;
use App\Models\JournalEntries;
use App\Models\SubShop;
use App\Services\Reports\Accounting\CashFlowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class CashFlowController extends Controller
{
    public function __construct(private readonly CashFlowService $service)
    {
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $shop = $user->shop ?: optional($user->subshops()->first())->shop;
        if (!$shop) {
            abort(403, 'No shop found for user');
        }

        $allSubshops = SubShop::where('shop_id', $shop->id)->orderBy('name')->get();
        $accessibleSubshopIds = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();

        $subshopId = $request->integer('subshop_id');
        if ($subshopId && !in_array($subshopId, $accessibleSubshopIds, true)) {
            abort(403, 'You do not have access to this subshop');
        }

        $bankLinkedAccountIds = BankAccounts::query()
            ->whereIn('subshop_id', $accessibleSubshopIds ?: [-1])
            ->where('is_active', 1)
            ->pluck('chart_of_account_id')
            ->filter()
            ->values()
            ->all();

        $cashAccountId = $request->integer('cash_account_id');
        if ($cashAccountId && !in_array($cashAccountId, $bankLinkedAccountIds, true)) {
            abort(422, 'Invalid cash/bank account selection');
        }

        $filters = [
            'cash_account_id' => $cashAccountId ?: 0,
            'from_date' => $request->date('from_date') ? Carbon::parse((string) $request->date('from_date')) : null,
            'to_date' => $request->date('to_date') ? Carbon::parse((string) $request->date('to_date')) : null,
            'subshop_id' => $subshopId,
            'reference_type' => $request->filled('reference_type') ? (string) $request->input('reference_type') : null,
            'per_page' => $request->integer('per_page') ?: 50,
            'page' => $request->integer('page') ?: null,
        ];

        $report = $cashAccountId ? $this->service->build($filters, $accessibleSubshopIds) : null;

        $cashAccounts = ChartsOfAccount::query()
            ->whereIn('id', $bankLinkedAccountIds ?: [-1])
            ->whereIn('subshop_id', $accessibleSubshopIds ?: [-1])
            ->where('is_active', 1)
            ->orderBy('account_name')
            ->get(['id', 'account_code', 'account_name']);

        $referenceTypes = JournalEntries::query()
            ->whereIn('subshop_id', $accessibleSubshopIds ?: [-1])
            ->distinct()
            ->orderBy('reference_type')
            ->pluck('reference_type')
            ->filter()
            ->values();

        $commonParams = array_filter([
            'cash_account_id' => $cashAccountId,
            'from_date' => $filters['from_date']?->toDateString(),
            'to_date' => $filters['to_date']?->toDateString(),
            'subshop_id' => $subshopId,
            'reference_type' => $filters['reference_type'],
        ], fn ($v) => $v !== null && $v !== '');

        return view('reports.accounting.cash_flow', [
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'cashAccounts' => $cashAccounts,
            'selectedCashAccountId' => $cashAccountId,
            'referenceTypes' => $referenceTypes,
            'report' => $report,
            'exportUrl' => $cashAccountId ? route('reports.accounting.cash_flow.export', array_merge(['format' => 'xlsx'], $commonParams)) : null,
            'pdfUrl' => $cashAccountId ? route('reports.accounting.cash_flow.export', array_merge(['format' => 'pdf'], $commonParams)) : null,
        ]);
    }

    public function export(Request $request, string $format = 'xlsx')
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $shop = $user->shop ?: optional($user->subshops()->first())->shop;
        if (!$shop) {
            abort(403, 'No shop found for user');
        }

        $allSubshops = SubShop::where('shop_id', $shop->id)->orderBy('name')->get();
        $accessibleSubshopIds = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();

        $subshopId = $request->integer('subshop_id');
        if ($subshopId && !in_array($subshopId, $accessibleSubshopIds, true)) {
            abort(403, 'You do not have access to this subshop');
        }

        $bankLinkedAccountIds = BankAccounts::query()
            ->whereIn('subshop_id', $accessibleSubshopIds ?: [-1])
            ->where('is_active', 1)
            ->pluck('chart_of_account_id')
            ->filter()
            ->values()
            ->all();

        $cashAccountId = $request->integer('cash_account_id');
        if (!$cashAccountId) {
            abort(422, 'Cash/Bank account is required');
        }

        if (!in_array($cashAccountId, $bankLinkedAccountIds, true)) {
            abort(422, 'Invalid cash/bank account selection');
        }

        $filters = [
            'cash_account_id' => (int) $cashAccountId,
            'from_date' => $request->date('from_date') ? Carbon::parse((string) $request->date('from_date')) : null,
            'to_date' => $request->date('to_date') ? Carbon::parse((string) $request->date('to_date')) : null,
            'subshop_id' => $subshopId,
            'reference_type' => $request->filled('reference_type') ? (string) $request->input('reference_type') : null,
        ];

        $report = $this->service->export($filters, $accessibleSubshopIds);

        $filenameBase = 'cash-flow-' . now()->format('Y-m-d-His');

        if (strtolower($format) === 'pdf') {
            $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : null;
            $shopLogoPath = $shop->logo ? public_path('storage/' . ltrim((string) $shop->logo, '/')) : null;

            $pdf = Pdf::loadView('reports.pdf.accounting.cash_flow', [
                'report' => $report,
                'shop' => $shop,
                'shopLogoPath' => $shopLogoPath,
                'subshopName' => $subshopName,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            return $pdf->download($filenameBase . '.pdf');
        }

        $export = new CashFlowExport($report);
        $ext = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(
            $export,
            $filenameBase . '.' . $ext,
            strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
