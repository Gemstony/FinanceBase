<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Payment Receipt #{{ $payment->id }}</title>
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

        .notes-box { background: #f8f9fa; border-left: 3px solid #6c757d; padding: 8px 12px; margin: 10px 0; font-size: 9px; }
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
    // $shopLogoPath is passed from controller with full public_path

    $loan = $payment->loan;
    $customer = $loan?->customer;
    $loanGroup = $loan?->loanGroup;
    $borrowerName = $customer?->name ?? $loanGroup?->name ?? '—';

    $principal = $principal ?? 0;
    $interest = $interest ?? 0;
    $penalty = $penalty ?? 0;

    $statusClass = match((string) $payment->status) {
        'confirmed', 'paid' => 'status-paid',
        'pending' => 'status-pending',
        'failed', 'cancelled' => 'status-failed',
        default => '',
    };
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
        <div class="report-title">Payment Receipt</div>
        <div class="report-sub">
            <div><strong>Receipt #:</strong> {{ $payment->id }}</div>
            <div><strong>Date:</strong> {{ $payment->payment_date?->format('d M Y') }}</div>
            <div><strong>Generated:</strong> {{ now()->format('Y-m-d H:i:s') }}</div>
        </div>
    </div>
</div>

<div class="receipt-box">
    <div class="receipt-header">
        <div class="receipt-title">Official Receipt</div>
        <div class="receipt-subtitle">This is an official receipt for loan payment</div>
    </div>

    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Loan Number:</div>
            <div class="info-value">{{ $loan?->loan_code ?? '—' }}</div>
            <div class="info-label">Payment Method:</div>
            <div class="info-value">{{ ucfirst($payment->payment_method ?? '—') }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Borrower:</div>
            <div class="info-value">{{ $borrowerName }}</div>
            <div class="info-label">Reference:</div>
            <div class="info-value">{{ $payment->reference_number ?? '—' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Processed By:</div>
            <div class="info-value">{{ $payment->user?->name ?? '—' }}</div>
            <div class="info-label">Status:</div>
            <div class="info-value {{ $statusClass }}">{{ ucfirst($payment->status ?? '—') }}</div>
        </div>
    </div>

    <div class="amount-box">
        <div class="amount-label">Total Payment Amount</div>
        <div class="amount-value">{{ number_format((float) $payment->amount, 2) }} {{ config('app.currency', 'TZS') }}</div>
    </div>

    <div class="block-title">Payment Allocation Breakdown</div>
    <table>
        <thead>
            <tr>
                <th style="width: 33%;">Principal Paid</th>
                <th style="width: 33%;">Interest Paid</th>
                <th style="width: 34%;">Penalty Paid</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="num">{{ number_format((float) $principal, 2) }}</td>
                <td class="num">{{ number_format((float) $interest, 2) }}</td>
                <td class="num">{{ number_format((float) $penalty, 2) }}</td>
            </tr>
        </tbody>
        <tfoot style="background: #e9ecef; font-weight: bold;">
            <tr>
                <td colspan="2" class="text-center">Total Allocated</td>
                <td class="num">{{ number_format((float) ($principal + $interest + $penalty), 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if($payment->notes)
        <div class="notes-box">
            <strong>Notes:</strong> {{ $payment->notes }}
        </div>
    @endif

    <div class="signature-section">
        <div class="signature-line">
            <div class="signature-box">
                <strong>Received By</strong><br>
                {{ $payment->user?->name ?? '____________________' }}<br>
                Date: {{ $payment->payment_date?->format('Y-m-d') }}
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
    <strong>Thank you for your payment!</strong><br>
    This receipt was computer-generated and is valid without signature.
</div>

<div class="footer">
    <div class="footer-left">
        <strong>{{ $shopName }}</strong> | Loan Payment Receipt #{{ $payment->id }}
    </div>
    <div class="footer-right">
        Page
    </div>
</div>

</body>
</html>
