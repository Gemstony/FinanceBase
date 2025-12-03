<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SuspendExpiredShops extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shops:suspend-expired {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Suspend shops with expired subscriptions after 3-day grace period';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Checking for shops with expired subscriptions...');

        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN MODE: No changes will be made');
        }

        // Find expired subscriptions that are more than 3 days past expiration
        $expiredSubscriptions = Subscription::where('status', 'active')
            ->where('end_date', '<', now()->subDays(3))
            ->with('shop')
            ->get();

        $this->info("Found {$expiredSubscriptions->count()} expired subscriptions");

        $suspendedCount = 0;
        $alreadySuspendedCount = 0;

        foreach ($expiredSubscriptions as $subscription) {
            $shop = $subscription->shop;

            if (!$shop) {
                $this->warn("Subscription {$subscription->id} has no associated shop");
                continue;
            }

            $this->info("  📅 Checking shop: {$shop->name} (ID: {$shop->id})");
            $this->info("     Subscription expired: {$subscription->end_date->format('Y-m-d')}");
            $this->info("     Days expired: {$subscription->end_date->diffInDays(now())} days");

            if ($shop->status === 'suspended') {
                $this->info("     ✅ Already suspended");
                $alreadySuspendedCount++;
                continue;
            }

            if (!$dryRun) {
                try {
                    $shop->update([
                        'status' => 'suspended',
                        'suspended_at' => now(),
                        'suspension_reason' => 'Subscription expired and grace period exceeded',
                        'is_active' => false
                    ]);

                    // Log the suspension
                    Log::info('Shop suspended due to expired subscription', [
                        'shop_id' => $shop->id,
                        'shop_name' => $shop->name,
                        'subscription_id' => $subscription->id,
                        'expired_date' => $subscription->end_date,
                        'suspended_at' => now()
                    ]);

                    $this->info("     🚫 Suspended successfully");
                } catch (\Exception $e) {
                    $this->error("     ❌ Failed to suspend: {$e->getMessage()}");
                    Log::error('Failed to suspend shop', [
                        'shop_id' => $shop->id,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            } else {
                $this->info("     🚫 Would suspend (dry run)");
            }

            $suspendedCount++;
        }

        $this->info("\n📊 Summary:");
        $this->info("  Total expired subscriptions: {$expiredSubscriptions->count()}");
        $this->info("  Shops suspended: {$suspendedCount}");
        $this->info("  Already suspended: {$alreadySuspendedCount}");

        if ($dryRun) {
            $this->info("  Note: This was a dry run, no changes were made");
        }

        $this->info('✅ Shop suspension check completed!');

        return Command::SUCCESS;
    }
}
