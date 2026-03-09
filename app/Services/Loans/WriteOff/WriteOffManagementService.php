<?php

declare(strict_types=1);

namespace App\Services\Loans\WriteOff;

use App\Models\LoanWriteoffRecoveries;
use App\Models\LoanWriteoffs;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WriteOffManagementService
{
    public function paginateWriteOffs(array $filters, ?int $subshopId = null)
    {
        $query = LoanWriteoffs::query()
            ->with([
                'loan.customer',
                'loan.loanGroup',
                'approvedBy',
            ])
            ->onlyWrittenOffLoans()
            ->withSum('recoveries as total_recovered_sum', 'total_recovered')
            ->orderByDesc('writeoff_date')
            ->orderByDesc('id');

        // Filter by subshop if provided
        if ($subshopId) {
            $query->whereHas('loan', function ($q) use ($subshopId) {
                $q->where('subshop_id', $subshopId);
            });
        }

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $query->filterDateRange($dateFrom, $dateTo);

        $borrower = trim((string) ($filters['borrower'] ?? ''));
        if ($borrower !== '') {
            $query->whereHas('loan', function ($loanQ) use ($borrower) {
                $loanQ->whereHas('customer', function ($q) use ($borrower) {
                    $q->where('name', 'like', "%{$borrower}%");
                })->orWhereHas('loanGroup', function ($q) use ($borrower) {
                    $q->where('name', 'like', "%{$borrower}%");
                });
            });
        }

        $amountMin = $filters['amount_min'] ?? null;
        $amountMax = $filters['amount_max'] ?? null;
        if ($amountMin !== null && $amountMin !== '') {
            $query->where('total_written_off', '>=', (float) $amountMin);
        }
        if ($amountMax !== null && $amountMax !== '') {
            $query->where('total_written_off', '<=', (float) $amountMax);
        }

        $paginator = $query->get();

        $paginator->transform(function (LoanWriteoffs $w) {
            $borrowerName = $w->loan?->borrower_type === 'group'
                ? ($w->loan?->loanGroup?->name ?? '-')
                : ($w->loan?->customer?->name ?? '-');

            $recovered = (float) ($w->total_recovered_sum ?? 0);
            $remaining = round(max(0.0, (float) $w->total_written_off - $recovered), 2);

            $w->setAttribute('borrower_name', $borrowerName);
            $w->setAttribute('total_recovered_amount', $recovered);
            $w->setAttribute('remaining_written_off_balance', $remaining);

            return $w;
        });

        return $paginator;
    }

    public function getWriteOffDetails(LoanWriteoffs $writeoff): array
    {
        $writeoff = LoanWriteoffs::query()
            ->with([
                'loan.customer',
                'loan.loanGroup',
                'loan.loanProduct',
                'approvedBy',
                'recoveries' => function ($q) {
                    $q->orderByDesc('recovery_date')->orderByDesc('id');
                },
            ])
            ->onlyWrittenOffLoans()
            ->findOrFail((int) $writeoff->id);

        $totalRecovered = (float) $writeoff->recoveries->sum('total_recovered');
        $remaining = round(max(0.0, (float) $writeoff->total_written_off - $totalRecovered), 2);

        $borrowerName = $writeoff->loan?->borrower_type === 'group'
            ? ($writeoff->loan?->loanGroup?->name ?? '-')
            : ($writeoff->loan?->customer?->name ?? '-');

        $recentRecoveries = $writeoff->recoveries->take(10);

        return [
            'writeoff' => $writeoff,
            'borrowerName' => $borrowerName,
            'totalRecovered' => round($totalRecovered, 2),
            'remainingBalance' => $remaining,
            'recentRecoveries' => $recentRecoveries,
            'canRecordRecovery' => $remaining > 0,
        ];
    }

    public function getWriteOffRecoveries(LoanWriteoffs $writeoff): array
    {
        $writeoff = LoanWriteoffs::query()
            ->with([
                'loan.customer',
                'loan.loanGroup',
            ])
            ->onlyWrittenOffLoans()
            ->findOrFail((int) $writeoff->id);

        $recoveries = LoanWriteoffRecoveries::query()
            ->with(['loan.customer', 'loan.loanGroup'])
            ->where('writeoff_id', (int) $writeoff->id)
            ->orderByDesc('recovery_date')
            ->orderByDesc('id')
            ->get();

        $totals = [
            'principal' => (float) LoanWriteoffRecoveries::query()->where('writeoff_id', (int) $writeoff->id)->sum('recovered_principal'),
            'interest' => (float) LoanWriteoffRecoveries::query()->where('writeoff_id', (int) $writeoff->id)->sum('recovered_interest'),
            'fees' => (float) LoanWriteoffRecoveries::query()->where('writeoff_id', (int) $writeoff->id)->sum('recovered_fees'),
            'penalties' => (float) LoanWriteoffRecoveries::query()->where('writeoff_id', (int) $writeoff->id)->sum('recovered_penalties'),
            'total' => (float) LoanWriteoffRecoveries::query()->where('writeoff_id', (int) $writeoff->id)->sum('total_recovered'),
        ];

        $borrowerName = $writeoff->loan?->borrower_type === 'group'
            ? ($writeoff->loan?->loanGroup?->name ?? '-')
            : ($writeoff->loan?->customer?->name ?? '-');

        return [
            'writeoff' => $writeoff,
            'borrowerName' => $borrowerName,
            'recoveries' => $recoveries,
            'totals' => $totals,
        ];
    }
}
