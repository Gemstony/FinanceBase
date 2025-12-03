@extends('adminlte::page')

@section('title', 'Inventory Movement Ledger')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <h1 class="d-none d-md-block text-light"><i class="fas fa-exchange-alt"></i> Movement Ledger</h1>
      <h1 class="d-md-none text-light"><i class="fas fa-exchange-alt"></i> Ledger</h1>
      <div class="small text-light-50">Unified stock movements across purchases, sales, returns and write-offs</div>
    </div>
    <a href="{{ route('reports.inventory') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-warehouse"></i> Inventory KPIs</a>
  </div>
 </div>
@stop

@section('content')
<div class="container-fluid">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="get" action="{{ route('reports.inventory.ledger') }}" class="mb-3">
        <div class="bg-light p-2 rounded border">
          <div class="form-row align-items-end">
            <div class="form-group col-md-3">
              <label class="small mb-1">Subshop</label>
              <select name="subshop_id" class="form-control">
                <option value="">All subshops</option>
                @foreach(($subshops ?? []) as $s)
                  <option value="{{ $s->id }}" {{ (int)($selectedSubshopId ?? 0) === (int)$s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-3">
              <div class="form-row">
                <div class="form-group col-6">
                  <label class="small mb-1">Date From</label>
                  <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="form-group col-6">
                  <label class="small mb-1">Date To</label>
                  <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
              </div>
            </div>
            <div class="form-group col-md-2">
              <label class="small mb-1">Event</label>
              <select name="event" class="form-control">
                @php $ev = request('event'); @endphp
                <option value="">All</option>
                <option value="purchase" {{ $ev==='purchase'?'selected':'' }}>Purchase</option>
                <option value="sale" {{ $ev==='sale'?'selected':'' }}>Sale</option>
                <option value="sales_return" {{ $ev==='sales_return'?'selected':'' }}>Sales Return</option>
                <option value="purchase_return" {{ $ev==='purchase_return'?'selected':'' }}>Purchase Return</option>
                <option value="write_off" {{ $ev==='write_off'?'selected':'' }}>Write-off</option>
              </select>
            </div>
            <div class="form-group col-md-2">
              <label class="small mb-1">Category</label>
              <select name="category_id" class="form-control">
                <option value="">All</option>
                @foreach(($categories ?? []) as $c)
                  <option value="{{ $c->id }}" {{ (int)($categoryId ?? 0) === (int)$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-2">
              <label class="small mb-1">Supplier</label>
              <select name="supplier_id" class="form-control">
                <option value="">All</option>
                @foreach(($suppliers ?? []) as $sp)
                  <option value="{{ $sp->id }}" {{ (int)($supplierId ?? 0) === (int)$sp->id ? 'selected' : '' }}>{{ $sp->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-12 d-flex align-items-center mt-2">
              <button class="btn btn-primary mr-2" type="submit"><i class="fas fa-filter"></i> Apply</button>
              <a class="btn btn-light border mr-2" href="{{ route('reports.inventory.ledger') }}"><i class="fas fa-undo"></i> Reset</a>
              @can('export_inventory_ledger_report')
              <div class="ml-auto">
                <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'csv']) }}">CSV</a>
                <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'xlsx']) }}">Excel</a>
                <a class="btn btn-sm btn-light border" href="{{ request()->fullUrlWithQuery(['export'=>'pdf']) }}">PDF</a>
              </div>
              @endcan
            </div>
          </div>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-hover">
          <thead class="thead-light">
            <tr>
              <th>Date</th>
              <th>Subshop</th>
              <th>Event</th>
              <th>Item</th>
              <th>Batch</th>
              <th class="text-right">Qty +/-</th>
              <th class="text-right">Unit Cost</th>
              <th class="text-right">Unit Retail</th>
              <th class="text-right">Value (Cost)</th>
              <th class="text-right">Value (Retail)</th>
              <th>Ref</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $r)
              <tr>
                <td>{{ \Carbon\Carbon::parse($r->date)->format('Y-m-d H:i') }}</td>
                <td>{{ optional(($subshops??collect())->firstWhere('id',$r->subshop_id))->name ?? '—' }}</td>
                <td><span class="badge badge-secondary">{{ str_replace('_',' ', $r->event_type) }}</span></td>
                <td>{{ $r->item_name }}</td>
                <td>{{ $r->batch_number }}</td>
                <td class="text-right">{{ number_format($r->qty_change) }}</td>
                <td class="text-right">{{ number_format($r->unit_cost, 2) }}</td>
                <td class="text-right">{{ number_format($r->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format($r->value_cost_change, 2) }}</td>
                <td class="text-right">{{ number_format($r->value_retail_change, 2) }}</td>
                <td>{{ $r->reference }}</td>
              </tr>
            @empty
              <tr><td colspan="11" class="text-center text-muted py-4"><i class="fas fa-inbox"></i> No movements</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-end">
        {{ $rows->links() }}
      </div>
    </div>
  </div>
</div>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
@stop
