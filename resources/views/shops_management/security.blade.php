@extends('adminlte::page')

@section('title', 'Security Dashboard')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body text-center">
            <h1 class="d-none d-md-block text-light"><i class="fas fa-shield-alt text-warning"></i> <strong>DB</strong> Security Management Panel</h1>
            <h1 class="d-md-none text-light"><i class="fas fa-shield-alt text-warning"></i> <strong>DB</strong> Security Panel</h1>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item active text-dark d-none d-md-inline" aria-current="page">Security Management Panel</li>
                <li class="breadcrumb-item active text-dark d-md-none" aria-current="page">Security Panel</li>
            </ol>
        </nav>

    </div>
@stop

@section('content')
<div class="container-fluid">

    @role('Super Admin')
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.security.clear-daily-logs') }}" class="mr-2 js-confirm" data-title="Clear old daily log files?" data-text="This will delete old daily log files and keep only today's." data-icon="warning">
                @csrf
                <button type="submit" class="btn btn-outline-danger m-2">
                    <i class="fas fa-trash-alt"></i> Clear Old Daily Log Files
                </button>
            </form>

            <form method="POST" action="{{ route('admin.security.clear-auth-logs') }}" class="js-confirm" data-title="Clear ALL authentication logs?" data-text="This will permanently delete ALL user authentication log records from the database." data-icon="warning">
                @csrf
                <button type="submit" class="btn btn-outline-warning m-2">
                    <i class="fas fa-broom"></i> Clear All Authentication Logs
                </button>
            </form>

            <form method="POST" action="{{ route('admin.security.clear-caches') }}" class="js-confirm" data-title="Clear caches and optimize?" data-text="This will clear route, view, config and application caches, then run optimize tasks." data-icon="question">
                @csrf
                <button type="submit" class="btn btn-outline-primary m-2">
                    <i class="fas fa-sync-alt"></i> Clear Caches & Optimize
                </button>
            </form>

            <button type="button" class="btn btn-outline-secondary m-2" id="btnOpenSessionsModal">
                <i class="fas fa-user-clock"></i> Sessions Management
            </button>

            <button type="button" class="btn btn-outline-info m-2" id="btnOpenTimezoneModal">
                <i class="fas fa-globe"></i> Timezone Settings
            </button>
        </div>
    </div>
    @endrole

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-6">
            <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fas fa-sign-in-alt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Login Attempts</span>
                    <span class="info-box-number">{{ $statistics['total_logs'] }}</span>
                    <div class="progress">
                        <div class="progress-bar bg-info" style="width: 100%"></div>
                    </div>
                    <span class="progress-description">
                        <a href="#logs-table" class="text-info">View Details <i class="fas fa-arrow-circle-right"></i></a>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="info-box">
                <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Successful Logins</span>
                    <span class="info-box-number">{{ $statistics['successful_logins'] }}</span>
                    <div class="progress">
                        <div class="progress-bar bg-success" style="width: {{ $statistics['total_logs'] > 0 ? round(($statistics['successful_logins'] / $statistics['total_logs']) * 100) : 0 }}%"></div>
                    </div>
                    <span class="progress-description">
                        <a href="#logs-table" class="text-success">View Details <i class="fas fa-arrow-circle-right"></i></a>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="info-box">
                <span class="info-box-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Failed Login Attempts</span>
                    <span class="info-box-number">{{ $statistics['failed_logins'] }}</span>
                    <div class="progress">
                        <div class="progress-bar bg-danger" style="width: {{ $statistics['total_logs'] > 0 ? round(($statistics['failed_logins'] / $statistics['total_logs']) * 100) : 0 }}%"></div>
                    </div>
                    <span class="progress-description">
                        <a href="#logs-table" class="text-danger">View Details <i class="fas fa-arrow-circle-right"></i></a>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="info-box">
                <span class="info-box-icon bg-warning"><i class="fas fa-ban"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Blocked IPs</span>
                    <span class="info-box-number">{{ $statistics['blocked_ips'] }}</span>
                    <div class="progress">
                        <div class="progress-bar bg-warning" style="width: {{ $statistics['blocked_ips'] > 0 ? min($statistics['blocked_ips'] * 10, 100) : 0 }}%"></div>
                    </div>
                    <span class="progress-description">
                        <a href="#blocked-ips-table" class="text-warning">View Details <i class="fas fa-arrow-circle-right"></i></a>
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- User Authentication Logs --}}
    <div class="card" id="logs-table">
        <div class="card-header">
            <h3 class="card-title">User Authentication Logs</h3>
        </div>
        <div class="card-body table-responsive p-3">
            <table class="table table-hover text-nowrap" id="securityLogsTable">
                <thead class="thead-dark">
                    <tr>
                        <th>No.</th>
                        <th>User</th>
                        <th>IP Address</th>
                        <th>Location</th>
                        <th>Device/Browser</th>
                        <th>Login At</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody><?php $count = 1;?>
                    @forelse($logs as $log)
                        <tr>
                            <td>#<?=$count++ ?></td>
                            <td>{{ $log->authenticatable ? $log->authenticatable->name : 'Guest' }}</td>
                            <td>{{ $log->ip_address }}</td>
                            <td>{{ $log->location ?? 'N/A' }}</td>
                            <td>{{ $log->user_agent }}</td>
                            <td>{{ $log->login_at ? $log->login_at->format('d/m/Y H:i') : 'N/A' }}</td>
                            <td>
                                @if($log->login_successful)
                                    <span class="badge badge-success">Success</span>
                                @else
                                    <span class="badge badge-danger">Failed</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No authentication logs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $logs->appends(request()->query())->links() }}
    </div>

    {{-- Blocked IPs --}}
    <div class="card mt-4" id="blocked-ips-table">
        <div class="card-header">
            <h3 class="card-title">Blocked IPs</h3>
        </div>
        <div class="card-body table-responsive p-3">
            <table class="table table-hover text-nowrap" id="blockedIPsTable">
                <thead class="thead-dark">
                    <tr>
                        <th>No.</th>
                        <th>IP Address</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody><?php $ipCount = 1;?>
                    @forelse($blockedIps as $ip)
                        <tr>
                            <td>#<?=$ipCount++ ?></td>
                            <td>{{ $ip->ip }}</td>
                            <td>{{ $ip->reason }}</td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <form method="POST" action="{{ route('admin.security.unblock-ip') }}" style="display: inline;">
                                        @csrf
                                        <input type="hidden" name="ip" value="{{ $ip->ip }}">
                                        <button type="submit" class="btn btn-sm btn-success" title="Unblock IP">
                                            <i class="fas fa-unlock"></i> Unblock
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center">No blocked IPs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Block New IP --}}
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">Block New IP</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.security.block-ip') }}">
                @csrf
                <div class="input-group">
                    <input type="text" name="ip" class="form-control" placeholder="Enter IP" required>
                    <button type="submit" class="btn btn-danger">Block IP</button>
                </div>
            </form>
        </div>
    </div>

