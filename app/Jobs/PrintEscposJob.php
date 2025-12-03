<?php

namespace App\Jobs;

use App\Models\PrinterSetting;
use App\Models\SalesOrders;
use App\Models\SalesReturns;
use App\Models\PurchaseOrders;
use App\Models\PurchaseReturns;
use App\Services\ReceiptPrinter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PrintEscposJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $docType; // invoice|sales_return|purchase|purchase_return
    public int $docId;
    public int $printerSettingId;
    public string $jobId;

    public $tries = 3;

    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function __construct(string $docType, int $docId, int $printerSettingId, ?string $jobId = null)
    {
        $this->docType = $docType;
        $this->docId = $docId;
        $this->printerSettingId = $printerSettingId;
        $this->jobId = $jobId ?: (string) Str::uuid();
        $this->onQueue('printing');
        // mark queued with metadata
        $this->snapshot('queued');
    }

    public function handle(ReceiptPrinter $printerService): void
    {
        Log::info('PrintEscposJob started', [
            'docType' => $this->docType,
            'docId' => $this->docId,
            'printerSettingId' => $this->printerSettingId,
            'attempts' => $this->attempts(),
            'jobId' => $this->jobId,
        ]);

        $this->snapshot('running');

        $ps = PrinterSetting::findOrFail($this->printerSettingId);

        switch ($this->docType) {
            case 'invoice':
                $order = SalesOrders::findOrFail($this->docId);
                $printerService->printInvoice($order, $ps, false);
                break;
            case 'sales_return':
                $ret = SalesReturns::findOrFail($this->docId);
                $printerService->printReturn($ret, $ps, false);
                break;
            case 'purchase':
                $po = PurchaseOrders::findOrFail($this->docId);
                $printerService->printPurchase($po, $ps, false);
                break;
            case 'purchase_return':
                $pr = PurchaseReturns::findOrFail($this->docId);
                $printerService->printPurchaseReturn($pr, $ps, false);
                break;
            default:
                throw new \InvalidArgumentException('Unsupported docType: ' . $this->docType);
        }

        $this->snapshot('success');
        Log::info('PrintEscposJob finished', [
            'docType' => $this->docType,
            'docId' => $this->docId,
            'printerSettingId' => $this->printerSettingId,
            'jobId' => $this->jobId,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $this->snapshot('failed', $e->getMessage());
        Log::error('PrintEscposJob failed', [
            'docType' => $this->docType,
            'docId' => $this->docId,
            'printerSettingId' => $this->printerSettingId,
            'attempts' => $this->attempts(),
            'message' => $e->getMessage(),
            'jobId' => $this->jobId,
        ]);
    }

    private function key(): string
    {
        return 'print_job:' . $this->jobId;
    }

    private function snapshot(string $status, string $message = null): void
    {
        $payload = [
            'status' => $status,
            'message' => $message,
            'docType' => $this->docType,
            'docId' => $this->docId,
            'printerSettingId' => $this->printerSettingId,
            'jobId' => $this->jobId,
            'attempts' => $this->attempts(),
            'updated_at' => now()->toISOString(),
            'created_at' => now()->toISOString(),
        ];
        Cache::put($this->key(), $payload, now()->addMinutes(60));
        // maintain recent index
        $indexKey = 'print_jobs:index';
        $list = Cache::get($indexKey, []);
        // remove any existing entry with same jobId
        $list = array_values(array_filter($list, function($row){ return ($row['jobId'] ?? null) !== $this->jobId; }));
        array_unshift($list, $payload);
        // limit to last 100
        if (count($list) > 100) { $list = array_slice($list, 0, 100); }
        Cache::put($indexKey, $list, now()->addHours(6));
    }
}
