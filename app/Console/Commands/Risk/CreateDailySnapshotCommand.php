<?php

namespace App\Console\Commands\Risk;

use App\Models\SubShop;
use App\Services\Loans\Risk\RiskSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to create daily risk snapshot.
 *
 * This should be run daily at end of business day.
 */
class CreateDailySnapshotCommand extends Command
{
    protected $signature = 'risk:create-snapshot
                            {--subshop= : Specific subshop ID (creates for all if omitted)}
                            {--date= : Snapshot date (today if omitted, format: Y-m-d)}';

    protected $description = 'Create daily risk snapshot for historical tracking';

    public function __construct(
        private readonly RiskSnapshotService $snapshotService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $date = $this->option('date')
            ? \Carbon\Carbon::parse($this->option('date'))
            : now();

        $subshopId = $this->option('subshop');

        $this->info("Creating risk snapshot for {$date->toDateString()}...");

        try {
            if ($subshopId) {
                // Create snapshot for specific subshop
                $snapshot = $this->snapshotService->createSnapshot($date, (int) $subshopId, 'console_command');
                $this->info("Created snapshot for subshop {$subshopId}: ID {$snapshot->id}");
            } else {
                // Get all shops and create snapshots per shop
                $shops = \App\Models\Shop::where('is_active', true)->get();

                if ($shops->isEmpty()) {
                    $this->warn("No active shops found.");
                    return self::FAILURE;
                }

                foreach ($shops as $shop) {
                    // Get subshops for this shop only
                    $subshops = SubShop::where('shop_id', $shop->id)
                        ->where('is_active', true)
                        ->get();

                    if ($subshops->isEmpty()) {
                        $this->warn("No active subshops found for shop: {$shop->name}");
                        continue;
                    }

                    // Create snapshots for each subshop
                    foreach ($subshops as $subshop) {
                        $snapshot = $this->snapshotService->createSnapshot($date, $subshop->id, 'console_command');
                        $this->info("Created snapshot for shop {$shop->name} / subshop {$subshop->name} (ID: {$subshop->id}): ID {$snapshot->id}");
                    }

                    // Create shop-specific portfolio-wide snapshot
                    // shop_id identifies which shop this portfolio snapshot belongs to
                    $portfolioSnapshot = $this->snapshotService->createPortfolioSnapshot($date, $shop->id, 'console_command');
                    $this->info("Created portfolio-wide snapshot for shop {$shop->name}: ID {$portfolioSnapshot->id}");
                }
            }

            Log::info("Daily risk snapshots created for {$date->toDateString()}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create snapshot: {$e->getMessage()}");
            Log::error("Failed to create risk snapshot: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
