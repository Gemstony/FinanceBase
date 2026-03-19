<?php

declare(strict_types=1);

namespace App\Services\Reports\Accounting;

use App\Models\JournalEntries;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class JournalReportService
{
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $from = $filters['from_date'];
        $to = $filters['to_date'];

        $query = $this->baseEntriesQuery($filters, $accessibleSubshopIds)
            ->whereDate('transaction_date', '>=', $from->toDateString())
            ->whereDate('transaction_date', '<=', $to->toDateString());

        $perPage = (int) ($filters['per_page'] ?? 15);
        if ($perPage <= 0) {
            $perPage = 15;
        }

        $entries = $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        foreach ($entries->getCollection() as $entry) {
            $totalDebit = (float) ($entry->lines?->sum('debit') ?? 0);
            $totalCredit = (float) ($entry->lines?->sum('credit') ?? 0);
            $entry->setAttribute('total_debit', $totalDebit);
            $entry->setAttribute('total_credit', $totalCredit);
            $entry->setAttribute('is_balanced', abs($totalDebit - $totalCredit) < 0.005);
        }

        $totals = $this->globalTotals($filters, $accessibleSubshopIds);
        $totals['entries_count'] = (int) $entries->total();

        return [
            'filters' => [
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
                'subshop_id' => (int) ($filters['subshop_id'] ?? 0) ?: null,
                'reference' => isset($filters['reference']) ? (string) $filters['reference'] : null,
                'reference_type' => isset($filters['reference_type']) ? (string) $filters['reference_type'] : null,
                'created_by' => isset($filters['created_by']) ? (int) $filters['created_by'] : null,
            ],
            'entries' => $entries,
            'totals' => $totals,
        ];
    }

    public function export(array $filters, array $accessibleSubshopIds): array
    {
        $from = $filters['from_date'];
        $to = $filters['to_date'];

        $entries = $this->baseEntriesQuery($filters, $accessibleSubshopIds)
            ->whereDate('transaction_date', '>=', $from->toDateString())
            ->whereDate('transaction_date', '<=', $to->toDateString())
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $entriesAll = [];
        foreach ($entries as $entry) {
            $totalDebit = (float) ($entry->lines?->sum('debit') ?? 0);
            $totalCredit = (float) ($entry->lines?->sum('credit') ?? 0);

            $lines = [];
            foreach (($entry->lines ?? []) as $line) {
                $lines[] = [
                    'account_code' => (string) ($line->account?->account_code ?? ''),
                    'account_name' => (string) ($line->account?->account_name ?? ''),
                    'debit' => (float) ($line->debit ?? 0),
                    'credit' => (float) ($line->credit ?? 0),
                    'description' => (string) ($line->description ?? ''),
                ];
            }

            $entriesAll[] = [
                'id' => (int) $entry->id,
                'transaction_date' => $entry->transaction_date?->format('Y-m-d') ?? '',
                'reference_type' => (string) ($entry->reference_type ?? ''),
                'reference_id' => (string) ($entry->reference_id ?? ''),
                'description' => (string) ($entry->description ?? ''),
                'created_by_name' => (string) ($entry->creator?->name ?? ''),
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'is_balanced' => abs($totalDebit - $totalCredit) < 0.005,
                'lines' => $lines,
            ];
        }

        $totals = $this->globalTotals($filters, $accessibleSubshopIds);
        $totals['entries_count'] = count($entriesAll);

        return [
            'filters' => [
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
                'subshop_id' => (int) ($filters['subshop_id'] ?? 0) ?: null,
                'reference' => isset($filters['reference']) ? (string) $filters['reference'] : null,
                'reference_type' => isset($filters['reference_type']) ? (string) $filters['reference_type'] : null,
                'created_by' => isset($filters['created_by']) ? (int) $filters['created_by'] : null,
            ],
            'entries_all' => $entriesAll,
            'totals' => $totals,
        ];
    }

    private function baseEntriesQuery(array $filters, array $accessibleSubshopIds): Builder
    {
        $subshopId = (int) ($filters['subshop_id'] ?? 0);

        $query = JournalEntries::query()
            ->with(['creator', 'lines.account']);

        if ($subshopId > 0) {
            $query->where('subshop_id', $subshopId);
        } else {
            $query->whereIn('subshop_id', $accessibleSubshopIds ?: [-1]);
        }

        if (!empty($filters['reference'])) {
            $reference = trim((string) $filters['reference']);
            $reference = ltrim($reference, '#');

            $query->where(function (Builder $q) use ($reference) {
                if ($reference !== '' && ctype_digit($reference)) {
                    $id = (int) $reference;
                    $q->whereKey($id)->orWhere('reference_id', $id);
                } else {
                    $q->where('description', 'like', '%' . $reference . '%');
                }
            });
        }

        if (!empty($filters['reference_type'])) {
            $query->where('reference_type', (string) $filters['reference_type']);
        }

        if (!empty($filters['created_by'])) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        return $query;
    }

    private function globalTotals(array $filters, array $accessibleSubshopIds): array
    {
        $from = $filters['from_date'];
        $to = $filters['to_date'];

        $subshopId = (int) ($filters['subshop_id'] ?? 0);
        $reference = isset($filters['reference']) ? trim((string) $filters['reference']) : '';
        $reference = ltrim($reference, '#');
        $referenceType = isset($filters['reference_type']) ? (string) $filters['reference_type'] : '';
        $createdBy = (int) ($filters['created_by'] ?? 0);

        $agg = \Illuminate\Support\Facades\DB::table('journal_entries as je')
            ->join('journal_entry_lines as jel', 'jel.journal_entry_id', '=', 'je.id')
            ->whereDate('je.transaction_date', '>=', $from->toDateString())
            ->whereDate('je.transaction_date', '<=', $to->toDateString());

        if ($subshopId > 0) {
            $agg->where('je.subshop_id', $subshopId);
        } else {
            $agg->whereIn('je.subshop_id', $accessibleSubshopIds ?: [-1]);
        }

        if ($reference !== '') {
            $agg->where(function ($q) use ($reference) {
                if (ctype_digit($reference)) {
                    $id = (int) $reference;
                    $q->where('je.id', $id)->orWhere('je.reference_id', $id);
                } else {
                    $q->where('je.description', 'like', '%' . $reference . '%');
                }
            });
        }

        if ($referenceType !== '') {
            $agg->where('je.reference_type', $referenceType);
        }

        if ($createdBy > 0) {
            $agg->where('je.created_by', $createdBy);
        }

        $row = $agg->selectRaw('COALESCE(SUM(jel.debit),0) as total_debit, COALESCE(SUM(jel.credit),0) as total_credit')->first();

        return [
            'total_debit' => (float) ($row->total_debit ?? 0),
            'total_credit' => (float) ($row->total_credit ?? 0),
            'entries_count' => 0,
        ];
    }
}
