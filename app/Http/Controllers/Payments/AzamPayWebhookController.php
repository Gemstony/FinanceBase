<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Jobs\HandleWebhookJob;
use App\Models\LoanPayments;
use App\Models\PaymentConfig;
use App\Models\PaymentTransaction;
use App\Services\Loans\Repayment\PaymentProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AzamPayWebhookController extends Controller
{
    /**
     * Handle AzamPay webhook callback.
     */
    public function handle(Request $request)
    {
        try {
            $payload = $request->all();

            Log::info('AzamPay webhook received', $payload);

            $config = PaymentConfig::provider('azampay')->active()->first();
            if (! $config) {
                Log::warning('AzamPay webhook received but provider not configured');

                return response()->json(['status' => 'error', 'message' => 'Provider not configured'], 400);
            }

            $requiredFields = ['transactionstatus', 'externalreference'];
            $hasRequiredFields = collect($requiredFields)->every(fn ($field) => isset($payload[$field]));

            if (! $hasRequiredFields && ! isset($payload['status']) && ! isset($payload['reference'])) {
                Log::warning('AzamPay webhook missing required fields', $payload);

                return response()->json(['status' => 'error', 'message' => 'Missing required fields'], 400);
            }

            // Try to find loan payment first
            $loanPaymentProcessed = $this->processLoanPaymentWebhook($payload);

            // If not a loan payment, use the generic handler
            if (! $loanPaymentProcessed) {
                HandleWebhookJob::dispatch('azampay', $payload)->onQueue('webhooks');
            }

            return response()->json(['status' => 'success', 'message' => 'Webhook received']);
        } catch (\Exception $e) {
            Log::error('AzamPay webhook handling failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Webhook handling failed'], 500);
        }
    }

    /**
     * Process loan payment webhook directly.
     */
    protected function processLoanPaymentWebhook(array $payload): bool
    {
        $externalRef = $payload['externalreference'] ?? null;
        $utilityRef = $payload['utilityref'] ?? null;
        $status = $payload['transactionstatus'] ?? $payload['status'] ?? 'UNKNOWN';
        $amount = $payload['amount'] ?? 0;
        $phone = $payload['msisdn'] ?? null;
        $operator = $payload['operator'] ?? null;

        if (! $externalRef && ! $utilityRef) {
            return false;
        }

        Log::info('AzamPay webhook: searching for payment', [
            'utilityRef' => $utilityRef,
            'externalRef' => $externalRef,
            'table' => 'loan_payments',
        ]);

        // First try to find by utilityRef (our original reference)
        $payment = null;
        if ($utilityRef) {
            Log::info('Searching by utilityRef', ['utilityRef' => $utilityRef]);
            $payment = LoanPayments::query()
                ->where('external_id', $utilityRef)
                ->orWhere('transaction_reference', $utilityRef)
                ->first();

            Log::info('Query result for utilityRef', [
                'found' => $payment ? 'yes' : 'no',
                'payment_id' => $payment?->id,
                'external_id' => $payment?->external_id,
                'transaction_reference' => $payment?->transaction_reference,
            ]);
        }

        // If not found, try by externalRef (AzamPay's transaction ID)
        if (! $payment && $externalRef) {
            Log::info('Searching by externalRef', ['externalRef' => $externalRef]);
            $payment = LoanPayments::query()
                ->where('external_id', $externalRef)
                ->orWhere('transaction_reference', $externalRef)
                ->first();

            Log::info('Query result for externalRef', [
                'found' => $payment ? 'yes' : 'no',
                'payment_id' => $payment?->id,
                'external_id' => $payment?->external_id,
                'transaction_reference' => $payment?->transaction_reference,
            ]);
        }

        if (! $payment) {
            Log::info('No loan payment found for webhook, will use generic handler', [
                'external_ref' => $externalRef,
                'utility_ref' => $utilityRef,
            ]);

            return false;
        }

        Log::info('AzamPay loan webhook found payment', [
            'payment_id' => $payment->id,
            'current_status' => $payment->status,
            'webhook_status' => $status,
        ]);

        $transaction = PaymentTransaction::where('external_id', $externalRef)
            ->orWhere('reference', $utilityRef)
            ->first();

        // Only process if status is success
        if (strtoupper($status) !== 'SUCCESS' && $status !== '200') {
            $payment->update(['status' => 'failed']);

            if ($transaction) {
                $transaction->markAsFailed(json_encode($payload));
                PaymentLog::log(
                    (int) $transaction->id,
                    'azampay',
                    null,
                    $payload,
                    'failed'
                );
            }

            Log::warning('AzamPay loan payment failed', [
                'payment_id' => $payment->id,
                'status' => $status,
                'transaction_id' => $transaction?->id,
            ]);

            return true;
        }

        // Already confirmed
        if ($payment->status === 'confirmed') {
            Log::info('AzamPay loan payment already confirmed', [
                'payment_id' => $payment->id,
            ]);

            return true;
        }

        // Update payment to confirmed - delegate to PaymentProcessor
        try {
            Log::info('AzamPay webhook: calling PaymentProcessor::confirmPendingPayment', [
                'payment_id' => $payment->id,
                'amount' => $amount,
                'phone' => $phone,
                'provider' => $operator,
            ]);

            $result = app(PaymentProcessor::class)->confirmPendingPayment(
                (int) $payment->id,
                (float) $amount,
                $phone,
                $operator
            );

            if ($transaction) {
                $transaction->markAsSuccess($externalRef, json_encode($payload));
                PaymentLog::log(
                    (int) $transaction->id,
                    'azampay',
                    null,
                    $payload,
                    'success'
                );
            }

            Log::info('Loan repayment completed via AzamPay webhook', [
                'payment_id' => $payment->id,
                'loan_id' => $payment->loan_id,
                'amount' => $amount,
                'transaction_id' => $transaction?->id,
            ]);

            return true;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('AzamPay loan webhook: model not found', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        } catch (\Exception $e) {
            if ($transaction) {
                $transaction->markAsFailed($e->getMessage());
                PaymentLog::log(
                    (int) $transaction->id,
                    'azampay',
                    null,
                    ['error' => $e->getMessage()],
                    'error'
                );
            }

            Log::error('AzamPay loan webhook processing failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return false;
        }
    }
}
