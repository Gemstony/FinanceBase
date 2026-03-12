<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\JournalEntries;
use App\Models\JournalEntryLines;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ManualJournalService
{
    public function __construct(
        private readonly DoubleEntryValidator $validator,
        private readonly JournalPostingEngine $postingEngine,
    ) {
    }

    public function createDraftJournal(Carbon $transactionDate, ?string $description = null): JournalEntries
    {
        $subshopId = (int) session('subshop_id');
        if ($subshopId <= 0) {
            throw new InvalidArgumentException('Active branch context is required.');
        }

        return JournalEntries::create([
            'subshop_id' => $subshopId,
            'reference_type' => 'manual_draft',
            'reference_id' => 0,
            'transaction_date' => $transactionDate->toDateString(),
            'description' => $description,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * @param array<int, array{account_id:int|string, debit:mixed, credit:mixed, description?:mixed}> $lines
     */
    public function addJournalLines(JournalEntries $journal, array $lines): void
    {
        if ((string) $journal->reference_type !== 'manual_draft') {
            throw new InvalidArgumentException('Only draft manual journals can be edited.');
        }

        $subshopId = (int) session('subshop_id');
        if ((int) $journal->subshop_id !== $subshopId) {
            throw new InvalidArgumentException('You cannot modify a journal from another branch.');
        }

        $toInsert = [];
        foreach ($lines as $line) {
            $accountId = (int) ($line['account_id'] ?? 0);
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            $toInsert[] = [
                'journal_entry_id' => (int) $journal->id,
                'account_id' => $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'description' => isset($line['description']) ? (string) $line['description'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        JournalEntryLines::query()->where('journal_entry_id', (int) $journal->id)->delete();

        if (!empty($toInsert)) {
            JournalEntryLines::insert($toInsert);
        }
    }

    public function validateJournal(JournalEntries $journal): void
    {
        $journal->loadMissing('lines');

        if ($journal->lines->count() < 2) {
            throw new InvalidArgumentException('Journal entry must contain at least two lines.');
        }

        $lines = [];
        foreach ($journal->lines as $line) {
            $lines[] = [
                'account_id' => (int) $line->account_id,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'description' => $line->description,
            ];
        }

        $this->validator->validate($lines);
    }

    public function postJournal(JournalEntries $draft): JournalEntries
    {
        if ((string) $draft->reference_type !== 'manual_draft') {
            throw new InvalidArgumentException('Only manual draft journals can be posted.');
        }

        if ((int) $draft->reference_id > 0) {
            throw new InvalidArgumentException('This manual journal has already been posted.');
        }

        $subshopId = (int) session('subshop_id');
        if ((int) $draft->subshop_id !== $subshopId) {
            throw new InvalidArgumentException('You cannot post a journal from another branch.');
        }

        $this->validateJournal($draft);

        return DB::transaction(function () use ($draft) {
            $draft = JournalEntries::query()->lockForUpdate()->findOrFail((int) $draft->id);
            $draft->loadMissing('lines');

            $lines = [];
            foreach ($draft->lines as $line) {
                $lines[] = [
                    'account_id' => (int) $line->account_id,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                    'description' => $line->description,
                ];
            }

            $txDate = Carbon::parse((string) $draft->transaction_date);
            $prevNow = Carbon::getTestNow();
            Carbon::setTestNow($txDate);
            try {
                $posted = $this->postingEngine->postJournalEntry(
                    $lines,
                    'manual_posted',
                    (int) $draft->id,
                    (string) $draft->description
                );
            } finally {
                Carbon::setTestNow($prevNow);
            }

            $draft->reference_id = (int) $posted->id;
            $draft->save();

            return $draft;
        });
    }

    public function reverseJournal(JournalEntries $postedDraft): JournalEntries
    {
        if ((string) $postedDraft->reference_type !== 'manual_draft') {
            throw new InvalidArgumentException('Only manual journals can be reversed.');
        }

        if ((int) $postedDraft->reference_id <= 0) {
            throw new InvalidArgumentException('Only posted manual journals can be reversed.');
        }

        $subshopId = (int) session('subshop_id');
        if ((int) $postedDraft->subshop_id !== $subshopId) {
            throw new InvalidArgumentException('You cannot reverse a journal from another branch.');
        }

        $postedDraft->loadMissing('lines');
        $this->validateJournal($postedDraft);

        $existingReversal = JournalEntries::query()
            ->where('subshop_id', (int) $postedDraft->subshop_id)
            ->where('reference_type', 'manual_reversal')
            ->where('reference_id', (int) $postedDraft->id)
            ->exists();
        if ($existingReversal) {
            throw new InvalidArgumentException('This manual journal has already been reversed.');
        }

        $reversalLines = [];
        foreach ($postedDraft->lines as $line) {
            $reversalLines[] = [
                'account_id' => (int) $line->account_id,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
                'description' => $line->description,
            ];
        }

        $txDate = Carbon::parse((string) $postedDraft->transaction_date);
        $prevNow = Carbon::getTestNow();
        Carbon::setTestNow($txDate);
        try {
            $reversal = $this->postingEngine->postJournalEntry(
                $reversalLines,
                'manual_reversal',
                (int) $postedDraft->id,
                'Reversal of journal #' . (int) $postedDraft->id
            );
        } finally {
            Carbon::setTestNow($prevNow);
        }

        return $reversal;
    }
}
