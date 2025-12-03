<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\ItemBatch;
use App\Models\WriteOff;
use App\Models\Item;
use App\Models\User;

class WriteOffExpiredBatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'writeoff:expired-batches {--dry-run : Only show what would be written off without changing data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically write off all quantities from expired item batches and adjust inventory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = now()->startOfDay();

        // Find batches with expire_date <= today and quantity > 0
        $batches = ItemBatch::with('item')
            ->whereNotNull('expire_date')
            ->whereDate('expire_date', '<=', $today)
            ->where('quantity', '>', 0)
            ->orderBy('expire_date')
            ->get();

        if ($batches->isEmpty()) {
            $this->info('No expired batches found to write off.');
            return Command::SUCCESS;
        }

        $totalBatches = 0;
        $totalUnits = 0;
        $totalValue = 0.0;

        foreach ($batches as $batch) {
            DB::transaction(function () use ($batch, $dryRun, &$totalBatches, &$totalUnits, &$totalValue) {
                // Lock the batch and item to avoid races
                $lockedBatch = ItemBatch::where('id', $batch->id)->lockForUpdate()->first();
                if (!$lockedBatch) {
                    return; // skip
                }
                if ($lockedBatch->quantity <= 0) {
                    return; // already processed
                }
                $item = Item::where('id', $lockedBatch->item_id)->lockForUpdate()->first();

                $qty = (int) $lockedBatch->quantity;
                $unitPrice = (float) ($lockedBatch->selling_price ?? ($item ? $item->price : 0));
                $value = $qty * $unitPrice;

                // Avoid duplicate write-offs: if an approved expired write-off exists for this batch with same qty, skip
                $duplicate = WriteOff::where('batch_id', $lockedBatch->id)
                    ->where('reason', 'Expired')
                    ->where('status', 'approved')
                    ->where('quantity', $qty)
                    ->exists();
                if ($duplicate) {
                    // Still zero out quantity if not already (safety)
                    if (!$dryRun) {
                        $lockedBatch->quantity = 0;
                        $lockedBatch->save();
                        if ($item && Schema::hasColumn('items', 'quantity')) {
                            $item->decrement('quantity', $qty);
                        }
                    }
                    return;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '[DRY-RUN] Batch #%s (Item #%d) qty %d -> write-off value TZS %s',
                        $lockedBatch->batch_number,
                        $lockedBatch->item_id,
                        $qty,
                        number_format($value, 2)
                    ));
                } else {
                    // Determine a system user to attribute this action
                    $systemUserId = optional(User::orderBy('id')->first())->id ?? 1;
                    // Create approved write-off
                    $writeoff = new WriteOff([
                        'subshop_id'   => $item ? $item->subshop_id : null,
                        'item_id'      => $lockedBatch->item_id,
                        'batch_id'     => $lockedBatch->id,
                        'quantity'     => $qty,
                        'reason'       => 'Expired',
                        'write_off_date' => now()->toDateString(),
                        'description'  => 'Auto write-off due to expiry on ' . optional($lockedBatch->expire_date)->format('Y-m-d'),
                        'unit_price'   => $unitPrice,
                        'total_value'  => $value,
                        'status'       => 'approved',
                        'created_by'   => $systemUserId,
                    ]);
                    $writeoff->save();

                    // Set batch quantity to 0 and update item cached quantity if present
                    $lockedBatch->quantity = 0;
                    $lockedBatch->save();
                    if ($item && Schema::hasColumn('items', 'quantity')) {
                        $item->decrement('quantity', $qty);
                        $item->save();
                    }

                    Log::info('Auto write-off (expired batch) created', [
                        'batch_id' => $lockedBatch->id,
                        'item_id' => $lockedBatch->item_id,
                        'quantity' => $qty,
                        'total_value' => $value,
                    ]);
                }

                $totalBatches++;
                $totalUnits += $qty;
                $totalValue += $value;
            });
        }

        $summary = sprintf('Processed %d expired batches, total units %d, total value TZS %s%s',
            $totalBatches,
            $totalUnits,
            number_format($totalValue, 2),
            $dryRun ? ' [DRY-RUN]' : ''
        );
        $this->info($summary);
        Log::info('[writeoff:expired-batches] ' . $summary);

        return Command::SUCCESS;
    }
}
