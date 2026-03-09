@extends('adminlte::page')

@section('title', 'Portfolio Risk Dashboard')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-shield-alt"></i> Portfolio Risk Dashboard</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-shield-alt"></i> Risk Dashboard</h1>
                <div class="small text-light-50">Monitoring the health of the loan portfolio</div>
            </div>
            <div class="d-flex">
                <a href="{{ route('risk.delinquent.index') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-exclamation-triangle"></i> Delinquent</a>
                <a href="{{ route('risk.collections') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-hand-holding-usd"></i> Collections</a>
            </div>
        </div>
    </div>

     
    <div class="d-flex justify-content-between align-items-center">
     <nav aria-label="breadcrumb">
         <ol class="breadcrumb">
             <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
             <li class="breadcrumb-item active" aria-current="page">Portfolio</li>
         </ol>
     </nav>

 </div>
@stop

@section('content')
    <div class="container-fluid">
        <!-- Main Metrics -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ number_format($summary['portfolio_outstanding'], 2) }}</h3>
                        <p>Total Portfolio Outstanding</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-university"></i>
                    </div>
                    <a href="#" class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></a>

                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ $performingCount }}</h3>
                        <p>Performing Loans</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <a href="#" class="small-box-footer">View List <i class="fas fa-arrow-circle-right"></i></a>

                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ $delinquentCount }}</h3>
                        <p>Delinquent Loans (1+ Days)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <a href="{{ route('risk.delinquent.index') }}" class="small-box-footer">View List <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>{{ $summary['par30'] }}<sup style="font-size: 20px">%</sup></h3>
                        <p>PAR 30</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <a href="{{ route('risk.delinquent.by_days', 30) }}" class="small-box-footer">View PAR30 <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>

        <!-- PAR Breakdown -->
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-danger">
                    <div class="card-header">
                        <h3 class="card-title">Portfolio At Risk (PAR) Breakdown</h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Threshold</th>
                                    <th style="width: 40px">Ratio</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>PAR 30</td>
                                    <td>> 30 Days</td>
                                    <td><span class="badge bg-warning">{{ $summary['par30'] }}%</span></td>
                                    <td><a href="{{ route('risk.delinquent.by_days', 30) }}" class="btn btn-sm btn-secondary"><i class="fas fa-eye"></i> Details</a></td>
                                </tr>
                                <tr>
                                    <td>PAR 60</td>
                                    <td>> 60 Days</td>
                                    <td><span class="badge bg-orange text-white">{{ $summary['par60'] }}%</span></td>
                                    <td><a href="{{ route('risk.delinquent.by_days', 60) }}" class="btn btn-sm btn-warning"><i class="fas fa-eye"></i> Details</a></td>
                                </tr>
                                <tr>
                                    <td>PAR 90</td>
                                    <td>> 90 Days</td>
                                    <td><span class="badge bg-danger">{{ $summary['par90'] }}%</span></td>
                                    <td><a href="{{ route('risk.delinquent.by_days', 90) }}" class="btn btn-sm btn-danger"><i class="fas fa-eye"></i> Details</a></td>
                                </tr>
                                <tr>
                                    <td>PAR 180</td>
                                    <td>> 180 Days</td>
                                    <td><span class="badge bg-dark">{{ $summary['par180'] }}%</span></td>
                                    <td><a href="{{ route('risk.delinquent.by_days', 180) }}" class="btn btn-sm btn-dark"><i class="fas fa-eye"></i> Details</a></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

