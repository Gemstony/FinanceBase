@extends('adminlte::page')

@section('title', 'Print Jobs')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-print"></i> Print Jobs</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-print"></i> Jobs</h1>
                <div class="small text-light-50">Shop: {{ isset($subshop) && $subshop ? $subshop->name : '-' }}</div>
            </div>
            @can('view_configure_printer')
            <a href="{{ route('printers.settings.index') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-cog"></i> Printer Settings</a>
            @endcan
        </div>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="h5 mb-0">Recent Jobs</div>
                <div>
                    <button id="refreshJobs" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sync"></i> Refresh</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="PrintJobsTable">
                    <thead class="thead-light">
                        <tr>
                            <th>Job ID</th>
                            <th>Type</th>
                            <th>Doc ID</th>
                            <th>Printer</th>
                            <th>Status</th>
                            <th>Attempts</th>
                            <th>Updated</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(($jobs ?? []) as $j)
                            @php $st = strtolower($j['status'] ?? 'unknown'); @endphp
                            <tr data-job-id="{{ $j['jobId'] ?? '' }}">
                                <td class="small">{{ $j['jobId'] ?? '' }}</td>
                                <td>{{ $j['docType'] ?? '-' }}</td>
                                <td>{{ $j['docId'] ?? '-' }}</td>
                                <td>{{ $j['printerSettingId'] ?? '-' }}</td>
                                <td>
                                    @if($st==='success')
                                        <span class="badge badge-success">Success</span>
                                    @elseif($st==='failed')
                                        <span class="badge badge-danger">Failed</span>
                                    @elseif($st==='running')
                                        <span class="badge badge-info">Running</span>
                                    @elseif($st==='queued')
                                        <span class="badge badge-secondary">Queued</span>
                                    @else
                                        <span class="badge badge-light">Unknown</span>
                                    @endif
                                </td>
                                <td>{{ $j['attempts'] ?? 0 }}</td>
                                <td class="small text-muted">{{ $j['updated_at'] ?? '' }}</td>
                                <td class="text-center">
                                    @if(in_array($st, ['failed','unknown']))
                                    @can('retry_print_jobs')
                                    <button class="btn btn-sm btn-outline-primary retry-job" data-id="{{ $j['jobId'] ?? '' }}"><i class="fas fa-redo"></i> Retry</button>
                                    @endcan
                                    @else
                                    <button class="btn btn-sm btn-outline-secondary" disabled>—</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @if(empty($jobs))
                        <tr><td colspan="8" class="text-center text-muted py-5"><i class="fas fa-inbox"></i> No jobs yet.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
window.DUKA = Object.assign({}, window.DUKA || {}, {
    apiPrintStatus: @json(url('/api/print-jobs/status')),
    apiPrintRetry: @json(url('/api/print-jobs/retry')),
    csrf: @json(csrf_token())
});

function pollStatus(jobId, row){
    if (!jobId) return;
    let attempts = 0, maxAttempts = 20;
    const statusCell = row ? row.querySelector('td:nth-child(5)') : null;
    const attemptsCell = row ? row.querySelector('td:nth-child(6)') : null;
    const doPoll = async () => {
        attempts++;
        try {
            const r = await fetch(window.DUKA.apiPrintStatus, { method:'POST', headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': window.DUKA.csrf }, body: JSON.stringify({ job_id: jobId }) });
            const j = await r.json().catch(()=>({ ok:false }));
            if (!j.ok) throw new Error('status failed');
            const st = (j.status||'').toLowerCase();
            if (statusCell) {
                let badge = '<span class="badge badge-light">Unknown</span>';
                if (st==='success') badge = '<span class="badge badge-success">Success</span>';
                else if (st==='failed') badge = '<span class="badge badge-danger">Failed</span>';
                else if (st==='running') badge = '<span class="badge badge-info">Running</span>';
                else if (st==='queued') badge = '<span class="badge badge-secondary">Queued</span>';
                statusCell.innerHTML = badge;
            }
            if (attemptsCell && typeof j.attempts !== 'undefined') attemptsCell.textContent = j.attempts;
            if (st==='success' || st==='failed') return;
            if (attempts < maxAttempts) setTimeout(doPoll, 2000);
        } catch(e){ if (attempts < maxAttempts) setTimeout(doPoll, 2500); }
    };
    doPoll();
}

document.addEventListener('click', async function(e){
    const btn = e.target.closest('.retry-job');
    if(!btn) return;
    const id = btn.getAttribute('data-id');
    if(!id) return;
    try {
        Swal.fire({ title:'Re-queuing...', allowOutsideClick:false, didOpen: () => Swal.showLoading() });
        const r = await fetch(window.DUKA.apiPrintRetry, { method:'POST', headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': window.DUKA.csrf }, body: JSON.stringify({ job_id: id }) });
        const j = await r.json().catch(()=>({ ok:false }));
        if(!j.ok) throw new Error(j.error || 'Retry failed');
        Swal.fire({ icon:'success', title:'Retry queued', timer:1200, showConfirmButton:false });
        const newId = j.job_id;
        // If row exists, update job id and start polling new id
        const row = btn.closest('tr');
        if (row) {
            row.setAttribute('data-job-id', newId);
            row.querySelector('td:nth-child(1)').textContent = newId;
            const statusCell = row.querySelector('td:nth-child(5)');
            if (statusCell) statusCell.innerHTML = '<span class="badge badge-secondary">Queued</span>';
            pollStatus(newId, row);
        }
    } catch(err){
        Swal.fire({ icon:'error', title:'Retry failed', text: err.message || 'Error' });
    }
});

document.getElementById('refreshJobs')?.addEventListener('click', function(){
    window.location.reload();
});
</script>
@stop
