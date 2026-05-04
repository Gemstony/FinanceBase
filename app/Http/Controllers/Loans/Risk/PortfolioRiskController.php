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

        // Apply filters from request
        $loans = $loans->filter(function ($loan) use ($request) {
            // Risk category filter - use pre-computed risk_category from enriched data
            if ($request->filled('risk_category')) {
                $riskCategory = $request->input('risk_category');
                $loanRiskCategory = $loan->risk_category ?? 'current';
                
                if ($loanRiskCategory !== $riskCategory) {
                    return false;
                }
            }

            // Borrower type filter
            if ($request->filled('borrower_type')) {
                if ($loan->borrower_type !== $request->input('borrower_type')) {
                    return false;
                }
            }

            // DPD range filter - use pre-computed max_days_overdue from enriched data
            if ($request->filled('min_dpd')) {
                if (($loan->max_days_overdue ?? 0) < (int) $request->input('min_dpd')) {
                    return false;
                }
            }
            if ($request->filled('max_dpd')) {
                if (($loan->max_days_overdue ?? 0) > (int) $request->input('max_dpd')) {
                    return false;
                }
            }

            // Officer filter
            if ($request->filled('officer')) {
                $officerId = (int) $request->input('officer');
                $loanOfficerId = (int) ($loan->latestDisbursement?->processor_id ?? 0);
                if ($loanOfficerId !== $officerId) {
                    return false;
                }
            }

            return true;
        });

        // Pre-compute additional data for the view
        $loanData = $this->precomputeLoanData($loans);

        // Get officers for filter dropdown - include shop owner and assigned staff
        $officers = collect();
        if ($subshopId) {
            $subshop = \App\Models\SubShop::find($subshopId);
            if ($subshop) {
                $shop = $subshop->shop;
                $officerSubshopIds = \App\Models\SubShop::where('shop_id', $shop->id)->pluck('id');
                
                $officers = \App\Models\User::query()
                    ->where(function ($q) use ($shop, $officerSubshopIds) {
                        $q->whereHas('shop', function ($sq) use ($shop) {
                            $sq->where('id', $shop->id);
                        })->orWhereHas('subshops', function ($sq) use ($shop, $officerSubshopIds) {
                            $sq->where('sub_shops.shop_id', $shop->id)
                                ->whereIn('sub_shops.id', $officerSubshopIds)
                                ->where('subshop_user.is_active', true);
                        });
                    })
                    ->orderBy('name')
                    ->distinct()
                    ->get(['id', 'name']);
            }
        }

        return view('risk.delinquent', [
            'loans' => $loans,
            'loanData' => $loanData,
            'officers' => $officers,
            'title' => 'All Delinquent Loans',
            'days' => 1
        ]);
    }

    /**
     * List delinquent loans by specific PAR category.
     */
    public function delinquentByDays(int $days, Request $request): View
    {
        $subshopId = $this->getCurrentSubshopId();

        // Use enriched method that pre-computes all risk metrics in bulk
        $loans = $this->delinquencyEngine->getDelinquentLoansEnriched($days, $subshopId);
        $loans->load(['customer', 'loanGroup', 'loanProduct', 'latestDisbursement.processor']);

        // Apply filters from request
        $loans = $loans->filter(function ($loan) use ($request) {
            // Risk category filter - use pre-computed risk_category from enriched data
            if ($request->filled('risk_category')) {
                $riskCategory = $request->input('risk_category');
                $loanRiskCategory = $loan->risk_category ?? 'current';
                
                if ($loanRiskCategory !== $riskCategory) {
                    return false;
                }
            }

            // Borrower type filter
            if ($request->filled('borrower_type')) {
                if ($loan->borrower_type !== $request->input('borrower_type')) {
                    return false;
                }
            }

            // DPD range filter - use pre-computed max_days_overdue from enriched data
            if ($request->filled('min_dpd')) {
                if (($loan->max_days_overdue ?? 0) < (int) $request->input('min_dpd')) {
                    return false;
                }
            }
            if ($request->filled('max_dpd')) {
                if (($loan->max_days_overdue ?? 0) > (int) $request->input('max_dpd')) {
                    return false;
                }
            }

            // Officer filter
            if ($request->filled('officer')) {
                $officerId = (int) $request->input('officer');
                $loanOfficerId = (int) ($loan->latestDisbursement?->processor_id ?? 0);
                if ($loanOfficerId !== $officerId) {
                    return false;
                }
            }

            return true;
        });

        // Pre-compute additional data for the view
        $loanData = $this->precomputeLoanData($loans);

        // Get officers for filter dropdown - include shop owner and assigned staff
        $officers = collect();
        if ($subshopId) {
            $subshop = \App\Models\SubShop::find($subshopId);
            if ($subshop) {
                $shop = $subshop->shop;
                $officerSubshopIds = \App\Models\SubShop::where('shop_id', $shop->id)->pluck('id');
                
                $officers = \App\Models\User::query()
                    ->where(function ($q) use ($shop, $officerSubshopIds) {
                        $q->whereHas('shop', function ($sq) use ($shop) {
                            $sq->where('id', $shop->id);
                        })->orWhereHas('subshops', function ($sq) use ($shop, $officerSubshopIds) {
                            $sq->where('sub_shops.shop_id', $shop->id)
                                ->whereIn('sub_shops.id', $officerSubshopIds)
                                ->where('subshop_user.is_active', true);
                        });
                    })
                    ->orderBy('name')
                    ->distinct()
                    ->get(['id', 'name']);
            }
        }

        return view('risk.delinquent', [
            'loans' => $loans,
            'loanData' => $loanData,
            'officers' => $officers,
            'title' => "PAR{$days} Delinquent Loans",
            'days' => $days
        ]);
    }

    /**
     * Get risk data for a single loan.
     */
    private function getLoanRiskData($loan): array
    {
        // Use the same logic as precomputeLoanData but for a single loan
        $loanIds = [$loan->id];
        $outstandingMap = $this->portfolioRisk->bulkCalculateOutstanding($loanIds);
        $dpdMap = $this->dpdCalculator->bulkCalculateMaxDpd($loanIds);
        
        $outstanding = $outstandingMap[$loan->id] ?? 0;
        $maxOverdue = $dpdMap[$loan->id] ?? 0;
        
        // Determine risk category based on DPD
        $riskCategory = 'current';
        if ($maxOverdue > 90) {
            $riskCategory = 'default';
        } elseif ($maxOverdue > 60) {
            $riskCategory = 'par90';
        } elseif ($maxOverdue > 30) {
            $riskCategory = 'par60';
        } elseif ($maxOverdue > 1) {
            $riskCategory = 'par30';
        }
        
        return [
            'outstanding_balance' => $outstanding,
            'max_days_overdue' => $maxOverdue,
            'risk_category' => $riskCategory,
        ];
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
