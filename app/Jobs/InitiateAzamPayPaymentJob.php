<?php

namespace App\Jobs;

use App\Models\PaymentLog;
use App\Services\Payments\Providers\AzamPayProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InitiateAzamPayPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $shopId,
        public int $paymentId,
        public string $phone,
        public float $amount,
        public string $provider,
        public string $externalId
    ) {}

    public function handle(): void
    {
        $transactionId = null;

        try {
            Log::info('InitiateAzamPayPaymentJob: Processing payment', [
                'payment_id' => $this->paymentId,
                'external_id' => $this->externalId,
                'attempt' => $this->attempts(),
            ]);

            $provider = new AzamPayProvider($this->shopId);

            $requestPayload = [
                'phone' => $this->phone,
                'amount' => $this->amount,
                'provider' => $this->provider,
                'reference' => $this->externalId,
            ];

            $response = $provider->initiateSTK($requestPayload);

            $payment = \App\Models\LoanPayments::find($this->paymentId);
            if (! $payment) {
                Log::error('InitiateAzamPayPaymentJob: Payment not found', [
                    'payment_id' => $this->paymentId,
                ]);

                return;
            }

            $transaction = \App\Models\PaymentTransaction::where('reference', $this->externalId)->first();
            $transactionId = $transaction?->id;

            if ($response['status'] === 'failed') {
                Log::error('InitiateAzamPayPaymentJob: AzamPay checkout failed', [
                    'payment_id' => $this->paymentId,
                    'response' => $response,
                ]);

                $payment->update([
                    'status' => 'failed',
                    'notes' => ($payment->notes ?? '')."\nPayment failed: ".($response['message'] ?? 'Unknown error'),
                ]);

                if ($transaction) {
                    $transaction->markAsFailed(json_encode($response));
                }

                if ($transactionId) {
                    PaymentLog::log(
                        $transactionId,
                        'azampay',
                        $requestPayload,
                        $response,
                        'failed'
                    );
                }
            } else {
                $actualExternalId = $response['external_id'] ?? $this->externalId;

                if ($actualExternalId !== $this->externalId) {
                    $payment->update([
                        'external_id' => $actualExternalId,
                        'transaction_reference' => $actualExternalId,
                        'reference_number' => $actualExternalId,
                    ]);

                    if ($transaction) {
                        $transaction->update(['external_id' => $actualExternalId]);
                    }
                }

                Log::info('InitiateAzamPayPaymentJob: Payment confirmed pending', [
                    'payment_id' => $this->paymentId,
                    'external_id' => $actualExternalId,
                ]);

                if ($transactionId) {
                    PaymentLog::log(
                        $transactionId,
                        'azampay',
                        $requestPayload,
                        $response,
                        'pending'
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('InitiateAzamPayPaymentJob: Failed', [
                'payment_id' => $this->paymentId,
                'error' => $e->getMessage(),
            ]);

            $payment = \App\Models\LoanPayments::find($this->paymentId);
            if ($payment && $payment->status === 'pending') {
                $payment->update([
                    'notes' => ($payment->notes ?? '')."\nAPI Error: ".$e->getMessage(),
                ]);
            }

            if ($transactionId) {
                PaymentLog::log(
                    $transactionId,
                    'azampay',
                    null,
                    ['error' => $e->getMessage()],
                    'error'
                );
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('InitiateAzamPayPaymentJob: Permanently failed', [
            'payment_id' => $this->paymentId,
            'error' => $exception->getMessage(),
        ]);

        $payment = \App\Models\LoanPayments::find($this->paymentId);
        if ($payment && $payment->status === 'pending') {
            $payment->update([
                'status' => 'failed',
                'notes' => ($payment->notes ?? '')."\nPayment processing failed: ".$exception->getMessage(),
            ]);
        }

        $transaction = \App\Models\PaymentTransaction::where('reference', $this->externalId)->first();
        if ($transaction) {
            $transaction->markAsFailed($exception->getMessage());

            PaymentLog::log(
                (int) $transaction->id,
                'azampay',
                null,
                ['error' => $exception->getMessage()],
                'failed'
            );
        }
    }
}
