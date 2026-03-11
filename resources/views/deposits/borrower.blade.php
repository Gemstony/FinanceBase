@extends('adminlte::page')

@section('title', 'Borrower Security Deposits')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-user-shield"></i> Borrower Deposits</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-user-shield"></i> Borrower Deposits</h1>
                    <p class="mb-0 text-light">Borrower: <strong>{{ $customer->name }}</strong></p>
                </div>
                <a href="{{ route('security-deposits.index') }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-striped table-hover" id="borrowerDepositsTable">
                    <thead class="thead-light">
                        <tr>
                            <th>Loan</th>
                            <th class="text-right">Amount</th>
                            <th>Status</th>
                            <th>Held At</th>
                            <th>Released At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deposits as $d)
                            <tr>
                                <td>{{ $d->loan?->loan_code ?? '—' }}</td>
                                <td class="text-right">{{ number_format((float) $d->amount, 2) }}</td>
                                <td>
                                        @php
                                            $cls = match((string) $d->status) {
                                                'held' => 'badge-success',
                                                'applied' => 'badge-info',
                                                'refunded' => 'badge-secondary',
                                                'forfeited' => 'badge-dark',
                                                default => 'badge-light',
                                            };
                                        @endphp
                                        <span class="badge {{ $cls }}">{{ ucfirst($d->status) }}</span>
                                    </td>
                                <td>{{ $d->held_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td>{{ $d->released_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No deposits found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-3">
                    {{ $deposits->links() }}
                </div>
            </div>
        </div>
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@push('js')
<script>
$(document).ready(function() {
    if ($('#borrowerDepositsTable').length) {
        $('#borrowerDepositsTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [] },
                { searchable: false, targets: [] }
            ],
            order: [[3, 'desc']]
        });
    }
});
</script>
@endpush
