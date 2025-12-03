@extends('adminlte::page')

@section('title', 'SMS Management')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body text-center">
            <h1 class="d-none d-md-block text-light"><i class="fas fa-sms text-warning"></i> <strong>DB</strong> SMS Management Panel</h1>
            <h1 class="d-md-none text-light"><i class="fas fa-sms text-warning"></i> <strong>DB</strong> SMS</h1>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item active text-dark d-none d-md-inline" aria-current="page">SMS Management Panel</li>
                <li class="breadcrumb-item active text-dark d-md-none" aria-current="page">SMS</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('sms.management.index') }}" id="filtersForm">
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="shop_id">Shop</label>
                                <select name="shop_id" id="shop_id" class="form-control">
                                    <option value="">All Shops</option>
                                    @foreach($shops as $shop)
                                        <option value="{{ $shop->id }}" {{ (string)$shop->id === (string)($filters['shop_id'] ?? '') ? 'selected' : '' }}>
                                            {{ $shop->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="subshop_id">Subshop</label>
                                <select name="subshop_id" id="subshop_id" class="form-control">
                                    <option value="">All Subshops</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="status">Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">All</option>
                                    <option value="sent" {{ ($filters['status'] ?? '') === 'sent' ? 'selected' : '' }}>Sent</option>
                                    <option value="queued" {{ ($filters['status'] ?? '') === 'queued' ? 'selected' : '' }}>Queued</option>
                                    <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
                                    <option value="error" {{ ($filters['status'] ?? '') === 'error' ? 'selected' : '' }}>Error</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="type">Type</label>
                                <select name="type" id="type" class="form-control">
                                    <option value="">All</option>
                                    @foreach($types as $t)
                                        <option value="{{ $t }}" {{ ($filters['type'] ?? '') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="date_from">From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                            </div>
                            <div class="form-group col-md-2">
                                <label for="date_to">To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-search"></i> Apply</button>
                            <a href="{{ route('sms.management.index') }}" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3 col-6 mb-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $summary['total'] }}</h3>
                    <p>Total SMS</p>
                </div>
                <div class="icon">
                    <i class="fas fa-sms"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $summary['sent'] }}</h3>
                    <p>Sent</p>
                </div>
                <div class="icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $summary['queued'] }}</h3>
                    <p>Queued</p>
                </div>
                <div class="icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $summary['failed'] + $summary['errors'] }}</h3>
                    <p>Failed / Errors</p>
                </div>
                <div class="icon">
                    <i class="fas fa-times"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-2">
        <div class="col-12">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                    <h5 class="mb-0"><i class="fas fa-list"></i> SMS Logs</h5>
                </div>
                <div class="card-body">
                    @if($logs->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="smsLogsTable">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Shop</th>
                                        <th>Subshop</th>
                                        <th>Owner</th>
                                        <th>Phone</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Provider</th>
                                        <th>Message</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($logs as $log)
                                        <tr>
                                            <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                                            <td>{{ $log->shop->name ?? '—' }}</td>
                                            <td>{{ $log->subshop->name ?? '—' }}</td>
                                            <td>{{ $log->owner->name ?? '—' }}</td>
                                            <td>{{ $log->phone }}</td>
                                            <td>{{ $log->type ? ucfirst($log->type) : '—' }}</td>
                                            <td>
                                                @php
                                                    $badge = match($log->status){
                                                        'sent' => 'success',
                                                        'queued' => 'warning',
                                                        'failed' => 'danger',
                                                        'error' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                @endphp
                                                <span class="badge badge-{{ $badge }}">{{ ucfirst($log->status) }}</span>
                                            </td>
                                            <td>{{ strtoupper($log->provider) }}</td>
                                            <td>{{ Str::limit($log->message, 80) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($logs->hasPages())
                        <div class="d-flex justify-content-center mt-3">
                            {{ $logs->links() }}
                        </div>
                        @endif
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-sms fa-3x text-muted mb-3"></i>
                            <h5>No SMS logs found</h5>
                            <p class="text-muted">Adjust filters or try again later.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@stop

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@section('js')
<script>
    @php
        $shopsData = $shops->map(function($s){
            return [
                'id' => $s->id,
                'name' => $s->name,
                'subshops' => ($s->subShops ? $s->subShops->map(function($ss){
                    return ['id' => $ss->id, 'name' => $ss->name];
                })->values()->all() : [])
            ];
        })->values()->all();
    @endphp
    const shopsData = @json($shopsData);

    function populateSubshops() {
        const shopId = document.getElementById('shop_id').value;
        const subshopSelect = document.getElementById('subshop_id');
        const selected = '{{ $filters['subshop_id'] ?? '' }}';
        subshopSelect.innerHTML = '<option value="">All Subshops</option>';
        if (!shopId) return;
        const shop = shopsData.find(s => String(s.id) === String(shopId));
        if (!shop) return;
        shop.subshops.forEach(ss => {
            const opt = document.createElement('option');
            opt.value = ss.id;
            opt.textContent = ss.name;
            if (String(selected) === String(ss.id)) opt.selected = true;
            subshopSelect.appendChild(opt);
        });
    }

    document.getElementById('shop_id').addEventListener('change', function() {
        document.getElementById('subshop_id').value = '';
        populateSubshops();
    });

    document.addEventListener('DOMContentLoaded', function(){
        populateSubshops();
        if (window.$ && $.fn.DataTable) {
            $('#smsLogsTable').DataTable({
                paging: false,
                searching: false,
                info: false,
                order: []
            });
        }
    });
</script>
@stop