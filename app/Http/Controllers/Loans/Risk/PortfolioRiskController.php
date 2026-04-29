<?php

namespace App\Http\Controllers\Loans\Risk;

use App\Http\Controllers\Controller;
use App\Models\RiskSnapshot;
use App\Models\RiskThreshold;
use App\Models\SubShop;
use App\Services\Loans\Risk\DpdCalculator;
use App\Services\Loans\Risk\LoanDelinquencyEngine;
use App\Services\Loans\Risk\PortfolioRiskCalculator;
use App\Services\Loans\Risk\ProvisionCalculationService;
use App\Services\Loans\Risk\RiskSnapshotService;
use App\Services\Loans\Risk\StressTestingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortfolioRiskController extends Controller
{
    public function __construct(
        private readonly LoanDelinquencyEngine $delinquencyEngine,
        private readonly PortfolioRiskCalculator $portfolioRisk,
        private readonly DpdCalculator $dpdCalculator,
    ) {
    }

    private function getCurrentSubshopId(): ?int
    {
        $subshopId = (int) session('subshop_id');

        return $subshopId > 0 ? $subshopId : null;
    }

    /**
     * Display the portfolio risk dashboard.
     */
    public function dashboard(Request $request): View
    {
        $subshopId = $this->getCurrentSubshopId();

        // Use cached summary for better performance
        $summary = $this->delinquencyEngine->getPortfolioRiskSummaryCached($subshopId);

        // Count performing vs delinquent loans within the active portfolio only.
        // Use enriched method that pre-computes all metrics
        $delinquentLoans = $this->delinquencyEngine->getDelinquentLoansEnriched(1, $subshopId);
        $delinquentCount = $delinquentLoans->count();

        // Calculate performing count efficiently
        $totalActiveWithOutstanding = $this->countActiveLoansWithOutstanding($subshopId);
        $performingCount = max(0, $totalActiveWithOutstanding - $delinquentCount);

        return view('risk.portfolio', compact('summary', 'delinquentCount', 'performingCount'));
    }

    /**
     * Count active loans that have outstanding balance.
     */
    private function countActiveLoansWithOutstanding(?int $subshopId): int
    {
        $query = $this->portfolioRisk->activeLoansQuery()->select(['id']);

        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
        }

        $count = 0;
        $query->chunkById(500, function ($loans) use (&$count) {
            $loanIds = $loans->pluck('id')->toArray();
            $outstandingMap = $this->portfolioRisk->bulkCalculateOutstanding($loanIds);

            foreach ($outstandingMap as $outstanding) {
                if ($outstanding > 0) {
                    $count++;
                }
            }
        });

        return $count;
    }

    /**
     * List all delinquent loans (1+ days overdue).
     */
    public function delinquentLoans(Request $request): View
    {
        $subshopId = $this->getCurrentSubshopId();

        // Use enriched method that pre-computes all risk metrics in bulk
        $loans = $this->delinquencyEngine->getDelinquentLoansEnriched(1, $subshopId);

        // Apply eager loading to avoid N+1
        $loans->load(['customer', 'loanGroup', 'loanProduct', 'latestDisbursement.processor']);

        // Pre-compute additional data for the view
        $loanData = $this->precomputeLoanData($loans);

        return view('risk.delinquent', [
            'loans' => $loans,
            'loanData' => $loanData,
            'title' => 'All Delinquent Loans',
            'days' => 1
        ]);
    }

    /**
     * List delinquent loans by specific PAR category.
     */
    public function delinquentByDays(int $days): View
    {
        $subshopId = $this->getCurrentSubshopId();

        // Use enriched method that pre-computes all risk metrics in bulk
        $loans = $this->delinquencyEngine->getDelinquentLoansEnriched($days, $subshopId);
        $loans->load(['customer', 'loanGroup', 'loanProduct', 'latestDisbursement.processor']);

        // Pre-compute additional data for the view
        $loanData = $this->precomputeLoanData($loans);

        return view('risk.delinquent', [
            'loans' => $loans,
            'loanData' => $loanData,
            'title' => "PAR{$days} Delinquent Loans",
            'days' => $days
        ]);
    }

    /**
     * Pre-compute all loan data needed by the view to avoid N+1 queries.
     *
     * @param \Illuminate\Support\Collection $loans
     * @return array<int, array> Array keyed by loan_id
     */
    private function precomputeLoanData($loans): array
    {
        if ($loans->isEmpty()) {
            return [];
        }

        $loanData = [];

        foreach ($loans as $loan) {
            $loanData[$loan->id] = [
                'outstanding_balance' => $loan->outstanding_balance ?? 0,
                'risk_category' => $loan->risk_category ?? 'current',
                'max_days_overdue' => $loan->max_days_overdue ?? 0,
            ];
        }

        return $loanData;
    }

    /**
     * Display risk history and trends.
     */
    public function history(Request $request, RiskSnapshotService $snapshotService): View
    {
        $subshopId = $this->getCurrentSubshopId();
        $range = (int) $request->input('range', 90);

        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays($range);

        $snapshots = RiskSnapshot::query()
            ->where('subshop_id', $subshopId)
            ->forDateRange($startDate, $endDate)
            ->orderBy('snapshot_date')
            ->get();

        // Get trend analysis
        $trendAnalysis = null;
        if ($snapshots->count() >= 2) {
            $trendAnalysis = $snapshotService->getTrendAnalysis($startDate, $endDate, $subshopId);
        }

        return view('risk.history', compact('snapshots', 'trendAnalysis', 'range'));
    }

    /**
     * Display provision calculation report.
     */
    public function provisionReport(ProvisionCalculationService $provisionService): View
    {
        $subshopId = $this->getCurrentSubshopId();

        // Generate provision report
        $report = $provisionService->generateProvisionReport($subshopId);

        return view('risk.provision-report', compact('report'));
    }

    /**
     * Display stress testing interface.
     */
    public function stressTest(Request $request, StressTestingService $stressService): View
    {
        $subshopId = $this->getCurrentSubshopId();

        $result = null;
        $params = [];
        $scenario = null;

        if ($request->isMethod('post')) {
            $scenario = $request->input('scenario', 'par_increase');
            $params = $request->except(['_token', 'scenario']);

            // Run the stress test
            $result = $stressService->runScenario($scenario, $params, $subshopId);
        }

        // Get historical stress comparison
        $historicalStress = $stressService->compareAgainstHistoricalStress($subshopId);

        return view('risk.stress-test', compact('result', 'params', 'scenario', 'historicalStress'));
    }

    /**
     * Display and manage risk thresholds.
     */
    public function thresholds(Request $request): View|RedirectResponse
    {
        $subshopId = (int) session('subshop_id');
 
        // Get current thresholds
        $thresholds = RiskThreshold::forSubshop($subshopId);

        // If no subshop-specific thresholds, use global
        if (!$thresholds && $subshopId) {
            $thresholds = RiskThreshold::global()->active()->first();
        }

        // Handle form submission
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'par30_warning_threshold' => 'required|numeric|min:0|max:100',
                'par30_critical_threshold' => 'required|numeric|min:0|max:100',
                'par90_warning_threshold' => 'required|numeric|min:0|max:100',
                'par90_critical_threshold' => 'required|numeric|min:0|max:100',
                'max_exposure_per_customer' => 'nullable|numeric|min:0',
                'max_portfolio_percentage_per_customer' => 'required|numeric|min:0|max:100',
                'max_sector_concentration' => 'required|numeric|min:0|max:100',
                'max_product_concentration' => 'required|numeric|min:0|max:100',
                'provision_rate_par30' => 'required|numeric|min:0|max:100',
                'provision_rate_par60' => 'required|numeric|min:0|max:100',
                'provision_rate_par90' => 'required|numeric|min:0|max:100',
                'provision_rate_default' => 'required|numeric|min:0|max:100',
                'is_active' => 'boolean',
                'subshop_id' => 'nullable|exists:sub_shops,id',
            ]);

            $data['is_active'] = $request->has('is_active');
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();

            if ($thresholds) {
                $thresholds->update($data);
            } else {
                RiskThreshold::create($data);
            }

            return redirect()->route('risk.thresholds')->with('success', 'Risk thresholds updated successfully.');
        }

        $subshops = SubShop::query()
            ->whereKey($subshopId)
            ->where('is_active', true)
            ->get();

        return view('risk.thresholds', compact('thresholds', 'subshops'));
    }
}
