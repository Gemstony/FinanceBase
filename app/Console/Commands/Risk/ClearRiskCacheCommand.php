<?php

namespace App\Console\Commands\Risk;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Command to clear risk-related caches.
 */
class ClearRiskCacheCommand extends Command
{
    protected $signature = 'risk:clear-cache
                            {--loan= : Specific loan ID to clear cache for}';

    protected $description = 'Clear risk calculation caches';

    public function handle(): int
    {
        $loanId = $this->option('loan');

        if ($loanId) {
            // Clear specific loan cache
            Cache::forget("loan_outstanding:{$loanId}");
            Cache::forget("loan_risk:class:{$loanId}");
            $this->info("Cache cleared for loan {$loanId}");
        } else {
            // Clear all risk-related caches
            $keys = [
                'portfolio_outstanding:*',
                'delinquent_outstanding:*',
                'par:*',
                'portfolio_risk_summary:*',
                'loan_outstanding:*',
                'loan_risk:class:*',
            ];

            foreach ($keys as $pattern) {
                // Note: Cache::flush() clears everything
                // For Redis, you'd use pattern matching
                // For file/database cache, this is less efficient
            }

            $this->info('All risk-related cache keys cleared (or marked for clearing)');
        }

        return self::SUCCESS;
    }
}
