@extends('adminlte::page')

@section('title', 'Receipt - ' . $payment->id)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-receipt"></i> Payment Receipt</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-receipt"></i> Payment Receipt</h1>
                    <p class="mb-0 text-light">Receipt #: <strong>{{ $payment->id }}</strong></p>
                </div>
                <a href="{{ route('loan.repayments.history', $payment->loan) }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card" id="print-area">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h4 class="mb-3">Receipt</h4>
                        <div><strong>Receipt Number:</strong> {{ $payment->id }}</div>
                        <div><strong>Loan Number:</strong> {{ $payment->loan?->loan_code ?? '—' }}</div>
                        <div><strong>Borrower:</strong> {{ $payment->loan?->customer?->name ?? '—' }}</div>
                        <div><strong>Officer:</strong> {{ $payment->user?->name ?? '—' }}</div>
                        <div><strong>Date:</strong> {{ $payment->payment_date?->format('Y-m-d') }}</div>
                    </div>
                    <div class="col-md-6 text-right">
                        <h4 class="mb-3">Payment</h4>
                        <div><strong>Payment Amount:</strong> {{ number_format((float) $payment->amount, 2) }}</div>
                        <div><strong>Method:</strong> {{ $payment->payment_method ?? '—' }}</div>
                        <div><strong>Reference:</strong> {{ $payment->reference_number ?? '—' }}</div>
                        <div><strong>Status:</strong> {{ $payment->status }}</div>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-md-12">
                        <h5>Allocation Breakdown</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Principal Paid</th>
                                        <th>Interest Paid</th>
                                        <th>Penalty Paid</th>
                                        <th>Fee Paid</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{ number_format((float) $principal, 2) }}</td>
                                        <td>{{ number_format((float) $interest, 2) }}</td>
                                        <td>{{ number_format((float) $penalty, 2) }}</td>
                                        <td>{{ number_format((float) $fee, 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                @if($payment->notes)
                    <div class="mt-3">
                        <strong>Notes:</strong>
                        <div class="text-muted">{{ $payment->notes }}</div>
                    </div>
                @endif
            </div>
            <div class="card-footer text-right">
                <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

