<?php

namespace App\Http\Controllers;

use App\Models\LoanApprovals;
use App\Models\LoanCollaterals;
use App\Models\LoanInstallments;
use App\Models\Loans;
use App\Models\SubShop;
use App\Models\loanGuarantors;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class LoanApprovalsController extends Controller
{
    /**
     * List loans pending approval for the logged-in user based on their role.
     *
     * A loan is actionable for a user when:
     * - loan.status = pending
     * - there is at least one pending approval row
     * - and the user's role matches the NEXT pending level (min level_order among pending)
     */
    public function index(): View
    {
        $subshopId = (int) session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);

        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $userRoleIds = $user->roles->pluck('id')->map(fn ($v) => (string) $v)->all();
        $userRoleNames = $user->roles->pluck('name')->map(fn ($v) => (string) $v)->all();

        $loans = Loans::query()
            ->where('subshop_id', $subshopId)
            ->where('status', 'pending')
            ->where('requires_approval', true)
            ->whereExists(function ($q) use ($userRoleIds, $userRoleNames) {
                $q->selectRaw('1')
                    ->from('loan_approvals as la')
                    ->join('loan_product_approval_levels as lvl', 'lvl.id', '=', 'la.loan_product_approval_level_id')
                    ->whereColumn('la.loan_id', 'loans.id')
                    ->where('la.is_active', true)
                    ->where('la.status', 'pending')
                    // must be the next pending (lowest level_order still pending)
                    ->whereRaw('la.level_order = (SELECT MIN(level_order) FROM loan_approvals WHERE loan_id = loans.id AND is_active = 1 AND status = \'pending\')')
                    // match either role id or role name, depending on how role_id is stored
                    ->where(function ($w) use ($userRoleIds, $userRoleNames) {
                        $w->whereIn('lvl.role_id', $userRoleIds);
                        if (!empty($userRoleNames)) {
                            $w->orWhereIn('lvl.role_id', $userRoleNames);
                        }
                    });
            })
            ->with(['loanProduct', 'customer', 'loanGroup'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('loans.approvals.index', compact('subshop', 'loans'));
    }

    /**
     * Show loan details and its approval workflow.
     */
    public function show(Loans $loan): View
    {
        $subshopId = (int) session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);

        if ((int) $loan->subshop_id !== $subshopId) {
            abort(404);
        }

        $installments = LoanInstallments::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->orderBy('installment_number')
            ->get();

        $collaterals = LoanCollaterals::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->with('customerCollateral')
            ->get();

        $guarantors = loanGuarantors::query()
            ->where('loan_id', $loan->id)
            ->with('guarantor')
            ->get();

        $approvals = LoanApprovals::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->with(['loanProductApprovalLevel.role', 'approver'])
            ->orderBy('level_order')
            ->get();

        /** @var LoanApprovals|null $nextPending */
        $nextPending = $approvals->firstWhere('status', 'pending');
        $canAct = $nextPending ? $this->userCanActOnApproval(Auth::user(), $nextPending) : false;

        return view('loans.approvals.approve', compact(
            'subshop',
            'loan',
            'installments',
            'collaterals',
            'guarantors',
            'approvals',
            'nextPending',
            'canAct'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function approve(Request $request, Loans $loan): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        return DB::transaction(function () use ($loan, $user, $validated) {
            $loanLocked = Loans::query()->where('id', $loan->id)->lockForUpdate()->first();
            if (!$loanLocked) {
                return back()->with('error', 'Loan not found.');
            }

            if ((string) $loanLocked->status !== 'pending') {
                return back()->with('error', 'This loan is not pending approval.');
            }

            if (!$loanLocked->requires_approval) {
                return back()->with('error', 'This loan does not require approval.');
            }

            $approval = $this->lockAndResolveNextPendingApproval($loan);

            if (!$this->userCanActOnApproval($user, $approval)) {
                return back()->with('error', 'You are not authorized to approve this loan at the current level.');
            }

            if ($approval->status !== 'pending') {
                return back()->with('error', 'This approval step is no longer pending.');
            }

            $approval->status = 'approved';
            $approval->approved_by = $user->id;
            $approval->approved_at = Carbon::now();
            $approval->comments = $validated['comments'] ?? null;
            $approval->save();

            $anyRejected = LoanApprovals::query()
                ->where('loan_id', $loan->id)
                ->where('is_active', true)
                ->where('status', 'rejected')
                ->exists();

            if ($anyRejected) {
                $loan->status = 'rejected';
                $loan->save();

                return back()->with('success', 'Loan approval recorded.');
            }

            $stillPending = LoanApprovals::query()
                ->where('loan_id', $loan->id)
                ->where('is_active', true)
                ->where('status', 'pending')
                ->exists();

            // If all levels are approved, move loan to approved state.
            if (!$stillPending) {
                $loanLocked->status = 'approved';
                $loanLocked->approval_completed = true;
                $loanLocked->save();

                // Installments are already generated during loan creation.
                // We only ensure maturity_date exists if missing.
                if (empty($loanLocked->maturity_date)) {
                    $lastDueDate = LoanInstallments::query()
                        ->where('loan_id', $loanLocked->id)
                        ->orderByDesc('due_date')
                        ->value('due_date');
                    if ($lastDueDate) {
                        $loanLocked->maturity_date = Carbon::parse($lastDueDate)->toDateString();
                        $loanLocked->save();
                    }
                }

                // Optional hook point: notify next approver. No-op for now.
            }

            return back()->with('success', 'Loan approval recorded successfully.');
        });
    }

    /**
     * Update the specified resource in storage.
     */
    public function reject(Request $request, Loans $loan): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'comments' => ['required', 'string', 'max:2000'],
        ], [
            'comments.required' => 'Please provide a reason/comment for rejecting this loan.',
        ]);

        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        return DB::transaction(function () use ($loan, $user, $validated) {
            $loanLocked = Loans::query()->where('id', $loan->id)->lockForUpdate()->first();
            if (!$loanLocked) {
                return back()->with('error', 'Loan not found.');
            }

            if ((string) $loanLocked->status !== 'pending') {
                return back()->with('error', 'This loan is not pending approval.');
            }

            if (!$loanLocked->requires_approval) {
                return back()->with('error', 'This loan does not require approval.');
            }

            $approval = $this->lockAndResolveNextPendingApproval($loan);

            if (!$this->userCanActOnApproval($user, $approval)) {
                return back()->with('error', 'You are not authorized to reject this loan at the current level.');
            }

            if ($approval->status !== 'pending') {
                return back()->with('error', 'This approval step is no longer pending.');
            }

            $approval->status = 'rejected';
            $approval->approved_by = $user->id;
            $approval->approved_at = Carbon::now();
            $approval->comments = $validated['comments'];
            $approval->save();

            $loanLocked->status = 'rejected';
            $loanLocked->approval_completed = false;
            $loanLocked->save();

            return back()->with('success', 'Loan rejected successfully.');
        });
    }

    private function lockAndResolveNextPendingApproval(Loans $loan): LoanApprovals
    {
        $pending = LoanApprovals::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->where('status', 'pending')
            ->orderBy('level_order')
            ->lockForUpdate()
            ->first();

        if (!$pending) {
            throw new RuntimeException('No pending approval step found for this loan.');
        }

        $pending->loadMissing('loanProductApprovalLevel');

        return $pending;
    }

    /**
     * A user can act only if they match the role configured on the approval level.
     *
     * NOTE: loan_product_approval_levels.role_id is stored as a string in this system.
     * It can be either:
     * - a role name (e.g. "Manager")
     * - or a role numeric id stored as a string (e.g. "3")
     */
    private function userCanActOnApproval(?\App\Models\User $user, LoanApprovals $approval): bool
    {
        if (!$user) {
            return false;
        }

        $approval->loadMissing('loanProductApprovalLevel');
        $level = $approval->loanProductApprovalLevel;
        if (!$level) {
            return false;
        }

        $roleKey = (string) ($level->role_id ?? '');
        if ($roleKey === '') {
            return false;
        }

        // role name match
        if ($user->hasRole($roleKey)) {
            return true;
        }

        // role id match (stored as string)
        return $user->roles->pluck('id')->map(fn ($v) => (string) $v)->contains($roleKey);
    }
}
