@extends('adminlte::page')

@section('title', 'Manual Journal #' . $journal->id)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-book"></i> Manual Journal #{{ $journal->id }}</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-book"></i> Journal #{{ $journal->id }}</h1>
                    <p class="mb-0 text-light">View journal details</p>
                </div>
                <a href="{{ route('accounting.manual-journals.index') }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('accounting.manual-journals.index') }}">Manual Journals</a></li>
                <li class="breadcrumb-item active" aria-current="page">#{{ $journal->id }}</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @php
            $totalDebit = (float) $journal->lines->sum('debit');
            $totalCredit = (float) $journal->lines->sum('credit');
            $isPosted = (int) $journal->reference_id > 0;
            $isDraft = !$isPosted;
            $isReversed = isset($isReversed) ? (bool) $isReversed : false;
        @endphp

        <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
            <div class="card-header"><strong>Journal Header</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="small text-muted">Reference</div>
                        <div><strong>#{{ $journal->id }}</strong></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Date</div>
                        <div><strong>{{ $journal->transaction_date?->format('Y-m-d') ?? '—' }}</strong></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Created By</div>
                        <div><strong>{{ $journal->creator?->name ?? '—' }}</strong></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Status</div>
                        <div>
                            @if($isDraft)
                                <span class="badge badge-warning">Draft</span>
                            @elseif($isPosted)
                                <span class="badge badge-success">Posted</span>
                            @else
                                <span class="badge badge-secondary">{{ $journal->reference_type }}</span>
                            @endif

                            @if($isReversed)
                                <span class="badge badge-danger ml-1">Reversed</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="small text-muted">Description</div>
                    <div>{{ $journal->description ?? '—' }}</div>
                </div>

                <hr>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Account</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($journal->lines as $l)
                                <tr>
                                    <td>{{ $l->account?->account_name ?? '—' }}</td>
                                    <td class="text-right">{{ number_format((float) $l->debit, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $l->credit, 2) }}</td>
                                    <td>{{ $l->description ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="text-right">Totals</th>
                                <th class="text-right">{{ number_format($totalDebit, 2) }}</th>
                                <th class="text-right">{{ number_format($totalCredit, 2) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-3">
                    @if($isPosted)
                        @if(!$isReversed)
                            <form method="POST" action="{{ route('accounting.manual-journals.reverse', $journal->id) }}" class="d-inline js-reverse-manual-journal">
                                @csrf
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-undo"></i> REVERSE JOURNAL
                                </button>
                            </form>
                        @else
                            <button type="button" class="btn btn-secondary" disabled>
                                <i class="fas fa-ban"></i> ALREADY REVERSED
                            </button>
                        @endif
                    @endif
                </div>

                @if($isPosted)
                    <small class="text-muted d-block mt-2">Posted journal id: {{ (int) $journal->reference_id }}</small>
                @endif
            </div>
        </div>
    </div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    $('.js-reverse-manual-journal').on('submit', function (e) {
        e.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Reverse this journal?'
            , text: 'This will create a reversal journal entry. The original will remain for audit.'
            , icon: 'warning'
            , showCancelButton: true
            , confirmButtonColor: '#dc3545'
            , cancelButtonColor: '#6c757d'
            , confirmButtonText: 'Yes, reverse'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
</script>
@endpush
