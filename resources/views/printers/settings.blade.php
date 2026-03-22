@extends('adminlte::page')

@section('title', 'Printer Settings')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-cog"></i> Printer Settings</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-cog"></i> Printers</h1>
      <div class="small text-light-50">Branch: {{ isset($subshop) && $subshop ? $subshop->name : '-' }}</div>
    </div>
    @can('view_print_jobs')
    <a href="{{ route('printers.jobs.index') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-print"></i> Print Jobs</a>
    @endcan
  </div>
 </div>
     <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">Printer Settings</li>
            </ol>
        </nav>
        <a href="{{ route('settings.general_settings.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>

    </div>
@endsection

@section('content')
@php
  $subshopName = isset($subshop) ? $subshop->name : '';
@endphp
<div class="row">
  <div class="col-md-5">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Add Printer ({{ $subshopName }})</h3>
      </div>
      <form method="POST" action="{{ route('printers.settings.store') }}" id="addPrinterForm">
        @csrf
        <div class="card-body">
          @if ($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif
          @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif
          @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
          @endif

          <div class="form-group">
            <label for="name">Name (optional)</label>
            <input type="text" name="name" id="name" class="form-control" placeholder="Front Desk Printer">
          </div>
          <div class="form-group">
            <label for="ip_address">IP Address</label>
            <div class="input-group">
              <input type="text" name="ip_address" id="ip_address" class="form-control" placeholder="192.168.1.50" required>
              <div class="input-group-append">
                <button type="button" id="btnTest" class="btn btn-outline-secondary">
                  <i class="fas fa-plug"></i> Test
                </button>
              </div>
            </div>
            <small class="form-text text-muted">The network IP of your ESC/POS printer.</small>
          </div>
          <div class="form-group">
            <label for="port">Port</label>
            <input type="number" name="port" id="port" class="form-control" value="9100" min="1" max="65535" required>
          </div>
          <div class="form-group form-check">
            <input type="checkbox" name="is_default" id="is_default" value="1" class="form-check-input">
            <label class="form-check-label" for="is_default">Set as default printer</label>
          </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
          @can('add_printer')
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save</button>
          @endcan

          @can('test_printer')
          <div class="d-flex align-items-center">
            <div class="custom-control custom-checkbox mr-3">
              <input type="checkbox" class="custom-control-input" id="dummy_mode_checkbox">
              <label class="custom-control-label" for="dummy_mode_checkbox">Test Mode</label>
            </div>
            <div class="btn-group">
              <button type="button" id="btnTestDefaultPrint" class="btn btn-outline-success btn-sm"><i class="fas fa-receipt"></i> Test Print</button>
              <button type="button" id="btnAutoDetect" class="btn btn-outline-info btn-sm"><i class="fas fa-search"></i> Auto-detect</button>
            </div>
          </div>
          @endcan
        </div>
      </form>
    </div>

    <div class="card d-none" id="autoDetectCard">
      <div class="card-header">
        <h3 class="card-title">Detected Printers</h3>
      </div>
      <div class="card-body">
        <div id="detectedList" class="list-group small"></div>
        <div id="detectHint" class="text-muted small mt-2">Click a detected printer to populate the form.</div>
      </div>
    </div>
  </div>

<!-- Dummy Preview Modal -->
<div class="modal fade" id="dummyPreviewModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-file-code mr-1"></i>Test Output (Dummy Mode)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">ESC/POS data generated successfully.</p>
        <p class="small text-muted" id="dummyMeta"></p>
        <a id="dummyDownload" href="#" download="test-receipt.bin" class="btn btn-sm btn-primary"><i class="fas fa-download"></i> Download Raw Data</a>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

  <div class="col-md-7">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Registered Printers</h3>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table id="printersTable" class="table table-striped mb-0">
            <thead>
              <tr>
                <th>Name</th>
                <th>IP</th>
                <th>Port</th>
                <th>Default</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($printers as $p)
                <tr>
                  <td>{{ $p->name ?? '-' }}</td>
                  <td>{{ $p->ip_address }}</td>
                  <td>{{ $p->port }}</td>
                  <td>
                    @if($p->is_default)
                      <span class="badge badge-success">Default</span>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td class="text-nowrap">
                    @can('edit_printer')
                    <button class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#editPrinterModal{{ $p->id }}">
                      <i class="fas fa-edit"></i>
                    </button>
                    @endcan
                    @can('test_printer')
                    <button class="btn btn-sm btn-outline-success btnTestPrint" data-printer-id="{{ $p->id }}" title="Test print to this printer">
                      <i class="fas fa-print"></i>
                    </button>
                    @endcan
                    @can('delete_printer')
                    <form method="POST" action="{{ route('printers.settings.destroy', $p) }}" class="d-inline" onsubmit="return confirm('Delete this printer?')">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                    @endcan
                  </td>
                </tr>

                <div class="modal fade" id="editPrinterModal{{ $p->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <form method="POST" action="{{ route('printers.settings.update', $p) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                          <h5 class="modal-title">Edit Printer</h5>
                          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                          <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control" value="{{ $p->name }}">
                          </div>
                          <div class="form-group">
                            <label>IP Address</label>
                            <input type="text" name="ip_address" class="form-control" value="{{ $p->ip_address }}" required>
                          </div>
                          <div class="form-group">
                            <label>Port</label>
                            <input type="number" name="port" class="form-control" value="{{ $p->port }}" min="1" max="65535" required>
                          </div>
                          <div class="form-group form-check">
                            <input type="checkbox" name="is_default" id="is_default_{{ $p->id }}" value="1" class="form-check-input" @checked($p->is_default)>
                            <label for="is_default_{{ $p->id }}" class="form-check-label">Set as default</label>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                          <button type="submit" class="btn btn-primary">Save changes</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              @empty
                <tr>
                  <td colspan="5" class="text-center text-muted p-4">No printers saved yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Auto-detect Results Modal -->
<div class="modal fade" id="detectModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-search mr-1"></i>Detected Network Printers</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div id="detectLoading" class="py-4 d-flex flex-column align-items-center justify-content-center">
          <div class="spinner-border text-primary mb-2" role="status" aria-live="polite" aria-label="Loading">
            <span class="sr-only">Loading...</span>
          </div>
          <div class="text-muted">Scanning your network on port 9100...</div>
        </div>
        <div id="detectError" class="alert alert-danger d-none"></div>
        <div id="detectNone" class="alert alert-warning d-none">No printers detected. Ensure your printer is powered on and connected to the same network.</div>
        <div id="detectList" class="list-group"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
  </div>
@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
@push('css')
<style>
/* Fallback spinner styles if Bootstrap spinner CSS is unavailable */
@keyframes spinner-border{to{transform: rotate(360deg)}}
.spinner-border{display:inline-block;width:2rem;height:2rem;vertical-align:text-bottom;border:.25em solid currentColor;border-right-color:transparent;border-radius:50%;animation:spinner-border .75s linear infinite}
</style>
@endpush
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
  var routeTest = @json(route('api.printers.test'));
  var routeAuto = @json(route('api.printers.autodetect'));
  var routeTestPrintDefault = @json(route('api.printers.test-print-default'));

  function q(id){ return document.getElementById(id); }
  function csrf(){ var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; }
  function saSuccess(msg){ if (window.Swal) { Swal.fire({icon:'success', title: msg, toast:true, position:'top-end', showConfirmButton:false, timer:2000}); } else { alert(msg); } }
  function saInfo(title, text){ if (window.Swal) { Swal.fire({icon:'info', title: title || 'Info', text: text || ''}); } else { alert((title||'') + (text? ('\n'+text):'')); } }
  function saError(msg){ if (window.Swal) { Swal.fire({icon:'error', title:'Failed', text: msg}); } else { alert('Failed: ' + msg); } }

  // Test connection
  var btnTest = q('btnTest');
  if (btnTest) {
    btnTest.addEventListener('click', function(){
      var ip = q('ip_address').value.trim();
      var port = parseInt(q('port').value, 10) || 9100;
      if (!ip) { alert('Enter IP address first'); return; }
      var self = this; var orig = self.innerHTML;
      self.disabled = true; self.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
      fetch(routeTest, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify({ ip_address: ip, port: port })
      }).then(function(r){ return r.json(); }).then(function(data){
        if (data.ok) saSuccess('Connection OK. Latency: ' + (data.latency_ms || '?') + ' ms');
        else saError(data.error || 'Unknown error');
      }).catch(function(e){ saError(e.message); }).finally(function(){ self.disabled = false; self.innerHTML = orig; });
    });
  }

  function openDummyPreview(base64){
    var meta = q('dummyMeta');
    var link = q('dummyDownload');
    if (base64) {
      var raw = atob(base64);
      var blob = new Blob([new Uint8Array(Array.from(raw, function(c){ return c.charCodeAt(0); }))], { type: 'application/octet-stream' });
      var url = URL.createObjectURL(blob);
      link.href = url; meta.textContent = 'Size: ' + blob.size + ' bytes';
    } else { link.removeAttribute('href'); meta.textContent = 'No data returned (dummy mode).'; }
    if (window.$ && $('#dummyPreviewModal').modal) { $('#dummyPreviewModal').modal('show'); } else { saInfo('Dummy output generated'); }
  }

  function testPrintDefault(){
    var dm = q('dummy_mode_checkbox');
    var dummy = (dm && dm.checked) ? 1 : 0;
    var self = q('btnTestDefaultPrint'); if (!self) return;
    var orig = self.innerHTML; self.disabled = true; self.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Printing...';
    fetch(routeTestPrintDefault, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ dummy: dummy })
    }).then(function(r){ return r.json(); }).then(function(data){
      if (data.ok) { if (data.dummy) openDummyPreview(data.data); else saSuccess('Test print sent to default printer.'); }
      else saError(data.error || 'Unknown error');
    }).catch(function(e){ saError(e.message); }).finally(function(){ self.disabled = false; self.innerHTML = orig; });
  }
  var btnDefault = q('btnTestDefaultPrint'); if (btnDefault) { btnDefault.addEventListener('click', function(e){ e.preventDefault(); testPrintDefault(); }); }

  // Per-printer test
  function testPrintFor(btn){
    var id = btn.getAttribute('data-printer-id');
    var dm = q('dummy_mode_checkbox'); var dummy = (dm && dm.checked) ? 1 : 0;
    var orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    var url = @json(url('/api/printers')) + '/' + id + '/test-print';
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ dummy: dummy }) })
      .then(function(r){ return r.json(); }).then(function(data){
        if (data.ok) { if (data.dummy) openDummyPreview(data.data); else saSuccess('Test print sent.'); }
        else saError(data.error || 'Unknown error');
      }).catch(function(e){ saError(e.message); }).finally(function(){ btn.disabled = false; btn.innerHTML = orig; });
  }
  var perBtns = document.querySelectorAll('.btnTestPrint');
  for (var i=0;i<perBtns.length;i++){ perBtns[i].addEventListener('click', function(e){ e.preventDefault(); testPrintFor(this); }); }
  document.addEventListener('click', function(e){ var t = e.target.closest ? e.target.closest('.btnTestPrint') : null; if (t) { e.preventDefault(); testPrintFor(t); } });

  // Auto-detect
  var btnAuto = q('btnAutoDetect');
  if (btnAuto) {
    btnAuto.addEventListener('click', function(){
      var self = this; self.disabled = true; self.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scanning...';
      var modal = window.$ ? $('#detectModal') : null;
      var list = q('detectList'); var loading = q('detectLoading'); var none = q('detectNone'); var err = q('detectError');
      list.innerHTML = ''; loading.classList.remove('d-none'); loading.style.display = 'flex'; none.classList.add('d-none'); err.classList.add('d-none'); err.textContent='';
      if (modal && modal.modal) modal.modal('show');
      var finished = false; var timedOut = false; var controller = ('AbortController' in window) ? new AbortController() : null;
      var timeoutId = setTimeout(function(){ if (finished) return; timedOut = true; if (controller) { try{ controller.abort(); }catch(_){} } loading.classList.add('d-none'); loading.style.display='none'; none.classList.remove('d-none'); self.disabled=false; self.innerHTML='<i class="fas fa-search"></i> Auto-detect'; }, 7000);
      var fetchOpts = { headers: { 'Accept': 'application/json' }, cache: 'no-store' }; if (controller) fetchOpts.signal = controller.signal;
      fetch(routeAuto, fetchOpts).then(function(r){ if (!r.ok) throw new Error('Status '+r.status); return r.json(); }).then(function(data){
        loading.classList.add('d-none'); loading.style.display='none';
        if (Array.isArray(data.printers) && data.printers.length){
          for (var i=0;i<data.printers.length;i++){
            var p = data.printers[i]; var a = document.createElement('a'); a.href='#'; a.className='list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            a.innerHTML = '<span><i class="fas fa-print mr-2"></i>' + (p.name || 'Printer') + '</span><span class="text-monospace">' + p.ip_address + ':' + p.port + '</span>';
            a.addEventListener('click', function(ev){ ev.preventDefault(); var parts = this.textContent.split('-').pop().trim(); var ipPort = parts.split(':'); q('ip_address').value = ipPort[0].trim(); q('port').value = parseInt(ipPort[1],10)||9100; if (modal && modal.modal) modal.modal('hide'); });
            list.appendChild(a);
          }
        } else { none.classList.remove('d-none'); }
      }).catch(function(e){ loading.classList.add('d-none'); loading.style.display='none'; err.textContent = 'Error: ' + e.message; err.classList.remove('d-none'); saError(e.message); })
      .finally(function(){ finished=true; clearTimeout(timeoutId); loading.classList.add('d-none'); loading.style.display='none'; self.disabled=false; self.innerHTML='<i class="fas fa-search"></i> Auto-detect'; });
    });
  }
})();
</script>
@stop
