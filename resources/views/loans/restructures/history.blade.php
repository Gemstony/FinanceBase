@extends('adminlte::page')

@section('title', 'Restructure History - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-history"></i> Restructure History</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-history"></i> Restructure History</h1>
                <p class="mb-0 text-light">Branch: <strong>{{ $subshop->name }}</strong></p>
            </div>
            <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back to Loan
            </a>
        </div>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('loans.management') }}">Loans</a></li>
        <li class="breadcrumb-item"><a href="{{ route('loans.loans.show', $loan) }}">{{ $loan->loan_code }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Restructure History</li>
    </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-3" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Requests</strong>
            <a href="{{ route('loan.restructures.create', $loan) }}" class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> New Request
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>New Term</th>
                            <th>New Rate</th>
                            <th>Status</th>
                            <th>Approved</th>
                            <th>Executed</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $r)
                            @php
                                $badge = match ((string) $r->status) {
                                    'pending' => 'badge-warning',
                                    'approved' => 'badge-info',
                                    'executed' => 'badge-success',
                                    default => 'badge-secondary',
                                };
                            @endphp
                            <tr>
                                <td>{{ (int) $r->id }}</td>
                                <td>{{ $r->restructure_date ? \Carbon\Carbon::parse($r->restructure_date)->format('Y-m-d') : '-' }}</td>
                                <td>{{ (int) ($r->new_term ?? $r->new_term_months ?? 0) }}</td>
                                <td>{{ number_format((float) ($r->new_interest_rate ?? 0), 2) }}%</td>
                                <td><span class="badge {{ $badge }}">{{ $r->status }}</span></td>
                                <td>{{ $r->approved_at ? $r->approved_at->format('Y-m-d H:i') : '-' }}</td>
                                <td>{{ $r->executed_at ? $r->executed_at->format('Y-m-d H:i') : '-' }}</td>
                                <td class="text-right">
                                    @if((string) $r->status === 'approved')
                                        <form method="POST" action="{{ route('loan.restructures.execute', $r) }}" class="d-inline js-restructure-execute-form">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-play"></i> Execute
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">No requests found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $requests->links() }}
            </div>
        </div>
    </div>
</div>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-restructure-execute-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Execute restructure now?',
                text: 'This will generate a new schedule version.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Execute',
                confirmButtonColor: '#dc3545',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (!result.isConfirmed) return;
                form.submit();
            });
        });
    });
});
</script>
@endsection
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
