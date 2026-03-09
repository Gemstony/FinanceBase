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
        // For collections, we typically look at everything 1+ days overdue
        $loans = $this->delinquencyEngine->getDelinquentLoans(1);
        
        $loans->load(['customer', 'loanGroup', 'loanOfficer']);

        // Enrich with outstanding balance and risk category for the view
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
