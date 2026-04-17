<?php

namespace App\Events\Loans\Risk;

use App\Models\Loans;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a loan becomes delinquent (1+ days overdue).
 */
class LoanBecameDelinquent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Loans $loan,
        public int $daysOverdue,
        public float $outstandingBalance
    ) {
    }
}
