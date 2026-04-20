<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Loan Schedule - {{ $loan->loan_code ?? 'Loan' }}</title>
    <style>
        @page { margin: 20px 15px; font-family: 'DejaVu Sans', Arial, sans-serif; size: A4 landscape; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 8px; color: #000; }

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

        .block-title { font-size: 10px; font-weight: 700; margin: 14px 0 6px 0; text-transform: uppercase; }

        .kpi { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .kpi td { border: 1px solid #000; padding: 7px 6px; }
        .kpi .kpi-label { font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .kpi .kpi-value { font-size: 11px; font-weight: 700; margin-top: 2px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #000; padding: 4px 3px; }
        th { background: #f5f5f5; font-weight: 700; text-align: left; font-size: 7px; }
        td { font-size: 8px; vertical-align: top; }
        td.num, th.num { text-align: right; }
        td.center, th.center { text-align: center; }

        .page-break { page-break-before: always; }
        .small { font-size: 9px; }

        .footer { border-top: 1px solid #000; margin-top: 14px; padding-top: 6px; font-size: 8px; }
        .footer-left { display: inline-block; vertical-align: top; width: 70%; }
        .footer-right { display: inline-block; vertical-align: top; width: 29%; text-align: right; }

        .status-paid { color: #28a745; font-weight: bold; }
        .status-partial { color: #17a2b8; font-weight: bold; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-overdue { color: #dc3545; font-weight: bold; }

        .customer-photo { max-width: 70px; max-height: 70px; object-fit: cover; border: 1px solid #ccc; border-radius: 4px; }
        .summary-box { border: 2px solid #000; padding: 8px 12px; margin: 10px 0; background: #f8f9fa; }
        .summary-title { font-size: 9px; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }
        .summary-value { font-size: 14px; font-weight: 800; color: #dc3545; }
    </style>
</head>
<body>
    @php
        $shopName = $shop->name ?? 'Institution';
        $shopEmail = $shop->email ?? null;
        $shopPhone = $shop->phone ?? null;
        $shopWebsite = $shop->website ?? null;
        $shopAddress = $shop->address ?? null;
        $shopRegion = $shop->region ?? null;
        $shopCountry = $shop->country ?? null;

        $branchLabel = $subshop->name ?? 'All Branches';

        $borrowerName = $loan->borrower_type === 'group'
            ? ($loan->loanGroup?->name ?? 'N/A')
            : ($loan->customer?->name ?? 'N/A');

        $borrowerPhone = $loan->borrower_type === 'group'
            ? ($loan->loanGroup?->phone ?? 'N/A')
            : ($loan->customer?->phone ?? 'N/A');

        $borrowerCode = $loan->borrower_type === 'group'
            ? null
            : ($loan->customer?->customer_code ?? null);

        $borrowerImage = $loan->borrower_type === 'group'
            ? null
            : ($loan->customer?->customer_image ?? null);

        $borrowerImageBase64 = null;
        if ($borrowerImage) {
            $imagePath = public_path('storage/' . ltrim((string) $borrowerImage, '/'));
            if (file_exists($imagePath)) {
                $borrowerImageBase64 = 'data:image/' . pathinfo($imagePath, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($imagePath));
            }
        }

        $outstandingBalance = $outstanding ?? 0;
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
                    @if($shopRegion || $shopCountry)
                        <div>
                            @if($shopRegion)<strong>Region:</strong> {{ $shopRegion }}@endif
                            @if($shopRegion && $shopCountry) | @endif
                            @if($shopCountry)<strong>Country:</strong> {{ $shopCountry }}@endif
                        </div>
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
            <div class="report-title">Loan Installment Schedule</div>
            <div class="report-sub">
                <div><strong>Branch:</strong> {{ $branchLabel }}</div>
                <div><strong>Loan Code:</strong> {{ $loan->loan_code ?? 'N/A' }}</div>
                @if($borrowerCode)
                    <div><strong>Customer Code:</strong> {{ $borrowerCode }}</div>
                @endif
                <div><strong>Generated:</strong> {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
            </div>
        </div>
    </div>

    <table style="width: 100%; margin-bottom: 8px;">
        <tbody>
            <tr>
                <td style="width: 60%; vertical-align: top; border: none; padding: 0;">
                    <div class="block-title">Loan Information</div>
                    <table style="margin-bottom: 0;">
                        <tbody>
                            <tr>
                                <td style="width: 20%;"><strong>Loan Code</strong></td>
                                <td style="width: 30%;">{{ $loan->loan_code ?? 'N/A' }}</td>
                                <td style="width: 20%;"><strong>Product</strong></td>
                                <td style="width: 30%;">{{ $loan->loanProduct?->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Borrower Type</strong></td>
                                <td>{{ ucfirst($loan->borrower_type ?? 'N/A') }}</td>
                                <td><strong>Status</strong></td>
                                <td>{{ ucfirst(str_replace('_', ' ', $loan->status ?? 'N/A')) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Borrower Name</strong></td>
                                <td>{{ $borrowerName }}</td>
                                <td><strong>Phone</strong></td>
                                <td>{{ $borrowerPhone }}</td>
                            </tr>
                            @if($borrowerCode)
                            <tr>
                                <td><strong>Customer Code</strong></td>
                                <td colspan="3">{{ $borrowerCode }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td><strong>Principal Amount</strong></td>
                                <td class="num">{{ number_format((float)$loan->principal_amount, 2) }}</td>
                                <td><strong>Interest Rate</strong></td>
                                <td>{{ number_format((float)$loan->interest_rate, 2) }}%</td>
                            </tr>
                            <tr>
                                <td><strong>Installments</strong></td>
                                <td>{{ $loan->installments ?? 'N/A' }}</td>
                                <td><strong>Repayment Frequency</strong></td>
                                <td>{{ $loan->loanProduct?->repaymentFrequency?->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Disbursement Date</strong></td>
                                <td>{{ $loan->disbursement_date ? \Carbon\Carbon::parse($loan->disbursement_date)->format('Y-m-d') : 'N/A' }}</td>
                                <td><strong>Maturity Date</strong></td>
                                <td>{{ $loan->maturity_date ? \Carbon\Carbon::parse($loan->maturity_date)->format('Y-m-d') : 'N/A' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </td>
                <td style="width: 40%; vertical-align: top; border: none; padding: 0 0 0 15px;">
                    @if($borrowerImageBase64)
                        <div style="text-align: center; margin-bottom: 8px;">
                            <img class="customer-photo" src="{{ $borrowerImageBase64 }}" alt="Customer Photo">
                            @if($borrowerCode)
                                <div style="font-size: 8px; margin-top: 4px; font-weight: 600;">Code: {{ $borrowerCode }}</div>
                            @endif
                        </div>
                    @endif
                    <div class="summary-box" style="text-align: center;">
                        <div class="summary-title">Outstanding Balance</div>
                        <div class="summary-value">{{ number_format((float)$outstandingBalance, 2) }}</div>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    @php
        $grandPrincipalDue = 0;
        $grandInterestDue = 0;
        $grandFeesDue = 0;
        $grandPenaltyDue = 0;
        $grandTotalDue = 0;
        $grandPrincipalPaid = 0;
        $grandInterestPaid = 0;
        $grandFeesPaid = 0;
        $grandPenaltyPaid = 0;
        $grandOutstanding = 0;
    @endphp

    @foreach($installmentsByVersion as $version => $rows)
        @php
            $title = ((int) $version === (int) $latestScheduleVersion) ? 'Current Schedule' : 'Previous Schedule (Restructured)';
            $isCurrent = ((int) $version === (int) $latestScheduleVersion);

            $versionPrincipalDue = 0;
            $versionInterestDue = 0;
            $versionFeesDue = 0;
            $versionPenaltyDue = 0;
            $versionTotalDue = 0;
            $versionPrincipalPaid = 0;
            $versionInterestPaid = 0;
            $versionFeesPaid = 0;
            $versionPenaltyPaid = 0;
            $versionOutstanding = 0;
        @endphp

        @if(!$isCurrent)
            <div class="page-break"></div>
        @endif

        <div style="margin-bottom: 12px;">
            <div class="block-title">{{ $title }} - Version {{ $version }}</div>
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th class="center" rowspan="2" style="width: 3%;">#</th>
                        <th rowspan="2" style="width: 8%;">Due Date</th>
                        <th class="center" colspan="5" style="background:#e8e8e8;">DUE AMOUNTS</th>
                        <th class="center" colspan="4" style="background:#d4edda;">PAID AMOUNTS</th>
                        <th class="center" rowspan="2" style="width: 8%; background:#fff3cd;">Balance</th>
                        <th class="center" rowspan="2" style="width: 6%;">Status</th>
                    </tr>
                    <tr>
                        <th class="num" style="width: 8%; background:#f0f0f0;">Principal</th>
                        <th class="num" style="width: 7%; background:#f0f0f0;">Interest</th>
                        <th class="num" style="width: 6%; background:#f0f0f0;">Fees</th>
                        <th class="num" style="width: 7%; background:#f0f0f0;">Penalty</th>
                        <th class="num" style="width: 8%; background:#e0e0e0; font-weight:800;">Total Due</th>
                        <th class="num" style="width: 8%; background:#e8f5e9;">Principal</th>
                        <th class="num" style="width: 7%; background:#e8f5e9;">Interest</th>
                        <th class="num" style="width: 6%; background:#e8f5e9;">Fees</th>
                        <th class="num" style="width: 7%; background:#e8f5e9;">Penalty</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $i)
                        @php
                            $versionPrincipalDue += (float)$i->principal_due;
                            $versionInterestDue += (float)$i->interest_due;
                            $versionFeesDue += (float)$i->fees_due;
                            $versionPenaltyDue += (float)$i->penalty_due;
                            $versionTotalDue += (float)$i->total_due;
                            $versionPrincipalPaid += (float)$i->principal_paid;
                            $versionInterestPaid += (float)$i->interest_paid;
                            $versionFeesPaid += (float)$i->fees_paid;
                            $versionPenaltyPaid += (float)$i->penalty_paid;
                            $versionOutstanding += (float)$i->total_outstanding;

                            $grandPrincipalDue += (float)$i->principal_due;
                            $grandInterestDue += (float)$i->interest_due;
                            $grandFeesDue += (float)$i->fees_due;
                            $grandPenaltyDue += (float)$i->penalty_due;
                            $grandTotalDue += (float)$i->total_due;
                            $grandPrincipalPaid += (float)$i->principal_paid;
                            $grandInterestPaid += (float)$i->interest_paid;
                            $grandFeesPaid += (float)$i->fees_paid;
                            $grandPenaltyPaid += (float)$i->penalty_paid;
                            $grandOutstanding += (float)$i->total_outstanding;

                            $statusClass = match((string) $i->status) {
                                'paid' => 'status-paid',
                                'partial' => 'status-partial',
                                'pending' => 'status-pending',
                                'overdue' => 'status-overdue',
                                default => '',
                            };
                        @endphp
                        <tr>
                            <td class="center">{{ $i->installment_number }}</td>
                            <td>{{ $i->due_date ? \Carbon\Carbon::parse($i->due_date)->format('Y-m-d') : '-' }}</td>
                            <td class="num">{{ number_format((float)$i->principal_due, 2) }}</td>
                            <td class="num">{{ number_format((float)$i->interest_due, 2) }}</td>
                            <td class="num">{{ number_format((float)$i->fees_due, 2) }}</td>
                            <td class="num">{{ number_format((float)$i->penalty_due, 2) }}</td>
                            <td class="num" style="font-weight:600; background:#f9f9f9;">{{ number_format((float)$i->total_due, 2) }}</td>
                            <td class="num">{{ number_format((float)$i->principal_paid, 2) }}</td>
                            <td class="num">{{ number_format((float)$i->interest_paid, 2) }}</td>
                            <td class="num">{{ number_format((float)$i->fees_paid, 2) }}</td>
                            <td class="num">{{ number_format((float)$i->penalty_paid, 2) }}</td>
                            <td class="num" style="font-weight:600;">{{ number_format((float)$i->total_outstanding, 2) }}</td>
                            <td class="center {{ $statusClass }}">{{ ucfirst($i->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="center" style="color:#777; padding:10px;">No installments found.</td></tr>
                    @endforelse
                </tbody>
                @if($rows->count() > 0)
                <tfoot style="background: #e9ecef; font-weight: bold; font-size: 7px;">
                    <tr>
                        <td colspan="2" class="center">TOTALS V{{ $version }}</td>
                        <td class="num">{{ number_format($versionPrincipalDue, 2) }}</td>
                        <td class="num">{{ number_format($versionInterestDue, 2) }}</td>
                        <td class="num">{{ number_format($versionFeesDue, 2) }}</td>
                        <td class="num">{{ number_format($versionPenaltyDue, 2) }}</td>
                        <td class="num" style="background:#d0d0d0;">{{ number_format($versionTotalDue, 2) }}</td>
                        <td class="num">{{ number_format($versionPrincipalPaid, 2) }}</td>
                        <td class="num">{{ number_format($versionInterestPaid, 2) }}</td>
                        <td class="num">{{ number_format($versionFeesPaid, 2) }}</td>
                        <td class="num">{{ number_format($versionPenaltyPaid, 2) }}</td>
                        <td class="num" style="background:#ffeaa7;">{{ number_format($versionOutstanding, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    @endforeach

    <div class="footer">
        <div class="footer-left">
            <strong>{{ $shopName }}</strong> | Loan Installment Schedule Report
        </div>
        <div class="footer-right">
            Page 
        </div>
    </div>
</body>
</html>
