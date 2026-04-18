<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Customer Details - {{ $customer->name ?? 'Customer' }}</title>
    <style>
        @page { margin: 24px 22px; font-family: 'DejaVu Sans', Arial, sans-serif; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #000; }

        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 12px; }
        .header-left { display: inline-block; vertical-align: top; width: 64%; }
        .header-right { display: inline-block; vertical-align: top; width: 35%; text-align: right; }
        .header-logo { display: inline-block; vertical-align: top; width: 90px; }
        .header-info { display: inline-block; vertical-align: top; width: calc(100% - 95px); }
        .logo { max-width: 85px; max-height: 85px; object-fit: contain; }

        .customer-photo { display: inline-block; vertical-align: top; width: 80px; margin-right: 10px; }
        .customer-photo img { width: 75px; height: 75px; object-fit: cover; border: 1px solid #ccc; border-radius: 4px; }
        .customer-info-header { display: inline-block; vertical-align: top; width: calc(100% - 95px); }

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
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #fff; font-weight: 700; text-align: left; }
        td.num, th.num { text-align: right; }

        .page-break { page-break-before: always; }
        .small { font-size: 9px; }

        .footer { border-top: 1px solid #000; margin-top: 14px; padding-top: 6px; font-size: 8px; }
        .footer-left { display: inline-block; vertical-align: top; width: 70%; }
        .footer-right { display: inline-block; vertical-align: top; width: 29%; text-align: right; }
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

        // Build customer address from components
        $customerAddress = collect([
            $customer->street,
            $customer->house_no ? 'House No: ' . $customer->house_no : null,
            $customer->ward,
            $customer->district,
            $customer->region,
        ])->filter()->implode(', ');

        // Format customer status
        $customerStatus = $customer->is_active ? 'Active' : 'Inactive';

        // Get customer image path for PDF (base64 encoded for DOMPDF compatibility)
        $customerImageBase64 = null;
        if ($customer->customer_image && file_exists(storage_path('app/public/' . $customer->customer_image))) {
            $imagePath = storage_path('app/public/' . $customer->customer_image);
            $imageData = file_get_contents($imagePath);
            $customerImageBase64 = 'data:image/' . pathinfo($imagePath, PATHINFO_EXTENSION) . ';base64,' . base64_encode($imageData);
        }
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
            <div class="report-title">Customer Details Report</div>
            <div class="report-sub">
                <div><strong>Branch:</strong> {{ $branchLabel }}</div>
                <div><strong>Customer:</strong> {{ $customer->name ?? 'N/A' }}</div>
                <div><strong>Generated:</strong> {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
            </div>
        </div>
    </div>

    <div class="block-title">Customer Information</div>
    <div style="margin-bottom: 10px;">
        @if($customerImageBase64)
            <div class="customer-photo">
                <img src="{{ $customerImageBase64 }}" alt="Customer Photo">
            </div>
        @endif
        <div class="customer-info-header">
            <table>
                <tbody>
                    <tr>
                        <td><strong>Customer Code</strong></td>
                        <td>{{ $customer->customer_code ?? 'N/A' }}</td>
                        <td><strong>Full Name</strong></td>
                        <td>{{ $customer->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Email</strong></td>
                        <td>{{ $customer->email ?? 'N/A' }}</td>
                        <td><strong>Phone</strong></td>
                        <td>{{ $customer->phone ?? 'N/A' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <table>
        <tbody>
            <tr>
                <td><strong>Alternative Phone</strong></td>
                <td>{{ $customer->altenative_phone ?? 'N/A' }}</td>
                <td><strong>Gender</strong></td>
                <td>{{ ucfirst($customer->gender) ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Date of Birth</strong></td>
                <td>{{ $customer->birth_date ? \Carbon\Carbon::parse($customer->birth_date)->format('Y-m-d') : 'N/A' }}</td>
                <td><strong>ID Type</strong></td>
                <td>{{ $customer->id_type ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>ID Number</strong></td>
                <td>{{ $customer->id_number ?? 'N/A' }}</td>
                <td><strong>Status</strong></td>
                <td>{{ $customerStatus }}</td>
            </tr>
            <tr>
                <td><strong>Address</strong></td>
                <td colspan="3">{{ $customerAddress ?: 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>District</strong></td>
                <td>{{ $customer->district ?? 'N/A' }}</td>
                <td><strong>Region</strong></td>
                <td>{{ $customer->region ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Work/Occupation</strong></td>
                <td>{{ $customer->work ?? 'N/A' }}</td>
                <td><strong>Work Address</strong></td>
                <td>{{ $customer->work_address ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Category</strong></td>
                <td>{{ $customer->category ?? 'N/A' }}</td>
                <td><strong>Registration Date</strong></td>
                <td>{{ $customer->created_at ? $customer->created_at->format('Y-m-d H:i') : 'N/A' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="block-title">Loan Summary</div>
    <table class="kpi">
        <tr>
            <td>
                <div class="kpi-label">Total Loans</div>
                <div class="kpi-value">{{ number_format($stats['loans_count'] ?? 0) }}</div>
            </td>
            <td>
                <div class="kpi-label">Active Loans</div>
                <div class="kpi-value">{{ number_format($stats['active_loans_count'] ?? 0) }}</div>
            </td>
            <td>
                <div class="kpi-label">Closed Loans</div>
                <div class="kpi-value">{{ number_format($stats['closed_loans_count'] ?? 0) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="kpi-label">Total Disbursed</div>
                <div class="kpi-value">{{ number_format($stats['total_principal'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Total Repaid</div>
                <div class="kpi-value">{{ number_format($stats['total_repaid'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Outstanding Balance</div>
                <div class="kpi-value">{{ number_format($stats['outstanding_balance'] ?? 0, 2) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="kpi-label">Overdue Loans</div>
                <div class="kpi-value">{{ number_format($stats['overdue_loans_count'] ?? 0) }}</div>
            </td>
            <td>
                <div class="kpi-label">Written Off</div>
                <div class="kpi-value">{{ number_format($stats['written_off_count'] ?? 0) }}</div>
            </td>
            <td>
                <div class="kpi-label">Max Days Past Due</div>
                <div class="kpi-value">{{ number_format($stats['max_days_past_due'] ?? 0) }}</div>
            </td>
        </tr>
    </table>

    @if(isset($allLoans) && count($allLoans) > 0)
    <div class="block-title">Loan History</div>
    <div class="small" style="margin-bottom:6px;">
        Showing {{ number_format(count($allLoans)) }} loan(s) for this customer.
    </div>
    <table>
        <thead>
            <tr>
                <th>Loan Code</th>
                <th>Product</th>
                <th class="num">Principal</th>
                <th class="num">Outstanding</th>
                <th>Status</th>
                <th>Disbursed Date</th>
                <th>Maturity Date</th>
                <th class="num">DPD</th>
            </tr>
        </thead>
        <tbody>
            @foreach($allLoans as $loan)
                <tr>
                    <td>{{ $loan->loan_code ?? '' }}</td>
                    <td>{{ $loan->loanProduct->name ?? 'N/A' }}</td>
                    <td class="num">{{ number_format($loan->principal_amount ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($loan->calculated_outstanding ?? 0, 2) }}</td>
                    <td>{{ $loan->status ?? '' }}</td>
                    <td>{{ $loan->disbursement_date ? $loan->disbursement_date->format('Y-m-d') : '' }}</td>
                    <td>{{ $loan->maturity_date ? $loan->maturity_date->format('Y-m-d') : '' }}</td>
                    <td class="num">{{ $loan->days_past_due ?? 0 }}</td>
                </tr>
            @endforeach
            @if(empty($allLoans))
                <tr><td colspan="8" style="text-align:center; color:#777; padding:10px;">No loans found</td></tr>
            @endif
        </tbody>
    </table>
    @endif

    @if(isset($recentTransactions) && count($recentTransactions) > 0)
    <div class="block-title">Recent Transactions</div>
    <div class="small" style="margin-bottom:6px;">
        Showing {{ number_format(count($recentTransactions)) }} recent transaction(s).
    </div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Transaction Code</th>
                <th>Type</th>
                <th class="num">Amount</th>
                <th>Loan Code</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($recentTransactions as $transaction)
                <tr>
                    <td>{{ $transaction->date ?? '' }}</td>
                    <td>{{ $transaction->transaction_code ?? '' }}</td>
                    <td>{{ $transaction->type ?? '' }}</td>
                    <td class="num">{{ number_format($transaction->amount ?? 0, 2) }}</td>
                    <td>{{ $transaction->loan_code ?? '' }}</td>
                    <td>{{ $transaction->status ?? '' }}</td>
                </tr>
            @endforeach
            @if(empty($recentTransactions))
                <tr><td colspan="6" style="text-align:center; color:#777; padding:10px;">No transactions found</td></tr>
            @endif
        </tbody>
    </table>
    @endif

    <div class="footer">
        <div class="footer-left">
            <strong>{{ $shopName }}</strong> | Customer Details Report
        </div>
        <div class="footer-right">
            Page 1
        </div>
    </div>
</body>
</html>
