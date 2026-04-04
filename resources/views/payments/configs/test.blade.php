@extends('adminlte::page')

@section('title', 'Test Payment Provider')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-vial"></i> Test Payment Provider</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-vial"></i> Test Provider</h1>
                <p class="mb-0 text-light">Verify provider connectivity and credentials</p>
            </div>
            <a href="{{ route('payments.configs') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.payment_settings.index') }}">Payment Settings</a></li>
            <li class="breadcrumb-item"><a href="{{ route('payments.configs') }}">Providers</a></li>
            <li class="breadcrumb-item active text-dark" aria-current="page">Test</li>
        </ol>
    </nav>
</div>
@stop

@section('content')
<div class="container-fluid">
    <div class="row">
        {{-- Left Column: Test Form --}}
        <div class="col-lg-5">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-plug mr-1"></i> Select Provider to Test</h3>
                </div>
                <div class="card-body">
                    @if($configs->isEmpty())
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            No active payment providers configured.
                            <a href="{{ route('payments.configs.create') }}">Add a provider</a> first.
                        </div>
                    @else
                        <form id="testForm">
                            @csrf

                            <div class="form-group">
                                <label for="config_id">
                                    <i class="fas fa-plug mr-1"></i> Provider <span class="text-danger">*</span>
                                </label>
                                <select name="config_id" id="config_id" class="form-control" required onchange="toggleFields()">
                                    <option value="">-- Select Provider --</option>
                                    @foreach($configs as $config)
                                        @php
                                            $badgeClass = match($config->provider) {
                                                'mpesa' => 'success',
                                                'airtel' => 'danger',
                                                'tigo' => 'info',
                                                'clickpesa' => 'primary',
                                                'azampay' => 'warning',
                                                default => 'secondary',
                                            };
                                        @endphp
                                        <option value="{{ $config->id }}" data-provider="{{ $config->provider }}" data-env="{{ $config->environment }}">
                                            {{ ucfirst($config->provider) }} ({{ ucfirst($config->environment) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- AzamPay specific fields (hidden by default, shown when AzamPay selected) --}}
                            <div id="azampay-hint" class="alert alert-info" style="display:none;">
                                <i class="fas fa-info-circle mr-1"></i>
                                <strong>AzamPay Test:</strong> Only tests authentication.
                                <hr class="my-2">
                                <small>Make sure you have configured:</small>
                                <ul class="mb-0 mt-1">
                                    <li>Client ID</li>
                                    <li>Client Secret</li>
                                    <li>App Name</li>
                                    <li>Base URL (sandbox or live)</li>
                                </ul>
                            </div>

                            {{-- M-Pesa/Airtel/Tigo fields --}}
                            <div id="other-fields">
                                <div class="form-group">
                                    <label for="amount">
                                        <i class="fas fa-coins mr-1"></i> Amount (TZS)
                                    </label>
                                    <input type="number" name="amount" id="amount" class="form-control" value="100" min="1" max="999999">
                                </div>

                                <div class="form-group">
                                    <label for="phone_number">
                                        <i class="fas fa-phone mr-1"></i> Phone Number
                                    </label>
                                    <input type="text" name="phone_number" id="phone_number" class="form-control" value="255000000000" placeholder="255XXXXXXXXX">
                                </div>

                                <div class="form-group">
                                    <label for="channel">
                                        <i class="fas fa-exchange-alt mr-1"></i> Channel
                                    </label>
                                    <select name="channel" id="channel" class="form-control">
                                        <option value="stk">STK Push (Collection)</option>
                                        <option value="b2c">B2C (Disbursement)</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" id="testBtn" class="btn btn-primary btn-block">
                                <i class="fas fa-play mr-1"></i> Test Connection
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Info Box --}}
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Testing Guide</h3>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Provider</th>
                                <th>What Gets Tested</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-warning">AzamPay</span></td>
                                <td>Authentication (OAuth token)</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-success">M-Pesa</span></td>
                                <td>OAuth + STK Push</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-danger">Airtel</span></td>
                                <td>OAuth + Payment request</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-info">Tigo</span></td>
                                <td>OAuth + Collection request</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-primary">ClickPesa</span></td>
                                <td>OAuth + STK Push</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mb-0 text-muted small">
                        <i class="fas fa-lock mr-1"></i>
                        Tests run in sandbox mode. No real money is transferred.
                    </p>
                </div>
            </div>
        </div>

        {{-- Right Column: Results --}}
        <div class="col-lg-7">
            {{-- Live Result --}}
            <div class="card card-outline" id="resultCard" style="display:none;">
                <div class="card-header" id="resultHeader">
                    <h3 class="card-title"><i class="fas fa-clipboard-check mr-1"></i> Test Result</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3" id="resultStatus"></div>

                    <table class="table table-bordered table-sm mb-3" id="resultDetails" style="display:none;">
                        <tr>
                            <th width="140">Provider</th>
                            <td id="resultProvider"></td>
                        </tr>
                        <tr>
                            <th>Environment</th>
                            <td id="resultEnvironment"></td>
                        </tr>
                        <tr>
                            <th>Message</th>
                            <td id="resultMessage"></td>
                        </tr>
                    </table>

                    <div id="debugSection" style="display:none;">
                        <a href="#" id="toggleDebug" class="text-primary">
                            <i class="fas fa-chevron-down mr-1"></i> Show Technical Details
                        </a>
                        <pre id="debugResponse" class="bg-light p-3 mt-2" style="display:none; font-size: 11px; max-height: 250px; overflow-y: auto;"></pre>
                    </div>
                </div>
            </div>

            {{-- Loading --}}
            <div class="card card-outline card-secondary" id="loadingCard" style="display:none;">
                <div class="card-body text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="sr-only">Testing...</span>
                    </div>
                    <p class="text-muted mb-0">Testing connection...</p>
                </div>
            </div>

            {{-- Recent Tests --}}
            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history mr-1"></i> Test History</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Provider</th>
                                    <th>Status</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentTests as $log)
                                    <tr>
                                        <td class="text-nowrap">{{ $log->created_at->format('M d, H:i') }}</td>
                                        <td><span class="badge badge-secondary">{{ ucfirst($log->provider) }}</span></td>
                                        <td>
                                            @if($log->status === 'success')
                                                <span class="badge badge-success"><i class="fas fa-check"></i> OK</span>
                                            @else
                                                <span class="badge badge-danger"><i class="fas fa-times"></i> Failed</span>
                                            @endif
                                        </td>
                                        <td class="text-truncate" style="max-width: 200px;" title="{{ $log->message }}">
                                            {{ Str::limit($log->message, 50) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">No tests yet</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<style>
    .card { border-top: 3px solid #007bff; }
</style>
@endpush

@section('js')
<script>
function toggleFields() {
    const select = document.getElementById('config_id');
    const option = select.options[select.selectedIndex];
    const provider = option ? option.dataset.provider : '';
    
    const azampayHint = document.getElementById('azampay-hint');
    const otherFields = document.getElementById('other-fields');
    const amountInput = document.getElementById('amount');
    const phoneInput = document.getElementById('phone_number');
    const channelInput = document.getElementById('channel');
    
    if (provider === 'azampay') {
        azampayHint.style.display = 'block';
        otherFields.style.display = 'none';
        amountInput.removeAttribute('required');
        phoneInput.removeAttribute('required');
        channelInput.removeAttribute('required');
    } else {
        azampayHint.style.display = 'none';
        otherFields.style.display = 'block';
        amountInput.setAttribute('required', 'required');
        phoneInput.setAttribute('required', 'required');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('testForm');
    if (!form) return;

    const testBtn = document.getElementById('testBtn');
    const resultCard = document.getElementById('resultCard');
    const loadingCard = document.getElementById('loadingCard');
    const resultHeader = document.getElementById('resultHeader');
    const resultStatus = document.getElementById('resultStatus');
    const resultDetails = document.getElementById('resultDetails');
    const resultProvider = document.getElementById('resultProvider');
    const resultEnvironment = document.getElementById('resultEnvironment');
    const resultMessage = document.getElementById('resultMessage');
    const debugSection = document.getElementById('debugSection');
    const debugResponse = document.getElementById('debugResponse');
    const toggleDebug = document.getElementById('toggleDebug');

    toggleDebug.addEventListener('click', function (e) {
        e.preventDefault();
        const isVisible = debugResponse.style.display !== 'none';
        debugResponse.style.display = isVisible ? 'none' : 'block';
        toggleDebug.innerHTML = isVisible
            ? '<i class="fas fa-chevron-down mr-1"></i> Show Technical Details'
            : '<i class="fas fa-chevron-up mr-1"></i> Hide Technical Details';
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        resultCard.style.display = 'none';
        loadingCard.style.display = 'block';
        testBtn.disabled = true;
        testBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Testing...';

        const formData = new FormData(form);

        fetch('{{ route("payments.configs.test.submit") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': formData.get('_token'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                _token: formData.get('_token'),
                config_id: formData.get('config_id'),
                amount: formData.get('amount'),
                phone_number: formData.get('phone_number'),
                channel: formData.get('channel'),
            }),
        })
        .then(response => response.json().then(data => ({ ok: response.ok, data })))
        .then(({ ok, data }) => {
            loadingCard.style.display = 'none';
            resultCard.style.display = 'block';

            if (data.success) {
                resultHeader.className = 'card-header bg-success';
                resultStatus.innerHTML = '<span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i> Connection Successful</span>';
            } else {
                resultHeader.className = 'card-header bg-danger';
                resultStatus.innerHTML = '<span class="badge badge-danger"><i class="fas fa-times-circle mr-1"></i> Connection Failed</span>';
            }

            resultProvider.textContent = data.provider ? data.provider.charAt(0).toUpperCase() + data.provider.slice(1) : '-';
            resultEnvironment.textContent = data.environment ? data.environment.toUpperCase() : '-';
            resultMessage.textContent = data.message || 'No details';
            resultDetails.style.display = 'table';

            if (data.provider_response && Object.keys(data.provider_response).length > 0) {
                debugSection.style.display = 'block';
                debugResponse.textContent = JSON.stringify(data.provider_response, null, 2);
                debugResponse.style.display = 'none';
                toggleDebug.innerHTML = '<i class="fas fa-chevron-down mr-1"></i> Show Technical Details';
            } else {
                debugSection.style.display = 'none';
            }
        })
        .catch(error => {
            loadingCard.style.display = 'none';
            resultCard.style.display = 'block';
            resultHeader.className = 'card-header bg-danger';
            resultStatus.innerHTML = '<span class="badge badge-danger"><i class="fas fa-exclamation-triangle mr-1"></i> Request Error</span>';
            resultMessage.textContent = error.message || 'Network error';
            resultDetails.style.display = 'table';
            debugSection.style.display = 'none';
        })
        .finally(() => {
            testBtn.disabled = false;
            testBtn.innerHTML = '<i class="fas fa-play mr-1"></i> Test Connection';
        });
    });
});
</script>
@stop