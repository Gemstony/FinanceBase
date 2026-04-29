@extends('adminlte::page')

@section('title', 'Loan Groups - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-users"></i> Loan Groups</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-users"></i> Groups</h1>
                <p class="mb-0 text-light">Managing loan groups for: <strong>{{ $subshop->name }}</strong></p>
            </div>
        </div>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('loans.loan_groups.index') }}">Loan Groups</a></li>
            <li class="breadcrumb-item active" aria-current="page">Loan Groups</li>
        </ol>
    </nav>
    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addLoanGroupModal">
        <i class="fas fa-plus"></i> New Loan Group
    </button>
</div>
@stop

@section('content')
<div class="container-fluid">
@if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-0">{{ $groups->count() }}</h4>
                            <p class="card-text">Total Groups</p>
                        </div>
                        <div class="fa-2x">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-0">{{ $groups->where('is_active', true)->count() }}</h4>
                            <p class="card-text">Active Groups</p>
                        </div>
                        <div class="fa-2x">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-0">{{ $groups->sum(function($g) { return $g->members->count(); }) }}</h4>
                            <p class="card-text">Total Members</p>
                        </div>
                        <div class="fa-2x">
                            <i class="fas fa-user-friends"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-0">{{ $groups->where('is_active', false)->count() }}</h4>
                            <p class="card-text">Inactive Groups</p>
                        </div>
                        <div class="fa-2x">
                            <i class="fas fa-pause-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="loanGroupsTable">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Members</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groups as $index => $g)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                @if($g->code)
                                    <span class="badge badge-info">{{ $g->code }}</span>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>{{ $g->name }}</td>
                            <td>
                                <span class="badge {{ $g->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $g->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                {{ $g->members->count() }}
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info view-btn"
                                        data-group-id="{{ $g->id }}"
                                        data-group-name="{{ $g->name }}"
                                        data-group-code="{{ $g->code ?? '' }}"
                                        data-group-description="{{ $g->description ?? '' }}"
                                        data-group-formation-date="{{ optional($g->formation_date)->format('Y-m-d') }}"
                                        data-group-is-active="{{ $g->is_active ? 1 : 0 }}">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-secondary members-btn"
                                        data-group-id="{{ $g->id }}"
                                        data-group-name="{{ $g->name }}">
                                    <i class="fas fa-user-plus"></i>
                                </button>
                                <button class="btn btn-sm btn-primary edit-btn"
                                        data-id="{{ $g->id }}"
                                        data-name="{{ $g->name }}"
                                        data-code="{{ $g->code ?? '' }}"
                                        data-description="{{ $g->description ?? '' }}"
                                        data-formation-date="{{ optional($g->formation_date)->format('Y-m-d') }}"
                                        data-is-active="{{ $g->is_active ? 1 : 0 }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="{{ route('loans.loan_groups.destroy', $g->id) }}" class="d-inline delete-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center">No loan groups found. Click 'New Loan Group' to add one.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@foreach($groups as $g)
    <script type="application/json" id="members-data-{{ $g->id }}">
        @json($g->members_json)
    </script>
@endforeach



<!-- Add Loan Group Modal -->
<div class="modal fade" id="addLoanGroupModal" tabindex="-1" role="dialog" aria-labelledby="addLoanGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('loans.loan_groups.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addLoanGroupModalLabel">Add New Loan Group</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="lg_name">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="lg_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="lg_code">Code</label>
                        <input type="text" class="form-control" id="lg_code" name="code">
                    </div>
                    <div class="form-group">
                        <label for="lg_description">Description</label>
                        <textarea class="form-control" id="lg_description" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="lg_formation_date">Formation Date</label>
                                <input type="date" class="form-control" id="lg_formation_date" name="formation_date">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="lg_is_active" name="is_active" value="1" checked>
                                    <label class="form-check-label" for="lg_is_active">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Loan Group Modal -->
