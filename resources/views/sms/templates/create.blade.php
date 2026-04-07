@extends('adminlte::page')

@section('title', 'Add SMS Template')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-plus-circle"></i> Add SMS Template</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-plus-circle"></i> Add Template</h1>
                <p class="mb-0 text-light">Create a new SMS message template</p>
            </div>
            <a href="{{ route('sms.templates.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
        <li class="breadcrumb-item"><a href="{{ route('settings.sms_settings.index') }}">SMS Settings</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sms.templates.index') }}">SMS Templates</a></li>
        <li class="breadcrumb-item active" aria-current="page">Add Template</li>
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
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Add SMS Template</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('sms.templates.store') }}" method="POST">
                        @csrf
                        

                        
                        <div class="row mb-3">
                            <label for="name" class="col-md-2 col-form-label">Template Name*</label>
                            <div class="col-md-10">
                                <input type="text" name="name" id="name" class="form-control" placeholder="e.g., Loan Disbursement Notification" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="event" class="col-md-2 col-form-label">Event*</label>
                            <div class="col-md-10">
                                <input type="text" name="event" id="event" class="form-control" placeholder="e.g., loan.disbursed" required>
                                <small class="text-muted">Common events: loan.disbursed, payment.received, otp.generated</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="message_template" class="col-md-2 col-form-label">Message Template*</label>
                            <div class="col-md-10">
                                <div class="mb-2">
                                    <small class="text-muted me-2">Quick insert:</small>
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="insertVariable('name')">@{{name}}</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="insertVariable('amount')">@{{amount}}</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="insertVariable('date')">@{{date}}</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="insertVariable('phone')">@{{phone}}</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="insertVariable('loan_code')">@{{loan_code}}</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="insertVariable('reference')">@{{reference}}</button>

                                    <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="insertVariable('outstanding_balance')">@{{outstanding_balance}}</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="insertVariable('principal_amount')">@{{principal_amount}}</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="insertVariable('overdue_days')">@{{overdue_days}}</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="insertVariable('days_until_due')">@{{days_until_due}}</button>
                                </div>
                                <textarea name="message_template" id="message_template" class="form-control" rows="4" placeholder="Hello @{{name}}, your loan of @{{amount}} has been disbursed." required oninput="autoDetectVariables()"></textarea>
                                <small class="text-muted">Use @{{variable_name}} for dynamic content. Variables are auto-detected.</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">Detected Variables</label>
                            <div class="col-md-10">
                                <div id="variables-container" class="d-flex flex-wrap gap-2">
                                    <span class="text-muted" id="no-variables-message">No variables detected yet. Type @{{variable_name}} in the template above.</span>
                                </div>
                                <input type="hidden" name="variables" id="variables-hidden">
                                <small class="text-muted">Variables are automatically detected from your template</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="is_active" class="col-md-2 col-form-label">Status</label>
                            <div class="col-md-10">
                                <div class="form-check">
                                    <input type="hidden" name="is_active" value="0">
                                     <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" checked>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-10 offset-md-2">
                                <button type="button" class="btn btn-outline-primary me-2" onclick="previewTemplate()">
                                    <i class="fas fa-eye"></i> Preview
                                </button>
                                <div id="preview-result" class="mt-2 p-3 bg-light rounded d-none">
                                    <strong>Preview:</strong> <span id="preview-text"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-10 offset-md-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Template
                                </button>
                                <a href="{{ route('sms.templates.index') }}" class="btn btn-secondary">Cancel</a>
                            </div>
                        </div>
     </form>
                 </div>
             </div>
         </div>
     </div>
 </div>
 @endsection

 @push('css')
 <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
 <style>
     .variable-badge {
         display: inline-flex;
         align-items: center;
         padding: 0.25rem 0.5rem;
         background-color: #e7f3ff;
         border: 1px solid #b6d4fe;
         border-radius: 0.25rem;
         font-size: 0.875rem;
         color: #0a58ca;
     }
     .variable-badge .remove-var {
         margin-left: 0.5rem;
         cursor: pointer;
         color: #dc3545;
         font-weight: bold;
     }
     .variable-badge .remove-var:hover {
         color: #a71d2a;
     }
 </style>
 @endpush

 @push('js')
 <script>
     let detectedVariables = new Set();
     
     function insertVariable(varName) {
         const textarea = document.getElementById('message_template');
         const start = textarea.selectionStart;
         const end = textarea.selectionEnd;
         const text = textarea.value;
         const before = text.substring(0, start);
         const after = text.substring(end);
         const insertion = '{' + '{' + varName + '}' + '}';
         
         textarea.value = before + insertion + after;
         textarea.focus();
         textarea.selectionStart = textarea.selectionEnd = start + insertion.length;
         
         autoDetectVariables();
     }
     
     function autoDetectVariables() {
         const template = document.getElementById('message_template').value;
         const variablePattern = /\{\{(\w+)\}\}/g;
         const matches = template.match(variablePattern);
         
         detectedVariables.clear();
         
         if (matches) {
             matches.forEach(match => {
                 const varName = match.replace(/\{\{|\}\}/g, '');
                 detectedVariables.add(varName);
             });
         }
         
         updateVariablesDisplay();
     }
     
     function updateVariablesDisplay() {
         const container = document.getElementById('variables-container');
         const noVarsMessage = document.getElementById('no-variables-message');
         const hiddenInput = document.getElementById('variables-hidden');
         
         if (detectedVariables.size === 0) {
             container.innerHTML = '<span class="text-muted" id="no-variables-message">No variables detected yet. Type @{{variable_name}} in the template above.</span>';
             hiddenInput.value = '';
             return;
         }
         
         let html = '';
         detectedVariables.forEach(varName => {
             html += `<span class="variable-badge">
                 '${varName}'
                 <span class="remove-var" onclick="removeVariable('${varName}')" title="Remove">&times;</span>
             </span>`;
         });
         
         container.innerHTML = html;
         hiddenInput.value = Array.from(detectedVariables).join(',');
     }
     
     function removeVariable(varName) {
         detectedVariables.delete(varName);
         updateVariablesDisplay();
     }
     
     function previewTemplate() {
         const template = document.getElementById('message_template').value;
         const sampleData = {};
         
         detectedVariables.forEach(varName => {
             sampleData[varName] = 'Sample ' + ucfirst(varName);
         });
         
         // Simple template variable replacement for preview
         let preview = template;
         Object.keys(sampleData).forEach(key => {
             preview = preview.replace(new RegExp('{' + '{' + key + '}' + '}', 'g'), sampleData[key]);
         });
         
         document.getElementById('preview-text').textContent = preview;
         document.getElementById('preview-result').classList.remove('d-none');
     }
     
     function ucfirst(string) {
         return string.charAt(0).toUpperCase() + string.slice(1);
     }
     
     // Initialize on page load
     document.addEventListener('DOMContentLoaded', function() {
         autoDetectVariables();
     });
 </script>
 @endpush
