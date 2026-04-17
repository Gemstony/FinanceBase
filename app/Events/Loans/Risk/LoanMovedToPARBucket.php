<?php

namespace App\Events\Loans\Risk;

use App\Models\Loans;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a loan moves to a higher PAR bucket (PAR30->PAR60->PAR90->Default).
 */
class LoanMovedToPARBucket
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Loans $loan,
        public string $newBucket, // par30, par60, par90, default
        public string $previousBucket,
        public int $maxDaysOverdue,
        public float $outstandingBalance
    ) {
    }
}
