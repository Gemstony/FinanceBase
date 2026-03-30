<?php

namespace App\Jobs;

use App\Models\PaymentTransaction;
use App\Services\Loans\LoanService;
use App\Services\Payments\PaymentManager;
use App\Services\Sms\SmsManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandleWebhookJob implements ShouldQueue
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
     * @param string $provider
     * @param array $payload
     */
    public function __construct(
        public string $provider,
        public array $payload
    ) {
        $this->onQueue('webhooks');
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
            Log::info('Processing webhook job', [
                'provider' => $this->provider,
                'attempt' => $this->attempts(),
            ]);

            $transaction = $paymentManager->handleWebhook($this->provider, $this->payload);

            if ($transaction) {
                $this->processIntegrations($transaction);
            }

            Log::info('Webhook job completed', [
                'provider' => $this->provider,
                'transaction_id' => $transaction?->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Webhook job failed', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Process integrations (Loan, SMS).
     *
     * @param PaymentTransaction $transaction
     * @return void
     */
    protected function processIntegrations(PaymentTransaction $transaction): void
    {
        try {
            // Loan integration
            if ($transaction->loan_id) {
                $this->processLoanIntegration($transaction);
            }

            // SMS integration
            $this->processSmsIntegration($transaction);
        } catch (\Exception $e) {
            Log::error('Integration processing failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process loan integration.
     *
     * @param PaymentTransaction $transaction
     * @return void
     */
    protected function processLoanIntegration(PaymentTransaction $transaction): void
    {
        try {
            $loanService = app(LoanService::class);

            if ($transaction->isSuccess()) {
                if ($transaction->channel === 'b2c') {
                    // B2C disbursement
                    $loanService->markDisbursed($transaction->loan_id);
                    Log::info('Loan marked as disbursed', [
                        'loan_id' => $transaction->loan_id,
                        'transaction_id' => $transaction->id,
                    ]);
                } else {
                    // STK/C2B repayment
                    $loanService->postRepayment($transaction);
                    Log::info('Loan repayment posted', [
                        'loan_id' => $transaction->loan_id,
                        'transaction_id' => $transaction->id,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Loan integration failed', [
                'transaction_id' => $transaction->id,
                'loan_id' => $transaction->loan_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process SMS integration.
     *
     * @param PaymentTransaction $transaction
     * @return void
     */
    protected function processSmsIntegration(PaymentTransaction $transaction): void
    {
        try {
            $smsManager = app(SmsManager::class);

            if ($transaction->isSuccess()) {
                $smsManager->sendEvent('payment.received', [
                    'customer_id' => $transaction->customer_id,
                    'amount' => $transaction->amount,
                    'reference' => $transaction->reference,
                    'provider' => $transaction->provider,
                ]);
            } elseif ($transaction->isFailed()) {
                $smsManager->sendEvent('payment.failed', [
                    'customer_id' => $transaction->customer_id,
                    'amount' => $transaction->amount,
                    'reference' => $transaction->reference,
                    'provider' => $transaction->provider,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SMS integration failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
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
        Log::error('Webhook job failed permanently', [
            'provider' => $this->provider,
            'error' => $exception->getMessage(),
        ]);
    }
}
