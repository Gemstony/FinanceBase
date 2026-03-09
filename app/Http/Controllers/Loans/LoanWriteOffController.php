<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\StoreLoanWriteOffRequest;
use App\Models\Loans;
use App\Services\Loans\WriteOff\LoanWriteOffCalculator;
use App\Services\Loans\WriteOff\LoanWriteOffEngine;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoanWriteOffController extends Controller
{
    public function __construct(
        private readonly LoanWriteOffEngine $writeOffEngine,
        private readonly LoanWriteOffCalculator $calculator,
    ) {
    }

    public function create(Loans $loan): View
    {
        $balances = $this->calculator->calculateBalances($loan);

        return view('loans.writeoff.create', [
            'loan' => $loan,
            'balances' => $balances,
            'today' => Carbon::today()->toDateString(),
        ]);
    }

    public function store(StoreLoanWriteOffRequest $request, Loans $loan): RedirectResponse
    {
        $data = $request->validated();

        $reason = (string) $data['reason'];
        $writeoffDate = Carbon::parse((string) $data['writeoff_date'])->toDateString();

        try {
            $this->writeOffEngine->writeOffLoan(
                loan: $loan,
                writeoffDate: $writeoffDate,
                reason: $reason,
                approvedBy: (int) (auth()->id() ?? 0)
            );

            return redirect()
                ->route('loans.loans.show', $loan)
                ->with('success', 'Loan written off successfully.');
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
}
