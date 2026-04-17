<?php

declare(strict_types=1);

namespace App\Services\Loans\Risk;

use App\Models\CollectionsAction;
use App\Models\PromiseToPay;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Promise to Pay Service
 *
 * Manages promise-to-pay commitments from customers.
 */
class PromiseToPayService
{
    /**
     * Create a new promise to pay.
     */
    public function createPromise(
        int $loanId,
        int $customerId,
        float $amountPromised,
        Carbon $promiseDate,
        string $promiseType = 'partial_payment',
        ?int $collectionsActionId = null,
        ?string $notes = null,
        ?int $subshopId = null
    ): PromiseToPay {
        return PromiseToPay::create([
            'loan_id' => $loanId,
            'customer_id' => $customerId,
            'subshop_id' => $subshopId,
            'collections_action_id' => $collectionsActionId,
            'amount_promised' => $amountPromised,
            'promise_date' => $promiseDate->toDateString(),
            'promise_type' => $promiseType,
            'status' => 'pending',
            'promise_notes' => $notes,
            'created_by' => Auth::id(),
            'follow_up_at' => $promiseDate->copy()->subDays(1), // Remind 1 day before
        ]);
    }

    /**
     * Record promise fulfillment.
     */
    public function recordFulfillment(
        PromiseToPay $promise,
        float $amountPaid,
        ?string $notes = null
    ): void {
        $promise->markFulfilled($amountPaid, $notes);

        // Update related collections action if exists
        if ($promise->collections_action_id) {
            $action = CollectionsAction::find($promise->collections_action_id);
            if ($action) {
                $action->markCompleted(
                    'successful_payment',
                    "Promise fulfilled. Paid: {$amountPaid}",
                    $amountPaid
                );
            }
        }
    }

    /**
     * Mark promise as broken.
     */
    public function markBroken(PromiseToPay $promise, ?string $reason = null): void
    {
        $promise->markBroken($reason);

        // Schedule follow-up action
        // This would typically call CollectionsActionService
    }

    /**
     * Get pending promises.
     */
    public function getPendingPromises(array|int|null $subshopIds = null): Collection
    {
        $query = PromiseToPay::with(['loan', 'customer'])
            ->pending()
            ->orderBy('promise_date');

        if (is_array($subshopIds) && !empty($subshopIds)) {
            $query->whereIn('subshop_id', $subshopIds);
        } elseif ($subshopIds) {
            $query->where('subshop_id', $subshopIds);
        }

        return $query->get();
    }

    /**
     * Get overdue promises.
     */
    public function getOverduePromises(array|int|null $subshopIds = null): Collection
    {
        $query = PromiseToPay::with(['loan', 'customer'])
            ->overdue()
            ->orderBy('promise_date');

        if (is_array($subshopIds) && !empty($subshopIds)) {
            $query->whereIn('subshop_id', $subshopIds);
        } elseif ($subshopIds) {
            $query->where('subshop_id', $subshopIds);
        }

        return $query->get();
    }

    /**
     * Get promises due today.
     */
    public function getPromisesDueToday(array|int|null $subshopIds = null): Collection
    {
        $query = PromiseToPay::with(['loan', 'customer'])
            ->dueToday()
            ->where('status', 'pending');

        if (is_array($subshopIds) && !empty($subshopIds)) {
            $query->whereIn('subshop_id', $subshopIds);
        } elseif ($subshopIds) {
            $query->where('subshop_id', $subshopIds);
        }

        return $query->get();
    }

    /**
     * Get promises statistics.
     */
    public function getStatistics(array|int|null $subshopIds = null, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = PromiseToPay::query();

        if (is_array($subshopIds) && !empty($subshopIds)) {
            $query->whereIn('subshop_id', $subshopIds);
        } elseif ($subshopIds) {
            $query->where('subshop_id', $subshopIds);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $promises = $query->get();

        $fulfilled = $promises->where('status', 'fulfilled');
        $broken = $promises->where('status', 'broken');

        return [
            'total_promises' => $promises->count(),
            'pending_promises' => $promises->where('status', 'pending')->count(),
            'fulfilled_promises' => $fulfilled->count(),
            'broken_promises' => $broken->count(),
            'fulfillment_rate' => $promises->count() > 0
                ? round(($fulfilled->count() / $promises->count()) * 100, 2)
                : 0,
            'total_promised_amount' => $promises->sum('amount_promised'),
            'total_fulfilled_amount' => $fulfilled->sum('amount_fulfilled'),
            'average_fulfillment_percentage' => $fulfilled->count() > 0
                ? round($fulfilled->avg(fn($p) => $p->getFulfillmentPercentage()), 2)
                : 0,
            'overdue_promises' => $promises->where('status', 'pending')
                ->where('promise_date', '<', today())
                ->count(),
        ];
    }

    /**
     * Send reminders for promises due soon.
     */
    public function sendReminders(int $daysBefore = 1): int
    {
        $promises = PromiseToPay::query()
            ->where('status', 'pending')
            ->whereDate('follow_up_at', today())
            ->whereNull('reminder_sent_at')
            ->orWhereDate('reminder_sent_at', '<', today()->subDays(1))
            ->get();

        $sent = 0;

        foreach ($promises as $promise) {
            // Here you would integrate with SMS/Email service
            // $this->smsService->sendPromiseReminder($promise);

            $promise->update([
                'reminder_sent_at' => now(),
                'reminder_count' => $promise->reminder_count + 1,
            ]);

            $sent++;
        }

        return $sent;
    }

    /**
     * Get promise history for a loan.
     */
    public function getLoanPromiseHistory(int $loanId): Collection
    {
        return PromiseToPay::where('loan_id', $loanId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
