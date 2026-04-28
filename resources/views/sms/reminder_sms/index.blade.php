@extends('adminlte::page')

@section('title', 'Reminder SMS')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-bell"></i> Send Reminder SMS</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-bell"></i> Reminder SMS</h1>
                <p class="mb-0 text-light">Send SMS reminders to customers based on loan status</p>
            </div>
            <a href="{{ url()->previous() }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.sms_settings.index') }}">SMS Settings</a></li>
            <li class="breadcrumb-item active text-dark" aria-current="page">Reminder SMS</li>
        </ol>
    </nav>
    <a href="{{ route('settings.sms_settings.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>
</div>
@stop

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title">Send Reminder SMS</h6>
                </div>
                <div class="card-body">
                    <form id="reminderForm" method="POST" action="{{ route('sms.reminders.send') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="reminder_type" class="form-label">Reminder Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="reminder_type" name="reminder_type" required>
                                    <option value="">Select Reminder Type</option>
                                    <option value="upcoming">Upcoming Due</option>
                                    <option value="overdue">Overdue</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="days" class="form-label">Days <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="days" name="days" min="1" max="365" placeholder="e.g. 3" required>
                                <small class="text-muted" id="days_help">Enter number of days</small>
                            </div>
                        </div>

                        <div class="row g-3 mt-3">
                            <div class="col-md-12">
                                <label for="template_id" class="form-label">SMS Template <span class="text-danger">*</span></label>
                                <select class="form-select" id="template_id" name="template_id" required>
                                    <option value="">Select Template</option>
                                    @foreach($templates as $template)
                                        <option value="{{ $template->id }}" data-variables="{{ json_encode($template->variables) }}">
                                            {{ $template->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mt-3">
                            <div class="col-md-12">
                                <label class="form-label">Available Variables</label>
                                <div id="variables_help" class="p-2 bg-light rounded">
                                    <span class="text-muted">Select a template to see available variables</span>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-3">
                            <div class="col-md-12">
                                <label class="form-label">Preview</label>
                                <div id="preview_box" class="p-3 bg-white border rounded">
                                    <span class="text-muted">Configure options above and click Preview</span>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-3">
                            <div class="col-md-12">
                                <div id="count_box" class="p-2 bg-info bg-opacity-10 rounded text-info">
                                    <span id="customer_count">0</span> customers will receive this SMS
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-4">
                            <div class="col-md-6">
                                <button type="button" class="btn btn-secondary w-100" id="previewBtn">
                                    <i class="fas fa-eye"></i> Preview
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-primary w-100" id="sendBtn">
                                    <i class="fas fa-paper-plane"></i> Send SMS
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title">Instructions</h6>
                </div>
                <div class="card-body">
                    <h6>Upcoming Due</h6>
                    <p class="text-muted small">Send reminders to customers whose installments are due within the specified number of days.</p>
                    
                    <h6 class="mt-3">Overdue</h6>
                    <p class="text-muted small">Send reminders to customers whose installments are overdue by the specified number of days.</p>

                    <h6 class="mt-3">Templates</h6>
                    <p class="text-muted small">Make sure you have created SMS templates with appropriate variables before sending reminders.</p>
                    
                    <a href="{{ route('sms.templates.index') }}" class="btn btn-outline-primary btn-sm mt-2">
                        <i class="fas fa-cog"></i> Manage Templates
                    </a>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title">Variable Reference</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0"><code>@{{name}}</code> - Customer name</li>
                        <li class="list-group-item px-0"><code>@{{loan_code}}</code> - Loan code</li>
                        <li class="list-group-item px-0"><code>@{{date}}</code> - Due date (DD-MM-YYYY)</li>
                        <li class="list-group-item px-0"><code>@{{amount}}</code> - Amount due</li>
                        <li class="list-group-item px-0"><code>@{{outstanding_balance}}</code> - Outstanding balance</li>
                        <li class="list-group-item px-0"><code>@{{principal_amount}}</code> - Principal amount</li>
                        <li class="list-group-item px-0"><code>@{{overdue_days}}</code> - Days overdue</li>
                        <li class="list-group-item px-0"><code>@{{days_until_due}}</code> - Days until due</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const reminderType = document.getElementById('reminder_type');
    const daysInput = document.getElementById('days');
    const daysHelp = document.getElementById('days_help');
    const templateSelect = document.getElementById('template_id');
    const variablesHelp = document.getElementById('variables_help');
    const previewBox = document.getElementById('preview_box');
    const customerCount = document.getElementById('customer_count');
    const previewBtn = document.getElementById('previewBtn');
    const sendBtn = document.getElementById('sendBtn');

    reminderType.addEventListener('change', function() {
        if (this.value === 'upcoming') {
            daysHelp.textContent = 'Enter number of days before due date (e.g. 3 = 3 days before due)';
        } else if (this.value === 'overdue') {
            daysHelp.textContent = 'Enter number of days overdue (e.g. 2 = 2 days overdue)';
        } else {
            daysHelp.textContent = 'Enter number of days';
        }
        customerCount.textContent = '0';
        previewBox.innerHTML = '<span class="text-muted">Configure options above and click Preview</span>';
    });

    templateSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const variables = selectedOption.dataset.variables ? JSON.parse(selectedOption.dataset.variables) : [];
        
        if (variables && variables.length > 0) {
            variablesHelp.innerHTML = variables.map(v => `<code>@{{${v}}}</code>`).join(', ');
        } else {
            variablesHelp.innerHTML = '<span class="text-muted">No variables defined for this template</span>';
        }
        customerCount.textContent = '0';
        previewBox.innerHTML = '<span class="text-muted">Configure options above and click Preview</span>';
    });

    previewBtn.addEventListener('click', function() {
        const reminderTypeVal = reminderType.value;
        const daysVal = daysInput.value;
        const templateIdVal = templateSelect.value;

        if (!reminderTypeVal || !daysVal || !templateIdVal) {
            alert('Please fill in all required fields');
            return;
        }

        previewBtn.disabled = true;
        previewBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

        fetch('{{ route('sms.reminders.preview') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                reminder_type: reminderTypeVal,
                days: daysVal,
                template_id: templateIdVal
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                previewBox.innerHTML = `<strong>Preview Message:</strong><br><br>${data.preview}`;
                customerCount.textContent = data.count;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while generating preview');
        })
        .finally(() => {
            previewBtn.disabled = false;
            previewBtn.innerHTML = '<i class="fas fa-eye"></i> Preview';
        });
    });

    document.getElementById('reminderForm').addEventListener('submit', function(e) {
        if (!confirm('Are you sure you want to send SMS reminders to the selected customers?')) {
            e.preventDefault();
        }
    });
});
</script>
@endpush
