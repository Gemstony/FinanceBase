<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckSubscriptionExpiryAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-expiry-alerts {--shop= : Check specific shop ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expiring subscription plans and send alerts to shop owners';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Checking for subscription expiry alerts...');

        $shopId = $this->option('shop');
        $shops = $shopId
            ? \App\Models\Shop::where('id', $shopId)->get()
            : \App\Models\Shop::where('is_active', true)->get();

        $totalExpiring = 0;
        $alertsSent = 0;

        foreach ($shops as $shop) {
            $this->info("🏪 Checking shop: {$shop->name}");

            // Get subscriptions expiring soon (within 10 days)
            $expiringSubscriptions = \App\Models\Subscription::with(['plan', 'shop'])
                ->where('shop_id', $shop->id)
                ->where('status', 'active')
                ->whereNotNull('end_date')
                ->where('end_date', '>=', now())
                ->where('end_date', '<=', now()->addDays(10))
                ->get();

            $expiringCount = $expiringSubscriptions->count();

            $totalExpiring += $expiringCount;

            if ($expiringCount > 0) {
                $this->info("  ⚠️  Found {$expiringCount} subscription(s) expiring soon");

                // Send notifications to shop owners
                $owners = collect();
                
                // Get the shop owner
                if ($shop->owner) {
                    $owners->push($shop->owner);
                }
                
                // Get shopkeepers as well
                $shopkeepers = $shop->shopkeepers()->get();
                $owners = $owners->merge($shopkeepers);

                foreach ($owners as $owner) {
                    try {
                        $owner->notify(new \App\Notifications\SubscriptionExpiryAlertNotification(
                            $expiringSubscriptions,
                            $shop
                        ));
                        $this->info("    📧 Alert sent to: {$owner->email}");
                        $alertsSent++;
                    } catch (\Exception $e) {
                        $this->error("    ❌ Failed to send alert to {$owner->email}: {$e->getMessage()}");
                    }
                }
            } else {
                $this->info("  ✅ No subscription expiry issues found");
            }
        }

        $this->info("📊 Summary: {$totalExpiring} subscription(s) expiring soon, {$alertsSent} alerts sent");
        $this->info('✅ Subscription expiry alert check completed!');
    }
}
