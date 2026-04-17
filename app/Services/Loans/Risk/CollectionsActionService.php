<?php

declare(strict_types=1);

namespace App\Services\Loans\Risk;

use App\Models\CollectionsAction;
use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Collections Action Service
 *
 * Manages collection actions tracking, scheduling, and follow-ups.
 */
class CollectionsActionService
{
    /**
     * Create a new collection action.
     */
    public function createAction(
        Loans $loan,
        string $actionType,
        ?Carbon $scheduledAt = null,
        ?string $notes = null,
        ?int $assignedTo = null
    ): CollectionsAction {
        $customer = $loan->borrower_type === 'group'
            ? $loan->loanGroup?->group_leader_id
            : $loan->customer_id;

        return CollectionsAction::create([
            'loan_id' => $loan->id,
            'customer_id' => $customer ?? $loan->customer_id,
            'subshop_id' => $loan->subshop_id,
            'action_type' => $actionType,
            'scheduled_at' => $scheduledAt,
            'status' => $scheduledAt && $scheduledAt->isFuture() ? 'pending' : 'in_progress',
            'notes' => $notes,
            'assigned_to' => $assignedTo ?? Auth::id(),
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Get pending actions for a user.
     */
    public function getPendingActionsForUser(int $userId): Collection
    {
        return CollectionsAction::assignedTo($userId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['loan', 'customer'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Get overdue actions.
     */
    public function getOverdueActions(array|int|null $subshopIds = null): Collection
    {
        $query = CollectionsAction::query()
            ->overdue()
            ->with(['loan', 'customer', 'assignedTo']);

        if (is_array($subshopIds) && !empty($subshopIds)) {
            $query->whereIn('subshop_id', $subshopIds);
        } elseif ($subshopIds) {
            $query->where('subshop_id', $subshopIds);
        }

        return $query->get();
    }

    /**
     * Complete an action.
     */
    public function completeAction(
        CollectionsAction $action,
        string $outcome,
        ?string $notes = null,
        ?float $amountCollected = null
    ): void {
        $action->markCompleted($outcome, $notes, $amountCollected);
    }

    /**
     * Schedule follow-up action.
     */
    public function scheduleFollowUp(
        CollectionsAction $previousAction,
        string $actionType,
        Carbon $scheduledAt,
        ?string $notes = null
    ): CollectionsAction {
        return $this->createAction(
            $previousAction->loan,
            $actionType,
            $scheduledAt,
            $notes,
            $previousAction->assigned_to
        );
    }

    /**
     * Get actions summary for a loan.
     */
    public function getLoanActionsSummary(int $loanId): array
    {
        $actions = CollectionsAction::where('loan_id', $loanId)
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'total_actions' => $actions->count(),
            'completed_actions' => $actions->where('status', 'completed')->count(),
            'successful_collections' => $actions->where('outcome', 'successful_payment')->count(),
            'total_collected' => $actions->sum('amount_collected'),
            'promises_made' => $actions->where('outcome', 'promise_made')->count(),
            'latest_action' => $actions->first(),
            'actions_by_type' => $actions->groupBy('action_type')
                ->map(fn($group) => $group->count())
                ->toArray(),
        ];
    }

    /**
     * Get dashboard statistics.
     */
    public function getDashboardStats(array|int|null $subshopIds = null, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = CollectionsAction::query();

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

        $actions = $query->get();

        return [
            'total_actions' => $actions->count(),
            'pending_actions' => $actions->where('status', 'pending')->count(),
            'completed_actions' => $actions->where('status', 'completed')->count(),
            'overdue_actions' => $actions->where('status', 'overdue')->count(),
            'successful_collections' => $actions->where('outcome', 'successful_payment')->count(),
            'total_collected' => $actions->sum('amount_collected'),
            'actions_by_type' => $actions->groupBy('action_type')
                ->map(fn($group) => [
                    'count' => $group->count(),
                    'amount_collected' => $group->sum('amount_collected'),
                ])
                ->toArray(),
        ];
    }

    /**
     * Bulk schedule actions for a list of loans.
     */
    public function bulkScheduleActions(
        Collection $loans,
        string $actionType,
        Carbon $scheduledAt,
        ?string $notes = null,
        ?int $assignedTo = null
    ): array {
        $created = [];

        foreach ($loans as $loan) {
            $created[] = $this->createAction($loan, $actionType, $scheduledAt, $notes, $assignedTo);
        }

        return $created;
    }

    /**
     * Auto-escalate overdue actions.
     */
    public function autoEscalateOverdue(int $daysThreshold = 3): int
    {
        $overdueActions = CollectionsAction::query()
            ->where('status', 'overdue')
            ->whereNull('escalated_at')
            ->whereDate('scheduled_at', '<=', now()->subDays($daysThreshold))
            ->get();

        $escalated = 0;

        foreach ($overdueActions as $action) {
            // Create escalation action
            $this->createAction(
                $action->loan,
                'escalation',
                now(),
                "Auto-escalated from overdue {$action->action_type} action",
                null // Unassigned - requires manager assignment
            );

            $escalated++;
        }

        return $escalated;
    }
}
