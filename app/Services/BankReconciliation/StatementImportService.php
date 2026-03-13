<?php

declare(strict_types=1);

namespace App\Services\BankReconciliation;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StatementImportService
{
    public function importCsv(BankStatement $statement, UploadedFile $file): int
    {
        $path = $file->store('bank_statements');

        $statement->file_path = $path;
        $statement->status = 'in_progress';
        $statement->save();

        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('Unable to read uploaded file.');
        }

        $rowIndex = 0;
        $imported = 0;

        DB::transaction(function () use ($statement, $handle, &$rowIndex, &$imported) {
            while (($row = fgetcsv($handle)) !== false) {
                $rowIndex++;

                if ($rowIndex === 1 && $this->looksLikeHeader($row)) {
                    continue;
                }

                $data = $this->parseRow($row, $rowIndex);

                BankStatementLine::query()->create([
                    'bank_statement_id' => (int) $statement->id,
                    'transaction_date' => $data['transaction_date'],
                    'reference' => $data['reference'],
                    'description' => $data['description'],
                    'debit' => $data['debit'],
                    'credit' => $data['credit'],
                    'amount' => $data['amount'],
                    'is_matched' => false,
                    'matched_journal_entry_id' => null,
                ]);

                $imported++;
            }
        });

        fclose($handle);

        return $imported;
    }

    private function looksLikeHeader(array $row): bool
    {
        $joined = strtolower(trim(implode(',', $row)));

        return str_contains($joined, 'date')
            && (str_contains($joined, 'reference') || str_contains($joined, 'description'));
    }

    private function parseRow(array $row, int $rowIndex): array
    {
        $date = trim((string) ($row[0] ?? ''));
        if ($date === '') {
            throw new InvalidArgumentException("CSV row {$rowIndex}: Date is required.");
        }

        try {
            $transactionDate = \Carbon\Carbon::parse($date)->toDateString();
        } catch (\Throwable $e) {
            throw new InvalidArgumentException("CSV row {$rowIndex}: Invalid date '{$date}'.");
        }

        $reference = trim((string) ($row[1] ?? ''));
        $description = trim((string) ($row[2] ?? ''));

        $debit = $this->toMoney($row[3] ?? 0);
        $credit = $this->toMoney($row[4] ?? 0);
        $amount = $this->toMoney($row[5] ?? 0);

        return [
            'transaction_date' => $transactionDate,
            'reference' => $reference !== '' ? $reference : null,
            'description' => $description !== '' ? $description : null,
            'debit' => $debit,
            'credit' => $credit,
            'amount' => $amount,
        ];
    }

    private function toMoney(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        return round((float) $value, 2);
    }
}
