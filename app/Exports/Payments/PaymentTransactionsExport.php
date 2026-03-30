<?php

namespace App\Exports\Payments;

use App\Models\PaymentTransaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PaymentTransactionsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected int $shopId;
    protected array $filters;

    public function __construct(int $shopId, array $filters = [])
    {
        $this->shopId = $shopId;
        $this->filters = $filters;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = PaymentTransaction::where('shop_id', $this->shopId)
            ->with(['customer', 'loan']);

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['provider'])) {
            $query->where('provider', $this->filters['provider']);
        }

        if (!empty($this->filters['start_date'])) {
            $query->where('created_at', '>=', $this->filters['start_date']);
        }

        if (!empty($this->filters['end_date'])) {
            $query->where('created_at', '<=', $this->filters['end_date']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Reference',
            'Customer',
            'Phone',
            'Amount',
            'Provider',
            'Channel',
            'Status',
            'External ID',
            'Loan Reference',
            'Initiated At',
            'Completed At',
            'Created At',
        ];
    }

    /**
     * @param PaymentTransaction $transaction
     * @return array
     */
    public function map($transaction): array
    {
        return [
            $transaction->reference,
            $transaction->customer?->name ?? 'N/A',
            $transaction->phone,
            number_format($transaction->amount, 2),
            ucfirst($transaction->provider),
            strtoupper($transaction->channel),
            ucfirst($transaction->status),
            $transaction->external_id ?? 'N/A',
            $transaction->loan?->reference ?? 'N/A',
            $transaction->initiated_at?->format('Y-m-d H:i:s') ?? 'N/A',
            $transaction->completed_at?->format('Y-m-d H:i:s') ?? 'N/A',
            $transaction->created_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param Worksheet $sheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => ['font' => ['bold' => true]],
        ];
    }
}
