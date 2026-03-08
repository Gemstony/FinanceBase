<?php

declare(strict_types=1);

namespace App\Services\Accounting;

class JournalEntryBuilder
{
    /** @var array<int, array{account_id:int, debit:float, credit:float, description:?string}> */
    private array $lines = [];

    /**
     * Add a debit line to the journal entry.
     *
     * @param int    $accountId   Chart of Accounts ID
     * @param float  $amount      Debit amount (must be > 0)
     * @param string|null $description Optional line description
     *
     * @return $this
     */
    public function addDebit(int $accountId, float $amount, ?string $description = null): self
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be greater than zero.');
        }

        $this->lines[] = [
            'account_id' => $accountId,
            'debit' => round($amount, 2),
            'credit' => 0.0,
            'description' => $description,
        ];

        return $this;
    }

    /**
     * Add a credit line to the journal entry.
     *
     * @param int    $accountId   Chart of Accounts ID
     * @param float  $amount      Credit amount (must be > 0)
     * @param string|null $description Optional line description
     *
     * @return $this
     */
    public function addCredit(int $accountId, float $amount, ?string $description = null): self
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be greater than zero.');
        }

        $this->lines[] = [
            'account_id' => $accountId,
            'debit' => 0.0,
            'credit' => round($amount, 2),
            'description' => $description,
        ];

        return $this;
    }

    /**
     * Get all built journal entry lines.
     *
     * @return array<int, array{account_id:int, debit:float, credit:float, description:?string}>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * Reset the builder for reuse.
     *
     * @return $this
     */
    public function reset(): self
    {
        $this->lines = [];

        return $this;
    }
}
