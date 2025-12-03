<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckExpiryAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:check-expiry-alerts {--subshop= : Check specific subshop ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expired and expiring soon inventory batches and send alerts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Checking for inventory expiry alerts...');

        $subshopId = $this->option('subshop');
        $subshops = $subshopId
            ? \App\Models\SubShop::where('id', $subshopId)->get()
            : \App\Models\SubShop::where('is_active', true)->get();

        $totalExpired = 0;
        $totalExpiring = 0;
        $alertsSent = 0;

        foreach ($subshops as $subshop) {
            $this->info("📦 Checking subshop: {$subshop->name}");

            // Get expired batches (already expired)
            $expiredBatches = \App\Models\ItemBatch::with(['item'])
                ->whereHas('item', function($query) use ($subshop) {
                    $query->where('subshop_id', $subshop->id);
                })
                ->whereNotNull('expire_date')
                ->where('expire_date', '<', now())
                ->where('quantity', '>', 0)
                ->get();

            // Get batches expiring soon (within 30 days)
            $expiringSoonBatches = \App\Models\ItemBatch::with(['item'])
                ->whereHas('item', function($query) use ($subshop) {
                    $query->where('subshop_id', $subshop->id);
                })
                ->whereNotNull('expire_date')
                ->where('expire_date', '>=', now())
                ->where('expire_date', '<=', now()->addDays(30))
                ->where('quantity', '>', 0)
                ->get();

            $expiredCount = $expiredBatches->count();
            $expiringCount = $expiringSoonBatches->count();

            $totalExpired += $expiredCount;
            $totalExpiring += $expiringCount;

            if ($expiredCount > 0 || $expiringCount > 0) {
                $this->info("  ⚠️  Found {$expiredCount} expired and {$expiringCount} expiring soon batches");

                // Send notifications to users who manage this subshop
                $users = \App\Models\User::whereHas('subshops', function($query) use ($subshop) {
                    $query->where('subshop_id', $subshop->id);
                })->get();

                // If no specific users, send to all admin users
                if ($users->isEmpty()) {
                    $users = \App\Models\User::role('Super Admin')->get();
                }

                foreach ($users as $user) {
                    try {
                        $user->notify(new \App\Notifications\ExpiryAlertNotification(
                            $expiredBatches,
                            $expiringSoonBatches,
                            $subshop
                        ));
                        $this->info("    📧 Alert sent to: {$user->email}");
                        $alertsSent++;
                    } catch (\Exception $e) {
                        $this->error("    ❌ Failed to send alert to {$user->email}: {$e->getMessage()}");
                    }
                }
            } else {
                $this->info("  ✅ No expiry issues found");
            }
        }

        $this->info("📊 Summary: {$totalExpired} expired, {$totalExpiring} expiring soon, {$alertsSent} alerts sent");
        $this->info('✅ Expiry alert check completed!');
    }
}
