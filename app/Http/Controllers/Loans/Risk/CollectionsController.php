<?php

namespace App\Http\Controllers\Loans\Risk;

use App\Http\Controllers\Controller;
use App\Services\Loans\Risk\LoanDelinquencyEngine;
use App\Services\Loans\Risk\PortfolioRiskCalculator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectionsController extends Controller
{
    public function __construct(
        private readonly LoanDelinquencyEngine $delinquencyEngine,
        private readonly PortfolioRiskCalculator $portfolioRisk,
    ) {
    }

    /**
     * Display the collections worklist.
     */
    public function index(Request $request): View
    {
        $subshopId = (int) session('subshop_id');

        // For collections, we typically look at everything 1+ days overdue
        $loans = $this->delinquencyEngine->getDelinquentLoans(1, $subshopId);
        
        $loans->load(['customer', 'loanGroup', 'latestDisbursement.processor']);

        // Enrich with outstanding balance and risk category for the view.
        // Also exclude any loans with 0 outstanding (safety filter).
        $loans = $loans->filter(function ($loan) {
            return $this->portfolioRisk->calculateLoanOutstanding($loan) > 0;
        })->values();

        foreach ($loans as $loan) {
            $loan->outstanding_balance = $this->portfolioRisk->calculateLoanOutstanding($loan);
            $loan->risk_category = $this->delinquencyEngine->classifyLoanRisk($loan);
            
            // Get max days overdue among its installments
            $loan->max_days_overdue = (int) $loan->installments()
                ->where('is_active', true)
                ->where('status', 'overdue')
                ->get()
                ->map(fn($i) => $this->delinquencyEngine->calculateDaysOverdue($i))
                ->max();
        }

        return view('risk.collections', compact('loans'));
    }
}
