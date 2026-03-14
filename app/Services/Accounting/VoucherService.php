<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\JournalEntries;
use App\Models\Vouchers;
use App\Models\VoucherLines;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VoucherService
{
    public function __construct(private readonly DoubleEntryValidator $validator)
    {
    }

    public function generateVoucherNumber(string $type): string
    {
        $type = strtolower(trim($type));
        $prefix = match ($type) {
            'receipt' => 'RV',
            'payment' => 'PV',
            default => throw new InvalidArgumentException('Invalid voucher type for number generation.'),
        };

        $latest = Vouchers::query()
            ->withTrashed()
            ->where('voucher_type', $type)
            ->orderByDesc('id')
            ->value('voucher_number');

        $next = 1;
        if ($latest) {
            if (preg_match('/^(?:RV|PV)-(\d{6})$/', (string) $latest, $m)) {
                $next = ((int) $m[1]) + 1;
            }
        }

        return $prefix . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param array{reference_type?:string, reference_id?:int|null, amount:float, payment_method?:string|null, bank_account_id?:int|null, voucher_date?:string|Carbon|null, description?:string|null, source_type?:string|null, created_by?:int|null, approved_by?:int|null, subshop_id?:int|null, journal_entry_id?:int|null, lines?:array<int, array{account_id:int,debit:float|int|string,credit:float|int|string,description?:string|null}>} $data
     */
    public function createReceiptVoucher(array $data): Vouchers
    {
        return $this->createVoucher('receipt', $data);
    }

    /**
     * @param array{reference_type?:string, reference_id?:int|null, amount:float, payment_method?:string|null, bank_account_id?:int|null, voucher_date?:string|Carbon|null, description?:string|null, source_type?:string|null, created_by?:int|null, approved_by?:int|null, subshop_id?:int|null, journal_entry_id?:int|null, lines?:array<int, array{account_id:int,debit:float|int|string,credit:float|int|string,description?:string|null}>} $data
     */
    public function createPaymentVoucher(array $data): Vouchers
    {
        return $this->createVoucher('payment', $data);
    }

    public function createVoucherFromJournalEntry(
        JournalEntries $journal,
        string $voucherType,
        array $overrides = []
    ): Vouchers {
        $journal->loadMissing('lines');

        $voucherType = strtolower(trim($voucherType));
        if (!in_array($voucherType, ['receipt', 'payment'], true)) {
            throw new InvalidArgumentException('Invalid voucher type.');
        }

        $existing = Vouchers::query()
            ->where('voucher_type', $voucherType)
            ->where('source_type', 'system')
            ->where('reference_type', (string) $journal->reference_type)
            ->where('reference_id', (int) $journal->reference_id)
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing->loadMissing(['lines']);
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

        $totalAmount = 0.0;
        foreach ($lines as $line) {
            $totalAmount += (float) $line['debit'];
        }
        $totalAmount = round($totalAmount, 2);

        $payload = array_merge([
            'reference_type' => (string) $journal->reference_type,
            'reference_id' => (int) $journal->reference_id,
            'amount' => $totalAmount,
            'payment_method' => null,
            'bank_account_id' => null,
            'voucher_date' => $journal->transaction_date ? Carbon::parse((string) $journal->transaction_date) : Carbon::now(),
            'description' => $journal->description,
            'source_type' => 'system',
            'created_by' => $journal->created_by,
            'approved_by' => null,
            'subshop_id' => $journal->subshop_id,
            'journal_entry_id' => (int) $journal->id,
            'lines' => $lines,
        ], $overrides);

        return $this->createVoucher($voucherType, $payload);
    }

    /**
     * @param array{reference_type?:string, reference_id?:int|null, amount:float, payment_method?:string|null, bank_account_id?:int|null, voucher_date?:string|Carbon|null, description?:string|null, source_type?:string|null, created_by?:int|null, approved_by?:int|null, subshop_id?:int|null, journal_entry_id?:int|null, lines?:array<int, array{account_id:int,debit:float|int|string,credit:float|int|string,description?:string|null}>} $data
     */
    private function createVoucher(string $voucherType, array $data): Vouchers
    {
        $voucherType = strtolower(trim($voucherType));
        if (!in_array($voucherType, ['receipt', 'payment'], true)) {
            throw new InvalidArgumentException('Invalid voucher type.');
        }

        $sourceType = (string) ($data['source_type'] ?? 'system');
        if (!in_array($sourceType, ['manual', 'system'], true)) {
            throw new InvalidArgumentException('Invalid voucher source type.');
        }

        $voucherDate = $data['voucher_date'] ?? Carbon::now();
        $voucherDate = $voucherDate instanceof Carbon ? $voucherDate : Carbon::parse((string) $voucherDate);

        $lines = $data['lines'] ?? null;
        if (!is_array($lines) || empty($lines)) {
            throw new InvalidArgumentException('Voucher lines are required.');
        }

        $normalizedLines = [];
        foreach ($lines as $line) {
            $normalizedLines[] = [
                'account_id' => (int) ($line['account_id'] ?? 0),
                'debit' => round((float) ($line['debit'] ?? 0), 2),
                'credit' => round((float) ($line['credit'] ?? 0), 2),
                'description' => $line['description'] ?? null,
            ];
        }

        $this->validator->validate($normalizedLines);

        $totalAmount = round((float) ($data['amount'] ?? 0), 2);
        if ($totalAmount <= 0) {
            $total = 0.0;
            foreach ($normalizedLines as $l) {
                $total += (float) $l['debit'];
            }
            $totalAmount = round($total, 2);
        }

        if ($totalAmount <= 0) {
            throw new InvalidArgumentException('Voucher amount must be greater than 0.');
        }

        $voucherNumber = $this->generateVoucherNumber($voucherType);

        return DB::transaction(function () use ($data, $voucherType, $sourceType, $voucherDate, $voucherNumber, $normalizedLines, $totalAmount) {
            $subshopId = isset($data['subshop_id']) && $data['subshop_id'] ? (int) $data['subshop_id'] : (int) session('subshop_id');

            $voucher = Vouchers::query()->create([
                'voucher_number' => $voucherNumber,
                'voucher_type' => $voucherType,
                'source_type' => $sourceType,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => !empty($data['reference_id']) ? (int) $data['reference_id'] : null,
                'total_amount' => $totalAmount,
                'payment_method' => $data['payment_method'] ?? null,
                'bank_account_id' => !empty($data['bank_account_id']) ? (int) $data['bank_account_id'] : null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'posted',
                'subshop_id' => $subshopId ?: null,
                'created_by' => !empty($data['created_by']) ? (int) $data['created_by'] : auth()->id(),
                'approved_by' => !empty($data['approved_by']) ? (int) $data['approved_by'] : null,
                'voucher_date' => $voucherDate,
            ]);

            $insert = [];
            foreach ($normalizedLines as $line) {
                $insert[] = [
                    'voucher_id' => (int) $voucher->id,
                    'account_id' => (int) $line['account_id'],
                    'debit' => (float) $line['debit'],
                    'credit' => (float) $line['credit'],
                    'description' => $line['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            VoucherLines::query()->insert($insert);

            return $voucher->load(['lines']);
        });
    }
}
