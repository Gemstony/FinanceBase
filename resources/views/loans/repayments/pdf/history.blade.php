<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Payment History - {{ $loan->loan_code }}</title>
    <style>
        @page { margin: 24px 22px; font-family: 'DejaVu Sans', Arial, sans-serif; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #000; }

        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 12px; }
        .header-left { display: inline-block; vertical-align: top; width: 64%; }
        .header-right { display: inline-block; vertical-align: top; width: 35%; text-align: right; }
        .header-logo { display: inline-block; vertical-align: top; width: 90px; }
        .header-info { display: inline-block; vertical-align: top; width: calc(100% - 95px); }
        .logo { max-width: 85px; max-height: 85px; object-fit: contain; }

        .inst-name { font-size: 14px; font-weight: 700; margin: 0 0 3px 0; }
        .inst-meta { font-size: 9px; line-height: 1.35; }
        .report-title { font-size: 12px; font-weight: 700; text-transform: uppercase; text-align: right; margin: 0 0 3px 0; }
        .report-sub { font-size: 9px; text-align: right; line-height: 1.35; }

        .receipt-box { border: 2px solid #000; padding: 15px; margin: 15px 0; }
        .receipt-header { text-align: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ccc; }
        .receipt-title { font-size: 16px; font-weight: 700; text-transform: uppercase; margin: 0; }
        .receipt-subtitle { font-size: 10px; color: #666; margin-top: 5px; }

        .info-grid { display: table; width: 100%; margin-bottom: 15px; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; width: 25%; font-weight: 700; padding: 4px 0; vertical-align: top; }
        .info-value { display: table-cell; width: 25%; padding: 4px 0; vertical-align: top; }

        .amount-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 12px; text-align: center; margin: 15px 0; }
        .amount-label { font-size: 10px; text-transform: uppercase; color: #666; }
        .amount-value { font-size: 18px; font-weight: 700; color: #000; }

        .block-title { font-size: 10px; font-weight: 700; margin: 14px 0 6px 0; text-transform: uppercase; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #f0f0f0; font-weight: 700; text-align: left; }
        td.num, th.num { text-align: right; }
        .text-center { text-align: center; }

        .signature-section { margin-top: 30px; }
        .signature-line { display: inline-block; width: 45%; margin-right: 5%; vertical-align: top; }
        .signature-box { border-top: 1px solid #000; margin-top: 40px; padding-top: 5px; font-size: 9px; }

        .footer { border-top: 1px solid #000; margin-top: 20px; padding-top: 6px; font-size: 8px; }
        .footer-left { display: inline-block; vertical-align: top; width: 70%; }
        .footer-right { display: inline-block; vertical-align: top; width: 29%; text-align: right; }

        .status-paid { color: #28a745; font-weight: bold; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-failed { color: #dc3545; font-weight: bold; }
        .status-reversed { color: #6c757d; font-weight: bold; }

        .summary-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; margin: 10px 0; }
        .summary-row { display: flex; justify-content: space-between; padding: 4px 0; }
        .summary-label { font-weight: 700; }
        .summary-value { font-weight: 700; }
    </style>
</head>
<body>
@php
    $shop = $shop ?? null;
    $shopName = $shop?->name ?? config('app.name', 'Institution');
    $shopEmail = $shop?->email ?? null;
    $shopPhone = $shop?->phone ?? null;
    $shopWebsite = $shop?->website ?? null;
    $shopAddress = $shop?->address ?? null;

    $customer = $loan?->customer;
    $loanGroup = $loan?->loanGroup;
    $borrowerName = $customer?->name ?? $loanGroup?->name ?? '—';

    $totalPaid = (float) $payments->where('status', '!=', 'reversed')->sum('amount');
    $totalPrincipal = (float) $payments->where('status', '!=', 'reversed')->sum(function($p) {
        return (float) $p->allocations->sum('principal_amount');
    });
    $totalInterest = (float) $payments->where('status', '!=', 'reversed')->sum(function($p) {
        return (float) $p->allocations->sum('interest_amount');
    });
    $totalPenalty = (float) $payments->where('status', '!=', 'reversed')->sum(function($p) {
        return (float) $p->allocations->sum('penalty_amount');
    });
@endphp

<div class="header">
    <div class="header-left">
        <div class="header-logo">
            @if(!empty($shopLogoPath) && file_exists($shopLogoPath))
                <img class="logo" src="{{ $shopLogoPath }}" alt="Logo">
            @endif
        </div>
        <div class="header-info">
            <div class="inst-name">{{ $shopName }}</div>
            <div class="inst-meta">
                @if($shopAddress)
                    <div><strong>Address:</strong> {{ $shopAddress }}</div>
                @endif
                @if($shopPhone)
                    <div><strong>Phone:</strong> {{ $shopPhone }}</div>
                @endif
                @if($shopEmail)
                    <div><strong>Email:</strong> {{ $shopEmail }}</div>
                @endif
                @if($shopWebsite)
                    <div><strong>Website:</strong> {{ $shopWebsite }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="header-right">
        <div class="report-title">Payment History</div>
        <div class="report-sub">
            <div><strong>Loan Code:</strong> {{ $loan->loan_code }}</div>
            <div><strong>Generated:</strong> {{ now()->format('Y-m-d H:i:s') }}</div>
        </div>
    </div>
</div>

<div class="receipt-box">
    <div class="receipt-header">
        <div class="receipt-title">Payment History Invoice</div>
        <div class="receipt-subtitle">Complete payment record for this loan</div>
    </div>

    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Loan Number:</div>
            <div class="info-value">{{ $loan->loan_code }}</div>
            <div class="info-label">Product:</div>
            <div class="info-value">{{ $loan->loanProduct?->name ?? '—' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Borrower:</div>
            <div class="info-value">{{ $borrowerName }}</div>
            <div class="info-label">Loan Status:</div>
            <div class="info-value">{{ ucfirst($loan->status ?? '—') }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Disbursement Date:</div>
            <div class="info-value">{{ $loan->disbursement_date?->format('Y-m-d') ?? '—' }}</div>
            <div class="info-label">Maturity Date:</div>
            <div class="info-value">{{ $loan->maturity_date?->format('Y-m-d') ?? '—' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Principal Amount:</div>
            <div class="info-value">{{ number_format((float) $loan->principal_amount, 2) }}</div>
            <div class="info-label">Interest Rate:</div>
            <div class="info-value">{{ number_format((float) $loan->interest_rate, 2) }}%</div>
        </div>
    </div>

    @isset($summary)
        <div class="summary-box">
            <div class="summary-row">
                <span class="summary-label">Principal Outstanding:</span>
                <span class="summary-value">{{ number_format((float) ($summary['principal_outstanding'] ?? 0), 2) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Interest Outstanding:</span>
                <span class="summary-value">{{ number_format((float) ($summary['interest_outstanding'] ?? 0), 2) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Penalty Outstanding:</span>
                <span class="summary-value">{{ number_format((float) ($summary['penalties_outstanding'] ?? 0), 2) }}</span>
            </div>
            <div class="summary-row" style="border-top: 1px solid #000; padding-top: 8px; margin-top: 4px;">
                <span class="summary-label">Total Balance:</span>
                <span class="summary-value">{{ number_format((float) ($summary['total_balance'] ?? 0), 2) }}</span>
            </div>
        </div>
    @endisset

    <div class="block-title">Payment History</div>
    <table>
        <thead>
            <tr>
                <th style="width: 12%;">Date</th>
                <th style="width: 15%;">Amount</th>
                <th style="width: 15%;">Principal</th>
                <th style="width: 15%;">Interest</th>
                <th style="width: 15%;">Penalty</th>
                <th style="width: 13%;">Method</th>
                <th style="width: 15%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $p)
                @php
                    $principal = (float) $p->allocations->sum('principal_amount');
                    $interest = (float) $p->allocations->sum('interest_amount');
                    $penalty = (float) $p->allocations->sum('penalty_amount');

                    $statusClass = match((string) $p->status) {
                        'confirmed', 'paid' => 'status-paid',
                        'pending' => 'status-pending',
                        'failed', 'cancelled' => 'status-failed',
                        'reversed' => 'status-reversed',
                        default => '',
                    };
                @endphp
                <tr>
                    <td>{{ $p->payment_date?->format('Y-m-d') }}</td>
                    <td class="num">{{ number_format((float) $p->amount, 2) }}</td>
                    <td class="num">{{ number_format($principal, 2) }}</td>
                    <td class="num">{{ number_format($interest, 2) }}</td>
                    <td class="num">{{ number_format($penalty, 2) }}</td>
                    <td>{{ ucfirst($p->payment_method ?? '—') }}</td>
                    <td class="{{ $statusClass }}">{{ ucfirst($p->status ?? '—') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No payments found.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot style="background: #e9ecef; font-weight: bold;">
            <tr>
                <td colspan="2" class="text-center">Total Paid</td>
                <td class="num">{{ number_format($totalPrincipal, 2) }}</td>
                <td class="num">{{ number_format($totalInterest, 2) }}</td>
                <td class="num">{{ number_format($totalPenalty, 2) }}</td>
                <td colspan="2" class="num">{{ number_format($totalPaid, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="signature-section">
        <div class="signature-line">
            <div class="signature-box">
                <strong>Generated By</strong><br>
                {{ Auth::user()?->name ?? '____________________' }}<br>
                Date: {{ now()->format('Y-m-d') }}
            </div>
        </div>
        <div class="signature-line">
            <div class="signature-box">
                <strong>Borrower Confirmation</strong><br>
                Signature: ____________________<br>
                Date: ____________________
            </div>
        </div>
    </div>
</div>

<div style="text-align: center; font-size: 9px; color: #666; margin-top: 15px;">
    <strong>Payment History Statement</strong><br>
    This document was computer-generated and shows the complete payment history for this loan.
</div>

<div class="footer">
    <div class="footer-left">
        <strong>{{ $shopName }}</strong> | Payment History - Loan {{ $loan->loan_code }}
    </div>
    <div class="footer-right">
        Page
    </div>
</div>

</body>
</html>