</div>
@stop

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@section('js')
<script>
  // Make CSRF token globally available for AJAX requests
  window.CSRF_TOKEN = '{{ csrf_token() }}';
  window.APP_ROUTES = window.APP_ROUTES || {};
</script>
<script>
    // Initialize DataTable for security logs
    $(function () {
        $('#securityLogsTable').DataTable({
            "order": [[5, "desc"]], // Order by Login At column (0-indexed)
            "pageLength": 10,
            "responsive": true,
            "language": {
                "search": "Search logs:",
                "lengthMenu": "Show _MENU_ logs per page",
                "info": "Showing _START_ to _END_ of _TOTAL_ logs",
                "infoEmpty": "No logs available",
                "infoFiltered": "(filtered from _MAX_ total logs)"
            }
        });
    });

    // Initialize DataTable for blocked IPs
    $(function () {
        $('#blockedIPsTable').DataTable({
            "order": [[1, "asc"]], // Order by IP Address column
            "pageLength": 10,
            "responsive": true,
            "language": {
                "search": "Search blocked IPs:",
                "lengthMenu": "Show _MENU_ IPs per page",
                "info": "Showing _START_ to _END_ of _TOTAL_ blocked IPs",
                "infoEmpty": "No blocked IPs available",
                "infoFiltered": "(filtered from _MAX_ total IPs)"
            }
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(function () {
        $(document).on('submit', 'form.js-confirm', function (e) {
            e.preventDefault();
            const form = this;
            const title = $(form).data('title') || 'Are you sure?';
            const text = $(form).data('text') || '';
            const icon = $(form).data('icon') || 'warning';

            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, proceed',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: @json(session('success')),
                timer: 3000,
                showConfirmButton: false
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: @json(session('error')),
            });
        @endif
    });
</script>

<!-- Sessions Management Modal -->
<div class="modal fade" id="sessionsModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-clock"></i> Sessions Management</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <button class="btn btn-sm btn-outline-danger" id="btnDestroyOtherSessions"><i class="fas fa-user-slash"></i> Destroy All Other Sessions</button>
            </div>
            <form id="formSessionTimeout" class="form-inline">
                <label class="mr-2">Session Timeout (minutes)</label>
                <input type="number" min="1" max="1440" class="form-control form-control-sm mr-2" id="inputSessionLifetime" required>
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-save"></i> Update</button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-bordered" id="sessionsTable">
                <thead class="thead-dark">
                    <tr>
                        <th>Session ID</th>
                        <th>User</th>
                        <th>IP Address</th>
                        <th>User Agent</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Timezone Settings Modal -->
<div class="modal fade" id="timezoneModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-globe"></i> Timezone Settings</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group">
            <label>Current Timezone</label>
            <input type="text" class="form-control" id="currentTimezone" readonly>
        </div>
        <div class="form-group">
            <label>Select New Timezone</label>
            <select class="form-control" id="selectTimezone"></select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="btnUpdateTimezone"><i class="fas fa-save"></i> Update Timezone</button>
      </div>
    </div>
  </div>
  
</div>

<script>
$(function(){
    const CSRF_TOKEN = window.CSRF_TOKEN || '{{ csrf_token() }}';
    const routes = {
        list: @json(route('admin.security.sessions')),
        destroy: @json(route('admin.security.sessions.destroy')),
        destroyOthers: @json(route('admin.security.sessions.destroy-others')),
        timeout: @json(route('admin.security.sessions.timeout')),
        tzInfo: @json(route('admin.security.timezone.info')),
        tzUpdate: @json(route('admin.security.timezone.update')),
    };

    let sessionsDT = null;

    function ensureSessionsDT(){
        if ($.fn.DataTable.isDataTable('#sessionsTable')) {
            sessionsDT = $('#sessionsTable').DataTable();
            return sessionsDT;
        }
        sessionsDT = $('#sessionsTable').DataTable({
            order: [[4, 'desc']],
            pageLength: 10,
            responsive: false,
            autoWidth: false,
            deferRender: true,
            retrieve: true,
            columns: [
                { title: 'Session ID' },
                { title: 'User' },
                { title: 'IP Address' },
                { title: 'User Agent' },
                { title: 'Last Activity' },
                { title: 'Actions' },
            ],
            columnDefs: [
                { targets: 5, orderable: false, searchable: false }
            ]
        });
        return sessionsDT;
    }

    function loadSessions(){
        if (!$('#sessionsModal').is(':visible')) {
            return; // don't operate when modal isn't visible
        }
        const dt = ensureSessionsDT();
        dt.clear().draw(false);
        $.getJSON(routes.list, function(resp){
            const rows = resp.data.map(function(s){
                const safeUA = $('<div/>').text(s.user_agent || '').html();
                const last = `${s.last_activity_human} <small class="text-muted">(${s.last_activity_at})</small>`;
                const actions = `<button class="btn btn-sm btn-outline-danger btnDestroySession" data-id="${s.id}"><i class="fas fa-times"></i> Destroy</button>`;
                return [
                    `<code>${s.id}</code>`,
                    `${s.user_name} ${s.user_id ? '(#'+s.user_id+')' : ''}`,
                    `${s.ip_address || ''}`,
                    `<span style="max-width: 320px; display:inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${safeUA}">${safeUA}</span>`,
                    last,
                    actions,
                ];
            });
            if (rows.length) {
                dt.rows.add(rows).draw(false);
            } else {
                dt.draw(false);
            }
            if (resp.lifetime) {
                $('#inputSessionLifetime').val(resp.lifetime);
            }
        }).fail(function(){
            Swal.fire({icon:'error', title:'Failed', text:'Failed to load sessions.'});
        });
    }

    $('#btnOpenSessionsModal').on('click', function(){
        $('#sessionsModal').modal('show');
    });

    $('#sessionsModal').on('hidden.bs.modal', function(){
        if ($.fn.DataTable.isDataTable('#sessionsTable')) {
            $('#sessionsTable').DataTable().destroy();
        }
        $('#sessionsTable tbody').empty();
        sessionsDT = null;
    });

    $('#sessionsModal').on('shown.bs.modal', function(){
        const dt = ensureSessionsDT();
        dt.columns.adjust();
        loadSessions();
    });

    // Timezone modal
    $('#btnOpenTimezoneModal').on('click', function(){
        $('#timezoneModal').modal('show');
        $('#selectTimezone').empty().append('<option>Loading...</option>');
        $.getJSON(routes.tzInfo, function(resp){
            $('#currentTimezone').val(resp.current || 'UTC');
            const options = (resp.timezones || []).map(function(tz){
                const selected = tz === resp.current ? 'selected' : '';
                return `<option value="${tz}" ${selected}>${tz}</option>`;
            }).join('');
            $('#selectTimezone').html(options);
        }).fail(function(){
            Swal.fire({icon:'error', title:'Failed', text:'Failed to load timezone list.'});
            $('#timezoneModal').modal('hide');
        });
    });

    $('#btnUpdateTimezone').on('click', function(){
        const tz = $('#selectTimezone').val();
        Swal.fire({
            title: 'Change timezone?',
            text: 'This updates APP_TIMEZONE and clears config cache.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, update',
        }).then(function(res){
            if(res.isConfirmed){
                $.ajax({
                    url: routes.tzUpdate,
                    method: 'POST',
                    data: { timezone: tz, _token: CSRF_TOKEN },
                }).done(function(resp){
                    Swal.fire({icon:'success', title:'Timezone updated', text:'New timezone: '+resp.timezone, timer:2000, showConfirmButton:false});
                    $('#timezoneModal').modal('hide');
                }).fail(function(xhr){
                    Swal.fire({icon:'error', title:'Failed', text: xhr.responseJSON?.message || 'Unable to update timezone'});
                });
            }
        });
    });

    $(document).on('click', '.btnDestroySession', function(){
        const id = $(this).data('id');
        Swal.fire({
            title: 'Destroy this session?',
            text: 'The user bound to this session will be logged out.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, destroy',
        }).then(function(res){
            if(res.isConfirmed){
                $.ajax({
                    url: routes.destroy,
                    method: 'POST',
                    data: { id: id, _token: CSRF_TOKEN },
                }).done(function(){
                    Swal.fire({icon:'success', title:'Destroyed', timer:1500, showConfirmButton:false});
                    loadSessions();
                }).fail(function(xhr){
                    Swal.fire({icon:'error', title:'Failed', text: xhr.responseJSON?.message || 'Unable to destroy session'});
                });
            }
        });
    });

    $('#btnDestroyOtherSessions').on('click', function(){
        Swal.fire({
            title: 'Destroy all other sessions?',
            text: 'All sessions except your current one will be removed.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, destroy',
        }).then(function(res){
            if(res.isConfirmed){
                $.ajax({
                    url: routes.destroyOthers,
                    method: 'POST',
                    data: { _token: CSRF_TOKEN },
                }).done(function(){
                    Swal.fire({icon:'success', title:'Destroyed', timer:1500, showConfirmButton:false});
                    loadSessions();
                }).fail(function(xhr){
                    Swal.fire({icon:'error', title:'Failed', text: xhr.responseJSON?.message || 'Unable to destroy sessions'});
                });
            }
        });
    });

    $('#formSessionTimeout').on('submit', function(e){
        e.preventDefault();
        const lifetime = parseInt($('#inputSessionLifetime').val(), 10);
        if (!lifetime || lifetime < 1 || lifetime > 1440) {
            Swal.fire({icon:'error', title:'Invalid timeout', text:'Enter a value between 1 and 1440 minutes.'});
            return;
        }
        $.ajax({
            url: routes.timeout,
            method: 'POST',
            data: { lifetime: lifetime, _token: CSRF_TOKEN },
        }).done(function(resp){
            Swal.fire({icon:'success', title:'Updated', text:'Session timeout updated to '+resp.lifetime+' minutes.', timer:1800, showConfirmButton:false});
        }).fail(function(xhr){
            Swal.fire({icon:'error', title:'Failed', text: xhr.responseJSON?.message || 'Unable to update session timeout'});
        });
    });
});
</script>
@stop
