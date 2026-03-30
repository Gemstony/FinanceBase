<?php

namespace App\Jobs;

use App\Services\Payments\PaymentManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     *
     * @param int $transactionId
     * @param array $data
     */
    public function __construct(
        public int $transactionId,
        public array $data
    ) {
        $this->onQueue('payments');
    }

    /**
     * Execute the job.
     *
     * @param PaymentManager $paymentManager
     * @return void
     */
    public function handle(PaymentManager $paymentManager): void
    {
        try {
            Log::info('Processing payment job', [
                'transaction_id' => $this->transactionId,
                'attempt' => $this->attempts(),
            ]);

            $paymentManager->processPayment($this->transactionId, $this->data);

            Log::info('Payment job completed', [
                'transaction_id' => $this->transactionId,
            ]);
        } catch (\Exception $e) {
            Log::error('Payment job failed', [
                'transaction_id' => $this->transactionId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Payment job failed permanently', [
            'transaction_id' => $this->transactionId,
            'error' => $exception->getMessage(),
        ]);

        // Mark transaction as failed
        $transaction = \App\Models\PaymentTransaction::find($this->transactionId);
        if ($transaction && $transaction->isPending()) {
            $transaction->markAsFailed('Payment processing failed: ' . $exception->getMessage());
        }
    }
}