<div class="modal fade" id="editLoanGroupModal" tabindex="-1" role="dialog" aria-labelledby="editLoanGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="editLoanGroupForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editLoanGroupModalLabel">Edit Loan Group</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_lg_name">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_lg_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_lg_code">Code</label>
                        <input type="text" class="form-control" id="edit_lg_code" name="code">
                    </div>
                    <div class="form-group">
                        <label for="edit_lg_description">Description</label>
                        <textarea class="form-control" id="edit_lg_description" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_lg_formation_date">Formation Date</label>
                                <input type="date" class="form-control" id="edit_lg_formation_date" name="formation_date">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="edit_lg_is_active" name="is_active" value="1">
                                    <label class="form-check-label" for="edit_lg_is_active">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manage Members Modal -->
<div class="modal fade" id="manageMembersModal" tabindex="-1" role="dialog" aria-labelledby="manageMembersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('loans.loan_groups.members.store', ['group' => ':group_id']) }}" method="POST" id="addMembersForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="manageMembersModalLabel">Manage Members</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Group:</strong> <span id="mm_group_name"></span>
                    </div>

                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addMemberRowBtn">
                            <i class="fas fa-plus"></i> Add Another Member
                        </button>
                    </div>

                    <div id="membersRowsContainer">
                        <div class="member-row" data-row="0">
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label>Customer <span class="text-danger">*</span></label>
                                        <select class="form-control customer-select select2" name="members[0][customer_id]" required>
                                            <option value="">-- Select customer --</option>
                                            @foreach($customers as $c)
                                                <option value="{{ $c->id }}" data-phone="{{ $c->phone }}">{{ $c->name }} - {{ $c->phone }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Role <span class="text-danger">*</span></label>
                                        <select class="form-control" name="members[0][role]" required>
                                            <option value="member">Member</option>
                                            <option value="leader">Leader</option>
                                            <option value="secretary">Secretary</option>
                                            <option value="treasurer">Treasurer</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Joined At</label>
                                        <input type="date" class="form-control" name="members[0][joined_at]" value="{{ now()->format('Y-m-d') }}">
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <div class="form-group">
                                        <label>&nbsp;</label><br>
                                        <button type="button" class="btn btn-danger btn-sm remove-member-row" style="display:none;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add Members
                        </button>
                    </div>

                    <hr>

                    <h6 class="font-weight-bold">Current Members</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="mm_members_tbody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Loan Group Modal -->
<div class="modal fade" id="viewLoanGroupModal" tabindex="-1" role="dialog" aria-labelledby="viewLoanGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewLoanGroupModalLabel">Loan Group Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="font-weight-bold text-primary">Group Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td width="30%"><strong>Code:</strong></td>
                                <td id="view_group_code">-</td>
                            </tr>
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td id="view_group_name">-</td>
                            </tr>
                            <tr>
                                <td><strong>Description:</strong></td>
                                <td id="view_group_description">-</td>
                            </tr>
                            <tr>
                                <td><strong>Formation Date:</strong></td>
                                <td id="view_group_formation_date">-</td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td id="view_group_status">-</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="font-weight-bold text-primary">Summary</h6>
                        <div class="row">
                            <div class="col-6 text-center">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h3 class="card-title mb-0" id="view_total_members">0</h3>
                                        <p class="card-text">Total Members</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 text-center">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h3 class="card-title mb-0" id="view_active_members">0</h3>
                                        <p class="card-text">Active Members</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="font-weight-bold text-primary mb-3">Group Members</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Customer Name</th>
                                <th>Role</th>
                                <th>Joined Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="view_members_tbody">
                            <tr>
                                <td colspan="5" class="text-center text-muted">Loading members...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">

<style>
    .table th, .table td {
        vertical-align: middle;
    }
    .action-buttons {
        white-space: nowrap;
    }
</style>
@endpush




@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    if ($('#loanGroupsTable').length) {
        $('#loanGroupsTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [0, 5] },
                { searchable: false, targets: [0, 5] }
            ],
            order: [[1, 'asc']]
        });
    }

    $('.edit-btn').click(function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var code = $(this).data('code');
        var description = $(this).data('description');
        var formationDate = $(this).data('formation-date');
        var isActive = $(this).data('is-active');

        $('#editLoanGroupForm').attr('action', '/loans/loan_groups/' + id);
        $('#edit_lg_name').val(name);
        $('#edit_lg_code').val(code);
        $('#edit_lg_description').val(description);
        $('#edit_lg_formation_date').val(formationDate);
        $('#edit_lg_is_active').prop('checked', !!isActive);

        $('#editLoanGroupModal').modal('show');
    });

    $('.view-btn').click(function() {
        var groupId = $(this).data('group-id');
        var groupName = $(this).data('group-name');
        var groupCode = $(this).data('group-code');
        var groupDescription = $(this).data('group-description');
        var groupFormationDate = $(this).data('group-formation-date');
        var groupIsActive = $(this).data('group-is-active');

        // Populate group information
        $('#view_group_code').text(groupCode || 'N/A');
        $('#view_group_name').text(groupName || 'N/A');
        $('#view_group_description').text(groupDescription || 'N/A');
        $('#view_group_formation_date').text(groupFormationDate || 'N/A');
        $('#view_group_status').html(groupIsActive ? 
            '<span class="badge badge-success">Active</span>' : 
            '<span class="badge badge-secondary">Inactive</span>');

        // Get members data
        var membersJsonEl = document.getElementById('members-data-' + groupId);
        var members = [];
        if (membersJsonEl && membersJsonEl.textContent) {
            try {
                members = JSON.parse(membersJsonEl.textContent);
            } catch (e) {
                members = [];
            }
        }

        // Update summary
        var totalMembers = members.length;
        var activeMembers = members.filter(function(m) { return m.id; }).length;
        $('#view_total_members').text(totalMembers);
        $('#view_active_members').text(activeMembers);

        // Populate members table
        var $tbody = $('#view_members_tbody');
        $tbody.empty();

        if (!members.length) {
            $tbody.append('<tr><td colspan="5" class="text-center text-muted">No members in this group.</td></tr>');
        } else {
            members.forEach(function(m, index) {
                var safeName = m.customer_name || 'N/A';
                var safeRole = m.role || 'member';
                var safeJoined = m.joined_at || 'N/A';
                var statusBadge = m.id ? 
                    '<span class="badge badge-success">Active</span>' : 
                    '<span class="badge badge-secondary">Inactive</span>';

                $tbody.append(
                    '<tr>' +
                    '<td>' + (index + 1) + '</td>' +
                    '<td>' + safeName + '</td>' +
                    '<td>' + safeRole.charAt(0).toUpperCase() + safeRole.slice(1) + '</td>' +
                    '<td>' + safeJoined + '</td>' +
                    '<td>' + statusBadge + '</td>' +
                    '</tr>'
                );
            });
        }

        $('#viewLoanGroupModal').modal('show');
    });

    $('.members-btn').click(function() {
        var groupId = $(this).data('group-id');
        var groupName = $(this).data('group-name');

        $('#mm_group_name').text(groupName);
        $('#addMembersForm').attr('action', '/loans/loan_groups/' + groupId + '/members');

        var membersJsonEl = document.getElementById('members-data-' + groupId);
        var members = [];
        if (membersJsonEl && membersJsonEl.textContent) {
            try {
                members = JSON.parse(membersJsonEl.textContent);
            } catch (e) {
                members = [];
            }
        }

        var $tbody = $('#mm_members_tbody');
        $tbody.empty();

        if (!members.length) {
            $tbody.append('<tr><td colspan="4" class="text-center text-muted">No active members in this group.</td></tr>');
        } else {
            members.forEach(function(m) {
                var safeName = m.customer_name || 'N/A';
                var safeRole = m.role || 'member';
                var safeJoined = m.joined_at || 'N/A';

                var leaveAction = '';
                if (m.id) {
                    leaveAction = '<form method="POST" action="/loans/loan_groups/members/' + m.id + '/leave" class="d-inline mm-leave-form">' +
                        '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                        '<button type="submit" class="btn btn-sm btn-danger">Leave</button>' +
                        '</form>';
                }

                $tbody.append(
                    '<tr>' +
                    '<td>' + safeName + '</td>' +
                    '<td>' + safeRole + '</td>' +
                    '<td>' + safeJoined + '</td>' +
                    '<td>' + leaveAction + '</td>' +
                    '</tr>'
                );
            });
        }

        $('#manageMembersModal').modal('show');
    });

    // Dynamic member rows functionality
    var memberRowCount = 1;

    // Initialize Select2 for customer selects
    function initCustomerSelect2($element) {
        $element.select2({
            placeholder: 'Search by name or phone...',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#manageMembersModal .modal-body'),
            templateResult: function(data) {
                if (!data.id) return data.text;
                var phone = $(data.element).data('phone') || '';
                return $('<span>').text(data.text);
            }
        });
    }

    // Initialize on modal show (not page load, to ensure modal is in DOM)
    $('#manageMembersModal').on('shown.bs.modal', function () {
        $('.customer-select').each(function() {
            if (!$(this).data('select2')) {
                initCustomerSelect2($(this));
            }
        });
    });

    $('#addMemberRowBtn').click(function() {
        var newRowNum = memberRowCount++;

        // Build fresh row HTML without Select2 artifacts
        var newRowHtml = '<div class="member-row" data-row="' + newRowNum + '">' +
            '<div class="row">' +
                '<div class="col-md-5">' +
                    '<div class="form-group">' +
                        '<label>Customer <span class="text-danger">*</span></label>' +
                        '<select class="form-control customer-select select2" name="members[' + newRowNum + '][customer_id]" required>' +
                            '<option value="">-- Select customer --</option>' +
                            '@foreach($customers as $c)<option value="{{ $c->id }}" data-phone="{{ $c->phone }}">{{ $c->name }} - {{ $c->phone }}</option>@endforeach' +
                        '</select>' +
                    '</div>' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<div class="form-group">' +
                        '<label>Role <span class="text-danger">*</span></label>' +
                        '<select class="form-control" name="members[' + newRowNum + '][role]" required>' +
                            '<option value="member">Member</option>' +
                            '<option value="leader">Leader</option>' +
                            '<option value="secretary">Secretary</option>' +
                            '<option value="treasurer">Treasurer</option>' +
                        '</select>' +
                    '</div>' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<div class="form-group">' +
                        '<label>Joined At</label>' +
                        '<input type="date" class="form-control" name="members[' + newRowNum + '][joined_at]" value="{{ now()->format('Y-m-d') }}">' +
                    '</div>' +
                '</div>' +
                '<div class="col-md-1">' +
                    '<div class="form-group">' +
                        '<label>&nbsp;</label><br>' +
                        '<button type="button" class="btn btn-danger btn-sm remove-member-row">' +
                            '<i class="fas fa-trash"></i>' +
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';

        // Add to container
        var $newRow = $(newRowHtml);
        $('#membersRowsContainer').append($newRow);

        // Initialize Select2 on the new row's select
        initCustomerSelect2($newRow.find('.customer-select'));
    });
    
    $(document).on('click', '.remove-member-row', function() {
        if ($('.member-row').length > 1) {
            $(this).closest('.member-row').remove();
        }
    });
    
    // Prevent duplicate customer selection
    $(document).on('change', '.customer-select', function() {
        var selectedCustomers = [];
        $('.customer-select').each(function() {
            var val = $(this).val();
            if (val) {
                selectedCustomers.push(val);
            }
        });

        // Update all selects to disable already-selected options
        $('.customer-select').each(function() {
            var $select = $(this);
            var currentVal = $select.val();

            // Get all options
            var options = [];
            $select.find('option').each(function() {
                var optionVal = $(this).val();
                var optionText = $(this).text();
                var optionPhone = $(this).data('phone');
                var isDisabled = optionVal && optionVal !== currentVal && selectedCustomers.includes(optionVal);
                options.push({
                    id: optionVal,
                    text: optionText,
                    phone: optionPhone,
                    disabled: isDisabled
                });
            });

            // Rebuild options
            $select.find('option').each(function(index) {
                $(this).prop('disabled', options[index].disabled);
            });

            // Trigger Select2 update
            $select.trigger('change.select2');
        });
    });

    $(document).on('submit', '.delete-form', function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    $(document).on('submit', '.mm-leave-form', function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'Remove member?',
            text: 'This will mark the member as left the group.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, remove'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // Show success/error messages
    @if(session('success'))
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '{{ session('success') }}',
        showConfirmButton: true,
        timerProgressBar: true,
        timer: 3000
    });
    @endif

    @if(session('error'))
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '{{ session('error') }}',
        showConfirmButton: true

    });
    @endif
});
</script>
@endpush