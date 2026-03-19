<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports\Accounting;

use App\Exports\Reports\Accounting\GeneralLedgerExport;
use App\Http\Controllers\Controller;
use App\Models\ChartsOfAccount;
use App\Models\JournalEntries;
use App\Models\SubShop;
use App\Services\Reports\Accounting\GeneralLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class GeneralLedgerController extends Controller
{
    public function __construct(private readonly GeneralLedgerService $service)
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

        $accountId = $request->integer('account_id');

        $filters = [
            'account_id' => $accountId ?: 0,
            'from_date' => $request->date('from_date') ? Carbon::parse((string) $request->date('from_date')) : null,
            'to_date' => $request->date('to_date') ? Carbon::parse((string) $request->date('to_date')) : null,
            'subshop_id' => $subshopId,
            'reference_type' => $request->filled('reference_type') ? (string) $request->input('reference_type') : null,
            'reference_search' => $request->filled('reference_search') ? (string) $request->input('reference_search') : null,
            'per_page' => $request->integer('per_page') ?: 50,
            'page' => $request->integer('page') ?: null,
        ];

        $report = $accountId ? $this->service->build($filters, $accessibleSubshopIds) : null;

        $accounts = ChartsOfAccount::query()
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
            'account_id' => $accountId,
            'from_date' => $filters['from_date']?->toDateString(),
            'to_date' => $filters['to_date']?->toDateString(),
            'subshop_id' => $subshopId,
            'reference_type' => $filters['reference_type'],
            'reference_search' => $filters['reference_search'],
        ], fn ($v) => $v !== null && $v !== '');

        return view('reports.accounting.general_ledger', [
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'accounts' => $accounts,
            'selectedAccountId' => $accountId,
            'referenceTypes' => $referenceTypes,
            'report' => $report,
            'exportUrl' => $accountId ? route('reports.accounting.general_ledger.export', array_merge(['format' => 'xlsx'], $commonParams)) : null,
            'pdfUrl' => $accountId ? route('reports.accounting.general_ledger.export', array_merge(['format' => 'pdf'], $commonParams)) : null,
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

        $accountId = $request->integer('account_id');
        if (!$accountId) {
            abort(422, 'Account is required');
        }

        $filters = [
            'account_id' => (int) $accountId,
            'from_date' => $request->date('from_date') ? Carbon::parse((string) $request->date('from_date')) : null,
            'to_date' => $request->date('to_date') ? Carbon::parse((string) $request->date('to_date')) : null,
            'subshop_id' => $subshopId,
            'reference_type' => $request->filled('reference_type') ? (string) $request->input('reference_type') : null,
            'reference_search' => $request->filled('reference_search') ? (string) $request->input('reference_search') : null,
        ];

        $report = $this->service->export($filters, $accessibleSubshopIds);

        $filenameBase = 'general-ledger-' . now()->format('Y-m-d-His');

        if (strtolower($format) === 'pdf') {
            $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : null;
            $shopLogoPath = $shop->logo ? public_path('storage/' . ltrim((string) $shop->logo, '/')) : null;

            $pdf = Pdf::loadView('reports.pdf.accounting.general_ledger', [
                'report' => $report,
                'shop' => $shop,
                'shopLogoPath' => $shopLogoPath,
                'subshopName' => $subshopName,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            return $pdf->download($filenameBase . '.pdf');
        }

        $export = new GeneralLedgerExport($report);
        $ext = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(
            $export,
            $filenameBase . '.' . $ext,
            strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function journalEntry(int $journalEntryId, Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $subshopId = $request->integer('subshop_id');

        $shop = $user->shop ?: optional($user->subshops()->first())->shop;
        if (!$shop) {
            abort(403, 'No shop found for user');
        }

        $allSubshops = SubShop::where('shop_id', $shop->id)->orderBy('name')->get();
        $accessibleSubshopIds = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();

        if ($subshopId && !in_array($subshopId, $accessibleSubshopIds, true)) {
            abort(403, 'You do not have access to this subshop');
        }

        $query = JournalEntries::query()
            ->with(['creator', 'lines.account'])
            ->whereKey($journalEntryId);

        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
        } else {
            $query->whereIn('subshop_id', $accessibleSubshopIds ?: [-1]);
        }

        $journal = $query->firstOrFail();

        return view('reports.accounting.journal_entry', [
            'journal' => $journal,
        ]);
    }
}
