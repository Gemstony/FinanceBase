<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\StoreLoanRecoveryRequest;
use App\Models\Loans;
use App\Services\Loans\WriteOff\LoanRecoveryProcessor;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoanRecoveryController extends Controller
{
    public function __construct(
        private readonly LoanRecoveryProcessor $recoveryProcessor
    ) {
    }

    public function create(Loans $loan): View
    {
        return view('loans.recovery.create', [
            'loan' => $loan,
            'today' => Carbon::today()->toDateString(),
        ]);
    }

    public function store(StoreLoanRecoveryRequest $request, Loans $loan): RedirectResponse
    {
        $data = $request->validated();

        try {
            $recoveryDate = Carbon::parse((string) $data['recovery_date'])->toDateString();

            $this->recoveryProcessor->processAutoSplit(
                loan: $loan,
                recoveryDate: $recoveryDate,
                amount: (float) $data['amount'],
                notes: $data['notes'] ?? null,
                recordedBy: (int) (auth()->id() ?? 0)
            );

            return redirect()
                ->route('loans.loans.show', $loan)
                ->with('success', 'Recovery recorded successfully.');
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
}
