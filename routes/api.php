<?php

use App\Http\Controllers\LoanRepaymentController;
use App\Http\Controllers\Payments\AzamPayWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('payments/azampay')->group(function () {
    Route::post('/webhook', [AzamPayWebhookController::class, 'handle'])->name('azampay.webhook');
    Route::post('/repayment/callback', [LoanRepaymentController::class, 'handleWebhook'])->name('azampay.repayment.webhook');
});
