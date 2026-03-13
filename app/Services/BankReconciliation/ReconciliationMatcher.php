<?php

declare(strict_types=1);

namespace App\Services\BankReconciliation;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\JournalEntries;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReconciliationMatcher
{
    public function autoMatch(BankStatement $statement, int $maxDateDiffDays = 3): int
    {
        return DB::transaction(function () use ($statement, $maxDateDiffDays): int {
            $statement = BankStatement::query()->lockForUpdate()->findOrFail((int) $statement->id);

            $lines = BankStatementLine::query()
                ->where('bank_statement_id', (int) $statement->id)
                ->whereNull('matched_journal_entry_id')
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                return 0;
            }

            $subshopId = (int) session('subshop_id');

            $minDate = $lines->min(fn (BankStatementLine $l) => $l->transaction_date?->copy()->subDays($maxDateDiffDays));
            $maxDate = $lines->max(fn (BankStatementLine $l) => $l->transaction_date?->copy()->addDays($maxDateDiffDays));

            $journals = JournalEntries::query()
                ->with('lines')
                ->where('subshop_id', $subshopId)
                ->when($minDate, fn ($q) => $q->whereDate('transaction_date', '>=', $minDate->toDateString()))
                ->when($maxDate, fn ($q) => $q->whereDate('transaction_date', '<=', $maxDate->toDateString()))
                ->orderByDesc('id')
                ->get();

            $alreadyUsedJournalIds = BankStatementLine::query()
                ->where('bank_statement_id', (int) $statement->id)
                ->where('is_matched', true)
                ->whereNotNull('matched_journal_entry_id')
                ->pluck('matched_journal_entry_id')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values()
                ->all();

            $usedJournalIds = array_fill_keys($alreadyUsedJournalIds, true);
            $matchedCount = 0;

            foreach ($lines as $line) {
                $candidate = $this->findCandidate($line, $journals, $maxDateDiffDays, $usedJournalIds);
                if (!$candidate || (int) $candidate->id <= 0) {
                    continue;
                }

                $line->is_matched = true;
                $line->matched_journal_entry_id = (int) $candidate->id;
                $line->save();

                $usedJournalIds[(int) $candidate->id] = true;
                $matchedCount++;
            }

            return $matchedCount;
        });
    }

    private function findCandidate(BankStatementLine $line, $journals, int $maxDateDiffDays, array $usedJournalIds): ?JournalEntries
    {
        $amount = round(max((float) $line->debit, (float) $line->credit), 2);
        if ($amount <= 0) {
            $amount = round(abs((float) $line->amount), 2);
        }

        if ($amount <= 0) {
            return null;
        }

        $lineDate = $line->transaction_date ? Carbon::parse((string) $line->transaction_date)->startOfDay() : null;

        $best = null;
        $bestScore = -INF;

        foreach ($journals as $j) {
            if (!empty($usedJournalIds[(int) $j->id])) {
                continue;
            }

            $journalDate = $j->transaction_date ? Carbon::parse((string) $j->transaction_date)->startOfDay() : null;
            if ($lineDate && $journalDate) {
                $diffDays = abs($lineDate->diffInDays($journalDate));
                if ($diffDays > $maxDateDiffDays) {
                    continue;
                }
            }

            $totals = $this->journalTotals($j);
            $journalAmount = round(max($totals['debit'], $totals['credit']), 2);
            if ($journalAmount !== $amount) {
                continue;
            }

            $score = 100;

            $score += $this->similarityScore((string) $line->reference, (string) $j->reference_id);
            $score += $this->similarityScore((string) $line->description, (string) $j->description);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $j;
            }
        }

        return $best;
    }

    private function journalTotals(JournalEntries $journal): array
    {
        $debit = 0.0;
        $credit = 0.0;

        foreach ($journal->lines as $line) {
            $debit += (float) $line->debit;
            $credit += (float) $line->credit;
        }

        return [
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
        ];
    }

    private function similarityScore(string $a, string $b): float
    {
        $a = trim(mb_strtolower($a));
        $b = trim(mb_strtolower($b));

        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $percent);

        return (float) ($percent / 10.0);
    }
}
