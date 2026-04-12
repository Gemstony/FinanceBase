<?php

namespace Tests\Feature;

use App\Models\LoanPayments;
use App\Models\PaymentMethodAccount;
use App\Services\Loans\Repayment\PaymentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class LoanRepaymentAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_repayment_with_cash_mapping(): void
    {
        $this->markTestIncomplete('TODO: Implement fixtures (subshop, charts_of_accounts, loan, installments) and assert journal lines use mapped cash account + receivables.');
    }

    public function test_repayment_with_azampay_mapping(): void
    {
        $this->markTestIncomplete('TODO: Implement fixtures and assert processMobilePayment stores payment_account_id, and webhook confirmation posts using stored account.');
    }

    public function test_repayment_with_savings(): void
    {
        $this->markTestIncomplete('TODO: Implement fixtures and assert repayment debits savings liability account mapping.');
    }

    public function test_missing_account_mapping_fails(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $subshopId = 1;
        PaymentMethodAccount::query()->where('subshop_id', $subshopId)->delete();

        $processor = app(PaymentProcessor::class);

        $this->markTestIncomplete('TODO: Create a loan + installments and attempt repayment without mapping; should throw before journal posting.');
    }

    public function test_webhook_uses_correct_account(): void
    {
        $this->markTestIncomplete('TODO: Create pending azampay payment and confirm; assert LoanPayments.payment_account_id is used in posted journal.');
    }

    public function test_accrual_posting_correct(): void
    {
        $this->markTestIncomplete('TODO: Seed accrued receivable balances and ensure repayment credits receivable accounts (not income) and principal account.');
    }
}
