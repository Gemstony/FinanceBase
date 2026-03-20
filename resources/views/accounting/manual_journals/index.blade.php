@extends('adminlte::page')

@section('title', 'Manual Journals')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-book"></i> Manual Journals</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-book"></i> Manual Journals</h1>
                    <p class="mb-0 text-light">Create and post manual journal entries</p>
                </div>
                <div>
                    <a href="{{ route('accounting.manual-journals.create') }}" class="btn btn-success border">
                        <i class="fas fa-plus"></i> New Journal
                    </a>

                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('accounting.accounting_settings.index') }}">Accounting</a></li>
                <li class="breadcrumb-item active" aria-current="page">Manual Journals</li>
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

        <div class="card shadow-sm border-0 mb-3" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
            <div class="card-body">
                <div class="row">
                    <div class="col-6 col-md-3">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3>{{ $totalCount ?? 0 }}</h3>
                                <p>Total</p>
                            </div>
                            <div class="icon"><i class="fas fa-book"></i></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3>{{ $draftCount ?? 0 }}</h3>
                                <p>Draft</p>
                            </div>
                            <div class="icon"><i class="fas fa-edit"></i></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3>{{ $postedCount ?? 0 }}</h3>
                                <p>Posted</p>
                            </div>
                            <div class="icon"><i class="fas fa-check"></i></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3>{{ $reversedCount ?? 0 }}</h3>
                                <p>Reversed</p>
                            </div>
                            <div class="icon"><i class="fas fa-undo"></i></div>
                        </div>
                    </div>
                </div>

                <form method="GET" action="{{ route('accounting.manual-journals.index') }}" class="mb-0">
                    <div class="bg-light p-2 rounded border">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-2">
                                <label class="small mb-1">Reference</label>
                                <input type="text" name="reference" class="form-control" value="{{ request('reference') }}" placeholder="#ID">
                            </div>
                            <div class="form-group col-md-3">
                                <label class="small mb-1">Description</label>
                                <input type="text" name="description" class="form-control" value="{{ request('description') }}" placeholder="Description">
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All</option>
                                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                                    <option value="posted" @selected(request('status') === 'posted')>Posted</option>
                                    <option value="reversed" @selected(request('status') === 'reversed')>Reversed</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">Created By</label>
                                <select name="created_by" class="form-control">
                                    <option value="">All</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}" @selected((string) request('created_by') === (string) $u->id)>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-1">
                                <label class="small mb-1">From</label>
                                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                            </div>
                            <div class="form-group col-md-1">
                                <label class="small mb-1">To</label>
                                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>
                            <div class="form-group col-md-1">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter"></i> Apply
                                </button>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('accounting.manual-journals.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="manualJournalsTable">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Reference</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Created By</th>
                                <th class="text-right">Total Debit</th>
                                <th class="text-right">Total Credit</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $counter = 1;
                            @endphp
                            @forelse($journals as $j)
                                @php
                                    $totalDebit = (float) $j->lines->sum('debit');
                                    $totalCredit = (float) $j->lines->sum('credit');
                                    $isPosted = (int) $j->reference_id > 0;
                                    $isDraft = !$isPosted;
                                    $isReversed = isset($reversedDraftIds) && in_array((int) $j->id, $reversedDraftIds, true);
                                @endphp
                                <tr>
                                    <td>{{ $counter++ }}</td>
                                    <td>#{{ $j->id }}</td>
                                    <td>{{ $j->transaction_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td>{{ $j->description ?? '—' }}</td>
                                    <td>{{ $j->creator?->name ?? '—' }}</td>
                                    <td class="text-right">{{ number_format($totalDebit, 2) }}</td>
                                    <td class="text-right">{{ number_format($totalCredit, 2) }}</td>
                                    <td>
                                        @if($isReversed)
                                            <span class="badge badge-danger">Reversed</span>
                                        @elseif($isDraft)
                                            <span class="badge badge-warning">Draft</span>
                                        @elseif($isPosted)
                                            <span class="badge badge-success">Posted</span>
                                        @else
                                            <span class="badge badge-secondary">{{ $j->reference_type }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('accounting.manual-journals.show', $j->id) }}">
                                            <i class="fas fa-eye"></i> View
                                        </a>

                                        @if($isPosted && !$isReversed)
                                            <form method="POST" action="{{ route('accounting.manual-journals.reverse', $j->id) }}" class="d-inline js-reverse-manual-journal">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-undo"></i> Reverse
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No manual journals found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $journals->links() }}
                </div>
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

    // Initialize DataTable with guard against double init
    var $mjTable = $('#manualJournalsTable');
    if ($.fn.DataTable && $.fn.DataTable.isDataTable($mjTable)) {
        $mjTable.DataTable().clear().destroy();
    }
    if ($.fn.DataTable) {
        $mjTable.DataTable({
            "order": [],
            "pageLength": 16,
            "language": {
                "search": "Search journals:",
                "lengthMenu": "Show _MENU_ journals per page",
                "zeroRecords": "No journals found",
                "emptyTable": "No journals available",
                "info": "Showing _START_ to _END_ of _TOTAL_ journals",
                "infoEmpty": "No journals available",
                "infoFiltered": "(filtered from _MAX_ total journals)"
            }
        });
    }
});
</script>
@endpush
